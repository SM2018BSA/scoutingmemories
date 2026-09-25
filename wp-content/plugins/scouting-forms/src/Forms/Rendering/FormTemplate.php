<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Forms\Logic\FieldLogic;
use ScoutingMemories\Forms\Forms\Submission\SpamGuard;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * FormTemplate
 *
 * Assembles a whole form the way Formidable does: the form's before_html (title/description),
 * each field, and the form's submit_html, using the templates stored in the form's options.
 * Messages (success, form-level errors) are shown above the form.
 *
 * Sections (divider ... end_divider) wrap their fields; collapsible ones start closed; repeating
 * ones draw one row per child entry with Formidable's names (item_meta[SECTION][ROW][FIELD]).
 * Page breaks split the form into pages the browser steps through; the server still checks
 * everything on the final submit and reopens the page with the first error. Fields hidden by
 * their logic start hidden; assets/js/forms-front.js applies the same rules as values change.
 */
class FormTemplate {

    private const DEFAULT_BEFORE = '[if form_name]<h3 class="frm_form_title">[form_name]</h3>[/if form_name][if form_description]<div class="frm_description">[form_description]</div>[/if form_description]';
    private const DEFAULT_SUBMIT = '<div class="frm_submit"><button class="frm_button_submit" type="submit">[button_label]</button></div>';

    /**
     * @param array<string, mixed> $form From FormRepository::find()
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $state values, errors, message, show_form, form_error
     * @param array<string, bool> $display title, description
     */
    public static function render(array $form, array $fields, array $state, array $display): string {
        wp_enqueue_style('sm-forms-front');
        $opts = $form['options'];

        $html = sprintf('<div class="%s frm_forms" id="frm_form_%d_container">', ThemeClasses::SCOPE, $form['id']);

        if (!empty($state['message'])) {
            $html .= '<div class="frm_message ' . esc_attr(ThemeClasses::alert('success')) . '" role="status">' . $state['message'] . '</div>';
        }
        if (isset($state['show_form']) && !$state['show_form']) {
            return $html . '</div>';
        }

        if (!empty($state['form_error'])) {
            $html .= '<div class="frm_error_style ' . esc_attr(ThemeClasses::alert('danger')) . '" role="alert">' . esc_html($state['form_error']) . '</div>';
        } elseif (!empty($state['errors'])) {
            $invalid = !empty($opts['invalid_msg']) ? (string) $opts['invalid_msg'] : __('There was a problem with your submission. Errors are marked below.', 'scouting-forms');
            $html .= '<div class="frm_error_style ' . esc_attr(ThemeClasses::alert('danger')) . '" role="alert">' . wp_kses_post($invalid) . '</div>';
        }

        // Current values: what was posted (after a failed submission) or each field's default
        $values = [];
        $byId = [];
        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $byId[$id] = $field;
            $values[$id] = array_key_exists($id, $state['values'] ?? []) ? $state['values'][$id] : DefaultValues::resolve($field);
        }
        $errors = (array) ($state['errors'] ?? []);

        $pages = self::pages($fields);
        $pageHtml = [];
        $errorPage = 0;
        foreach ($pages as $n => $pageFields) {
            $pageHtml[$n] = self::range($pageFields, $values, $byId, $errors);
            if (!$errorPage && $errors && self::rangeHasError($pageFields, $errors)) {
                $errorPage = $n + 1;
            }
        }

        $html .= sprintf(
            '<form enctype="multipart/form-data" method="post" class="frm-show-form sm-form%s" id="form_%s" novalidate data-sm-form="%d" data-sm-ajax="%s"%s>',
            !empty($opts['form_class']) ? ' ' . esc_attr($opts['form_class']) : '',
            esc_attr($form['key']),
            (int) $form['id'],
            esc_url(admin_url('admin-ajax.php')),
            count($pages) > 1 ? ' data-sm-start-page="' . max(1, $errorPage) . '"' : ''
        );
        $html .= '<div class="frm_form_fields"><fieldset>';
        $html .= self::before($form, $display);
        $html .= '<div class="frm_fields_container">';

        if (count($pages) > 1) {
            $last = count($pages) - 1;
            foreach ($pageHtml as $n => $content) {
                $html .= '<div class="sm-page" data-sm-page="' . ($n + 1) . '">' . $content;
                if ($n < $last) {
                    $html .= self::pageNav($form, $n, self::breakLabel($fields, $n));
                }
                $html .= '</div>';
            }
        } else {
            $html .= $pageHtml[0] ?? '';
        }

        // Editing an entry: the nonce is tied to that entry, and the button says "Update"
        $editId = (int) ($state['entry_id'] ?? 0);
        $html .= wp_nonce_field($editId ? 'sm_update_entry_' . $editId : 'sm_submit_form_' . $form['id'], '_sm_form_nonce', true, false);
        $html .= sprintf('<input type="hidden" name="sm_form_id" value="%d" />', $form['id']);
        if ($editId) {
            $html .= sprintf('<input type="hidden" name="sm_entry_id" value="%d" />', $editId);
        }
        $html .= SpamGuard::fields((int) $form['id']);
        $html .= self::submit($form, $fields, count($pages) > 1, $editId > 0);
        $html .= '</div></fieldset></div>';
        $html .= self::browserConfig($fields);
        $html .= '</form>';
        $html .= '<noscript><style>.sm-forms .sm-toggle-container[hidden]{display:block!important}</style></noscript>';

        if (!empty($opts['after_html'])) {
            $html .= wp_kses_post(do_shortcode((string) $opts['after_html']));
        }

        wp_enqueue_script('sm-forms-front');
        return $html . '</div>';
    }

    /**
     * Fields in order, drawing sections around the fields they contain.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $byId
     * @param array<int|string, string> $errors
     */
    private static function range(array $fields, array $values, array $byId, array $errors): string {
        $html = '';
        $fields = array_values($fields);
        $count = count($fields);
        for ($i = 0; $i < $count; $i++) {
            $field = $fields[$i];
            $id = (int) $field['id'];
            $type = $field['type'];
            if ($type === 'end_divider' || $type === 'break') {
                continue;
            }

            $ctx = ['values' => $values, 'hidden' => !FieldLogic::isShown($field, $values, $byId)];

            if ($type === 'divider') {
                // The section's fields run up to its end_divider
                $end = $i + 1;
                while ($end < $count && $fields[$end]['type'] !== 'end_divider' && $fields[$end]['type'] !== 'divider') {
                    $end++;
                }
                $inner = !empty($field['field_options']['repeat'])
                    ? self::repeater($field, $values[$id] ?? [], $values, $errors)
                    : self::range(array_slice($fields, $i + 1, $end - $i - 1), $values, $byId, $errors);
                if (!empty($field['field_options']['slide'])) {
                    $inner = '<div class="sm-toggle-container frm_grid_container" hidden>' . $inner . '</div>';
                } elseif (!empty($field['field_options']['repeat'])) {
                    $inner = '<div class="frm_grid_container">' . $inner . '</div>';
                }
                $ctx['inner'] = $inner;
                $html .= FieldRenderer::render($field, '', '', $ctx);
                $i = ($end < $count && $fields[$end]['type'] === 'end_divider') ? $end : $end - 1;
                continue;
            }

            $html .= FieldRenderer::render($field, $values[$id] ?? '', (string) ($errors[$id] ?? ''), $ctx);
        }
        return $html;
    }

    /**
     * A repeating section: one row per entry of its child form, plus add/remove buttons.
     *
     * @param array<string, mixed> $section The repeating divider
     * @param mixed $posted item_meta[SECTION] as posted: form, row_ids, and one array per row
     * @param array<int, mixed> $parentValues
     * @param array<int|string, string> $errors Row errors keyed "FIELD-SECTION-ROW"
     */
    private static function repeater(array $section, $posted, array $parentValues, array $errors): string {
        $sectionId = (int) $section['id'];
        $childFormId = (int) ($section['field_options']['form_select'] ?? 0);
        $childFields = FormRepository::fields($childFormId);
        $childById = [];
        foreach ($childFields as $child) {
            $childById[(int) $child['id']] = $child;
        }

        $rows = self::repeaterRows($posted);
        if (!$rows) {
            $rows = [0 => []];
        }

        $html = sprintf('<input type="hidden" name="item_meta[%d][form]" value="%d" class="frm_dnc" />', $sectionId, $childFormId);
        $first = true;
        foreach ($rows as $key => $rowValues) {
            foreach ($childFields as $child) {
                if (!array_key_exists((int) $child['id'], $rowValues)) {
                    $rowValues[(int) $child['id']] = DefaultValues::resolve($child);
                }
            }
            $html .= sprintf(
                '<div id="frm_section_%1$d-%2$s" class="frm_repeat_grid frm_repeat_%1$d%3$s frm_grid_container" data-sm-row="%1$d" data-sm-row-key="%2$s">',
                $sectionId,
                esc_attr((string) $key),
                $first ? ' frm_first_repeat' : ''
            );
            $html .= sprintf('<input type="hidden" name="item_meta[%d][row_ids][]" value="%s" />', $sectionId, esc_attr((string) $key));
            foreach ($childFields as $child) {
                $childId = (int) $child['id'];
                $containerId = $childId . '-' . $sectionId . '-' . $key;
                $html .= FieldRenderer::render($child, $rowValues[$childId] ?? '', (string) ($errors[$containerId] ?? ''), [
                    'name' => sprintf('item_meta[%d][%s][%d]', $sectionId, $key, $childId),
                    'key' => $child['key'] . '-' . $key,
                    'id' => $containerId,
                    'class' => 'frm_field_' . $childId . '_container',
                    'values' => $rowValues,
                    'hidden' => !FieldLogic::isShown($child, $rowValues, $childById),
                ]);
            }
            $html .= self::repeatButtons($sectionId, (string) $key);
            $html .= '</div>';
            $first = false;
        }
        return $html;
    }

    /**
     * Rows of a repeating section from its posted (or loaded) value.
     *
     * @param mixed $posted
     * @return array<string, array<int, mixed>> row key => field ID => value
     */
    public static function repeaterRows($posted): array {
        if (!is_array($posted)) {
            return [];
        }
        $keys = isset($posted['row_ids']) && is_array($posted['row_ids']) ? $posted['row_ids'] : array_keys($posted);
        $rows = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            if ($key === 'form' || $key === 'row_ids' || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
                continue;
            }
            $row = isset($posted[$key]) && is_array($posted[$key]) ? $posted[$key] : [];
            $clean = [];
            foreach ($row as $fieldId => $value) {
                if (is_numeric($fieldId) && (int) $fieldId > 0) {
                    $clean[(int) $fieldId] = $value;
                }
            }
            $rows[$key] = $clean;
        }
        return $rows;
    }

    private static function repeatButtons(int $sectionId, string $key): string {
        $add = '<svg viewBox="0 0 20 20" width="1em" height="1em" aria-hidden="true" class="frmsvg frm-svg-icon"><path d="M11 5H9v4H5v2h4v4h2v-4h4V9h-4V5zm-1-5a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"></path></svg>';
        $remove = '<svg viewBox="0 0 20 20" width="1em" height="1em" aria-hidden="true" class="frmsvg frm-svg-icon"><path d="M5 9v2h10V9H5zm5-9a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"></path></svg>';
        return sprintf(
            '<div class="frm_form_field frm_hidden_container frm_repeat_buttons">'
            . '<button type="button" class="sm-add-row frm_button btn btn-link" data-parent="%1$d" title="%3$s" aria-label="%3$s">%5$s</button>'
            . '<button type="button" class="sm-remove-row frm_button btn btn-link" data-parent="%1$d" data-key="%2$s" title="%4$s" aria-label="%4$s">%6$s</button>'
            . '</div>',
            $sectionId,
            esc_attr($key),
            esc_attr__('Add another row', 'scouting-forms'),
            esc_attr__('Remove this row', 'scouting-forms'),
            $add,
            $remove
        );
    }

    /**
     * Fields split at page breaks.
     *
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function pages(array $fields): array {
        $pages = [[]];
        foreach ($fields as $field) {
            if ($field['type'] === 'break') {
                $pages[] = [];
                continue;
            }
            $pages[count($pages) - 1][] = $field;
        }
        return $pages;
    }

    /**
     * The label of the page break that ends page $n ("Next").
     *
     * @param array<int, array<string, mixed>> $fields
     */
    private static function breakLabel(array $fields, int $n): string {
        $seen = 0;
        foreach ($fields as $field) {
            if ($field['type'] === 'break') {
                if ($seen === $n) {
                    return $field['name'] !== '' ? $field['name'] : __('Next', 'scouting-forms');
                }
                $seen++;
            }
        }
        return __('Next', 'scouting-forms');
    }

    private static function pageNav(array $form, int $n, string $nextLabel): string {
        $prev = !empty($form['options']['prev_value']) ? (string) $form['options']['prev_value'] : __('Previous', 'scouting-forms');
        $html = '<div class="frm_submit sm-page-nav">';
        if ($n > 0) {
            $html .= '<button type="button" class="frm_prev_page ' . esc_attr(ThemeClasses::button('secondary')) . '" data-sm-prev>' . esc_html($prev) . '</button> ';
        }
        $html .= '<button type="button" class="frm_next_page ' . esc_attr(ThemeClasses::button('scout')) . '" data-sm-next>' . esc_html($nextLabel) . '</button>';
        return $html . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<int|string, string> $errors
     */
    private static function rangeHasError(array $fields, array $errors): bool {
        foreach ($fields as $field) {
            $id = (int) $field['id'];
            if (isset($errors[$id])) {
                return true;
            }
            foreach (array_keys($errors) as $key) {
                if (is_string($key) && preg_match('/^\d+-' . $id . '-/', $key)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Field rules for forms-front.js, as JSON inside the form.
     *
     * @param array<int, array<string, mixed>> $fields
     */
    private static function browserConfig(array $fields): string {
        $all = $fields;
        foreach ($fields as $field) {
            if ($field['type'] === 'divider' && !empty($field['field_options']['repeat'])) {
                $all = array_merge($all, FormRepository::fields((int) ($field['field_options']['form_select'] ?? 0)));
            }
        }
        $config = [
            'fields' => FieldLogic::rulesForBrowser($all),
            'text' => [
                'loading' => __('Loading…', 'scouting-forms'),
                'search' => __('Type to search', 'scouting-forms'),
                'noResults' => __('No matches', 'scouting-forms'),
                'remove' => __('Remove', 'scouting-forms'),
            ],
        ];
        return '<script type="application/json" class="sm-form-config">' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }

    /**
     * @param array<string, bool> $display
     */
    private static function before(array $form, array $display): string {
        $template = !empty($form['options']['before_html']) ? (string) $form['options']['before_html'] : self::DEFAULT_BEFORE;
        $showName = !empty($display['title']) && $form['name'] !== '';
        $showDesc = !empty($display['description']) && trim($form['description']) !== '';

        foreach (['form_name' => $showName, 'form_description' => $showDesc] as $name => $keep) {
            $template = preg_replace('/\[if ' . $name . '\](.*?)\[\/if ' . $name . '\]/s', $keep ? '$1' : '', $template);
        }

        // [form_name] outside the [if form_name] block (the screen-reader legend) always shows
        return strtr($template, [
            '[form_name]' => esc_html($form['name']),
            '[form_description]' => $showDesc ? wp_kses_post(do_shortcode($form['description'])) : '',
            '[form_key]' => esc_attr($form['key']),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private static function submit(array $form, array $fields, bool $paged = false, bool $editing = false): string {
        $opts = $form['options'];
        $label = !empty($opts['submit_value']) ? (string) $opts['submit_value'] : '';
        foreach ($fields as $field) {
            if ($field['type'] === 'submit' && $field['name'] !== '') {
                $label = $field['name'];
            }
        }
        if ($editing) {
            $label = !empty($opts['edit_value']) ? (string) $opts['edit_value'] : (string) FormidableSettings::pro('update_value', __('Update', 'scouting-forms'));
        }
        if ($label === '') {
            $label = __('Submit', 'scouting-forms');
        }

        $template = !empty($opts['submit_html']) ? (string) $opts['submit_html'] : self::DEFAULT_SUBMIT;
        // Drafts are not used on the site; on paged forms the Previous button is drawn here
        $template = preg_replace('/\[if save_draft\].*?\[\/if save_draft\]/s', '', $template);
        $prev = '';
        if ($paged) {
            $prevLabel = !empty($opts['prev_value']) ? (string) $opts['prev_value'] : __('Previous', 'scouting-forms');
            $prev = '<button type="button" class="frm_prev_page ' . esc_attr(ThemeClasses::button('secondary')) . '" data-sm-prev>' . esc_html($prevLabel) . '</button> ';
        }
        $template = preg_replace('/\[if back_button\].*?\[\/if back_button\]/s', $prev, $template);
        $template = strtr($template, [
            '[button_label]' => esc_html($label),
            '[button_action]' => '',
            '[back_hook]' => '',
            '[back_label]' => '',
            '[draft_hook]' => '',
            '[draft_label]' => '',
            '[form_key]' => esc_attr($form['key']),
        ]);
        // Formidable's button class plus the site's Bootstrap button
        $template = preg_replace('/class="frm_button_submit/', 'class="frm_button_submit ' . esc_attr(ThemeClasses::button('scout')), $template, 1);
        if ($paged) {
            // Only the last page shows the submit button (forms-front.js)
            $template = preg_replace('/class="frm_submit/', 'data-sm-final class="frm_submit', $template, 1);
        }

        return preg_replace('/\[(?:if [a-z_]+|\/if [a-z_]+)\]/', '', $template);
    }
}
