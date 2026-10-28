<?php

namespace ScoutingMemories\Forms\Models;

/**
 * PostFields
 *
 * Fields a "Create Post" action maps onto a WordPress post (title, status, category, custom
 * fields...) are not kept in the entry's values: Formidable reads them from the entry's post.
 * Each such field says where in field_options: post_field (post_title, post_status,
 * post_category, post_custom, ...), custom_field (the meta key) and taxonomy.
 */
class PostFields {

    private const COLUMNS = ['post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author', 'post_parent', 'menu_order', 'comment_status', 'post_password'];

    /**
     * @param array<string, mixed> $field From FormRepository
     * @return array{kind:string, name:string}|null kind is column, meta or taxonomy
     */
    public static function mapping(array $field): ?array {
        $postField = (string) ($field['field_options']['post_field'] ?? '');
        if ($postField === '') {
            return null;
        }
        if ($postField === 'post_custom') {
            $key = (string) ($field['field_options']['custom_field'] ?? '');
            return $key !== '' ? ['kind' => 'meta', 'name' => $key] : null;
        }
        if ($postField === 'post_category') {
            $taxonomy = (string) ($field['field_options']['taxonomy'] ?? '');
            return ['kind' => 'taxonomy', 'name' => $taxonomy !== '' ? $taxonomy : 'category'];
        }
        return in_array($postField, self::COLUMNS, true) ? ['kind' => 'column', 'name' => $postField] : null;
    }

    /**
     * Raw value of a mapped field for a post (taxonomy fields give term IDs).
     * Callers prime caches first (_prime_post_caches / update_object_term_cache) when batching.
     *
     * @param array{kind:string, name:string} $map
     * @param array<string, mixed> $field
     * @return mixed
     */
    public static function value(int $postId, array $map, array $field) {
        $post = get_post($postId);
        if (!$post) {
            return '';
        }
        switch ($map['kind']) {
            case 'meta':
                return self::metaValue($postId, $map['name']);
            case 'taxonomy':
                $terms = get_the_terms($postId, $map['name']);
                if (!is_array($terms)) {
                    return [];
                }
                $exclude = array_map('intval', (array) ($field['field_options']['exclude_cat'] ?? []));
                $ids = [];
                foreach ($terms as $term) {
                    if (!in_array((int) $term->term_id, $exclude, true)) {
                        $ids[] = (int) $term->term_id;
                    }
                }
                return $ids;
            default:
                return $post->{$map['name']} ?? '';
        }
    }

    /**
     * A custom field named "_name" is the ACF field "name" when the post has ACF fields: read
     * with get_field(), as Formidable does ("_name" itself holds ACF's reference key).
     *
     * @return mixed
     */
    public static function metaValue(int $postId, string $key) {
        if (self::isAcfField($postId, $key) && function_exists('get_field')) {
            return get_field(substr($key, 1), $postId);
        }
        return get_post_meta($postId, $key, true);
    }

    /**
     * Save a custom field as Formidable does: blank removes it; "_name" of an ACF field on a post
     * that already has ACF fields is saved through ACF (on a new post the Add a Post form's hidden
     * fields write ACF's reference keys into "_name" themselves).
     *
     * @param mixed $value
     */
    public static function saveMeta(int $postId, string $key, $value): void {
        if ($value === '' || $value === [] || $value === null) {
            delete_post_meta($postId, $key);
            return;
        }
        if (self::isAcfField($postId, $key) && function_exists('update_field')) {
            update_field(self::acfFieldKey($key), $value, $postId);
            return;
        }
        update_post_meta($postId, $key, wp_slash($value));
    }

    private static function isAcfField(int $postId, string $key): bool {
        if (self::acfFieldKey($key) === '') {
            return false;
        }
        $objects = get_field_objects($postId);
        return is_array($objects) && isset($objects[substr($key, 1)]);
    }

    /**
     * ACF field key (field_...) for a custom field named "_name", or ''.
     */
    private static function acfFieldKey(string $key): string {
        static $cache = [];
        if ($key === '' || $key[0] !== '_' || !function_exists('get_field_objects')) {
            return '';
        }
        if (!array_key_exists($key, $cache)) {
            global $wpdb;
            $cache[$key] = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_excerpt = %s LIMIT 1",
                substr($key, 1)
            ));
        }
        return $cache[$key];
    }

    /**
     * SQL selecting the post IDs whose mapped field matches, for view filters.
     *
     * @param array{kind:string, name:string} $map
     * @param callable(string): string $condition Builds the comparison for a given SQL column
     */
    public static function matchSql(array $map, callable $condition): string {
        global $wpdb;
        switch ($map['kind']) {
            case 'meta':
                return $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND ", $map['name'])
                    . $condition('meta_value');
            case 'taxonomy':
                return $wpdb->prepare(
                    "SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                     WHERE tt.taxonomy = %s AND ",
                    $map['name']
                ) . '(' . $condition('CAST(t.term_id AS CHAR)') . ' OR ' . $condition('t.name') . ' OR ' . $condition('t.slug') . ')';
            default:
                return "SELECT ID FROM {$wpdb->posts} WHERE " . $condition($map['name']);
        }
    }
}
