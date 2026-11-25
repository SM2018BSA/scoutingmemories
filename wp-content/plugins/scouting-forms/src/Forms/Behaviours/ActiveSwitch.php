<?php

namespace ScoutingMemories\Forms\Forms\Behaviours;

use ScoutingMemories\Forms\Models\FormRepository;

/**
 * ActiveSwitch
 *
 * One job: the rules of the "Active" switch on the Add a Lodge / Council / Camp / Post forms.
 *
 * Those forms have a tick box ("Active") next to a field that keeps the answer as the words
 * "Yes" or "No" (the theme reads that one), and an "End Date" year. The owner's old jQuery
 * snippets, stored with each form, tied them together; assets/js/forms-front.js now does it,
 * told which fields belong together by the attributes printed here:
 *
 *   - when the form opens: the stored answer is "Yes" -> the box is ticked;
 *   - the box is ticked: the end year becomes this year and the stored answer "Yes";
 *   - the box is unticked: the stored answer becomes "No".
 *
 * The table is keyed by the tick box's field key, exactly the fields the old snippets addressed
 * (#field_KEY). A form that has no field with such a key gets nothing, as before.
 */
class ActiveSwitch {

    /** Tick box key => [key of the field that keeps "Yes"/"No", key of the end-year field or ''] */
    private const RULES = [
        'lodge_active_0' => ['lodge_active_1', 'lodge_end'],
        'council_active_0' => ['council_active_1', 'council_end'],
        'camp_active_0' => ['camp_active_1', 'camp_end'],
        'post_active_0' => ['post_active_1', ''],
    ];

    /**
     * The attributes for a field's input: empty unless the field is one of the tick boxes and the
     * field that keeps its answer is in the same form.
     *
     * @param array<string, mixed> $field From FormRepository
     */
    public static function attributes(array $field): string {
        $rule = self::RULES[(string) ($field['key'] ?? '')] ?? null;
        if (!$rule) {
            return '';
        }
        $ids = [];
        foreach (FormRepository::fields((int) ($field['form_id'] ?? 0)) as $other) {
            $ids[$other['key']] = (int) $other['id'];
        }
        if (empty($ids[$rule[0]])) {
            return '';
        }
        $attrs = sprintf(' data-sm-active-switch="%d"', $ids[$rule[0]]);
        if ($rule[1] !== '' && !empty($ids[$rule[1]])) {
            $attrs .= sprintf(' data-sm-active-end="%d"', $ids[$rule[1]]);
        }
        return $attrs;
    }
}
