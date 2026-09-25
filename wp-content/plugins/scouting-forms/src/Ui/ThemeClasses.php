<?php

namespace ScoutingMemories\Forms\Ui;

/**
 * ThemeClasses
 *
 * The one place front-end markup gets its CSS classes. It uses the site theme's Bootstrap 5.3
 * classes, so forms and views look like the rest of Scouting Memories. The few rules Bootstrap
 * doesn't have (site-green button, required-field marker, grid helpers, thumbnails) live in
 * assets/css/forms-front.css, scoped to `.sm-forms`. Tailwind is used only in the wp-admin builder.
 */
class ThemeClasses {

    /**
     * Wrapper class for everything the plugin renders on the front end (scopes forms-front.css).
     */
    public const SCOPE = 'sm-forms';

    /**
     * Card Containers
     */
    public static function card(string $extra = ''): string {
        return trim("card shadow-sm p-4 {$extra}");
    }

    public static function cardHeader(string $extra = ''): string {
        return trim("d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 {$extra}");
    }

    public static function cardTitle(string $extra = ''): string {
        return trim("h5 mb-0 {$extra}");
    }

    public static function cardBody(string $extra = ''): string {
        return trim("d-grid gap-3 {$extra}");
    }

    public static function cardFooter(string $extra = ''): string {
        return trim("d-flex align-items-center justify-content-end gap-2 border-top pt-3 mt-4 {$extra}");
    }

    /**
     * Button Components
     *
     * @param string $variant 'primary' | 'secondary' | 'danger' | 'outline' | 'ghost' | 'scout'
     * @param string $size    'xs' | 'sm' | 'md' | 'lg' | 'icon'
     * @param string $extra   Additional classes
     */
    public static function button(string $variant = 'primary', string $size = 'md', string $extra = ''): string {
        $sizes = [
            'xs'   => 'btn-sm',
            'sm'   => 'btn-sm',
            'md'   => '',
            'lg'   => 'btn-lg',
            'icon' => 'btn-sm',
        ];

        $variants = [
            'primary'   => 'btn-primary',
            'scout'     => 'btn-scout',
            'secondary' => 'btn-secondary',
            'danger'    => 'btn-danger',
            'outline'   => 'btn-outline-secondary',
            'ghost'     => 'btn-link',
        ];

        $sz = $sizes[$size] ?? '';
        $vr = $variants[$variant] ?? $variants['primary'];

        return trim(preg_replace('/\s+/', ' ', "btn {$vr} {$sz} {$extra}"));
    }

    /**
     * Form Control Elements
     */
    public static function input(string $extra = ''): string {
        return trim("form-control {$extra}");
    }

    public static function textarea(string $extra = ''): string {
        return trim("form-control {$extra}");
    }

    public static function select(string $extra = ''): string {
        return trim("form-select {$extra}");
    }

    public static function checkbox(string $extra = ''): string {
        return trim("form-check-input {$extra}");
    }

    public static function radio(string $extra = ''): string {
        return trim("form-check-input {$extra}");
    }

    public static function label(bool $required = false, string $extra = ''): string {
        $req = $required ? 'sm-required' : '';
        return trim(preg_replace('/\s+/', ' ', "form-label {$req} {$extra}"));
    }

    public static function helperText(string $extra = ''): string {
        return trim("form-text {$extra}");
    }

    public static function errorText(string $extra = ''): string {
        return trim("invalid-feedback d-block {$extra}");
    }

    /**
     * Badges & Status Pills
     *
     * @param string $variant 'success' | 'info' | 'warning' | 'danger' | 'neutral' | 'scout'
     */
    public static function badge(string $variant = 'info', string $extra = ''): string {
        $variants = [
            'success' => 'text-bg-success',
            'info'    => 'text-bg-info',
            'warning' => 'text-bg-warning',
            'danger'  => 'text-bg-danger',
            'neutral' => 'text-bg-light border',
            'scout'   => 'sm-badge-scout',
        ];

        $vr = $variants[$variant] ?? $variants['neutral'];
        return trim("badge {$vr} {$extra}");
    }

    /**
     * Data Tables
     */
    public static function table(string $extra = ''): string {
        return trim("table table-hover align-middle {$extra}");
    }

    public static function thead(string $extra = ''): string {
        return trim("table-light {$extra}");
    }

    public static function th(string $extra = ''): string {
        return trim($extra);
    }

    public static function tbody(string $extra = ''): string {
        return trim($extra);
    }

    public static function tr(string $extra = ''): string {
        return trim($extra);
    }

    public static function td(string $extra = ''): string {
        return trim($extra);
    }

    /**
     * Layout Utilities
     */
    public static function grid(int $cols = 2, string $gap = '3', string $extra = ''): string {
        $cols = max(1, min(4, $cols));
        return trim("sm-grid sm-grid-{$cols} gap-{$gap} {$extra}");
    }

    public static function flexBetween(string $extra = ''): string {
        return trim("d-flex align-items-center justify-content-between gap-3 {$extra}");
    }

    /**
     * Status messages shown after a submission
     *
     * @param string $variant 'success' | 'danger' | 'warning' | 'info'
     */
    public static function alert(string $variant = 'info', string $extra = ''): string {
        return trim("alert alert-{$variant} {$extra}");
    }
}
