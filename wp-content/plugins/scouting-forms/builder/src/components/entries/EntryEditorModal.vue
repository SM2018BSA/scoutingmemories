<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import UiDialog from '../ui/UiDialog.vue';
import UiButton from '../ui/UiButton.vue';
import EntryField, { type EntryFieldDef } from './EntryField.vue';

interface Group {
  title: string;
  note: string;
  fields: EntryFieldDef[];
}

const props = defineProps<{
  open: boolean;
  entryId: number | null;
  formId: number;
  fields: EntryFieldDef[];
  metas: Record<string, any>;
  display?: Record<string, string>;
  errors?: Record<string, string>;
  saving: boolean;
  ajaxUrl?: string;
}>();

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'save', payload: { entryId: number | null; formId: number; metas: Record<string, any> }): void;
}>();

const values = ref<Record<string, any>>({});
const shown = ref<Record<string, string>>({});
const localFields = ref<EntryFieldDef[]>([]);

watch(() => [props.metas, props.fields, props.display], () => {
  values.value = JSON.parse(JSON.stringify(props.metas || {}));
  shown.value = { ...(props.display || {}) };
  localFields.value = JSON.parse(JSON.stringify(props.fields || []));
}, { deep: true, immediate: true });

const skipped = ['end_divider', 'break', 'html', 'captcha', 'submit', 'password', 'summary', 'form'];

// Fields grouped under their section headings; repeating sections are listed, not edited here
const groups = computed<Group[]>(() => {
  const out: Group[] = [{ title: '', note: '', fields: [] }];
  for (const field of localFields.value) {
    if (field.type === 'divider') {
      const opts = field.field_options || {};
      let note = '';
      // Formidable stores "no" as the string "0"
      if (opts.repeat && String(opts.repeat) !== '0') {
        const section = values.value[String(field.id)];
        const rows = section && Array.isArray(section.row_ids) ? section.row_ids.length : 0;
        note = `${rows} repeating ${rows === 1 ? 'row' : 'rows'} (kept as they are; edit them on the site's form).`;
      }
      out.push({ title: field.name, note, fields: [] });
      continue;
    }
    if (skipped.includes(field.type)) continue;
    out[out.length - 1].fields.push(field);
  }
  return out.filter((g) => g.fields.length || g.note);
});

// A Dynamic field that depends on another loads its choices again when that one changes
async function refreshDependents(parentId: number, parentValue: any) {
  if (!props.ajaxUrl) return;
  const list = (Array.isArray(parentValue) ? parentValue : [parentValue]).filter((v) => String(v ?? '') !== '');
  for (const field of localFields.value) {
    const opts = field.field_options || {};
    if (field.type !== 'data' || !Array.isArray(opts.hide_field)) continue;
    const index = opts.hide_field.map(String).indexOf(String(parentId));
    if (index === -1 || (Array.isArray(opts.hide_opt) && String(opts.hide_opt[index] ?? '') !== '')) continue;

    const params = new URLSearchParams({ action: 'sm_forms_dynamic', field: String(field.id), parent: String(parentId) });
    list.forEach((v) => params.append('value[]', String(v)));
    try {
      const res = await fetch(`${props.ajaxUrl}?${params.toString()}`, { credentials: 'same-origin' });
      const json = await res.json();
      if (!json.success) continue;
      if (opts.data_type === 'data') {
        shown.value[String(field.id)] = json.data.text || '';
        values.value[String(field.id)] = json.data.value || '';
      } else {
        const fresh = (json.data.options || []).map((pair: [string, string]) => ({ value: String(pair[0]), label: pair[1] }));
        field.choices = fresh;
        const allowed = new Set(fresh.map((c: { value: string }) => c.value));
        const current = values.value[String(field.id)];
        values.value[String(field.id)] = Array.isArray(current) ? current.filter((v: string) => allowed.has(String(v))) : (allowed.has(String(current)) ? current : '');
      }
    } catch {
      // Leave the choices as they were
    }
  }
}

function update(field: EntryFieldDef, value: any) {
  values.value[String(field.id)] = value;
  if (field.type === 'data') {
    refreshDependents(field.id, value);
  }
}

function handleSave() {
  emit('save', { entryId: props.entryId, formId: props.formId, metas: values.value });
}
</script>

<template>
  <UiDialog
    :open="open"
    @update:open="emit('update:open', $event)"
    :title="entryId ? `Edit entry #${entryId}` : 'Add an entry'"
    :description="entryId ? 'Changes are checked and saved the same way as on the site\'s form.' : 'Fill in the fields; the form\'s rules and emails apply as on the site.'"
    max-width="max-w-3xl"
  >
    <div class="py-2">
      <div v-if="localFields.length === 0" class="text-center py-6 text-slate-400">Loading the form's fields…</div>

      <div v-else class="space-y-6 max-h-[62vh] overflow-y-auto pr-1">
        <div
          v-if="errors && Object.keys(errors).length"
          class="px-3 py-2 text-sm rounded-lg bg-amber-50 border border-amber-200 text-amber-900"
          role="alert"
        >
          Some fields need attention. They are marked below.
        </div>

        <section v-for="(group, index) in groups" :key="index" class="space-y-3">
          <h3 v-if="group.title" class="text-sm font-bold text-slate-900 border-b border-slate-200 pb-1">{{ group.title }}</h3>
          <p v-if="group.note" class="text-xs text-slate-500">{{ group.note }}</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <EntryField
              v-for="field in group.fields"
              :key="field.id"
              :field="field"
              :model-value="values[String(field.id)]"
              :display="shown[String(field.id)]"
              :error="errors?.[String(field.id)]"
              :class="['textarea', 'rte', 'checkbox'].includes(field.type) ? 'sm:col-span-2' : ''"
              @update:model-value="update(field, $event)"
            />
          </div>
        </section>
      </div>
    </div>

    <template #footer>
      <UiButton variant="outline" @click="emit('update:open', false)">Cancel</UiButton>
      <UiButton variant="scout" :disabled="saving" @click="handleSave">
        {{ saving ? 'Saving…' : (entryId ? 'Save changes' : 'Add entry') }}
      </UiButton>
    </template>
  </UiDialog>
</template>
