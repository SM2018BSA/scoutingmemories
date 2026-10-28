<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  ComboboxRoot,
  ComboboxAnchor,
  ComboboxInput,
  ComboboxTrigger,
  ComboboxContent,
  ComboboxViewport,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxEmpty,
  ComboboxPortal
} from 'reka-ui';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';

interface Choice {
  value: string;
  label: string;
}

/**
 * Searchable dropdown (Reka UI Combobox) for long lists such as councils or camps.
 * Filtering is done here so a list of thousands stays fast: at most 100 matches are drawn.
 */
const props = defineProps<{
  modelValue: string | string[];
  choices: Choice[];
  multiple?: boolean;
  id?: string;
  label?: string;
  invalid?: boolean;
  placeholder?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | string[]): void;
}>();

const search = ref('');
const labels = computed(() => new Map(props.choices.map((c) => [c.value, c.label])));

const selected = computed<string[]>(() => {
  if (Array.isArray(props.modelValue)) return props.modelValue.map(String);
  return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined ? [String(props.modelValue)] : [];
});

const matches = computed(() => {
  const term = search.value.trim().toLowerCase();
  const list = term === '' ? props.choices : props.choices.filter((c) => c.label.toLowerCase().includes(term));
  return list.slice(0, 100);
});

const model = computed({
  get: () => (props.multiple ? selected.value : (selected.value[0] ?? '')),
  set: (value: string | string[]) => {
    emit('update:modelValue', props.multiple ? (Array.isArray(value) ? value : [value]) : (Array.isArray(value) ? (value[0] ?? '') : value));
  }
});

function remove(value: string) {
  emit('update:modelValue', selected.value.filter((v) => v !== value));
}
</script>

<template>
  <div class="space-y-1.5">
    <div v-if="multiple && selected.length" class="flex flex-wrap gap-1.5">
      <span
        v-for="value in selected"
        :key="value"
        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-50 text-emerald-900 border border-emerald-200"
      >
        {{ labels.get(value) || value }}
        <button
          type="button"
          class="p-0.5 rounded hover:bg-emerald-100 cursor-pointer"
          :aria-label="`Remove ${labels.get(value) || value}`"
          :title="`Remove ${labels.get(value) || value}`"
          @click="remove(value)"
        >
          <X class="w-3 h-3" />
        </button>
      </span>
    </div>

    <ComboboxRoot
      v-model="model"
      :multiple="multiple"
      :ignore-filter="true"
      :reset-search-term-on-blur="true"
      :open-on-focus="true"
    >
      <ComboboxAnchor
        class="flex items-center w-full px-3 py-2 text-sm bg-white border rounded-lg shadow-2xs focus-within:ring-2 focus-within:ring-emerald-600/25"
        :class="invalid ? 'border-red-400' : 'border-slate-300'"
      >
        <ComboboxInput
          :id="id"
          v-model="search"
          class="flex-1 min-w-0 bg-transparent outline-none text-slate-900 placeholder:text-slate-400"
          :placeholder="placeholder || 'Type to search'"
          :aria-label="label"
          :aria-invalid="invalid ? 'true' : 'false'"
          :display-value="() => (multiple ? '' : (labels.get(selected[0] || '') || ''))"
        />
        <ComboboxTrigger class="p-0.5 text-slate-500 cursor-pointer" :aria-label="`Show choices for ${label || 'this field'}`">
          <ChevronsUpDown class="w-4 h-4" />
        </ComboboxTrigger>
      </ComboboxAnchor>

      <ComboboxPortal>
        <ComboboxContent
          position="popper"
          :side-offset="4"
          class="z-[100000] w-[var(--reka-combobox-trigger-width)] max-h-72 overflow-hidden bg-white border border-slate-200 rounded-lg shadow-lg"
        >
          <ComboboxViewport class="p-1 max-h-72 overflow-y-auto">
            <ComboboxEmpty class="px-3 py-2 text-sm text-slate-500">No matches</ComboboxEmpty>
            <ComboboxItem
              v-for="choice in matches"
              :key="choice.value"
              :value="choice.value"
              :text-value="choice.label"
              class="flex items-center justify-between gap-2 px-3 py-1.5 text-sm rounded-md cursor-pointer text-slate-800 data-[highlighted]:bg-emerald-50 data-[state=checked]:font-semibold"
            >
              <span>{{ choice.label }}</span>
              <ComboboxItemIndicator>
                <Check class="w-4 h-4 text-emerald-700" />
              </ComboboxItemIndicator>
            </ComboboxItem>
            <div v-if="matches.length === 100" class="px-3 py-1.5 text-xs text-slate-500">
              Showing the first 100 matches. Type to narrow the list.
            </div>
          </ComboboxViewport>
        </ComboboxContent>
      </ComboboxPortal>
    </ComboboxRoot>
  </div>
</template>
