<script setup lang="ts">
interface Props {
  modelValue?: string | number;
  label?: string;
  placeholder?: string;
  required?: boolean;
  type?: string;
  error?: string;
  helper?: string;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  label: '',
  placeholder: '',
  required: false,
  type: 'text',
  error: '',
  helper: '',
  disabled: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void;
}>();

function onInput(event: Event) {
  const target = event.target as HTMLInputElement;
  emit('update:modelValue', target.value);
}
</script>

<template>
  <div class="w-full space-y-1">
    <label v-if="label" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      @input="onInput"
      class="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all disabled:bg-slate-50 disabled:text-slate-400"
      :class="{'border-red-500 focus:border-red-500 focus:ring-red-500/20': error}"
    />
    <p v-if="error" class="text-xs text-red-600 font-medium">{{ error }}</p>
    <p v-else-if="helper" class="text-xs text-slate-500">{{ helper }}</p>
  </div>
</template>
