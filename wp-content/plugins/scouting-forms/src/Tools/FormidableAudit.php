<?php

namespace ScoutingMemories\Forms\Tools;

/**
 * FormidableAudit
 *
 * Read-only snapshot of everything the site configures in Formidable Forms: forms, fields
 * (with conditional logic and Dynamic-field settings), form actions, views, where forms and
 * views are placed, which roles hold frm_* capabilities, and which Formidable APIs the active
 * theme calls. Later work checks the plugin's behaviour against this snapshot.
 *
 * No entries (personal data) are exported, email addresses are redacted and Formidable's global
 * settings (which hold CAPTCHA keys) are left out. The output file is git-ignored.
 *
 * Run locally:
 *   docker compose exec -T wordpress php -r 'define("WP_USE_THEMES",false);
 *     $_SERVER["HTTP_HOST"]="localhost:8088"; require "/var/www/html/wp-load.php";
 *     echo \ScoutingMemories\Forms\Tools\FormidableAudit::writeFile();'
 */
class FormidableAudit {

    public const OUTPUT = 'docs/formidable-usage.json';

    /**
     * Build the snapshot and write it to the plugin's docs folder.
     *
     * @return string Summary line with the output path and counts
     */
    public static function writeFile(): string {
        $data = self::build();
        $path = SM_FORMS_PLUGIN_DIR . self::OUTPUT;
        wp_mkdir_p(dirname($path));
        file_put_contents($path, wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return sprintf(
            "Wrote %s: %d forms, %d fields, %d actions, %d views, %d placements, %d theme call sites\n",
            self::OUTPUT,
            count($data['forms']),
            array_sum(array_map(fn($f) => count($f['fields']), $data['forms'])),
            count($data['actions']),
            count($data['views']),
            count($data['placements']),
            count($data['theme_calls'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(): array {
        return [
            'generated_at' => gmdate('c'),
            'note' => 'Read-only Formidable configuration snapshot. No entries; emails redacted.',
            'forms' => self::forms(),
            'actions' => self::actions(),
            'views' => self::views(),
            'placements' => self::placements(),
            'capabilities' => self::capabilities(),
            'theme_calls' => self::themeCalls(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function forms(): array {
        global $wpdb;
        $forms = [];
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}frm_forms WHERE status = 'published' ORDER BY id");

        foreach ($rows as $form) {
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS entries, MAX(created_at) AS last_entry FROM {$wpdb->prefix}frm_items WHERE form_id = %d",
                $form->id
            ));
            $fields = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order, id",
                $form->id
            ));

            $forms[] = [
                'id' => (int) $form->id,
                'key' => $form->form_key,
                'name' => $form->name,
                'description' => $form->description,
                'parent_form_id' => (int) $form->parent_form_id,
                'is_template' => (int) $form->is_template,
                'entries' => (int) $stats->entries,
                'last_entry' => $stats->last_entry,
                'options' => self::redact(maybe_unserialize($form->options)),
                'fields' => array_map(fn($field) => [
                    'id' => (int) $field->id,
                    'key' => $field->field_key,
                    'name' => $field->name,
                    'description' => $field->description,
                    'type' => $field->type,
                    'default_value' => self::redact(maybe_unserialize($field->default_value)),
                    'options' => self::redact(maybe_unserialize($field->options)),
                    'field_order' => (int) $field->field_order,
                    'required' => (int) $field->required,
                    'field_options' => self::redact(maybe_unserialize($field->field_options)),
                ], $fields),
            ];
        }

        return $forms;
    }

    /**
     * Form actions (email, on_submit, register, wppost, ...). Stored as frm_form_actions posts:
     * post_excerpt = action type, menu_order = form ID, post_content = JSON settings.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function actions(): array {
        $posts = get_posts([
            'post_type' => 'frm_form_actions',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'orderby' => 'menu_order ID',
            'order' => 'ASC',
        ]);

        return array_map(fn($post) => [
            'id' => $post->ID,
            'type' => $post->post_excerpt,
            'form_id' => (int) $post->menu_order,
            'status' => $post->post_status,
            'name' => $post->post_title,
            'settings' => self::redact(json_decode($post->post_content, true)),
        ], $posts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function views(): array {
        $posts = get_posts([
            'post_type' => 'frm_display',
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $views = [];
        foreach ($posts as $post) {
            $meta = [];
            foreach (get_post_meta($post->ID) as $key => $values) {
                if (strpos($key, 'frm_') === 0) {
                    $meta[$key] = self::redact(maybe_unserialize($values[0]));
                }
            }
            $views[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'meta' => $meta,
            ];
        }

        return $views;
    }

    /**
     * Published content that places Formidable forms/views via shortcodes or blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function placements(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type NOT IN ('frm_display', 'frm_form_actions', 'revision')
               AND (post_content LIKE '%[formidable%' OR post_content LIKE '%[display-frm-data%'
                    OR post_content LIKE '%[frm-%' OR post_content LIKE '%wp:formidable%')"
        );

        $placements = [];
        foreach ($rows as $row) {
            preg_match_all('/\[(formidable|display-frm-data|frm-[a-z-]+)\b[^\]]*\]/i', $row->post_content, $shortcodes);
            preg_match_all('/<!--\s*wp:formidable\/[^>]*-->/i', $row->post_content, $blocks);
            $placements[] = [
                'id' => (int) $row->ID,
                'type' => $row->post_type,
                'title' => $row->post_title,
                'shortcodes' => array_values(array_unique($shortcodes[0])),
                'blocks' => array_values(array_unique($blocks[0])),
            ];
        }

        return $placements;
    }

    /**
     * @return array<string, string[]>
     */
    private static function capabilities(): array {
        $out = [];
        foreach (wp_roles()->roles as $role => $info) {
            $caps = array_keys(array_filter($info['capabilities']));
            $frm = array_values(array_filter($caps, fn($cap) => strpos($cap, 'frm_') === 0));
            if ($frm) {
                sort($frm);
                $out[$role] = $frm;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Every place the active theme uses a Formidable class, hook, table or view/form ID constant.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function themeCalls(): array {
        $themeDir = get_stylesheet_directory();
        $pattern = '/(Frm[A-Z][A-Za-z]+(?:::[A-Za-z_]+)?|[\'"]frm_[a-z_]+[\'"]|wp_frm_[a-z_]+)/';
        $calls = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($themeDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($themeDir))), '/');
            if (strpos($relative, 'vendor/') === 0 || strpos($relative, 'node_modules/') === 0) {
                continue;
            }
            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach (array_unique($matches[1]) as $symbol) {
                        $calls[] = [
                            'symbol' => trim($symbol, '\'"'),
                            'file' => $relative,
                            'line' => $number + 1,
                            'code' => trim(mb_substr($line, 0, 200)),
                        ];
                    }
                }
            }
        }

        usort($calls, fn($a, $b) => [$a['symbol'], $a['file'], $a['line']] <=> [$b['symbol'], $b['file'], $b['line']]);
        return $calls;
    }

    /**
     * Replace email addresses anywhere in a value (recursively) so the snapshot holds no
     * personal contact details.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function redact($value) {
        if (is_array($value)) {
            return array_map([__CLASS__, 'redact'], $value);
        }
        if (is_string($value)) {
            return preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', $value);
        }
        return $value;
    }
}
