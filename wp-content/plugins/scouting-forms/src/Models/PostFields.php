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
                return get_post_meta($postId, $map['name'], true);
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
