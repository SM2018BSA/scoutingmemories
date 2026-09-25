<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;

/**
 * EntryQuery
 *
 * Finds the entries a view shows, in SQL: Formidable's filters (=, !=, LIKE, not LIKE, <, >,
 * <=, >=, LIKE%, %LIKE, group_by, group_by_newest), ordering by fields or entry columns, and paging.
 *
 * Filter values may use [get param=name] (from the URL or the view shortcode's attributes) and
 * current_user; a filter whose [get param] is empty is skipped, as in Formidable. Dynamic ("data")
 * fields store linked entry IDs, so a text value is also matched against the linked entry's value.
 */
class EntryQuery {

    private const COLUMNS = ['id', 'item_key', 'created_at', 'updated_at', 'user_id', 'post_id', 'is_draft'];

    /**
     * @param array<string, mixed> $view From ViewRepository::find()
     * @param array<string, string> $params URL + shortcode parameters for [get param=...]
     * @return array{ids: int[], total: int, page: int, pages: int}
     */
    public static function run(array $view, array $params, int $page = 1): array {
        global $wpdb;
        $items = $wpdb->prefix . 'frm_items';

        $fields = [];
        foreach (FormRepository::fields($view['form_id']) as $field) {
            $fields[(string) $field['id']] = $field;
        }

        $where = [$wpdb->prepare('i.form_id = %d', $view['form_id']), 'i.is_draft = 0'];
        foreach (ViewRepository::filters($view) as $filter) {
            $clause = self::filterClause($filter, $fields, $params, $view);
            if ($clause !== '') {
                $where[] = $clause;
            }
        }
        $whereSql = implode(' AND ', $where);

        [$joins, $orderSql] = self::ordering($view, $fields);

        $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT i.id) FROM {$items} i WHERE {$whereSql}");
        $limit = (int) ($view['options']['limit'] ?? 0);
        if ($limit > 0) {
            $total = min($total, $limit);
        }

        $pageSize = (int) ($view['options']['page_size'] ?? 0);
        $pages = $pageSize > 0 ? max(1, (int) ceil($total / $pageSize)) : 1;
        $page = max(1, min($page, $pages));

        if ($pageSize > 0) {
            $take = $limit > 0 ? min($pageSize, max(0, $limit - ($page - 1) * $pageSize)) : $pageSize;
            $limitSql = $wpdb->prepare('LIMIT %d OFFSET %d', $take, ($page - 1) * $pageSize);
        } else {
            $limitSql = $limit > 0 ? $wpdb->prepare('LIMIT %d', $limit) : '';
        }

        $ids = $wpdb->get_col("SELECT i.id FROM {$items} i {$joins} WHERE {$whereSql} GROUP BY i.id {$orderSql} {$limitSql}");

        return ['ids' => array_map('intval', $ids), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param array{field:string, op:string, value:string} $filter
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, string> $params
     */
    private static function filterClause(array $filter, array $fields, array $params, array $view): string {
        $formId = (int) $view['form_id'];
        global $wpdb;
        $metas = $wpdb->prefix . 'frm_item_metas';
        $items = $wpdb->prefix . 'frm_items';
        $op = $filter['op'];
        $target = $filter['field'];

        if ($op === 'group_by' || $op === 'group_by_newest') {
            if (!isset($fields[$target])) {
                return '';
            }
            $pick = $op === 'group_by_newest' ? 'MAX' : 'MIN';
            return $wpdb->prepare(
                "i.id IN (SELECT {$pick}(m.item_id) FROM {$metas} m INNER JOIN {$items} g ON g.id = m.item_id
                  WHERE m.field_id = %d AND g.form_id = %d AND g.is_draft = 0 AND m.meta_value <> '' GROUP BY m.meta_value)",
                (int) $target,
                $formId
            );
        }

        $raw = $filter['value'];
        $usesParam = (bool) preg_match('/\[get param=/', $raw);
        $value = self::resolveValue($raw, $params);
        if ($usesParam && $value === '') {
            // Formidable ignores a filter whose URL value is empty
            return '';
        }

        if (in_array($target, self::COLUMNS, true)) {
            [$sqlOp, $sqlValue] = self::operator($op, $value);
            return $sqlOp === '' ? '' : $wpdb->prepare("i.{$target} {$sqlOp} %s", $sqlValue);
        }
        if (!isset($fields[$target])) {
            return '';
        }
        $field = $fields[$target];

        // Match the positive form of the operator, then exclude those entries for != / not LIKE
        $negate = $op === '!=' || stripos($op, 'not ') === 0;
        $positive = $negate ? ($op === '!=' ? '=' : 'LIKE') : ($op === '==' ? '=' : $op);
        if (self::operator($positive, $value)[0] === '') {
            return '';
        }

        if ($value === '' && $positive === '=') {
            // "= ''" means the field is blank, "!= ''" that it has a value
            $negate = !$negate;
            $condition = static function (string $column): string {
                return "{$column} <> ''";
            };
        } else {
            // Formidable: "=" on a field that holds several values means "contains"
            $dataCheckbox = $field['type'] === 'data' && ($field['field_options']['data_type'] ?? '') === 'checkbox';
            if (in_array($op, ['=', '=='], true) && self::holdsSeveralValues($field) && !($dataCheckbox && !is_numeric($value))) {
                $positive = 'LIKE';
            }
            $linkedIds = self::linkedIdsForText($field, $positive, $value);
            $condition = self::valueCondition($positive, $value, $linkedIds);

            // Theme code can replace the condition through Formidable's frm_where_filter hook
            // (the camp and lodge search views match council slugs this way)
            $custom = self::whereFilter($view, $field, $op, $linkedIds ?: $value);
            if ($custom !== '') {
                return ($negate ? 'i.id NOT IN (' : 'i.id IN (') . $custom . ')';
            }
        }

        // Post-mapped field: the value lives on the entry's post
        $map = PostFields::mapping($field);
        if ($map) {
            $match = PostFields::matchSql($map, $condition);
            return $negate
                ? '(i.post_id = 0 OR i.post_id NOT IN (' . $match . '))'
                : '(i.post_id > 0 AND i.post_id IN (' . $match . '))';
        }

        $match = $wpdb->prepare("SELECT item_id FROM {$metas} WHERE field_id = %d AND ", (int) $field['id']) . $condition('meta_value');
        return ($negate ? 'i.id NOT IN (' : 'i.id IN (') . $match . ')';
    }

    /**
     * A Dynamic field stores linked entry IDs, so a text value (a state name) is first turned
     * into the IDs of the linked entries it matches, or failing that, entry keys.
     *
     * @param array<string, mixed> $field
     * @return int[] Empty when the value is not text for a Dynamic field, or nothing matched
     */
    private static function linkedIdsForText(array $field, string $positive, string $value): array {
        $isCategory = ($field['field_options']['post_field'] ?? '') === 'post_category';
        if ($field['type'] !== 'data' || $value === '' || is_numeric($value) || $isCategory) {
            return [];
        }
        return self::linkedIds($field, $positive, $value);
    }

    /**
     * How a stored value is compared, as a function of the SQL column holding it: against the
     * linked entry IDs when there are any, otherwise against the value itself. Several stored
     * IDs are kept serialized, so an ID matches the whole value or a quoted item in it.
     *
     * @param int[] $linkedIds
     */
    private static function valueCondition(string $positive, string $value, array $linkedIds): callable {
        global $wpdb;

        if ($linkedIds) {
            return static function (string $column) use ($wpdb, $linkedIds, $positive): string {
                $in = implode(',', array_map(static fn($id) => $wpdb->prepare('%s', (string) $id), $linkedIds));
                $sql = "{$column} IN ({$in})";
                if ($positive === 'LIKE') {
                    foreach ($linkedIds as $id) {
                        $sql .= $wpdb->prepare(" OR {$column} LIKE %s", '%' . $wpdb->esc_like('"' . $id . '"') . '%');
                    }
                }
                return '(' . $sql . ')';
            };
        }

        [$sqlOp, $sqlValue] = self::operator($positive, $value);
        return static function (string $column) use ($wpdb, $sqlOp, $sqlValue): string {
            return $wpdb->prepare("{$column} {$sqlOp} %s", $sqlValue);
        };
    }

    /**
     * Entries of a Dynamic field's linked form whose shown value (or entry key) matches.
     *
     * @param array<string, mixed> $field
     * @return int[]
     */
    private static function linkedIds(array $field, string $positive, string $value): array {
        global $wpdb;
        $display = FormRepository::field((int) ($field['field_options']['form_select'] ?? 0));
        if (!$display) {
            return [];
        }
        [$sqlOp, $sqlValue] = self::operator($positive, $value);

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT item_id FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND meta_value {$sqlOp} %s",
            (int) $display['id'],
            $sqlValue
        ));
        if (!$ids) {
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d AND item_key {$sqlOp} %s",
                (int) $display['form_id'],
                $sqlValue
            ));
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Formidable's frm_where_filter hook, with the arguments it passes. A callback that returns
     * a string supplies its own SQL condition, written for Formidable's query: entry values as
     * "it" (meta_value, item_id) joined to fields as "fi". The theme uses it for two search
     * views. Array results (changes to Formidable's internal query array) are not supported;
     * no code on the site returns one.
     *
     * @param array<string, mixed> $view
     * @param array<string, mixed> $field
     * @param int[]|string $whereVal
     * @return string SQL selecting entry IDs, or '' to use the plugin's own condition
     */
    private static function whereFilter(array $view, array $field, string $op, $whereVal): string {
        global $wpdb;
        if (!has_filter('frm_where_filter')) {
            return '';
        }

        $default = ['fi.id' => (int) $field['id']];
        $where = apply_filters('frm_where_filter', $default, [
            'where_opt' => (string) $field['id'],
            'where_is' => $op,
            'where_val' => $whereVal,
            'form_id' => (int) $view['form_id'],
            'display' => get_post((int) $view['id']),
            'drafts' => 0,
            'use_ids' => false,
            'after_where' => true,
            'entry_ids' => [],
        ]);
        if (!is_string($where) || trim($where) === '') {
            return '';
        }
        return "SELECT it.item_id FROM {$wpdb->prefix}frm_item_metas it INNER JOIN {$wpdb->prefix}frm_fields fi ON fi.id = it.field_id WHERE " . $where;
    }

    /**
     * Checkboxes, multi-selects and address fields keep several values in one entry.
     *
     * @param array<string, mixed> $field
     */
    private static function holdsSeveralValues(array $field): bool {
        $opts = $field['field_options'];
        if ($field['type'] === 'checkbox' || $field['type'] === 'address') {
            return true;
        }
        if ($field['type'] === 'data' && ($opts['data_type'] ?? '') === 'checkbox') {
            return true;
        }
        return in_array($field['type'], ['select', 'data', 'lookup'], true) && !empty($opts['multiple']);
    }

    /**
     * @return array{0:string, 1:string} SQL operator and the value to bind
     */
    private static function operator(string $op, string $value): array {
        global $wpdb;
        switch ($op) {
            case '=':
            case '==':
            case '!=':
                return [$op === '!=' ? '!=' : '=', $value];
            case '>':
            case '<':
            case '>=':
            case '<=':
                return [$op, $value];
            case 'LIKE':
            case 'not LIKE':
                return [$op === 'LIKE' ? 'LIKE' : 'NOT LIKE', '%' . $wpdb->esc_like($value) . '%'];
            case 'LIKE%':
                return ['LIKE', $wpdb->esc_like($value) . '%'];
            case '%LIKE':
                return ['LIKE', '%' . $wpdb->esc_like($value)];
            default:
                return ['', ''];
        }
    }

    /**
     * @param array<string, string> $params
     */
    public static function resolveValue(string $raw, array $params): string {
        if ($raw === 'current_user' || $raw === '[user_id]') {
            return (string) get_current_user_id();
        }
        $value = preg_replace_callback('/\[get param=["\']?([A-Za-z0-9_\-]+)["\']?[^\]]*\]/', static function ($m) use ($params) {
            return (string) ($params[$m[1]] ?? '');
        }, $raw);
        return trim((string) $value);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array{0:string, 1:string} JOIN clauses and ORDER BY clause
     */
    private static function ordering(array $view, array $fields): array {
        global $wpdb;
        $metas = $wpdb->prefix . 'frm_item_metas';
        $joins = '';
        $order = [];

        foreach (ViewRepository::ordering($view) as $n => $row) {
            $field = $row['field'];
            if ($field === 'rand') {
                $order[] = 'RAND()';
            } elseif (in_array($field, self::COLUMNS, true)) {
                $order[] = "i.{$field} {$row['dir']}";
            } elseif (isset($fields[$field]) && ($map = PostFields::mapping($fields[$field])) && $map['kind'] !== 'taxonomy') {
                $alias = 'o' . $n;
                if ($map['kind'] === 'column') {
                    $joins .= " LEFT JOIN {$wpdb->posts} {$alias} ON {$alias}.ID = i.post_id";
                    $order[] = "MAX({$alias}.{$map['name']}) {$row['dir']}";
                } else {
                    $joins .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} {$alias} ON {$alias}.post_id = i.post_id AND {$alias}.meta_key = %s", $map['name']);
                    $order[] = "MAX({$alias}.meta_value) {$row['dir']}";
                }
            } elseif (isset($fields[$field])) {
                $alias = 'o' . $n;
                $joins .= $wpdb->prepare(" LEFT JOIN {$metas} {$alias} ON {$alias}.item_id = i.id AND {$alias}.field_id = %d", (int) $field);
                $numeric = in_array($fields[$field]['type'], ['number', 'range'], true);
                $order[] = ($numeric ? "CAST(MAX({$alias}.meta_value) AS DECIMAL(20,6))" : "MAX({$alias}.meta_value)") . " {$row['dir']}";
            }
        }

        // Formidable's default: oldest first
        $order[] = 'i.created_at ASC';
        $order[] = 'i.id ASC';
        return [$joins, 'ORDER BY ' . implode(', ', $order)];
    }
}
