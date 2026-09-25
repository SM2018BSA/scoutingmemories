<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\ShortcodeTrust;

/**
 * FieldValue
 *
 * [sm_field_value field_id=163 user_id=current] (and [frm-field-value] once Formidable is gone):
 * one field's value from an entry, like Formidable's shortcode. The entry is the latest one of the
 * field's form, optionally for a user (user_id=current or an ID; never logged-out entries) or a
 * given entry (entry=ID or key, or the name of a URL parameter holding it; child entries of that
 * ID count too). Options: show (e.g. show=id), sep, default. The site uses it in form actions,
 * e.g. to email the person who submitted an index form.
 */
class FieldValue {

    /**
     * @param array<string, string>|string $atts
     */
    public static function render($atts = []): string {
        global $wpdb;
        $atts = is_array($atts) ? $atts : [];
        $default = (string) ($atts['default'] ?? '');
        $field = FormRepository::field(absint($atts['field_id'] ?? 0));
        if (!$field) {
            return esc_html($default);
        }
        // Outside administrators' settings (a post any author can write): only people who may see
        // entries, or someone asking about their own entry (user_id=current)
        if (!ShortcodeTrust::isTrusted() && !Permissions::can('view_entries')
            && !(is_user_logged_in() && ($atts['user_id'] ?? '') === 'current' && empty($atts['entry']) && empty($atts['entry_id']))) {
            return esc_html($default);
        }

        $where = [$wpdb->prepare('form_id = %d', (int) $field['form_id'])];
        $limit = '';

        $user = (string) ($atts['user_id'] ?? '');
        if ($user !== '') {
            $userId = $user === 'current' ? get_current_user_id() : absint($user);
            if (!$userId) {
                return esc_html($default);
            }
            $where[] = $wpdb->prepare('user_id = %d', $userId);
        }

        $entry = (string) ($atts['entry'] ?? ($atts['entry_id'] ?? ''));
        if ($entry !== '') {
            if (!ctype_digit($entry) && isset($_GET[$entry]) && is_scalar($_GET[$entry])) {
                // entry="entry" means "the entry in the URL"
                $entry = sanitize_title(wp_unslash((string) $_GET[$entry]));
            }
            if ($entry === '') {
                return esc_html($default);
            }
            $where[] = ctype_digit($entry)
                ? $wpdb->prepare('(id = %d OR parent_item_id = %d)', (int) $entry, (int) $entry)
                : $wpdb->prepare('item_key = %s', $entry);
        } else {
            $limit = ' LIMIT 1';
        }

        $ids = array_map('intval', $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC{$limit}"
        ));
        if (!$ids) {
            return esc_html($default);
        }

        $values = new EntryValues((int) $field['form_id']);
        $values->load($ids);
        // Categories as plain names unless links=1; long text in paragraphs, as Formidable does
        $show = array_intersect_key($atts, array_flip(['show', 'sep', 'format', 'size', 'links'])) + ['links' => '0', 'unlinked' => 'raw'];
        $parts = [];
        foreach ($ids as $id) {
            $shown = $field['type'] === 'file' && !isset($atts['show'])
                ? self::fileLinks($values->raw($id, (int) $field['id']), (string) ($atts['size'] ?? 'thumbnail'))
                : $values->display($id, (int) $field['id'], $show);
            if ($shown !== '' && in_array($field['type'], ['textarea', 'rte'], true) && !isset($atts['show'])) {
                $shown = wpautop($field['type'] === 'textarea' ? str_replace('<br />', '', $shown) : $shown);
            }
            if ($shown !== '') {
                $parts[] = $shown;
            }
        }
        return $parts ? implode(', ', $parts) : esc_html($default);
    }

    /**
     * Files as Formidable shows them here: the thumbnail linked to the file.
     *
     * @param mixed $value Attachment ID(s)
     */
    private static function fileLinks($value, string $size): string {
        $links = [];
        foreach ((array) $value as $attachmentId) {
            $url = (int) $attachmentId ? wp_get_attachment_url((int) $attachmentId) : false;
            if (!$url) {
                continue;
            }
            $image = wp_get_attachment_image((int) $attachmentId, $size);
            $links[] = '<a href="' . esc_url($url) . '" class="frm_file_link">' . ($image !== '' ? $image : esc_html(basename($url))) . '</a>';
        }
        return implode(' ', $links);
    }
}
