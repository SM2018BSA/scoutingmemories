<script setup lang="ts">
interface Props {
  title?: string;
  subtitle?: string;
}

defineProps<Props>();
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden text-slate-800 transition-all">
    <div v-if="title || $slots.header" class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
      <slot name="header">
        <div>
          <h3 class="text-base font-bold text-slate-900 tracking-tight">{{ title }}</h3>
          <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </slot>
    </div>
    <div class="p-6">
      <slot />
    </div>
    <div v-if="$slots.footer" class="border-t border-slate-100 px-6 py-3 bg-slate-50 flex items-center justify-end gap-3">
      <slot name="footer" />
    </div>
  </div>
</template>
