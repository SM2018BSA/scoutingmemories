<?php

namespace ScoutingMemories\Forms\Forms\Logic;

use ScoutingMemories\Forms\Forms\Rendering\DynamicOptions;
use ScoutingMemories\Forms\Support\Permissions;

/**
 * FieldLogic
 *
 * Field show/hide logic, following Formidable (FrmProFieldsHelper::is_field_hidden):
 *   - each rule compares another field's value (hide_field) with hide_opt using hide_field_cond;
 *   - show_hide "show"/"hide" with any_all "any"/"all";
 *   - a rule on a Dynamic field with a blank value means "has any value" (never true when blank);
 *   - a dependent Dynamic field with no choices for its parent's value is hidden;
 *   - fields inside a hidden section are hidden; hidden and user_id fields ignore logic.
 * Hidden fields are not validated and their values are not saved. The browser applies the same
 * rules (assets/js/forms-front.js) from rulesForBrowser().
 */
class FieldLogic {

    /**
     * @param array<string, mixed> $field
     * @param array<int, mixed> $values field_id => value (the posted or current values)
     * @param array<int, array<string, mixed>> $byId All fields of the form (and its sections), by ID
     */
    public static function isShown(array $field, array $values, array $byId): bool {
        if (!self::visibleToUser($field)) {
            return false;
        }
        $section = (int) ($field['field_options']['in_section'] ?? 0);
        if ($section > 0 && isset($byId[$section]) && $section !== (int) $field['id'] && !self::isShown($byId[$section], $values, $byId)) {
            return false;
        }
        if (in_array($field['type'], ['hidden', 'user_id'], true) || !self::rules($field)) {
            return true;
        }

        $outcomes = [];
        foreach (self::rules($field) as $rule) {
            $observed = $values[$rule['field']] ?? '';
            $expected = $rule['value'];
            $parent = $byId[$rule['field']] ?? null;
            if ($rule['anything'] && $parent && $parent['type'] === 'data' && $field['type'] === 'data') {
                // "Dynamic field is anything": true when it has a value
                $expected = self::isBlank($observed) && $rule['cond'] === '==' ? 'anything' : $observed;
            }
            $outcomes[] = self::meets($observed, $rule['cond'], $expected);
        }

        $visible = ($field['field_options']['show_hide'] ?? 'show') === 'show';
        $anyAll = $field['field_options']['any_all'] ?? 'any';
        if ($anyAll === 'any' ? !in_array(true, $outcomes, true) : in_array(false, $outcomes, true)) {
            $visible = !$visible;
        }

        if ($visible && $field['type'] === 'data' && ($field['field_options']['data_type'] ?? '') !== 'data'
            && DynamicOptions::isDependent($field) && !DynamicOptions::forField($field, $values)) {
            $visible = false;
        }
        return $visible;
    }

    /**
     * Formidable's per-field visibility setting ("admin_only": roles that may see the field).
     *
     * @param array<string, mixed> $field
     */
    public static function visibleToUser(array $field): bool {
        $roles = $field['field_options']['admin_only'] ?? '';
        if ($roles === '' || $roles === [] || $roles === [''] || $roles === null) {
            return true;
        }
        return Permissions::hasRole($roles);
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, array{field:int, cond:string, value:string, anything:bool}>
     */
    public static function rules(array $field): array {
        $opts = $field['field_options'];
        $parents = (array) ($opts['hide_field'] ?? []);
        $conds = (array) ($opts['hide_field_cond'] ?? []);
        $values = (array) ($opts['hide_opt'] ?? []);
        $rules = [];
        foreach ($parents as $i => $parent) {
            if (!is_numeric($parent) || (int) $parent <= 0) {
                continue;
            }
            $value = $values[$i] ?? '';
            $rules[] = [
                'field' => (int) $parent,
                'cond' => (string) ($conds[$i] ?? '=='),
                'value' => is_array($value) ? implode(',', $value) : trim((string) $value),
                'anything' => $value === '' || $value === null,
            ];
        }
        return $rules;
    }

    /**
     * Rules for the browser: field ID => logic, and which parents drive dependent choices.
     *
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public static function rulesForBrowser(array $fields): array {
        $out = [];
        foreach ($fields as $field) {
            $rules = self::rules($field);
            $isDynamic = $field['type'] === 'data';
            if (!$rules && !$isDynamic) {
                continue;
            }
            $out[(string) $field['id']] = [
                'showHide' => (string) ($field['field_options']['show_hide'] ?? 'show'),
                'anyAll' => (string) ($field['field_options']['any_all'] ?? 'any'),
                'rules' => $rules,
                'section' => (int) ($field['field_options']['in_section'] ?? 0),
                'dynamic' => $isDynamic ? [
                    'dependent' => DynamicOptions::isDependent($field),
                    'display' => ($field['field_options']['data_type'] ?? 'select') === 'data',
                    'multiple' => !empty($field['field_options']['multiple']),
                ] : null,
            ];
        }
        return $out;
    }

    /**
     * Formidable's value_meets_condition, including list values (checkboxes, multi-selects).
     *
     * @param mixed $observed
     * @param mixed $expected
     */
    public static function meets($observed, string $cond, $expected): bool {
        $expected = is_array($expected) ? array_map(static fn($v) => trim((string) $v), $expected) : trim((string) $expected);
        if (is_array($observed)) {
            $observed = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $observed), 'strlen'));
            return self::meetsList($observed, $cond, $expected);
        }
        $observed = trim((string) $observed);
        if (is_array($expected)) {
            $expected = implode(',', $expected);
        }

        switch ($cond) {
            case '==':
                return $observed == $expected; // loose, like Formidable ("1" == "1.0")
            case '!=':
                return $observed != $expected;
            case '>':
                return $observed > $expected;
            case '>=':
                return $observed >= $expected;
            case '<':
                return $observed < $expected;
            case '<=':
                return $observed <= $expected;
            case 'LIKE':
                return $expected === '' || stripos($observed, $expected) !== false;
            case 'not LIKE':
                return !($expected === '' || stripos($observed, $expected) !== false);
            case '%LIKE':
                return $expected === '' || strcasecmp(substr($observed, -strlen($expected)), $expected) === 0;
            case 'LIKE%':
                return strcasecmp(substr($observed, 0, strlen($expected)), $expected) === 0;
        }
        return false;
    }

    /**
     * @param string[] $observed
     * @param string|string[] $expected
     */
    private static function meetsList(array $observed, string $cond, $expected): bool {
        switch ($cond) {
            case '==':
                return is_array($expected) ? (bool) array_intersect($expected, $observed) : in_array($expected, $observed);
            case '!=':
                return !in_array(is_array($expected) ? implode(',', $expected) : $expected, $observed);
            case '>':
                return $observed && min($observed) > $expected;
            case '<':
                return $observed && max($observed) < $expected;
            case 'LIKE':
            case 'not LIKE':
                $found = false;
                foreach ($observed as $one) {
                    if (strpos($one, (string) $expected) !== false) {
                        $found = true;
                        break;
                    }
                }
                return $cond === 'LIKE' ? $found : !$found;
            case '%LIKE':
                foreach ($observed as $one) {
                    if ((string) $expected === substr($one, -strlen((string) $expected))) {
                        return true;
                    }
                }
                return false;
            case 'LIKE%':
                foreach ($observed as $one) {
                    if (strpos($one, (string) $expected) === 0) {
                        return true;
                    }
                }
                return false;
        }
        return false;
    }

    /**
     * @param mixed $value
     */
    public static function isBlank($value): bool {
        if (is_array($value)) {
            return !array_filter($value, static fn($v) => trim((string) $v) !== '');
        }
        return trim((string) $value) === '';
    }
}
