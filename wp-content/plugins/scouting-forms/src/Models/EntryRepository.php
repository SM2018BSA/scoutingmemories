<?php

namespace ScoutingMemories\Forms\Models;

use ScoutingMemories\Forms\Support\TestData;

/**
 * EntryRepository
 *
 * The single place the plugin creates Formidable entries (wp_frm_items + wp_frm_item_metas),
 * with the same columns Formidable itself writes. Later phases hook form actions (emails,
 * post creation, registration) and Formidable-compatible events in here.
 */
class EntryRepository {

    /**
     * Create an entry and its field values.
     *
     * @param int $formId
     * @param array<int|string, mixed> $metas field_id => value (arrays are serialized like Formidable does)
     * @param array<string, mixed> $args Optional: key, name, user_id, post_id, parent_item_id, is_draft, ip
     * @return int New entry ID, or 0 on failure
     */
    public static function create(int $formId, array $metas, array $args = []): int {
        global $wpdb;

        $userId = isset($args['user_id']) ? (int) $args['user_id'] : get_current_user_id();
        $now = current_time('mysql');
        $baseKey = $args['key'] ?? ('entry-' . wp_generate_password(8, false, false));

        $inserted = $wpdb->insert($wpdb->prefix . 'frm_items', [
            'item_key' => TestData::itemKey($baseKey),
            'name' => (string) ($args['name'] ?? ''),
            'description' => '',
            'ip' => isset($args['ip']) ? (string) $args['ip'] : self::clientIp(),
            'form_id' => $formId,
            'post_id' => (int) ($args['post_id'] ?? 0),
            'user_id' => $userId,
            'parent_item_id' => (int) ($args['parent_item_id'] ?? 0),
            'is_draft' => (int) ($args['is_draft'] ?? 0),
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s']);

        if (!$inserted) {
            return 0;
        }

        $entryId = (int) $wpdb->insert_id;
        foreach ($metas as $fieldId => $value) {
            $wpdb->insert($wpdb->prefix . 'frm_item_metas', [
                'meta_value' => is_array($value) ? maybe_serialize($value) : (string) $value,
                'field_id' => (int) $fieldId,
                'item_id' => $entryId,
                'created_at' => $now,
            ], ['%s', '%d', '%d', '%s']);
        }

        self::purgeCaches($formId);
        return $entryId;
    }

    private static function clientIp(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private static function purgeCaches(int $formId): void {
        wp_cache_delete('sm_entries_form_' . $formId);
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }
    }
}
