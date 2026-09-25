<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\Permissions;

/**
 * TemplateTags
 *
 * Fills a view's row template for one entry, the way Formidable does:
 *   [id] [key] [N] [field_key] (with show= sep= format= options), [created-at] [updated-at],
 *   [user_id], [get param=x], [if N equals=".."]...[/if N], [editlink], [deletelink],
 *   [frm-entry-update-field], [detaillink].
 * Tags it does not know are left alone so nested shortcodes (other views, forms) still run.
 */
class TemplateTags {

    /**
     * @param array<string, mixed> $view
     * @param array<string, string> $params
     */
    public static function render(string $template, int $entryId, EntryValues $values, array $view, array $params): string {
        $entry = $values->entry($entryId);
        if (!$entry) {
            return '';
        }

        // Entry ID/key first, so tags like [frm-show-entry id=[id]] get a real ID
        $out = str_replace(['[id]', '[key]'], [(string) $entryId, esc_html((string) $entry['item_key'])], $template);

        $out = self::conditionals($out, $entryId, $values);

        $out = preg_replace_callback('/\[editlink([^\]]*)\]/', static function ($m) use ($entry) {
            return self::editLink($entry, self::atts($m[1]));
        }, $out);

        $out = preg_replace_callback('/\[deletelink([^\]]*)\]/', static function ($m) use ($entry) {
            return self::deleteLink($entry, self::atts($m[1]));
        }, $out);

        $out = preg_replace_callback('/\[frm-entry-update-field([^\]]*)\]/', static function ($m) use ($entry) {
            return self::updateFieldLink($entry, self::atts($m[1]));
        }, $out);

        $out = preg_replace_callback('/\[detaillink\]/', static function () use ($entryId, $view) {
            return esc_url(add_query_arg($view['param'], $entryId));
        }, $out);

        // Value tags: [N], [field_key], [created-at format=".."], [get param=x], ...
        // Inside an HTML tag a value stays fully escaped (it is an attribute). In page text its
        // quotes can be literal, so the view's formatting turns them into curly quotes like
        // Formidable's output.
        $parts = preg_split('/(<[^>]*>)/', $out, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            $inTag = $part !== '' && $part[0] === '<';
            $parts[$i] = preg_replace_callback('/\[([A-Za-z0-9_\-]+)((?:\s+[^\]\[]*)?)\]/', static function ($m) use ($entry, $entryId, $values, $params, $inTag) {
                $value = self::valueTag($m[1], self::atts($m[2]), $entry, $entryId, $values, $params);
                if ($value === null) {
                    return $m[0];
                }
                return $inTag ? $value : str_replace(['&quot;', '&#039;'], ['"', "'"], $value);
            }, $part);
        }
        return implode('', $parts);
    }

    /**
     * Point nested Formidable shortcodes at this plugin's versions.
     */
    public static function ownShortcodes(string $html): string {
        return preg_replace(
            ['/\[display-frm-data(?=[\s\]])/', '/\[formidable(?=[\s\]])/', '/\[frm-show-entry(?=[\s\]])/'],
            ['[sm_view', '[sm_form', '[sm_show_entry'],
            $html
        );
    }

    /**
     * @param array<string, string> $atts
     * @param array<string, mixed> $entry
     * @param array<string, string> $params
     */
    private static function valueTag(string $tag, array $atts, array $entry, int $entryId, EntryValues $values, array $params): ?string {
        switch ($tag) {
            case 'created-at':
            case 'created_at':
            case 'updated-at':
            case 'updated_at':
                $column = strpos($tag, 'created') === 0 ? 'created_at' : 'updated_at';
                $format = $atts['format'] ?? get_option('date_format');
                return esc_html(get_date_from_gmt((string) $entry[$column], $format));
            case 'user_id':
                return (string) (int) $entry['user_id'];
            case 'post_id':
                return (string) (int) $entry['post_id'];
            case 'get':
                $name = (string) ($atts['param'] ?? '');
                return esc_html((string) ($params[$name] ?? ($atts['default'] ?? '')));
        }

        $fieldId = $values->fieldId($tag);
        if ($fieldId === 0) {
            return null;
        }
        return $values->display($entryId, $fieldId, $atts);
    }

    /**
     * [if N] / [if N equals="x"] / not_equal / like / not_like / greater_than / less_than.
     * Innermost blocks first, so an [if] may contain another [if] of a different field.
     */
    private static function conditionals(string $out, int $entryId, EntryValues $values): string {
        $pattern = '/\[if\s+([A-Za-z0-9_\-]+)([^\]]*)\]((?:(?!\[if\s).)*?)\[\/if\s+\1\]/s';
        for ($i = 0; $i < 10 && preg_match($pattern, $out); $i++) {
            $out = preg_replace_callback($pattern, static function ($m) use ($entryId, $values) {
                $fieldId = $values->fieldId($m[1]);
                if ($fieldId === 0) {
                    return $m[0];
                }
                $raw = $values->raw($entryId, $fieldId);
                $shown = html_entity_decode(wp_strip_all_tags($values->display($entryId, $fieldId)), ENT_QUOTES);
                $raw = is_array($raw) ? implode(', ', $raw) : (string) $raw;
                return self::conditionMet($raw, $shown, self::atts($m[2])) ? $m[3] : '';
            }, $out);
        }
        return $out;
    }

    /**
     * @param array<string, string> $atts
     */
    private static function conditionMet(string $raw, string $shown, array $atts): bool {
        $is = static function (string $want) use ($raw, $shown): bool {
            return strcasecmp($raw, $want) === 0 || strcasecmp($shown, $want) === 0;
        };
        if (isset($atts['equals'])) {
            return $atts['equals'] === '' ? trim($raw) === '' : $is($atts['equals']);
        }
        if (isset($atts['not_equal'])) {
            return $atts['not_equal'] === '' ? trim($raw) !== '' : !$is($atts['not_equal']);
        }
        if (isset($atts['like'])) {
            return stripos($raw, $atts['like']) !== false || stripos($shown, $atts['like']) !== false;
        }
        if (isset($atts['not_like'])) {
            return stripos($raw, $atts['not_like']) === false && stripos($shown, $atts['not_like']) === false;
        }
        if (isset($atts['greater_than'])) {
            return is_numeric($raw) && (float) $raw > (float) $atts['greater_than'];
        }
        if (isset($atts['less_than'])) {
            return is_numeric($raw) && (float) $raw < (float) $atts['less_than'];
        }
        return trim($raw) !== '';
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string> $atts
     */
    private static function editLink(array $entry, array $atts): string {
        if (!Permissions::canEditEntry($entry)) {
            return '';
        }
        $pageId = (int) ($atts['page_id'] ?? 0);
        $base = $pageId > 0 ? get_permalink($pageId) : '';
        $url = add_query_arg(['frm_action' => 'edit', 'entry' => (int) $entry['id']], $base ?: remove_query_arg(['frm_action', 'entry']));
        $class = trim('frm_edit_link ' . ($atts['class'] ?? ''));

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($atts['label'] ?? __('Edit', 'scouting-forms')) . '</a>';
    }

    /**
     * Deleting changes data, so it is a small POST form with a nonce rather than a link.
     *
     * @param array<string, mixed> $entry
     * @param array<string, string> $atts
     */
    private static function deleteLink(array $entry, array $atts): string {
        if (!Permissions::canDeleteEntry($entry)) {
            return '';
        }
        $entryId = (int) $entry['id'];
        $confirm = $atts['confirm'] ?? __('Are you sure you want to delete that entry?', 'scouting-forms');

        return EntryActions::button(
            'delete',
            $entryId,
            ['sm_confirm' => $confirm],
            $atts['label'] ?? __('Delete', 'scouting-forms'),
            trim('frm_delete_link ' . ($atts['class'] ?? ''))
        );
    }

    /**
     * [frm-entry-update-field id=[id] field_id=465 value="publish" label="Publish"]
     *
     * @param array<string, mixed> $entry
     * @param array<string, string> $atts
     */
    private static function updateFieldLink(array $entry, array $atts): string {
        $entryId = (int) ($atts['id'] ?? $entry['id']);
        $fieldId = (int) ($atts['field_id'] ?? 0);
        $value = (string) ($atts['value'] ?? '');
        if ($entryId !== (int) $entry['id'] || $fieldId <= 0) {
            return '';
        }
        if (!Permissions::canEditEntry($entry)) {
            return '';
        }

        return EntryActions::button(
            'update_field',
            $entryId,
            ['sm_field' => (string) $fieldId, 'sm_value' => $value],
            $atts['label'] ?? __('Update', 'scouting-forms'),
            trim('frm_update_field_link ' . ($atts['class'] ?? ''))
        );
    }

    /**
     * shortcode_parse_atts() without the numeric keys, always an array.
     *
     * @return array<string, string>
     */
    private static function atts(string $text): array {
        $parsed = shortcode_parse_atts(trim($text));
        if (!is_array($parsed)) {
            return [];
        }
        return array_filter($parsed, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
