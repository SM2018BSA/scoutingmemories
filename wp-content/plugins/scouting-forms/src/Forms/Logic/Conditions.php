<?php

namespace ScoutingMemories\Forms\Forms\Logic;

/**
 * Conditions
 *
 * Evaluates Formidable-style rules. The same rule format is used by form-action conditions
 * ("send this email only if ...") and, in Phase 3, by field show/hide logic:
 *
 *   ['hide_field' => 12, 'hide_field_cond' => '==', 'hide_opt' => 'Yes']
 *
 * Operators: == != > < >= <= LIKE "not LIKE" LIKE% %LIKE
 */
class Conditions {

    /**
     * Should a form action run, given its `conditions` settings and the entry's values?
     *
     * @param array<int|string, mixed> $conditions Action settings['conditions']
     * @param array<int, mixed> $values field_id => value
     */
    public static function actionShouldRun(array $conditions, array $values): bool {
        $rules = self::rules($conditions);
        if (!$rules) {
            return true;
        }
        $matched = self::matchRules($rules, (string) ($conditions['any_all'] ?? 'any'), $values);
        return ($conditions['send_stop'] ?? 'send') === 'stop' ? !$matched : $matched;
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @param array<int, mixed> $values
     */
    public static function matchRules(array $rules, string $anyAll, array $values): bool {
        $results = [];
        foreach ($rules as $rule) {
            $fieldId = (int) ($rule['hide_field'] ?? 0);
            $results[] = self::compare($values[$fieldId] ?? '', (string) ($rule['hide_field_cond'] ?? '=='), $rule['hide_opt'] ?? '');
        }
        return $anyAll === 'all' ? !in_array(false, $results, true) : in_array(true, $results, true);
    }

    /**
     * The numbered rule entries inside a conditions array.
     *
     * @param array<int|string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    public static function rules(array $conditions): array {
        $rules = [];
        foreach ($conditions as $key => $rule) {
            if (is_numeric($key) && is_array($rule) && !empty($rule['hide_field'])) {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    /**
     * Compare an entry value (string or array for checkboxes / multi-selects) with a rule value.
     *
     * @param mixed $actual
     * @param mixed $expected
     */
    public static function compare($actual, string $operator, $expected): bool {
        $expected = is_array($expected) ? array_map('strval', $expected) : (string) $expected;

        if (is_array($actual)) {
            $actual = array_map('strval', $actual);
            if ($operator === '!=') {
                return !self::anyMatches($actual, '==', $expected);
            }
            if ($operator === 'not LIKE') {
                return !self::anyMatches($actual, 'LIKE', $expected);
            }
            return self::anyMatches($actual, $operator, $expected);
        }

        return self::single((string) $actual, $operator, $expected);
    }

    /**
     * @param string[] $actual
     * @param string|string[] $expected
     */
    private static function anyMatches(array $actual, string $operator, $expected): bool {
        if (!$actual) {
            return self::single('', $operator, $expected);
        }
        foreach ($actual as $value) {
            if (self::single($value, $operator, $expected)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string|string[] $expected
     */
    private static function single(string $actual, string $operator, $expected): bool {
        if (is_array($expected)) {
            $any = false;
            foreach ($expected as $one) {
                $any = $any || self::single($actual, $operator === '!=' ? '==' : $operator, $one);
            }
            return $operator === '!=' ? !$any : $any;
        }

        $a = trim($actual);
        $e = trim($expected);
        $bothNumeric = is_numeric($a) && is_numeric($e);

        switch ($operator) {
            case '==':
                return $bothNumeric ? (float) $a === (float) $e : strcasecmp($a, $e) === 0;
            case '!=':
                return $bothNumeric ? (float) $a !== (float) $e : strcasecmp($a, $e) !== 0;
            case '>':
                return $bothNumeric ? (float) $a > (float) $e : strcmp($a, $e) > 0;
            case '<':
                return $bothNumeric ? (float) $a < (float) $e : strcmp($a, $e) < 0;
            case '>=':
                return $bothNumeric ? (float) $a >= (float) $e : strcmp($a, $e) >= 0;
            case '<=':
                return $bothNumeric ? (float) $a <= (float) $e : strcmp($a, $e) <= 0;
            case 'LIKE':
                return $e === '' ? $a === '' : stripos($a, $e) !== false;
            case 'not LIKE':
                return $e === '' ? $a !== '' : stripos($a, $e) === false;
            case 'LIKE%':
                return stripos($a, $e) === 0;
            case '%LIKE':
                return $e === '' || strcasecmp(substr($a, -strlen($e)), $e) === 0;
            default:
                return false;
        }
    }
}
