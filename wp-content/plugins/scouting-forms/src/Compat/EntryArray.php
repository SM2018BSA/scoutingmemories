<?php

namespace ScoutingMemories\Forms\Compat;

use ScoutingMemories\Forms\Views\EntryValues;

/**
 * EntryArray
 *
 * An entry as Formidable's [frm-show-entry format=array] returns it, which the theme reads
 * everywhere (new Entry($id)->entry_array): field key => displayed value, plus "key-value" =>
 * saved value when the two differ (a Dynamic field's linked name and ID); blank fields left out;
 * a repeating section as key => ['form' => child form, 'i<row id>' => row array] with each of its
 * fields also listed at the top as a list over the rows.
 */
class EntryArray {

    private const SKIP = ['break', 'divider', 'end_divider', 'form', 'password', 'credit_card', 'html', 'captcha', 'submit', 'summary'];

    /**
     * @param mixed $id Entry ID or key
     * @return array<string, mixed>|string '' when there is no such entry
     */
    public static function build($id) {
        $entry = is_scalar($id) && (string) $id !== '' ? Data::getOne((string) $id) : null;
        if (!$entry) {
            return '';
        }
        $values = new EntryValues((int) $entry->form_id);
        $values->load([(int) $entry->id]);

        $out = [];
        foreach (self::fields($values, (int) $entry->form_id) as $field) {
            if ($field['type'] === 'divider' && !empty($field['field_options']['repeat'])) {
                self::pushRows($values, (int) $entry->id, $field, $out);
                continue;
            }
            if (in_array($field['type'], self::SKIP, true)) {
                continue;
            }
            self::pushField($values, (int) $entry->id, $field, $out);
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fields(EntryValues $values, int $formId): array {
        return \ScoutingMemories\Forms\Models\FormRepository::fields($formId);
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $out
     */
    private static function pushField(EntryValues $values, int $entryId, array $field, array &$out): void {
        $saved = $values->raw($entryId, (int) $field['id']);
        $shown = $values->plain($entryId, (int) $field['id']);
        // A Dynamic field holding one value is listed even if it links to nothing (a list is not)
        if (Data::isEmpty($field['type'] === 'data' && !is_array($saved) ? $saved : $shown)) {
            return;
        }
        $out[$field['key']] = $shown;
        if (!empty($field['field_options']['separate_value']) || $shown !== $saved) {
            $out[$field['key'] . '-value'] = $saved;
        }
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, mixed> $out
     */
    private static function pushRows(EntryValues $values, int $entryId, array $section, array &$out): void {
        global $wpdb;
        $childForm = (int) ($section['field_options']['form_select'] ?? 0);
        $rowIds = array_values(array_filter(array_map('intval', (array) $values->raw($entryId, (int) $section['id']))));
        if (!$rowIds && $childForm) {
            $rowIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}frm_items WHERE parent_item_id=%d AND form_id=%d ORDER BY id",
                $entryId,
                $childForm
            )));
        }
        if (!$rowIds || !$childForm) {
            return;
        }
        $values->addForm($childForm);
        $values->load($rowIds);
        $childFields = array_filter(self::fields($values, $childForm), static function ($f) {
            return !in_array($f['type'], self::SKIP, true);
        });

        $key = (string) $section['key'];
        $out[$key] = ['form' => (string) $childForm];
        $index = 0;
        foreach ($rowIds as $rowId) {
            if (!$values->entry($rowId)) {
                continue;
            }
            $row = [];
            foreach ($childFields as $field) {
                self::pushField($values, $rowId, $field, $row);
            }
            $out[$key]['i' . $rowId] = $row;

            foreach ($childFields as $field) {
                $shown = $values->plain($rowId, (int) $field['id']);
                $saved = $values->raw($rowId, (int) $field['id']);
                $out[$field['key']][$index] = $shown;
                if ($shown !== $saved) {
                    $out[$field['key'] . '-value'][$index] = $saved;
                }
            }
            $index++;
        }
    }
}
