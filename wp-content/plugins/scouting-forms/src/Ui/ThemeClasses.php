<?php

namespace ScoutingMemories\Forms\Ui;

/**
 * ThemeClasses
 *
 * Object-Oriented Encapsulation Layer for Tailwind CSS v4 Selectors.
 * Provides clean, consistent, typed methods for UI components across the site,
 * ensuring templates never scatter raw utility strings.
 */
class ThemeClasses {

    /**
     * Card Containers
     */
    public static function card(string $extra = ''): string {
        return trim("bg-white border border-slate-200 rounded-xl shadow-sm p-6 text-slate-800 transition-all {$extra}");
    }

    public static function cardHeader(string $extra = ''): string {
        return trim("border-b border-slate-100 pb-4 mb-4 flex items-center justify-between {$extra}");
    }

    public static function cardTitle(string $extra = ''): string {
        return trim("text-lg font-bold text-slate-900 tracking-tight {$extra}");
    }

    public static function cardBody(string $extra = ''): string {
        return trim("space-y-4 {$extra}");
    }

    public static function cardFooter(string $extra = ''): string {
        return trim("border-t border-slate-100 pt-4 mt-6 flex items-center justify-end gap-3 {$extra}");
    }

    /**
     * Button Components
     *
     * @param string $variant 'primary' | 'secondary' | 'danger' | 'outline' | 'ghost' | 'scout'
     * @param string $size    'xs' | 'sm' | 'md' | 'lg' | 'icon'
     * @param string $extra   Additional utility overrides
     */
    public static function button(string $variant = 'primary', string $size = 'md', string $extra = ''): string {
        $base = "inline-flex items-center justify-center font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none cursor-pointer";

        $sizes = [
            'xs'   => "px-2 py-1 text-xs gap-1",
            'sm'   => "px-3 py-1.5 text-xs gap-1.5",
            'md'   => "px-4 py-2 text-sm gap-2",
            'lg'   => "px-5 py-2.5 text-base gap-2.5",
            'icon' => "p-2 text-sm w-9 h-9"
        ];

        $variants = [
            'primary'   => "bg-blue-600 text-white hover:bg-blue-700 shadow-sm focus:ring-blue-500",
            'scout'     => "bg-[#025600] text-white hover:bg-[#013e00] shadow-sm focus:ring-[#025600]",
            'secondary' => "bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-400 border border-slate-200",
            'danger'    => "bg-red-600 text-white hover:bg-red-700 shadow-sm focus:ring-red-500",
            'outline'   => "bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm focus:ring-slate-400",
            'ghost'     => "text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-300"
        ];

        $sz = $sizes[$size] ?? $sizes['md'];
        $vr = $variants[$variant] ?? $variants['primary'];

        return trim("{$base} {$sz} {$vr} {$extra}");
    }

    /**
     * Form Control Elements
     */
    public static function input(string $extra = ''): string {
        return trim("w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all disabled:bg-slate-50 disabled:text-slate-400 {$extra}");
    }

    public static function textarea(string $extra = ''): string {
        return trim("w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all disabled:bg-slate-50 {$extra}");
    }

    public static function select(string $extra = ''): string {
        return trim("w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all disabled:bg-slate-50 {$extra}");
    }

    public static function checkbox(string $extra = ''): string {
        return trim("w-4 h-4 text-blue-600 bg-white border-slate-300 rounded focus:ring-blue-500 focus:ring-offset-0 transition-all cursor-pointer {$extra}");
    }

    public static function radio(string $extra = ''): string {
        return trim("w-4 h-4 text-blue-600 bg-white border-slate-300 focus:ring-blue-500 focus:ring-offset-0 transition-all cursor-pointer {$extra}");
    }

    public static function label(bool $required = false, string $extra = ''): string {
        $req = $required ? "after:content-['*'] after:ml-0.5 after:text-red-500" : "";
        return trim("block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 {$req} {$extra}");
    }

    public static function helperText(string $extra = ''): string {
        return trim("text-xs text-slate-500 mt-1 {$extra}");
    }

    public static function errorText(string $extra = ''): string {
        return trim("text-xs text-red-600 font-medium mt-1 {$extra}");
    }

    /**
     * Badges & Status Pills
     *
     * @param string $variant 'success' | 'info' | 'warning' | 'danger' | 'neutral' | 'scout'
     */
    public static function badge(string $variant = 'info', string $extra = ''): string {
        $base = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border";

        $variants = [
            'success' => "bg-emerald-50 text-emerald-700 border-emerald-200",
            'info'    => "bg-blue-50 text-blue-700 border-blue-200",
            'warning' => "bg-amber-50 text-amber-700 border-amber-200",
            'danger'  => "bg-red-50 text-red-700 border-red-200",
            'neutral' => "bg-slate-100 text-slate-600 border-slate-200",
            'scout'   => "bg-[#025600]/10 text-[#025600] border-[#025600]/20"
        ];

        $vr = $variants[$variant] ?? $variants['neutral'];
        return trim("{$base} {$vr} {$extra}");
    }

    /**
     * Data Tables
     */
    public static function table(string $extra = ''): string {
        return trim("min-w-full divide-y divide-slate-200 text-sm text-left {$extra}");
    }

    public static function thead(string $extra = ''): string {
        return trim("bg-slate-50 text-slate-500 uppercase text-xs font-semibold tracking-wider {$extra}");
    }

    public static function th(string $extra = ''): string {
        return trim("px-4 py-3 text-slate-600 font-semibold {$extra}");
    }

    public static function tbody(string $extra = ''): string {
        return trim("divide-y divide-slate-100 bg-white {$extra}");
    }

    public static function tr(string $extra = ''): string {
        return trim("hover:bg-slate-50/80 transition-colors {$extra}");
    }

    public static function td(string $extra = ''): string {
        return trim("px-4 py-3 text-slate-800 whitespace-nowrap {$extra}");
    }

    /**
     * Layout Utilities
     */
    public static function grid(int $cols = 2, string $gap = '6', string $extra = ''): string {
        $colClass = [
            1 => "grid-cols-1",
            2 => "grid-cols-1 md:grid-cols-2",
            3 => "grid-cols-1 md:grid-cols-3",
            4 => "grid-cols-1 sm:grid-cols-2 lg:grid-cols-4"
        ][$cols] ?? "grid-cols-1 md:grid-cols-{$cols}";

        return trim("grid {$colClass} gap-{$gap} {$extra}");
    }

    public static function flexBetween(string $extra = ''): string {
        return trim("flex items-center justify-between gap-4 {$extra}");
    }
}
