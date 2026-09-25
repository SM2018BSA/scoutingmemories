<script setup lang="ts">
import { computed } from 'vue';
import { CheckboxRoot, CheckboxIndicator, RadioGroupRoot, RadioGroupItem, RadioGroupIndicator } from 'reka-ui';
import { Check } from 'lucide-vue-next';
import UiCombobox from '../ui/UiCombobox.vue';
import UiSwitch from '../ui/UiSwitch.vue';

export interface EntryFieldDef {
  id: number;
  name: string;
  description?: string;
  description_text?: string;
  type: string;
  required?: boolean;
  choices?: { value: string; label: string }[];
  field_options?: Record<string, any>;
}

/**
 * One field of an entry in the admin editor, drawn by its type: text boxes, text areas, date,
 * dropdowns (searchable for Dynamic fields and long lists), checkboxes, radio buttons, switches.
 * Fields filled in automatically (user, uploads, "just show it" values) are shown, not edited.
 */
const props = defineProps<{
  field: EntryFieldDef;
  modelValue: any;
  display?: string;
  error?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void;
}>();

const inputId = computed(() => `sm-entry-field-${props.field.id}`);
const opts = computed(() => props.field.field_options || {});
const choices = computed(() => props.field.choices || []);
const dataType = computed(() => (props.field.type === 'data' ? String(opts.value.data_type || 'select') : ''));
// Formidable stores "no" as the string "0"
const isMultiple = computed(() => !!opts.value.multiple && String(opts.value.multiple) !== '0');

const kind = computed(() => {
  const t = props.field.type;
  if (['user_id', 'file'].includes(t) || dataType.value === 'data') return 'readonly';
  if (t === 'textarea' || t === 'rte') return 'textarea';
  if (t === 'toggle') return 'switch';
  if (t === 'checkbox' || dataType.value === 'checkbox') return 'checkboxes';
  if (t === 'radio' || dataType.value === 'radio') return 'radios';
  if (t === 'data' || (t === 'select' && (isMultiple.value || choices.value.length > 15))) return 'combobox';
  if (t === 'select') return 'select';
  return 'input';
});

const inputType = computed(() => ({ email: 'email', url: 'url', number: 'number', phone: 'tel', date: 'date', time: 'time', range: 'number' } as Record<string, string>)[props.field.type] || 'text');

const asList = computed<string[]>(() => {
  const v = props.modelValue;
  if (Array.isArray(v)) return v.map(String);
  return v === '' || v === null || v === undefined ? [] : [String(v)];
});

function toggleChoice(value: string, on: boolean) {
  const next = new Set(asList.value);
  if (on) next.add(value);
  else next.delete(value);
  emit('update:modelValue', Array.from(next));
}

const switchOn = computed(() => asList.value.length > 0 && asList.value[0] !== '0');
</script>

<template>
  <div class="space-y-1.5">
    <label :for="inputId" class="block text-xs font-bold text-slate-700">
      {{ field.name }}
      <span v-if="field.required" class="text-red-700" aria-hidden="true">*</span>
      <span v-if="field.required" class="sr-only">(required)</span>
    </label>

    <p v-if="kind === 'readonly'" :id="inputId" class="text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 min-h-9">
      {{ display || '—' }}
    </p>

    <textarea
      v-else-if="kind === 'textarea'"
      :id="inputId"
      :value="modelValue ?? ''"
      rows="4"
      class="w-full px-3 py-2 text-sm bg-white border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-600/25"
      :class="error ? 'border-red-400' : 'border-slate-300'"
      :aria-invalid="error ? 'true' : 'false'"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />

    <UiSwitch
      v-else-if="kind === 'switch'"
      :model-value="switchOn"
      :label="switchOn ? 'On' : 'Off'"
      @update:model-value="emit('update:modelValue', $event ? ['1'] : [])"
    />

    <div v-else-if="kind === 'checkboxes'" :id="inputId" role="group" :aria-label="field.name" class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
      <label v-for="choice in choices" :key="choice.value" class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
        <CheckboxRoot
          :model-value="asList.includes(choice.value)"
          class="flex items-center justify-center w-4 h-4 border border-slate-400 rounded bg-white data-[state=checked]:bg-emerald-700 data-[state=checked]:border-emerald-700"
          @update:model-value="toggleChoice(choice.value, $event === true)"
        >
          <CheckboxIndicator>
            <Check class="w-3 h-3 text-white" />
          </CheckboxIndicator>
        </CheckboxRoot>
        <span>{{ choice.label }}</span>
      </label>
    </div>

    <RadioGroupRoot
      v-else-if="kind === 'radios'"
      :id="inputId"
      :model-value="asList[0] ?? ''"
      :aria-label="field.name"
      class="grid grid-cols-1 sm:grid-cols-2 gap-1.5"
      @update:model-value="emit('update:modelValue', String($event))"
    >
      <label v-for="choice in choices" :key="choice.value" class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
        <RadioGroupItem :value="choice.value" class="flex items-center justify-center w-4 h-4 border border-slate-400 rounded-full bg-white">
          <RadioGroupIndicator class="block w-2 h-2 rounded-full bg-emerald-700" />
        </RadioGroupItem>
        <span>{{ choice.label }}</span>
      </label>
    </RadioGroupRoot>

    <UiCombobox
      v-else-if="kind === 'combobox'"
      :id="inputId"
      :model-value="isMultiple ? asList : (asList[0] ?? '')"
      :choices="choices"
      :multiple="isMultiple"
      :label="field.name"
      :invalid="!!error"
      @update:model-value="emit('update:modelValue', $event)"
    />

    <select
      v-else-if="kind === 'select'"
      :id="inputId"
      :value="asList[0] ?? ''"
      class="w-full px-3 py-2 text-sm bg-white border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-600/25"
      :class="error ? 'border-red-400' : 'border-slate-300'"
      :aria-invalid="error ? 'true' : 'false'"
      @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option value=""></option>
      <option v-for="choice in choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
    </select>

    <input
      v-else
      :id="inputId"
      :type="inputType"
      :value="modelValue ?? ''"
      class="w-full px-3 py-2 text-sm bg-white border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-600/25"
      :class="error ? 'border-red-400' : 'border-slate-300'"
      :aria-invalid="error ? 'true' : 'false'"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />

    <p v-if="error" class="text-xs font-medium text-red-700" role="alert">{{ error }}</p>
    <p v-else-if="field.description_text" class="text-xs text-slate-500">{{ field.description_text }}</p>
  </div>
</template>
