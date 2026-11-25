<?php

namespace ScoutingMemories\Forms\Forms\Behaviours;

use ScoutingMemories\Forms\Models\PostFields;

/**
 * UncategorizedChoice
 *
 * One job: say which choice of a category list is not shown.
 *
 * On Add a Post the category tick boxes do not offer the site's default category
 * ("Uncategorized": WordPress's Settings > Writing > Default Post Category). The owner's old
 * style block, stored with the form, hid it by its position in the list; here it is found by
 * what it is. The choice stays in the page (not shown), so a post that already has that category
 * keeps it when it is edited, exactly as before.
 */
class UncategorizedChoice {

    /** Field keys the rule applies to (the old style rule named this one field) */
    private const FIELD_KEYS = ['aap_category'];

    /**
     * @param array<string, mixed> $field From FormRepository
     * @param string $value The choice's value (a term ID for a category field)
     */
    public static function isHidden(array $field, string $value): bool {
        if (!in_array((string) ($field['key'] ?? ''), self::FIELD_KEYS, true) || !ctype_digit($value)) {
            return false;
        }
        $map = PostFields::mapping($field);
        if (!$map || $map['kind'] !== 'taxonomy' || $map['name'] !== 'category') {
            return false;
        }
        return (int) $value === (int) get_option('default_category');
    }
}
