<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

/**
 * StoredScripts
 *
 * One job: take the <script> and <style> blocks out of HTML stored with a form (its "before" and
 * "after" HTML), as the form is drawn. The stored text itself is never edited.
 *
 * Those blocks are the old site's jQuery snippets and style rules. The site no longer loads
 * jQuery and carries no CSS of its own; what the snippets did is done by the plugin instead
 * (Forms\Behaviours\ActiveSwitch, Forms\Behaviours\UncategorizedChoice, assets/js/forms-front.js).
 */
class StoredScripts {

    private const BLOCKS = '~<(script|style)\b[^>]*>.*?</\1\s*>~is';

    public static function strip(string $html): string {
        if (stripos($html, '<s') === false) {
            return $html;
        }
        $stripped = preg_replace(self::BLOCKS, '', $html);
        return is_string($stripped) ? trim($stripped) : $html;
    }
}
