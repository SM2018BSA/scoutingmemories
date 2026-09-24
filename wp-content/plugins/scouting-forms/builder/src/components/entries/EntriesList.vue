<script setup lang="ts">
import { ref, watch } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiBadge from '../ui/UiBadge.vue';
import { Filter, Edit3, Trash2, Plus, ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface FormSummary {
  id: number;
  name: string;
  entry_count: number;
}

interface EntryRow {
  id: number;
  item_key: string;
  name: string;
  created_at: string;
}

const props = defineProps<{
  forms: FormSummary[];
  selectedFormId: number;
  entries: EntryRow[];
  total: number;
  page: number;
  pages: number;
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: 'change-form', formId: number): void;
  (e: 'change-page', page: number): void;
  (e: 'edit-entry', entryId: number): void;
  (e: 'create-entry'): void;
  (e: 'delete-entry', entryId: number): void;
}>();

const activeForm = ref<number>(props.selectedFormId || (props.forms[0]?.id || 8));

watch(() => props.selectedFormId, (newId) => {
  if (newId) activeForm.value = newId;
});

function onSelectForm() {
  emit('change-form', activeForm.value);
}
</script>

<template>
  <div class="space-y-4">
    <!-- Filter Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center gap-3 flex-1 max-w-md">
        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5 whitespace-nowrap">
          <Filter class="w-3.5 h-3.5 text-blue-600" />
          <span>Filter by Form:</span>
        </label>
        <select
          v-model="activeForm"
          @change="onSelectForm"
          class="w-full px-3.5 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-900 shadow-2xs focus:bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all font-medium"
        >
          <option v-for="f in forms" :key="f.id" :value="f.id">
            {{ f.name }} ({{ f.entry_count }} entries)
          </option>
        </select>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-slate-500">
          Total: {{ total.toLocaleString() }} entries
        </span>
        <UiButton variant="primary" size="sm" @click="emit('create-entry')">
          <Plus class="w-4 h-4" />
          <span>Add New Entry</span>
        </UiButton>
      </div>
    </div>

    <!-- Entries Data Table -->
    <UiCard>
      <div v-if="loading" class="text-center py-12 text-slate-400">
        <div class="inline-block w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin mb-2" />
        <p class="text-sm">Loading entries...</p>
      </div>

      <div v-else-if="entries.length === 0" class="text-center py-12 text-slate-400">
        <p class="text-sm">No entries found for this form.</p>
      </div>

      <div v-else class="overflow-x-auto -mx-6 -my-6">
        <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
          <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider">
            <tr>
              <th class="px-6 py-3.5">ID</th>
              <th class="px-6 py-3.5">Title / Summary</th>
              <th class="px-6 py-3.5">Item Key</th>
              <th class="px-6 py-3.5">Created Date</th>
              <th class="px-6 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr
              v-for="entry in entries"
              :key="entry.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="px-6 py-3.5 font-mono text-xs text-slate-500 font-semibold">
                #{{ entry.id }}
              </td>
              <td class="px-6 py-3.5 font-bold text-slate-900">
                {{ entry.name || '—' }}
              </td>
              <td class="px-6 py-3.5 font-mono text-xs text-slate-500">
                {{ entry.item_key }}
              </td>
              <td class="px-6 py-3.5 text-xs text-slate-500">
                {{ entry.created_at }}
              </td>
              <td class="px-6 py-3.5 text-right space-x-2">
                <UiButton
                  variant="outline"
                  size="sm"
                  @click="emit('edit-entry', entry.id)"
                  title="Edit entry fields"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                  <span>Edit Entry</span>
                </UiButton>
                <button
                  type="button"
                  @click="emit('delete-entry', entry.id)"
                  class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors cursor-pointer"
                  title="Delete Entry"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <template v-if="pages > 1" #footer>
        <div class="flex items-center justify-between w-full">
          <span class="text-xs text-slate-500">
            Page {{ page }} of {{ pages }}
          </span>
          <div class="flex items-center gap-1.5">
            <UiButton
              variant="outline"
              size="sm"
              :disabled="page <= 1"
              @click="emit('change-page', page - 1)"
            >
              <ChevronLeft class="w-4 h-4" />
              <span>Prev</span>
            </UiButton>
            <UiButton
              variant="outline"
              size="sm"
              :disabled="page >= pages"
              @click="emit('change-page', page + 1)"
            >
              <span>Next</span>
              <ChevronRight class="w-4 h-4" />
            </UiButton>
          </div>
        </div>
      </template>
    </UiCard>
  </div>
</template>
