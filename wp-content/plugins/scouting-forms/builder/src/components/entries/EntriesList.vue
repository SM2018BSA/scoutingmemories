<script setup lang="ts">
import { ref, watch } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import { Filter, Edit3, Trash2, Plus, ChevronLeft, ChevronRight, Search, Download, ArrowDownUp } from 'lucide-vue-next';

interface FormSummary {
  id: number;
  name: string;
  entry_count: number;
  parent_form_id?: number | string;
}

interface EntryRow {
  id: number;
  item_key: string;
  name: string;
  created_at: string;
  user?: string;
  columns?: Record<string, string>;
}

const props = defineProps<{
  forms: FormSummary[];
  selectedFormId: number;
  entries: EntryRow[];
  columns: { id: number; name: string }[];
  total: number;
  page: number;
  pages: number;
  loading: boolean;
  search: string;
  order: 'asc' | 'desc';
  exportUrl: string;
  canCreate: boolean;
  canEdit: boolean;
  canDelete: boolean;
}>();

const emit = defineEmits<{
  (e: 'change-form', formId: number): void;
  (e: 'change-page', page: number): void;
  (e: 'search', term: string): void;
  (e: 'toggle-order'): void;
  (e: 'edit-entry', entryId: number): void;
  (e: 'create-entry'): void;
  (e: 'delete-entry', entryId: number): void;
}>();

const activeForm = ref<number>(props.selectedFormId);
const term = ref(props.search);
let timer: number | undefined;

watch(() => props.selectedFormId, (id) => { activeForm.value = id; });
watch(() => props.search, (value) => { term.value = value; });

function onSearchInput() {
  window.clearTimeout(timer);
  timer = window.setTimeout(() => emit('search', term.value.trim()), 350);
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1">
        <label for="sm-entries-form" class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5 whitespace-nowrap">
          <Filter class="w-3.5 h-3.5 text-emerald-700" aria-hidden="true" />
          <span>Form</span>
        </label>
        <select
          id="sm-entries-form"
          v-model="activeForm"
          @change="emit('change-form', activeForm)"
          class="w-full sm:max-w-xs px-3.5 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 font-medium"
        >
          <option v-for="f in forms.filter((x) => !Number(x.parent_form_id))" :key="f.id" :value="f.id">
            {{ f.name }} ({{ Number(f.entry_count).toLocaleString() }})
          </option>
        </select>

        <div class="relative w-full sm:max-w-xs">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" aria-hidden="true" />
          <label for="sm-entries-search" class="sr-only">Search entries</label>
          <input
            id="sm-entries-search"
            v-model="term"
            type="search"
            placeholder="Search entries"
            class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20"
            @input="onSearchInput"
          />
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-slate-500" aria-live="polite">{{ total.toLocaleString() }} entries</span>
        <UiButton variant="outline" size="sm" :title="order === 'desc' ? 'Showing newest first' : 'Showing oldest first'" @click="emit('toggle-order')">
          <ArrowDownUp class="w-4 h-4" aria-hidden="true" />
          <span>{{ order === 'desc' ? 'Newest first' : 'Oldest first' }}</span>
        </UiButton>
        <a
          :href="exportUrl"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50"
          title="Download all entries of this form as a spreadsheet (CSV)"
        >
          <Download class="w-4 h-4" aria-hidden="true" />
          <span>Download CSV</span>
        </a>
        <UiButton v-if="canCreate" variant="scout" size="sm" @click="emit('create-entry')">
          <Plus class="w-4 h-4" aria-hidden="true" />
          <span>Add entry</span>
        </UiButton>
      </div>
    </div>

    <UiCard>
      <div v-if="loading" class="text-center py-12 text-slate-400" role="status">
        <div class="inline-block w-8 h-8 border-3 border-emerald-700 border-t-transparent rounded-full animate-spin mb-2" aria-hidden="true" />
        <p class="text-sm">Loading entries…</p>
      </div>

      <div v-else-if="entries.length === 0" class="text-center py-12 text-slate-500">
        <p class="text-sm">{{ search ? 'No entries match your search.' : 'No entries for this form yet.' }}</p>
      </div>

      <div v-else class="overflow-x-auto -mx-6 -my-6">
        <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
          <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider">
            <tr>
              <th scope="col" class="px-4 py-3">ID</th>
              <th v-for="column in columns" :key="column.id" scope="col" class="px-4 py-3">{{ column.name }}</th>
              <th scope="col" class="px-4 py-3">Added by</th>
              <th scope="col" class="px-4 py-3">Created</th>
              <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="entry in entries" :key="entry.id" class="hover:bg-slate-50/80">
              <td class="px-4 py-3 font-mono text-xs text-slate-500">#{{ entry.id }}</td>
              <td v-for="column in columns" :key="column.id" class="px-4 py-3 text-slate-900 max-w-xs truncate" :title="entry.columns?.[String(column.id)] || ''">
                {{ entry.columns?.[String(column.id)] || '—' }}
              </td>
              <td class="px-4 py-3 text-xs text-slate-600">{{ entry.user || '—' }}</td>
              <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ entry.created_at }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap space-x-1.5">
                <UiButton v-if="canEdit" variant="outline" size="sm" :title="`Edit entry #${entry.id}`" @click="emit('edit-entry', entry.id)">
                  <Edit3 class="w-3.5 h-3.5" aria-hidden="true" />
                  <span>Edit</span>
                </UiButton>
                <button
                  v-if="canDelete"
                  type="button"
                  class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-semibold text-slate-600 rounded-lg hover:text-red-700 hover:bg-red-50 cursor-pointer"
                  :title="`Delete entry #${entry.id}`"
                  @click="emit('delete-entry', entry.id)"
                >
                  <Trash2 class="w-4 h-4" aria-hidden="true" />
                  <span>Delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <template v-if="pages > 1" #footer>
        <nav class="flex items-center justify-between w-full" aria-label="Entry pages">
          <span class="text-xs text-slate-500">Page {{ page }} of {{ pages }}</span>
          <div class="flex items-center gap-1.5">
            <UiButton variant="outline" size="sm" :disabled="page <= 1" @click="emit('change-page', page - 1)">
              <ChevronLeft class="w-4 h-4" aria-hidden="true" />
              <span>Previous</span>
            </UiButton>
            <UiButton variant="outline" size="sm" :disabled="page >= pages" @click="emit('change-page', page + 1)">
              <span>Next</span>
              <ChevronRight class="w-4 h-4" aria-hidden="true" />
            </UiButton>
          </div>
        </nav>
      </template>
    </UiCard>
  </div>
</template>
