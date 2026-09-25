<script setup lang="ts">
import { ref } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiBadge from '../ui/UiBadge.vue';
import { ArrowLeft, Save, Tag, Plus, Trash2 } from 'lucide-vue-next';

/**
 * Edits a view (frm_display post): templates, display format, paging, limit, filters and sort
 * order. Filters and sorting accept the view form's fields and the entry's own columns; the
 * server drops anything else.
 */
interface FormField { id: number; name: string; field_key: string; type: string }

const props = defineProps<{
  view: any;
  availableFields: FormField[];
  saving: boolean;
}>();

const emit = defineEmits<{
  (e: 'back'): void;
  (e: 'save', view: any): void;
}>();

const columns = [
  { id: 'id', name: 'Entry ID' },
  { id: 'created_at', name: 'Entry created' },
  { id: 'updated_at', name: 'Entry updated' },
  { id: 'user_id', name: 'Entry owner (user ID)' },
  { id: 'item_key', name: 'Entry key' },
  { id: 'post_id', name: 'Linked post ID' },
  { id: 'is_draft', name: 'Is a draft' }
];
const filterOps: Record<string, string> = {
  '=': 'is', '!=': 'is not', '>': 'greater than', '<': 'less than', '>=': 'at least', '<=': 'at most',
  'LIKE': 'contains', 'not LIKE': 'does not contain', 'LIKE%': 'starts with', '%LIKE': 'ends with',
  'group_by': 'unique (first entry)', 'group_by_newest': 'unique (newest entry)'
};

const filters = ref((props.view.where || []).map((w: string, i: number) => ({
  field: String(w), op: String(props.view.where_is?.[i] ?? '='), value: String(props.view.where_val?.[i] ?? '')
})));
const sorts = ref((props.view.order_by || []).map((o: string, i: number) => ({
  field: String(o), dir: String(props.view.order?.[i] ?? 'ASC').toUpperCase()
})));

const rowEditorRef = ref<HTMLTextAreaElement | null>(null);
const detailEditorRef = ref<HTMLTextAreaElement | null>(null);
const lastEditor = ref<'content' | 'detail'>('content');

function insertTag(tag: string) {
  const key = lastEditor.value;
  const el = key === 'detail' ? detailEditorRef.value : rowEditorRef.value;
  const insertion = `[${tag}]`;
  const text = String(props.view[key] || '');
  if (!el) {
    props.view[key] = text + insertion;
    return;
  }
  const start = el.selectionStart;
  props.view[key] = text.substring(0, start) + insertion + text.substring(el.selectionEnd);
  setTimeout(() => {
    el.focus();
    el.selectionStart = el.selectionEnd = start + insertion.length;
  }, 30);
}

function save() {
  const f = filters.value.filter((r: any) => r.field !== '');
  props.view.where = f.map((r: any) => r.field);
  props.view.where_is = f.map((r: any) => r.op);
  props.view.where_val = f.map((r: any) => r.value);
  const s = sorts.value.filter((r: any) => r.field !== '');
  props.view.order_by = s.map((r: any) => r.field);
  props.view.order = s.map((r: any) => r.dir);
  emit('save', props.view);
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center gap-3">
        <UiButton variant="outline" size="sm" @click="emit('back')">
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Views</span>
        </UiButton>
        <div>
          <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span>{{ view.title }}</span>
            <UiBadge variant="info">View #{{ view.id }}</UiBadge>
          </h2>
          <p class="text-xs text-slate-500 font-mono mt-0.5">[display-frm-data id={{ view.id }}] or [sm_view id={{ view.id }}]</p>
        </div>
      </div>
      <UiButton variant="scout" :disabled="saving" @click="save">
        <Save class="w-4 h-4" />
        <span>{{ saving ? 'Saving…' : 'Save view' }}</span>
      </UiButton>
    </div>

    <UiCard title="Settings">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <UiInput v-model="view.title" label="View title" required />
        <div>
          <label class="sm-builder-label">Show</label>
          <select v-model="view.show_count" class="sm-builder-input">
            <option value="all">All entries (a list)</option>
            <option value="one">One entry</option>
            <option value="dynamic">List with a detail page per entry</option>
            <option value="calendar">Calendar</option>
          </select>
        </div>
        <UiInput v-model="view.page_size" type="number" label="Entries per page" placeholder="(no paging)" />
        <UiInput v-model="view.limit" type="number" label="At most (limit)" placeholder="(no limit)" />
      </div>
    </UiCard>

    <UiCard title="Filters" subtitle="Only entries matching every filter are shown. Values may use [get param=name] (from the address) or current_user.">
      <div class="space-y-2">
        <div v-for="(row, i) in filters" :key="i" class="flex flex-wrap items-center gap-2">
          <select v-model="row.field" class="sm-builder-input !w-auto min-w-48 flex-1" aria-label="Filter field">
            <option value="">Choose…</option>
            <optgroup label="Fields">
              <option v-for="f in availableFields" :key="f.id" :value="String(f.id)">{{ f.name || '(no label)' }} (#{{ f.id }})</option>
            </optgroup>
            <optgroup label="Entry">
              <option v-for="c in columns" :key="c.id" :value="c.id">{{ c.name }}</option>
            </optgroup>
          </select>
          <select v-model="row.op" class="sm-builder-input !w-auto" aria-label="Operator">
            <option v-for="(label, op) in filterOps" :key="op" :value="op">{{ label }}</option>
          </select>
          <input v-if="!String(row.op).startsWith('group_by')" v-model="row.value" class="sm-builder-input !w-auto min-w-40 flex-1" placeholder="Value (blank = empty)" aria-label="Filter value" />
          <button type="button" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer" title="Remove filter" @click="filters.splice(i, 1)"><Trash2 class="w-4 h-4" /></button>
        </div>
        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900 cursor-pointer" @click="filters.push({ field: '', op: '=', value: '' })">
          <Plus class="w-3.5 h-3.5" /> Add a filter
        </button>
      </div>
    </UiCard>

    <UiCard title="Sort order" subtitle="Entries are sorted by these, in order; then oldest first.">
      <div class="space-y-2">
        <div v-for="(row, i) in sorts" :key="i" class="flex flex-wrap items-center gap-2">
          <select v-model="row.field" class="sm-builder-input !w-auto min-w-48 flex-1" aria-label="Sort by">
            <option value="">Choose…</option>
            <optgroup label="Fields">
              <option v-for="f in availableFields" :key="f.id" :value="String(f.id)">{{ f.name || '(no label)' }} (#{{ f.id }})</option>
            </optgroup>
            <optgroup label="Entry">
              <option v-for="c in columns" :key="c.id" :value="c.id">{{ c.name }}</option>
              <option value="rand">Random</option>
            </optgroup>
          </select>
          <select v-model="row.dir" class="sm-builder-input !w-auto" aria-label="Direction">
            <option value="ASC">A→Z / oldest / lowest first</option>
            <option value="DESC">Z→A / newest / highest first</option>
          </select>
          <button type="button" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer" title="Remove" @click="sorts.splice(i, 1)"><Trash2 class="w-4 h-4" /></button>
        </div>
        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900 cursor-pointer" @click="sorts.push({ field: '', dir: 'ASC' })">
          <Plus class="w-3.5 h-3.5" /> Add a sort
        </button>
      </div>
    </UiCard>

    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-4">
      <div class="flex items-center gap-2 mb-2 text-xs font-bold text-blue-900 uppercase tracking-wider">
        <Tag class="w-3.5 h-3.5 text-blue-600" />
        <span>Click a tag to insert it into the {{ lastEditor === 'detail' ? 'detail page' : 'row' }} template</span>
      </div>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="t in ['id', 'key', 'created-at', 'updated-at', 'editlink', 'detaillink', 'deletelink']"
          :key="t"
          type="button"
          class="px-2.5 py-1 bg-white hover:bg-blue-600 hover:text-white text-blue-800 border border-blue-200 rounded-md font-mono text-xs font-semibold cursor-pointer"
          @click="insertTag(t)"
        >[{{ t }}]</button>
        <button
          v-for="f in availableFields"
          :key="f.id"
          type="button"
          class="px-2.5 py-1 bg-white hover:bg-[#025600] hover:text-white text-slate-800 border border-slate-200 rounded-md font-mono text-xs cursor-pointer"
          :title="`${f.name} (${f.type}, key ${f.field_key})`"
          @click="insertTag(String(f.id))"
        >[{{ f.id }}] {{ f.name }}</button>
      </div>
    </div>

    <UiCard title="Before the entries" subtitle="HTML shown once above the entries (e.g. a table's opening tags)">
      <textarea v-model="view.before_content" rows="3" class="w-full font-mono text-xs p-3.5 bg-slate-900 text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </UiCard>

    <UiCard title="Each entry" subtitle="HTML repeated for every entry, with [id] / [123] tags and [if 123]…[/if 123]">
      <textarea ref="rowEditorRef" v-model="view.content" rows="8" class="w-full font-mono text-xs p-3.5 bg-slate-900 text-emerald-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @focus="lastEditor = 'content'" />
    </UiCard>

    <UiCard v-if="view.show_count === 'dynamic'" title="Detail page" subtitle="Shown instead of the list when one entry is opened ([detaillink])">
      <textarea ref="detailEditorRef" v-model="view.detail" rows="8" class="w-full font-mono text-xs p-3.5 bg-slate-900 text-emerald-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @focus="lastEditor = 'detail'" />
    </UiCard>

    <UiCard title="After the entries" subtitle="HTML shown once below the entries (e.g. closing tags)">
      <textarea v-model="view.after_content" rows="3" class="w-full font-mono text-xs p-3.5 bg-slate-900 text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </UiCard>

    <UiCard title="When nothing matches" subtitle="Text or HTML shown when no entries match">
      <UiInput v-model="view.empty_msg" placeholder="No Entries Found" />
    </UiCard>
  </div>
</template>
