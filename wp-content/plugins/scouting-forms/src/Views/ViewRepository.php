<?php

namespace ScoutingMemories\Forms\Views;

/**
 * ViewRepository
 *
 * Loads a Formidable view (a frm_display post and its frm_* meta) into a plain array.
 */
class ViewRepository {

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id = 0, string $key = ''): ?array {
        $post = null;
        if ($id > 0) {
            $post = get_post($id);
        } elseif ($key !== '') {
            $post = get_page_by_path($key, OBJECT, 'frm_display');
        }
        if (!$post || $post->post_type !== 'frm_display') {
            return null;
        }

        $options = maybe_unserialize(get_post_meta($post->ID, 'frm_options', true));
        $options = is_array($options) ? $options : [];

        return [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'form_id' => (int) get_post_meta($post->ID, 'frm_form_id', true),
            'show' => (string) (get_post_meta($post->ID, 'frm_show_count', true) ?: 'all'),
            'param' => (string) (get_post_meta($post->ID, 'frm_param', true) ?: 'entry'),
            'content' => (string) $post->post_content,
            'detail' => (string) get_post_meta($post->ID, 'frm_dyncontent', true),
            'options' => $options,
        ];
    }

    /**
     * Formidable stores filters/ordering as parallel keyed arrays; return them as rows.
     *
     * @return array<int, array{field:string, op:string, value:string}>
     */
    public static function filters(array $view): array {
        $o = $view['options'];
        $rows = [];
        foreach ((array) ($o['where'] ?? []) as $i => $field) {
            if ($field === '' || $field === null) {
                continue;
            }
            $rows[] = [
                'field' => (string) $field,
                'op' => (string) (($o['where_is'] ?? [])[$i] ?? '='),
                'value' => (string) (($o['where_val'] ?? [])[$i] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * @return array<int, array{field:string, dir:string}>
     */
    public static function ordering(array $view): array {
        $o = $view['options'];
        $rows = [];
        foreach ((array) ($o['order_by'] ?? []) as $i => $field) {
            if ($field === '' || $field === null) {
                continue;
            }
            $dir = strtoupper((string) (($o['order'] ?? [])[$i] ?? 'ASC'));
            $rows[] = ['field' => (string) $field, 'dir' => $dir === 'DESC' ? 'DESC' : 'ASC'];
        }
        return $rows;
    }
}
