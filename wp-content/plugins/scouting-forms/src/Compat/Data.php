<?php

namespace ScoutingMemories\Forms\Compat;

/**
 * Data
 *
 * The entry lookups the theme makes through Formidable's classes (FrmEntry::getAll/getOne/get_meta,
 * FrmEntryMeta::getEntryIds/add_entry_meta/update_entry_meta), with the same return shapes. Where
 * conditions use Formidable's array syntax ('meta_value like' => x, 'it.id' => [..]); column names
 * must match an allow-list, so a condition can never carry SQL of its own.
 */
class Data {

    private const COLUMN = '/^(?:(?:it|e|fi|fr|m|f)\.)?[a-z_]+$/';
    private const OPERATORS = ['!', '<', '>', '<=', '>=', 'like', 'not like', 'like%', '%like', '-'];

    /**
     * Formidable's where array to SQL (see FrmDb::parse_where_from_array). Unknown columns or
     * operators make the whole condition false.
     *
     * @param array<int|string, mixed> $where
     * @return array{0:string, 1:array<int, mixed>}
     */
    public static function where(array $where): array {
        $sql = '';
        $values = [];
        if (!$where) {
            return ['1=1', []];
        }
        $glue = ' AND ';
        if (isset($where['or'])) {
            $glue = ' OR ';
            unset($where['or']);
        }
        $parts = [];
        foreach ($where as $key => $value) {
            if (is_int($key)) {
                [$nested, $nestedValues] = self::where((array) $value);
                $parts[] = '(' . $nested . ')';
                $values = array_merge($values, $nestedValues);
                continue;
            }
            $part = self::condition((string) $key, $value, $values);
            if ($part === null) {
                return ['1=0', []];
            }
            $parts[] = $part;
        }
        $sql = implode($glue, $parts);
        return [$sql !== '' ? $sql : '1=1', $values];
    }

    /**
     * @param mixed $value
     * @param array<int, mixed> $values
     */
    private static function condition(string $key, $value, array &$values): ?string {
        $key = trim($key);
        $column = $key;
        $op = '';
        if (preg_match('/^(\S+)\s*(.*)$/', $key, $m)) {
            $column = $m[1];
            $op = strtolower(trim($m[2]));
        }
        if (substr($column, -1) === '!' || substr($column, -1) === '-') {
            $op = substr($column, -1);
            $column = substr($column, 0, -1);
        }
        if (!preg_match(self::COLUMN, $column) || ($op !== '' && !in_array($op, self::OPERATORS, true))) {
            return null;
        }
        if (in_array($column, ['created_at', 'updated_at', 'it.created_at', 'it.updated_at', 'e.created_at', 'e.updated_at'], true)) {
            $column = 'CAST(' . $column . ' as CHAR)';
        }

        global $wpdb;
        if (strpos($op, 'like') !== false) {
            $not = $op === 'not like' ? 'NOT ' : '';
            $list = [];
            foreach ((array) $value as $v) {
                $start = $op === 'like%' ? '' : '%';
                $end = $op === '%like' ? '' : '%';
                $list[] = "{$column} {$not}LIKE %s";
                $values[] = $start . $wpdb->esc_like((string) $v) . $end;
            }
            return $list ? '(' . implode(' OR ', $list) . ')' : '1=0';
        }
        if (is_array($value)) {
            if (!$value) {
                // An empty list matches nothing ("any of these IDs" with no IDs), never everything
                return $op === '!' ? '1=1' : '1=0';
            }
            $values = array_merge($values, array_values($value));
            return $column . ($op === '!' ? ' NOT' : '') . ' IN (' . implode(',', array_fill(0, count($value), '%s')) . ')';
        }
        if ($value === null) {
            return $column . ($op === '!' ? ' IS NOT NULL' : ' IS NULL');
        }
        $sqlOp = ['' => '=', '!' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>=', '-' => '='][$op];
        $numeric = is_numeric($value) && strpos($column, 'meta_value') === false;
        $values[] = $numeric ? $value + 0 : $value;
        return "{$column} {$sqlOp} " . ($numeric ? (is_float($value + 0) ? '%f' : '%d') : '%s');
    }

    /**
     * FrmEntry::getAll: entries keyed by ID (objects), with their metas when $meta is true.
     *
     * @param array<int|string, mixed>|string $where
     * @return array<int, object>
     */
    public static function getAll($where, string $orderBy = '', string $limit = '', bool $meta = false, bool $incForm = true): array {
        global $wpdb;
        if (!is_array($where)) {
            return [];
        }
        [$sql, $values] = self::where($where);
        $fields = 'it.id, it.item_key, it.name, it.ip, it.form_id, it.post_id, it.user_id, it.parent_item_id, it.updated_by, it.created_at, it.updated_at, it.is_draft, it.description';
        $table = "{$wpdb->prefix}frm_items it";
        if ($incForm) {
            $fields = 'it.*, fr.name as form_name, fr.form_key as form_key';
            $table .= " LEFT OUTER JOIN {$wpdb->prefix}frm_forms fr ON it.form_id=fr.id";
        }
        $query = "SELECT {$fields} FROM {$table} WHERE {$sql}" . self::orderBy($orderBy) . self::limit($limit);
        $entries = $wpdb->get_results($values ? $wpdb->prepare($query, $values) : $query, OBJECT_K);
        if (!$entries) {
            return [];
        }
        if ($meta) {
            $in = implode(',', array_map('intval', array_keys($entries)));
            $rows = $wpdb->get_results("SELECT it.item_id, it.meta_value, it.field_id FROM {$wpdb->prefix}frm_item_metas it WHERE it.field_id != 0 AND it.item_id IN ({$in})");
            foreach ($rows as $row) {
                $entry = $entries[$row->item_id] ?? null;
                if (!$entry) {
                    continue;
                }
                if (!isset($entry->metas)) {
                    $entry->metas = [];
                }
                $entry->metas[$row->field_id] = maybe_unserialize($row->meta_value);
            }
        }
        foreach ($entries as $entry) {
            self::prepare($entry);
        }
        return $entries;
    }

    /**
     * FrmEntry::getOne: one entry (by ID or key) with its form's name/key; metas when $meta.
     *
     * @param int|string $id
     * @return object|null
     */
    public static function getOne($id, bool $meta = false) {
        global $wpdb;
        if ($id === null || $id === '' || is_array($id) || is_object($id)) {
            return null;
        }
        $query = "SELECT it.*, fr.name as form_name, fr.form_key as form_key FROM {$wpdb->prefix}frm_items it
                  LEFT OUTER JOIN {$wpdb->prefix}frm_forms fr ON it.form_id=fr.id WHERE "
            . (is_numeric($id) ? $wpdb->prepare('it.id=%d', $id) : $wpdb->prepare('it.item_key=%s', $id));
        $entry = $wpdb->get_row($query);
        if (!$entry) {
            return null;
        }
        if ($meta) {
            $entry = self::getMeta($entry);
        }
        self::prepare($entry);
        return $entry;
    }

    /**
     * FrmEntry::get_meta: adds ->metas (field ID => value, and field key => value when the
     * frm_include_meta_keys filter says so).
     *
     * @param object|false|null $entry
     * @return object|false|null
     */
    public static function getMeta($entry) {
        global $wpdb;
        if (!$entry || !is_object($entry) || empty($entry->id)) {
            return $entry;
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.field_id, m.meta_value, f.field_key FROM {$wpdb->prefix}frm_item_metas m LEFT JOIN {$wpdb->prefix}frm_fields f ON m.field_id=f.id WHERE m.item_id=%d AND m.field_id != 0",
            $entry->id
        ));
        $entry->metas = [];
        $includeKey = apply_filters('frm_include_meta_keys', false, ['form_id' => $entry->form_id ?? 0]);
        foreach ($rows as $row) {
            $entry->metas[$row->field_id] = maybe_unserialize($row->meta_value);
            if ($includeKey && $row->field_key !== null) {
                $entry->metas[$row->field_key] = $entry->metas[$row->field_id];
            }
        }
        return $entry;
    }

    /**
     * FrmEntryMeta::getEntryIds: IDs of (non-draft) entries with a matching meta.
     *
     * @param array<int|string, mixed>|string $where
     * @return array<int, string>|string|null
     */
    public static function getEntryIds($where = [], string $orderBy = '', string $limit = '', bool $unique = true, array $args = []) {
        global $wpdb;
        if (!is_array($where)) {
            return [];
        }
        $args = wp_parse_args($args, ['is_draft' => false, 'user_id' => '', 'group_by' => '', 'return_parent_id' => false, 'return_parent_id_if_0_return_id' => false]);
        if (!$args['is_draft']) {
            $where['e.is_draft'] = 0;
        } elseif ((int) $args['is_draft'] === 1) {
            $where['e.is_draft'] = 1;
        }
        if (!empty($args['user_id'])) {
            $where['e.user_id'] = (int) $args['user_id'];
        }
        [$sql, $values] = self::where($where);
        $select = $args['return_parent_id_if_0_return_id'] ? 'IF (e.parent_item_id = 0, it.item_id, e.parent_item_id)'
            : ($args['return_parent_id'] ? 'e.parent_item_id' : 'it.item_id');
        $query = 'SELECT ' . ($unique ? 'DISTINCT ' : '') . $select
            . " FROM {$wpdb->prefix}frm_item_metas it LEFT OUTER JOIN {$wpdb->prefix}frm_fields fi ON it.field_id=fi.id"
            . " INNER JOIN {$wpdb->prefix}frm_items e ON (e.id=it.item_id) WHERE {$sql}" . self::orderBy($orderBy) . self::limit($limit);
        $query = $values ? $wpdb->prepare($query, $values) : $query;
        if (trim($limit) === 'LIMIT 1' || trim($limit) === '1') {
            return $wpdb->get_var($query);
        }
        $ids = $wpdb->get_col($query);
        if (trim($orderBy) === '') {
            // Formidable's query comes back in ID order
            sort($ids, SORT_NUMERIC);
        }
        return $ids;
    }

    /**
     * FrmEntryMeta::add_entry_meta. Blank values are not saved; unlike Formidable, a second row
     * for a field that already has one is not added (the theme then updates it instead).
     *
     * @param mixed $value
     */
    public static function addMeta(int $entryId, int $fieldId, $value): int {
        global $wpdb;
        if (self::isEmpty($value) || $entryId <= 0) {
            return 0;
        }
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}frm_item_metas WHERE item_id=%d AND field_id=%d LIMIT 1", $entryId, $fieldId));
        if ($exists) {
            return 0;
        }
        $ok = $wpdb->insert($wpdb->prefix . 'frm_item_metas', [
            'meta_value' => is_array($value) ? serialize(array_filter($value, [self::class, 'isNotEmpty'])) : trim((string) $value),
            'item_id' => $entryId,
            'field_id' => $fieldId,
            'created_at' => current_time('mysql', true),
        ]);
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * FrmEntryMeta::update_entry_meta: rows changed (0 when there is no row to update).
     *
     * @param mixed $value
     * @return int|false
     */
    public static function updateMeta(int $entryId, int $fieldId, $value) {
        global $wpdb;
        if (!$fieldId) {
            return false;
        }
        if (is_array($value)) {
            $value = array_filter($value, [self::class, 'isNotEmpty']);
        }
        return $wpdb->update($wpdb->prefix . 'frm_item_metas', ['meta_value' => maybe_serialize($value)], ['item_id' => $entryId, 'field_id' => $fieldId]);
    }

    /**
     * @param mixed $value
     */
    public static function isEmpty($value): bool {
        return $value === null || $value === '' || $value === [] || (is_array($value) && !array_filter($value, [self::class, 'isNotEmpty']));
    }

    /**
     * @param mixed $value
     */
    public static function isNotEmpty($value): bool {
        return !self::isEmpty($value);
    }

    private static function prepare(object $entry): void {
        if (isset($entry->description) && is_string($entry->description)) {
            $decoded = json_decode($entry->description, true);
            $entry->description = is_array($decoded) ? $decoded : maybe_unserialize($entry->description);
        }
        // Formidable unslashes the whole entry, metas included
        foreach (get_object_vars($entry) as $k => $v) {
            $entry->$k = wp_unslash($v);
        }
    }

    private static function orderBy(string $orderBy): string {
        $orderBy = trim($orderBy);
        if ($orderBy === '') {
            return '';
        }
        // "ORDER BY it.created_at DESC" and similar only
        return preg_match('/^(ORDER BY\s+)?((?:it|e|fi)\.)?[a-z_]+(\s+(ASC|DESC))?$/i', $orderBy)
            ? ' ' . (stripos($orderBy, 'ORDER BY') === 0 ? $orderBy : 'ORDER BY ' . $orderBy) : '';
    }

    private static function limit(string $limit): string {
        if (preg_match('/^\s*(LIMIT\s+)?(\d+)(\s*,\s*(\d+))?\s*$/i', $limit, $m)) {
            return ' LIMIT ' . (int) $m[2] . (isset($m[4]) ? ', ' . (int) $m[4] : '');
        }
        return '';
    }
}
