<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';

interface Props extends PrimitiveProps {
  variant?: 'primary' | 'scout' | 'secondary' | 'danger' | 'outline' | 'ghost';
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'icon';
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  as: 'button',
  variant: 'primary',
  size: 'md',
  disabled: false
});

const variantClasses: Record<string, string> = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700 shadow-sm focus:ring-blue-500/30',
  scout: 'bg-[#025600] text-white hover:bg-[#013e00] shadow-sm focus:ring-[#025600]/30',
  secondary: 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200',
  danger: 'bg-red-600 text-white hover:bg-red-700 shadow-sm focus:ring-red-500/30',
  outline: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
};

const sizeClasses: Record<string, string> = {
  xs: 'px-2.5 py-1 text-xs gap-1',
  sm: 'px-3 py-1.5 text-xs gap-1.5',
  md: 'px-4 py-2 text-sm gap-2',
  lg: 'px-5 py-2.5 text-base gap-2.5',
  icon: 'p-2 text-sm w-9 h-9',
};
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :disabled="disabled"
    class="inline-flex items-center justify-center font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none"
    :class="[variantClasses[variant], sizeClasses[size]]"
  >
    <slot />
  </Primitive>
</template>
