<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';

interface Props {
  modelValue: boolean;
  label?: string;
  disabled?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
}>();
</script>

<template>
  <label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
    <SwitchRoot
      :model-value="modelValue"
      :disabled="disabled"
      @update:model-value="emit('update:modelValue', Boolean($event))"
      class="w-10 h-6 bg-slate-200 rounded-full relative data-[state=checked]:bg-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <SwitchThumb
        class="block w-4 h-4 bg-white rounded-full shadow-md transition-transform duration-150 translate-x-1 will-change-transform data-[state=checked]:translate-x-5"
      />
    </SwitchRoot>
    <span v-if="label" class="text-xs font-semibold text-slate-700">{{ label }}</span>
  </label>
</template>
