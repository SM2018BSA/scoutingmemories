<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;

/**
 * StoredDynamicText
 *
 * Values for editing an entry. A Dynamic field saved to the entry's post can come back as text
 * the theme wrote there after the save: Add a Post's States holds abbreviations ("NY") instead
 * of the State entries' IDs. Formidable showed such a box empty, so the member had to pick the
 * states again before the entry could be saved. Here each text is turned back into the entry it
 * stands for, so the box shows the saved choices and the fields that depend on it (Council) get
 * their choices. When two entries share the text ("ME" is Maine and Middle East), the one the
 * entry's own dependent choices belong to wins (the saved Council's state). A text that still
 * matches no entry, or more than one, is left out and the box shows the rest.
 *
 * Dynamic values kept on the entry itself are not touched: the theme's council slugs there are
 * sent back unchanged (FieldRenderer, Validator).
 */
final class StoredDynamicText {

    /**
     * @param array<int, array<string, mixed>> $fields The form's fields
     * @param array<int, mixed> $values From EntryRepository::formValues
     * @return array<int, mixed>
     */
    public static function asEntryIds(array $fields, array $values): array {
        foreach ($fields as $field) {
            $id = (int) $field['id'];
            if ($field['type'] !== 'data' || !array_key_exists($id, $values) || !PostFields::mapping($field)
                || ($field['field_options']['data_type'] ?? 'select') === 'data') {
                continue;
            }
            $value = $values[$id];
            $list = self::texts($value);
            if (!array_filter($list, static fn(string $v): bool => !ctype_digit($v))) {
                continue;
            }
            $ids = [];
            foreach ($list as $text) {
                $entryId = ctype_digit($text) ? $text : self::pick($field, DynamicOptions::idsForStoredText($field, $text), $fields, $values);
                if ($entryId !== '' && !in_array($entryId, $ids, true)) {
                    $ids[] = $entryId;
                }
            }
            $values[$id] = is_array($value) || !empty($field['field_options']['multiple']) ? $ids : (string) ($ids[0] ?? '');
        }
        return $values;
    }

    /**
     * One of several matching entries: the only one whose dependent choices (in a field of this
     * form that takes its choices from $field) include what that field has saved.
     *
     * @param array<string, mixed> $field
     * @param string[] $candidates
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     */
    private static function pick(array $field, array $candidates, array $fields, array $values): string {
        if (count($candidates) <= 1) {
            return (string) ($candidates[0] ?? '');
        }
        $matching = [];
        foreach ($fields as $child) {
            if ($child['type'] !== 'data' || !in_array((int) $field['id'], DynamicOptions::dynamicParents($child), true)) {
                continue;
            }
            $saved = array_filter(self::texts($values[(int) $child['id']] ?? ''), 'ctype_digit');
            $linked = FormRepository::field((int) ($child['field_options']['form_select'] ?? 0));
            if (!$saved || !$linked) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $offered = array_map('strval', array_keys(DynamicOptions::dependent($child, $linked, (int) $field['id'], $candidate)));
                if (array_intersect($saved, $offered)) {
                    $matching[$candidate] = true;
                }
            }
        }
        return count($matching) === 1 ? (string) array_key_first($matching) : '';
    }

    /**
     * @param mixed $value
     * @return string[] The non-blank values as text
     */
    private static function texts($value): array {
        return array_values(array_filter(
            array_map('strval', array_filter((array) $value, 'is_scalar')),
            static fn(string $v): bool => trim($v) !== ''
        ));
    }
}
