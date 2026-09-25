<script setup lang="ts">
import { computed } from 'vue';
import { Plus, Trash2, Lock } from 'lucide-vue-next';

/**
 * Rule rows in Formidable's format: field, operator, value. Used for field show/hide logic and
 * for "only run this action if ..." conditions. Rows with a list value the builder cannot edit
 * are shown locked (the server keeps their value as it is).
 */
interface Row { index?: number | null; field: number; cond: string; value: string; locked?: boolean }
interface FieldRef { id: number; name: string; type: string; choices?: { label: string; value: string }[] | null }

const props = defineProps<{
  rows: Row[];
  fields: FieldRef[];
  operators: string[];
  excludeId?: number;
}>();

const opLabels: Record<string, string> = {
  '==': 'is', '!=': 'is not', '>': 'is greater than', '<': 'is less than', '>=': 'is at least',
  '<=': 'is at most', 'LIKE': 'contains', 'not LIKE': 'does not contain', 'LIKE%': 'starts with', '%LIKE': 'ends with'
};

const targets = computed(() => props.fields.filter((f) =>
  f.id && f.id !== props.excludeId && !['divider', 'end_divider', 'break', 'html', 'captcha', 'submit', 'password'].includes(f.type)
));

function choicesOf(id: number) {
  const f = props.fields.find((x) => x.id === Number(id));
  return f && f.choices && f.choices.length ? f.choices : null;
}

function addRow() {
  props.rows.push({ index: null, field: 0, cond: '==', value: '', locked: false });
}
</script>

<template>
  <div class="space-y-2">
    <div v-for="(row, i) in rows" :key="i" class="flex flex-wrap items-center gap-2">
      <select v-model.number="row.field" :disabled="row.locked" class="sm-builder-input !w-auto min-w-48 flex-1" aria-label="Field">
        <option :value="0">Choose a field…</option>
        <option v-for="f in targets" :key="f.id" :value="f.id">{{ f.name || '(no label)' }} (#{{ f.id }})</option>
      </select>
      <select v-model="row.cond" :disabled="row.locked" class="sm-builder-input !w-auto" aria-label="Operator">
        <option v-for="op in operators" :key="op" :value="op">{{ opLabels[op] || op }}</option>
      </select>
      <template v-if="row.locked">
        <span class="inline-flex items-center gap-1 text-xs text-slate-500 flex-1" :title="row.value">
          <Lock class="w-3.5 h-3.5" /> {{ row.value || '(list)' }} — list value, kept as it is
        </span>
      </template>
      <select v-else-if="choicesOf(row.field)" v-model="row.value" class="sm-builder-input !w-auto min-w-40 flex-1" aria-label="Value">
        <option value="">(blank / anything)</option>
        <option v-for="c in choicesOf(row.field)!" :key="c.value" :value="c.value">{{ c.label }}</option>
      </select>
      <input v-else v-model="row.value" class="sm-builder-input !w-auto min-w-40 flex-1" placeholder="Value (blank = empty)" aria-label="Value" />
      <button type="button" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer" title="Remove rule" @click="rows.splice(i, 1)">
        <Trash2 class="w-4 h-4" />
      </button>
    </div>
    <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900 cursor-pointer" @click="addRow">
      <Plus class="w-3.5 h-3.5" /> Add a rule
    </button>
  </div>
</template>
