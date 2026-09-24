<script setup lang="ts">
interface Props {
  variant?: 'success' | 'info' | 'warning' | 'danger' | 'neutral' | 'scout';
}

withDefaults(defineProps<Props>(), {
  variant: 'info'
});

const variantClasses: Record<string, string> = {
  success: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  info:    'bg-blue-50 text-blue-700 border-blue-200',
  warning: 'bg-amber-50 text-amber-700 border-amber-200',
  danger:  'bg-red-50 text-red-700 border-red-200',
  neutral: 'bg-slate-100 text-slate-600 border-slate-200',
  scout:   'bg-[#025600]/10 text-[#025600] border-[#025600]/20'
};
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border"
    :class="variantClasses[variant]"
  >
    <slot />
  </span>
</template>
