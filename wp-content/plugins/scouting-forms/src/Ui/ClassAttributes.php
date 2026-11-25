<?php

namespace ScoutingMemories\Forms\Ui;

/**
 * ClassAttributes
 *
 * One job: walk the class attributes of a piece of HTML and hand each one's names to a callback
 * that returns the names to print instead. It knows nothing about which names mean what; the
 * tables that do are Views\LegacyClassMap (Bootstrap names) and
 * Forms\Rendering\FormidableClasses (Formidable's hook names).
 *
 * Text inside <script>, <style> and <textarea> is left exactly as it is.
 */
class ClassAttributes {

    private const SKIPPED_BLOCKS = '~(<(script|style|textarea)\b.*?</\2\s*>)~is';
    private const OPENING_TAG = '~<([a-zA-Z][a-zA-Z0-9]*)\b((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>~';
    private const CLASS_ATTRIBUTE = '~(\sclass\s*=\s*)(["\'])(.*?)\2~is';

    /**
     * @param callable(string[] $names, string $tag): string[] $rewrite
     */
    public static function rewrite(string $html, callable $rewrite): string {
        if (stripos($html, 'class') === false) {
            return $html;
        }
        $parts = preg_split(self::SKIPPED_BLOCKS, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $html;
        }
        $out = '';
        $count = count($parts);
        // preg_split with two capture groups gives: text, whole block, its tag name, text, ...
        for ($i = 0; $i < $count; $i += 3) {
            $out .= self::rewriteTags($parts[$i], $rewrite) . ($parts[$i + 1] ?? '');
        }
        return $out;
    }

    /**
     * The same for a bare list of names ("btn btn-sm"), as stored in a setting or passed to a shortcode.
     *
     * @param callable(string[] $names, string $tag): string[] $rewrite
     */
    public static function rewriteList(string $classes, callable $rewrite, string $tag = ''): string {
        $names = self::split($classes);
        return $names ? implode(' ', array_unique($rewrite($names, strtolower($tag)))) : '';
    }

    /**
     * @param callable(string[] $names, string $tag): string[] $rewrite
     */
    private static function rewriteTags(string $html, callable $rewrite): string {
        $result = preg_replace_callback(self::OPENING_TAG, static function (array $tag) use ($rewrite): string {
            if (stripos($tag[2], 'class') === false) {
                return $tag[0];
            }
            $attributes = preg_replace_callback(self::CLASS_ATTRIBUTE, static function (array $attr) use ($rewrite, $tag): string {
                return $attr[1] . $attr[2] . self::rewriteList($attr[3], $rewrite, $tag[1]) . $attr[2];
            }, $tag[2], 1);
            return '<' . $tag[1] . ($attributes ?? $tag[2]) . '>';
        }, $html);
        return $result ?? $html;
    }

    /**
     * @return string[]
     */
    private static function split(string $classes): array {
        return array_values(array_filter(preg_split('/\s+/', trim($classes)) ?: [], 'strlen'));
    }
}
