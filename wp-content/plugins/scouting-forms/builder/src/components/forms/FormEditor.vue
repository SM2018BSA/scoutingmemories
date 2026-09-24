<script setup lang="ts">
import { ref } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiBadge from '../ui/UiBadge.vue';
import UiSwitch from '../ui/UiSwitch.vue';
import UiDialog from '../ui/UiDialog.vue';
import {
  ArrowLeft,
  Save,
  Plus,
  ArrowUp,
  ArrowDown,
  Trash2,
  CheckCircle2,
  AlertCircle
} from 'lucide-vue-next';

interface FieldItem {
  id: number;
  name: string;
  type: string;
  field_key: string;
  required: boolean;
  default_value: string;
  field_order: number;
  classes?: string;
  placeholder?: string;
  field_options?: any;
}

interface FormData {
  id: number;
  name: string;
  form_key: string;
  description: string;
  submit_value?: string;
  success_msg?: string;
  fields: FieldItem[];
}

const props = defineProps<{
  form: FormData;
  saving: boolean;
}>();

const emit = defineEmits<{
  (e: 'back'): void;
  (e: 'save', payload: { form: FormData; fields: FieldItem[] }): void;
}>();

// Add Field Modal State
const isAddModalOpen = ref(false);
const newFieldType = ref('text');
const newFieldLabel = ref('');

const fieldTypes = [
  { value: 'text', label: 'Single Line Text' },
  { value: 'textarea', label: 'Multi-line Paragraph' },
  { value: 'number', label: 'Number / Year' },
  { value: 'select', label: 'Dropdown Select' },
  { value: 'radio', label: 'Radio Buttons' },
  { value: 'checkbox', label: 'Checkboxes' },
  { value: 'date', label: 'Date Picker' },
  { value: 'email', label: 'Email Address' },
  { value: 'url', label: 'Website URL' },
  { value: 'hidden', label: 'Hidden Field' }
];

function moveField(index: number, direction: 'up' | 'down') {
  const targetIndex = direction === 'up' ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= props.form.fields.length) return;

  const temp = props.form.fields[index];
  props.form.fields[index] = props.form.fields[targetIndex];
  props.form.fields[targetIndex] = temp;
}

function removeField(index: number) {
  if (confirm(`Remove field "${props.form.fields[index].name}"?`)) {
    props.form.fields.splice(index, 1);
  }
}

function handleAddField() {
  if (!newFieldLabel.value.trim()) return;

  const keySlug = newFieldLabel.value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

  props.form.fields.push({
    id: 0, // 0 signifies new field to be inserted
    name: newFieldLabel.value.trim(),
    type: newFieldType.value,
    field_key: `field_${keySlug || Date.now()}`,
    required: false,
    default_value: '',
    field_order: props.form.fields.length + 1,
    placeholder: '',
    classes: ''
  });

  newFieldLabel.value = '';
  newFieldType.value = 'text';
  isAddModalOpen.value = false;
}

function handleSave() {
  emit('save', {
    form: props.form,
    fields: props.form.fields
  });
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Navigation & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center gap-3">
        <UiButton variant="outline" size="sm" @click="emit('back')">
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Forms</span>
        </UiButton>
        <div>
          <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span>{{ form.name }}</span>
            <UiBadge variant="info">Form #{{ form.id }}</UiBadge>
          </h2>
          <p class="text-xs text-slate-500 font-mono mt-0.5">Key: {{ form.form_key }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UiButton variant="outline" @click="isAddModalOpen = true">
          <Plus class="w-4 h-4" />
          <span>Add New Field</span>
        </UiButton>

        <UiButton variant="scout" :disabled="saving" @click="handleSave">
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Form & Fields' }}</span>
        </UiButton>
      </div>
    </div>

    <!-- Form Settings Panel -->
    <UiCard title="General Form Settings" subtitle="Configure submission actions, keys, and confirmation messages">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput v-model="form.name" label="Form Title" required />
        <UiInput v-model="form.form_key" label="Form Key (Unique Slug)" required />
        <UiInput v-model="form.submit_value" label="Submit Button Label" placeholder="Submit" />
        <UiInput v-model="form.success_msg" label="Success Message" placeholder="Your submission was successful." />
      </div>
    </UiCard>

    <!-- Fields Canvas / Reordering List -->
    <UiCard>
      <template #header>
        <div>
          <h3 class="text-base font-bold text-slate-900 tracking-tight">Form Fields ({{ form.fields.length }})</h3>
          <p class="text-xs text-slate-500 mt-0.5">Drag or use arrow buttons to reorder fields. Changes persist directly to the database.</p>
        </div>
        <UiButton variant="outline" size="sm" @click="isAddModalOpen = true">
          <Plus class="w-3.5 h-3.5" />
          <span>Add Field</span>
        </UiButton>
      </template>

      <div v-if="form.fields.length === 0" class="text-center py-12 text-slate-400">
        <p class="text-sm">No fields in this form yet. Click "Add New Field" above to start.</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="(field, index) in form.fields"
          :key="field.id || index"
          class="bg-slate-50 border border-slate-200 rounded-xl p-4 transition-all hover:border-slate-300 hover:shadow-xs"
        >
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <!-- Left: Order & Meta -->
            <div class="flex items-center gap-3">
              <div class="flex flex-col gap-1">
                <button
                  type="button"
                  :disabled="index === 0"
                  @click="moveField(index, 'up')"
                  class="p-1 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer disabled:cursor-not-allowed rounded hover:bg-slate-200 transition-colors"
                  title="Move Up"
                >
                  <ArrowUp class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  :disabled="index === form.fields.length - 1"
                  @click="moveField(index, 'down')"
                  class="p-1 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer disabled:cursor-not-allowed rounded hover:bg-slate-200 transition-colors"
                  title="Move Down"
                >
                  <ArrowDown class="w-3.5 h-3.5" />
                </button>
              </div>

              <div>
                <div class="flex items-center gap-2">
                  <UiBadge variant="neutral">Order {{ index + 1 }}</UiBadge>
                  <UiBadge variant="info">{{ field.type }}</UiBadge>
                  <span v-if="field.id" class="text-xs text-slate-400 font-mono">ID: {{ field.id }}</span>
                </div>
              </div>
            </div>

            <!-- Middle: Editable Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
              <UiInput v-model="field.name" label="Label / Title" placeholder="Field Label" />
              <UiInput v-model="field.field_key" label="Field Key" placeholder="field_key" />
              <UiInput v-model="field.placeholder" label="Placeholder" placeholder="Optional placeholder" />
            </div>

            <!-- Right: Controls & Actions -->
            <div class="flex items-center justify-end gap-3 pt-2 md:pt-0 border-t md:border-t-0 border-slate-200">
              <UiSwitch v-model="field.required" label="Required" />
              <button
                type="button"
                @click="removeField(index)"
                class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors cursor-pointer"
                title="Delete Field"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </UiCard>

    <!-- Add Field Modal Dialog -->
    <UiDialog
      v-model:open="isAddModalOpen"
      title="Add New Form Field"
      description="Select the input type and label for your new field"
    >
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
            Field Type
          </label>
          <select
            v-model="newFieldType"
            class="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all"
          >
            <option v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">
              {{ ft.label }} ({{ ft.value }})
            </option>
          </select>
        </div>

        <UiInput
          v-model="newFieldLabel"
          label="Field Label"
          placeholder="e.g. Council Name, Operating Years..."
          required
        />
      </div>

      <template #footer>
        <UiButton variant="outline" @click="isAddModalOpen = false">Cancel</UiButton>
        <UiButton variant="primary" :disabled="!newFieldLabel.trim()" @click="handleAddField">
          Add Field to Form
        </UiButton>
      </template>
    </UiDialog>
  </div>
</template>
