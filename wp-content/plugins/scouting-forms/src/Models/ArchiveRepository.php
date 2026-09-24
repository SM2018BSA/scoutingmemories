<?php

namespace ScoutingMemories\Forms\Models;

/**
 * ArchiveRepository
 *
 * Provides fast, unified access to Councils, Camps, Lodges, and States.
 * Compatible with existing archive database records and cached for high performance.
 */
class ArchiveRepository {

    const FORM_COUNCILS = 8;
    const FORM_CAMPS    = 11;
    const FORM_LODGES   = 7;
    const FORM_STATES   = 10;

    // Field IDs from archive schema
    const FID_STATE_NAME = 113;
    const FID_STATE_ACL  = 114;

    const FID_COUNCIL_NAME   = 98;
    const FID_COUNCIL_NUM    = 138;
    const FID_COUNCIL_STATE  = 100;
    const FID_COUNCIL_START  = 102;
    const FID_COUNCIL_END    = 103;
    const FID_COUNCIL_SLUG   = 105;
    const FID_COUNCIL_ACTIVE = 329;

    const FID_CAMP_NAME    = 117;
    const FID_CAMP_STATE   = 115;
    const FID_CAMP_COUNCIL = 116;
    const FID_CAMP_START   = 120;
    const FID_CAMP_END     = 121;
    const FID_CAMP_SLUG    = 123;
    const FID_CAMP_ACTIVE  = 328;

    const FID_LODGE_NAME    = 90;
    const FID_LODGE_NUM     = 91;
    const FID_LODGE_COUNCIL = 89;
    const FID_LODGE_STATE   = 88;
    const FID_LODGE_START   = 94;
    const FID_LODGE_END     = 95;
    const FID_LODGE_SLUG    = 97;
    const FID_LODGE_ACTIVE  = 327;

    /**
     * Get all states
     *
     * @return array
     */
    public static function getStates(): array {
        global $wpdb;

        $cache_key = 'sm_archive_states';
        $states = wp_cache_get($cache_key);
        if ($states !== false) {
            return $states;
        }

        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $query = "
            SELECT 
                i.id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS name,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS code
            FROM {$items_table} i
            JOIN {$metas_table} m ON i.id = m.item_id
            WHERE i.form_id = %d
            GROUP BY i.id
            ORDER BY name ASC
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, self::FID_STATE_NAME, self::FID_STATE_ACL, self::FORM_STATES),
            ARRAY_A
        );

        $states = [];
        if ($results) {
            foreach ($results as $row) {
                if (!empty($row['name'])) {
                    $states[] = [
                        'id'   => (int) $row['id'],
                        'name' => trim($row['name']),
                        'code' => trim($row['code'] ?? '')
                    ];
                }
            }
        }

        wp_cache_set($cache_key, $states, '', 3600);
        return $states;
    }

    /**
     * Get councils with optional filtering
     *
     * @param int|null $stateId
     * @param bool $activeOnly
     * @param string $search
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getCouncils(?int $stateId = null, bool $activeOnly = false, string $search = '', int $limit = 0, int $offset = 0): array {
        global $wpdb;

        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $where_clauses = ["i.form_id = " . self::FORM_COUNCILS];

        if ($stateId) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND (meta_value = %d OR meta_value LIKE %s))",
                self::FID_COUNCIL_STATE,
                $stateId,
                '%"' . $stateId . '"%'
            );
        }

        if ($activeOnly) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND meta_value = 'Yes')",
                self::FID_COUNCIL_ACTIVE
            );
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE (field_id = %d OR field_id = %d OR field_id = %d) AND meta_value LIKE %s)",
                self::FID_COUNCIL_NAME,
                self::FID_COUNCIL_NUM,
                self::FID_COUNCIL_SLUG,
                $like
            );
        }

        $where_sql = implode(' AND ', $where_clauses);
        $limit_sql = $limit > 0 ? $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset) : "";

        $query = "
            SELECT 
                i.id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS name,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS number,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS slug,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS state_id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS start_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS end_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS active
            FROM {$items_table} i
            JOIN {$metas_table} m ON i.id = m.item_id
            WHERE {$where_sql}
            GROUP BY i.id
            ORDER BY name ASC
            {$limit_sql}
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                self::FID_COUNCIL_NAME,
                self::FID_COUNCIL_NUM,
                self::FID_COUNCIL_SLUG,
                self::FID_COUNCIL_STATE,
                self::FID_COUNCIL_START,
                self::FID_COUNCIL_END,
                self::FID_COUNCIL_ACTIVE
            ),
            ARRAY_A
        );

        $councils = [];
        if ($results) {
            foreach ($results as $r) {
                $name = trim($r['name'] ?? '');
                $num  = trim($r['number'] ?? '');
                $display = $num ? "{$name} (#{$num})" : $name;

                $councils[] = [
                    'id'           => (int) $r['id'],
                    'name'         => $name,
                    'number'       => $num,
                    'display_name' => $display,
                    'slug'         => trim($r['slug'] ?? ''),
                    'state_id'     => (int) ($r['state_id'] ?? 0),
                    'start_date'   => trim($r['start_date'] ?? ''),
                    'end_date'     => trim($r['end_date'] ?? ''),
                    'active'       => trim($r['active'] ?? 'No')
                ];
            }
        }

        return $councils;
    }

    /**
     * Get camps with optional council/state filtering
     *
     * @param int|null $councilId
     * @param int|null $stateId
     * @param string $search
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getCamps(?int $councilId = null, ?int $stateId = null, string $search = '', int $limit = 0, int $offset = 0): array {
        global $wpdb;

        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $where_clauses = ["i.form_id = " . self::FORM_CAMPS];

        if ($councilId) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND (meta_value = %d OR meta_value LIKE %s))",
                self::FID_CAMP_COUNCIL,
                $councilId,
                '%"' . $councilId . '"%'
            );
        }

        if ($stateId) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND (meta_value = %d OR meta_value LIKE %s))",
                self::FID_CAMP_STATE,
                $stateId,
                '%"' . $stateId . '"%'
            );
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE (field_id = %d OR field_id = %d) AND meta_value LIKE %s)",
                self::FID_CAMP_NAME,
                self::FID_CAMP_SLUG,
                $like
            );
        }

        $where_sql = implode(' AND ', $where_clauses);
        $limit_sql = $limit > 0 ? $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset) : "";

        $query = "
            SELECT 
                i.id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS name,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS slug,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS council_meta,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS state_id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS start_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS end_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS active
            FROM {$items_table} i
            JOIN {$metas_table} m ON i.id = m.item_id
            WHERE {$where_sql}
            GROUP BY i.id
            ORDER BY name ASC
            {$limit_sql}
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                self::FID_CAMP_NAME,
                self::FID_CAMP_SLUG,
                self::FID_CAMP_COUNCIL,
                self::FID_CAMP_STATE,
                self::FID_CAMP_START,
                self::FID_CAMP_END,
                self::FID_CAMP_ACTIVE
            ),
            ARRAY_A
        );

        $camps = [];
        if ($results) {
            foreach ($results as $r) {
                $camps[] = [
                    'id'           => (int) $r['id'],
                    'name'         => trim($r['name'] ?? ''),
                    'slug'         => trim($r['slug'] ?? ''),
                    'council_meta' => $r['council_meta'] ?? '',
                    'state_id'     => (int) ($r['state_id'] ?? 0),
                    'start_date'   => trim($r['start_date'] ?? ''),
                    'end_date'     => trim($r['end_date'] ?? ''),
                    'active'       => trim($r['active'] ?? 'No')
                ];
            }
        }

        return $camps;
    }

    /**
     * Get lodges with optional council/state filtering
     *
     * @param int|null $councilId
     * @param int|null $stateId
     * @param string $search
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getLodges(?int $councilId = null, ?int $stateId = null, string $search = '', int $limit = 0, int $offset = 0): array {
        global $wpdb;

        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $where_clauses = ["i.form_id = " . self::FORM_LODGES];

        if ($councilId) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND (meta_value = %d OR meta_value LIKE %s))",
                self::FID_LODGE_COUNCIL,
                $councilId,
                '%"' . $councilId . '"%'
            );
        }

        if ($stateId) {
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE field_id = %d AND (meta_value = %d OR meta_value LIKE %s))",
                self::FID_LODGE_STATE,
                $stateId,
                '%"' . $stateId . '"%'
            );
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
                "i.id IN (SELECT item_id FROM {$metas_table} WHERE (field_id = %d OR field_id = %d OR field_id = %d) AND meta_value LIKE %s)",
                self::FID_LODGE_NAME,
                self::FID_LODGE_NUM,
                self::FID_LODGE_SLUG,
                $like
            );
        }

        $where_sql = implode(' AND ', $where_clauses);
        $limit_sql = $limit > 0 ? $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset) : "";

        $query = "
            SELECT 
                i.id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS name,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS number,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS slug,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS council_id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS state_id,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS start_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS end_date,
                MAX(CASE WHEN m.field_id = %d THEN m.meta_value END) AS active
            FROM {$items_table} i
            JOIN {$metas_table} m ON i.id = m.item_id
            WHERE {$where_sql}
            GROUP BY i.id
            ORDER BY name ASC
            {$limit_sql}
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                self::FID_LODGE_NAME,
                self::FID_LODGE_NUM,
                self::FID_LODGE_SLUG,
                self::FID_LODGE_COUNCIL,
                self::FID_LODGE_STATE,
                self::FID_LODGE_START,
                self::FID_LODGE_END,
                self::FID_LODGE_ACTIVE
            ),
            ARRAY_A
        );

        $lodges = [];
        if ($results) {
            foreach ($results as $r) {
                $name = trim($r['name'] ?? '');
                $num  = trim($r['number'] ?? '');
                $display = $num ? "{$name} (#{$num})" : $name;

                $lodges[] = [
                    'id'           => (int) $r['id'],
                    'name'         => $name,
                    'number'       => $num,
                    'display_name' => $display,
                    'slug'         => trim($r['slug'] ?? ''),
                    'council_id'   => (int) ($r['council_id'] ?? 0),
                    'state_id'     => (int) ($r['state_id'] ?? 0),
                    'start_date'   => trim($r['start_date'] ?? ''),
                    'end_date'     => trim($r['end_date'] ?? ''),
                    'active'       => trim($r['active'] ?? 'No')
                ];
            }
        }

        return $lodges;
    }

    /**
     * Update active end dates for Councils, Lodges, and Camps
     * Replaces the old smp_action=update_end_dates Formidable tool.
     *
     * @param string|null $currentYear
     * @return array Counts of updated rows
     */
    public static function updateActiveEndDates(?string $currentYear = null): array {
        global $wpdb;

        if (!$currentYear) {
            $currentYear = date('Y');
        }

        $metas_table = $wpdb->prefix . 'frm_item_metas';
        $items_table = $wpdb->prefix . 'frm_items';

        $counts = ['councils' => 0, 'camps' => 0, 'lodges' => 0];

        // 1. Councils
        $councils_sql = "
            UPDATE {$metas_table} m
            JOIN {$items_table} i ON m.item_id = i.id
            JOIN {$metas_table} active_meta ON active_meta.item_id = i.id AND active_meta.field_id = %d
            SET m.meta_value = %s
            WHERE i.form_id = %d AND m.field_id = %d AND active_meta.meta_value = 'Yes'
        ";
        $counts['councils'] = $wpdb->query($wpdb->prepare($councils_sql, self::FID_COUNCIL_ACTIVE, $currentYear, self::FORM_COUNCILS, self::FID_COUNCIL_END));

        // 2. Camps
        $camps_sql = "
            UPDATE {$metas_table} m
            JOIN {$items_table} i ON m.item_id = i.id
            JOIN {$metas_table} active_meta ON active_meta.item_id = i.id AND active_meta.field_id = %d
            SET m.meta_value = %s
            WHERE i.form_id = %d AND m.field_id = %d AND active_meta.meta_value = 'Yes'
        ";
        $counts['camps'] = $wpdb->query($wpdb->prepare($camps_sql, self::FID_CAMP_ACTIVE, $currentYear, self::FORM_CAMPS, self::FID_CAMP_END));

        // 3. Lodges
        $lodges_sql = "
            UPDATE {$metas_table} m
            JOIN {$items_table} i ON m.item_id = i.id
            JOIN {$metas_table} active_meta ON active_meta.item_id = i.id AND active_meta.field_id = %d
            SET m.meta_value = %s
            WHERE i.form_id = %d AND m.field_id = %d AND active_meta.meta_value = 'Yes'
        ";
        $counts['lodges'] = $wpdb->query($wpdb->prepare($lodges_sql, self::FID_LODGE_ACTIVE, $currentYear, self::FORM_LODGES, self::FID_LODGE_END));

        return $counts;
    }

    /**
     * Get aggregate counts of archive items for dashboard/guide telemetry
     *
     * @return array
     */
    public static function getArchiveCounts(): array {
        global $wpdb;

        $cache_key = 'sm_archive_counts';
        $counts = wp_cache_get($cache_key);
        if ($counts !== false) {
            return $counts;
        }

        $items_table = $wpdb->prefix . 'frm_items';
        $results = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as cnt FROM {$items_table} WHERE form_id IN (8, 11, 7, 10, 6) GROUP BY form_id",
            OBJECT_K
        );

        $counts = [
            'councils' => (int) ($results[self::FORM_COUNCILS]->cnt ?? 0),
            'camps'    => (int) ($results[self::FORM_CAMPS]->cnt ?? 0),
            'lodges'   => (int) ($results[self::FORM_LODGES]->cnt ?? 0),
            'states'   => (int) ($results[self::FORM_STATES]->cnt ?? 0),
            'posts'    => (int) ($results[6]->cnt ?? 0),
        ];

        wp_cache_set($cache_key, $counts, '', 3600);
        return $counts;
    }
}
