<script setup lang="ts">
import { ref, computed } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiBadge from '../ui/UiBadge.vue';
import UiDialog from '../ui/UiDialog.vue';
import FieldSettings from './FieldSettings.vue';
import ActionsPanel from './ActionsPanel.vue';
import {
  ArrowLeft, Save, Plus, ArrowUp, ArrowDown, Trash2, ChevronDown, ChevronRight, AlertCircle, GitBranch, Undo2
} from 'lucide-vue-next';

/**
 * Edits a form in Formidable's tables: settings, fields (with choices and conditional logic) and
 * actions. Field changes are saved together with "Save form"; the server checks everything first
 * and saves nothing if a field needs attention (the message appears on that field).
 */
const props = defineProps<{
  builder: any;
  saving: boolean;
  errors: Record<string, string>;
  actionErrors: Record<string, Record<string, string>>;
  actionSaving: number | null;
  canEdit: boolean;
}>();

const emit = defineEmits<{
  (e: 'back'): void;
  (e: 'save', payload: { form: any; fields: any[]; deleted: number[] }): void;
  (e: 'save-action', action: any): void;
  (e: 'create-action', type: string): void;
  (e: 'remove-action', action: any): void;
}>();

const form = computed(() => props.builder.form);
const fields = computed<any[]>(() => props.builder.fields);
const deleted = ref<number[]>([]);
const removed = ref<{ field: any; index: number }[]>([]);
const expanded = ref<Record<string, boolean>>({});

const typeLabels: Record<string, string> = {
  text: 'Text', textarea: 'Paragraph', email: 'Email', url: 'Website', number: 'Number', phone: 'Phone',
  date: 'Date', time: 'Time', select: 'Dropdown', radio: 'Radio buttons', checkbox: 'Checkboxes', hidden: 'Hidden',
  html: 'HTML', divider: 'Section', end_divider: 'Section end', break: 'Page break', data: 'Dynamic', toggle: 'Toggle',
  user_id: 'User ID', file: 'File upload', rte: 'Rich text', password: 'Password', captcha: 'reCAPTCHA', range: 'Slider'
};

// Sections indent the fields inside them
const depth = computed(() => {
  let open = false;
  return fields.value.map((f) => {
    if (f.type === 'divider') { open = true; return 0; }
    if (f.type === 'end_divider') { open = false; return 0; }
    return open ? 1 : 0;
  });
});

const hasLogic = (f: any) => f.logic && f.logic.rows && f.logic.rows.length > 0;
const refOf = (f: any) => String(f.ref || f.id);

function move(index: number, d: number) {
  const j = index + d;
  const list = fields.value;
  if (j < 0 || j >= list.length) return;
  [list[index], list[j]] = [list[j], list[index]];
}

function remove(index: number) {
  const f = fields.value[index];
  if (f.theme_constant) {
    alert(`The theme uses "${f.name}" (${f.theme_constant}), so it cannot be deleted.`);
    return;
  }
  if (['divider', 'end_divider'].includes(f.type)) {
    alert('Sections cannot be deleted here.');
    return;
  }
  if (f.id) {
    if (!confirm(`Delete the field "${f.name}"?\n\nWhen you save, the field and every answer saved in it are deleted. This cannot be undone.`)) return;
    deleted.value.push(f.id);
    removed.value.push({ field: f, index });
  }
  fields.value.splice(index, 1);
}

// Add field dialog
const isAddOpen = ref(false);
const newType = ref('text');
const newLabel = ref('');
let newCount = 0;

function addField() {
  if (!newLabel.value.trim()) return;
  const choiceType = ['select', 'radio', 'checkbox'].includes(newType.value);
  const ref = `new${Date.now()}${newCount++}`;
  fields.value.push({
    id: 0, ref, name: newLabel.value.trim(), field_key: '', type: newType.value, description: '', required: false,
    default_value: '', default_locked: false, placeholder: '', classes: '', blank: '', invalid: '', unique: false,
    unique_msg: '', separate_value: false,
    choices: choiceType ? [{ key: '', label: 'Option 1', value: 'Option 1', array: false }, { key: '', label: 'Option 2', value: 'Option 2', array: false }] : null,
    logic: { show_hide: 'show', any_all: 'any', rows: [] }, in_section: 0, repeat: false, info: [], theme_constant: ''
  });
  expanded.value[ref] = true;
  newLabel.value = '';
  newType.value = 'text';
  isAddOpen.value = false;
}

function save() {
  emit('save', { form: form.value, fields: fields.value, deleted: deleted.value });
}

// Called by the parent after a successful save (the server sends the fresh form)
function afterSave() {
  deleted.value = [];
  removed.value = [];
}

// Put fields marked for deletion back where they were
function restoreDeleted() {
  for (const r of [...removed.value].reverse()) {
    fields.value.splice(Math.min(r.index, fields.value.length), 0, r.field);
  }
  deleted.value = [];
  removed.value = [];
}
defineExpose({ afterSave });

const errorCount = computed(() => Object.keys(props.errors || {}).length);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm sticky top-8 z-10">
      <div class="flex items-center gap-3">
        <UiButton variant="outline" size="sm" @click="emit('back')">
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Forms</span>
        </UiButton>
        <div>
          <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span>{{ form.name }}</span>
            <UiBadge variant="info">Form #{{ form.id }}</UiBadge>
            <UiBadge v-if="form.parent_form_id" variant="neutral">Part of form #{{ form.parent_form_id }}</UiBadge>
          </h2>
          <p class="text-xs text-slate-500 font-mono mt-0.5">[sm_form id={{ form.id }}]</p>
        </div>
      </div>
      <div v-if="canEdit" class="flex items-center gap-2">
        <span v-if="deleted.length" class="text-xs text-red-700 font-semibold">{{ deleted.length }} field(s) will be deleted</span>
        <UiButton v-if="deleted.length" variant="ghost" size="sm" @click="restoreDeleted"><Undo2 class="w-3.5 h-3.5" /><span>Undo</span></UiButton>
        <UiButton variant="outline" @click="isAddOpen = true"><Plus class="w-4 h-4" /><span>Add field</span></UiButton>
        <UiButton variant="scout" :disabled="saving" @click="save">
          <Save class="w-4 h-4" /><span>{{ saving ? 'Saving…' : 'Save form' }}</span>
        </UiButton>
      </div>
    </div>

    <div v-if="errorCount" class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
      <AlertCircle class="w-5 h-5 flex-shrink-0" />
      <div>
        <p class="font-semibold">The fields were not saved. {{ errors.form || 'Please fix the marked fields.' }}</p>
        <ul v-if="deleted.length" class="mt-1 text-xs">
          <li v-for="id in deleted.filter((d) => errors[String(d)])" :key="id">Field #{{ id }}: {{ errors[String(id)] }}</li>
        </ul>
      </div>
    </div>

    <UiCard title="Form settings">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput v-model="form.name" label="Form title" required />
        <UiInput v-model="form.form_key" label="Form key" required />
        <UiInput v-model="form.submit_value" label="Submit button" placeholder="Submit" />
        <UiInput v-model="form.edit_value" label="Update button (when editing)" placeholder="Update" />
      </div>
      <div class="mt-4">
        <label class="sm-builder-label">Description</label>
        <textarea v-model="form.description" rows="2" class="sm-builder-input text-sm" />
      </div>
    </UiCard>

    <UiCard>
      <template #header>
        <div>
          <h3 class="text-base font-bold text-slate-900 tracking-tight">Fields ({{ fields.length }})</h3>
          <p class="text-xs text-slate-500 mt-0.5">Click a field to change its settings. Use the arrows to reorder. Nothing is stored until you press "Save form".</p>
        </div>
      </template>

      <p v-if="!fields.length" class="text-center py-12 text-sm text-slate-400">No fields yet.</p>

      <div class="space-y-2">
        <div
          v-for="(field, index) in fields"
          :key="refOf(field)"
          class="border rounded-xl transition-all"
          :class="[
            errors[refOf(field)] ? 'border-red-400 bg-red-50/40' : field.type === 'divider' || field.type === 'end_divider' ? 'border-slate-300 bg-slate-100' : 'border-slate-200 bg-slate-50',
            depth[index] ? 'ml-8' : ''
          ]"
        >
          <div class="flex items-center gap-3 p-3">
            <div v-if="canEdit" class="flex flex-col">
              <button type="button" :disabled="index === 0" class="p-0.5 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer" title="Move up" @click="move(index, -1)"><ArrowUp class="w-3.5 h-3.5" /></button>
              <button type="button" :disabled="index === fields.length - 1" class="p-0.5 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer" title="Move down" @click="move(index, 1)"><ArrowDown class="w-3.5 h-3.5" /></button>
            </div>
            <button type="button" class="flex-1 flex items-center gap-2 text-left cursor-pointer min-w-0" :aria-expanded="!!expanded[refOf(field)]" @click="expanded[refOf(field)] = !expanded[refOf(field)]">
              <component :is="expanded[refOf(field)] ? ChevronDown : ChevronRight" class="w-4 h-4 text-slate-400 flex-shrink-0" />
              <span class="font-semibold text-sm text-slate-900 truncate">{{ field.name || '(no label)' }}<span v-if="field.required" class="text-red-500"> *</span></span>
              <UiBadge variant="neutral">{{ typeLabels[field.type] || field.type }}</UiBadge>
              <UiBadge v-if="field.repeat" variant="info">Repeating</UiBadge>
              <span v-if="hasLogic(field)" class="inline-flex items-center gap-1 text-xs text-blue-700" title="Has conditional logic"><GitBranch class="w-3.5 h-3.5" />logic</span>
              <UiBadge v-if="!field.id" variant="scout">New</UiBadge>
              <span v-if="field.id" class="text-xs text-slate-400 font-mono">#{{ field.id }}</span>
            </button>
            <button v-if="canEdit" type="button" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer" title="Delete field" @click="remove(index)">
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
          <p v-if="errors[refOf(field)]" class="px-4 pb-2 text-xs text-red-700 font-semibold">{{ errors[refOf(field)] }}</p>
          <div v-if="expanded[refOf(field)]" class="px-4 pb-4">
            <FieldSettings :field="field" :fields="fields" :operators="builder.operators" />
          </div>
        </div>
      </div>
    </UiCard>

    <ActionsPanel
      :actions="builder.actions"
      :fields="fields"
      :pages="builder.pages"
      :operators="builder.operators"
      :errors="actionErrors"
      :saving="actionSaving"
      :can-edit="canEdit"
      :can-delete="!!builder.can_delete"
      @save="emit('save-action', $event)"
      @create="emit('create-action', $event)"
      @remove="emit('remove-action', $event)"
    />

    <UiDialog v-model:open="isAddOpen" title="Add a field" description="Choose the kind of field and its label. It is added at the end; move it with the arrows.">
      <div class="space-y-4 py-2">
        <div>
          <label class="sm-builder-label">Kind of field</label>
          <select v-model="newType" class="sm-builder-input">
            <option v-for="t in builder.new_field_types" :key="t" :value="t">{{ typeLabels[t] || t }}</option>
          </select>
        </div>
        <UiInput v-model="newLabel" label="Label" placeholder="e.g. Council Name" required />
      </div>
      <template #footer>
        <UiButton variant="outline" @click="isAddOpen = false">Cancel</UiButton>
        <UiButton variant="primary" :disabled="!newLabel.trim()" @click="addField">Add field</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
