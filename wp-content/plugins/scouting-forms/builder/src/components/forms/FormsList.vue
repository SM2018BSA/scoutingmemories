<script setup lang="ts">
import { ref, computed } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiBadge from '../ui/UiBadge.vue';
import { Search, Edit3, ListOrdered, Copy, Check } from 'lucide-vue-next';

interface FormItem {
  id: number;
  form_key: string;
  name: string;
  description: string;
  status: string;
  field_count: number;
  entry_count: number;
}

const props = defineProps<{
  forms: FormItem[];
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: 'select-form', formId: number): void;
  (e: 'view-entries', formId: number): void;
}>();

const searchQuery = ref('');
const copiedId = ref<number | null>(null);

const filteredForms = computed(() => {
  if (!searchQuery.value.trim()) return props.forms;
  const q = searchQuery.value.toLowerCase();
  return props.forms.filter(f =>
    f.name.toLowerCase().includes(q) ||
    f.form_key.toLowerCase().includes(q) ||
    String(f.id).includes(q)
  );
});

function copyShortcode(id: number) {
  const code = `[sm_form id="${id}"]`;
  navigator.clipboard.writeText(code);
  copiedId.value = id;
  setTimeout(() => {
    copiedId.value = null;
  }, 2000);
}
</script>

<template>
  <div class="space-y-4">
    <!-- Header & Search Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search forms by name, key, or ID..."
          class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all"
        />
      </div>

      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <span>Showing {{ filteredForms.length }} of {{ forms.length }} forms</span>
      </div>
    </div>

    <!-- Forms Data Table -->
    <UiCard>
      <div v-if="loading" class="text-center py-12 text-slate-400">
        <div class="inline-block w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin mb-2" />
        <p class="text-sm">Loading historical forms from database...</p>
      </div>

      <div v-else-if="filteredForms.length === 0" class="text-center py-12 text-slate-400">
        <p class="text-sm">No forms found matching your query.</p>
      </div>

      <div v-else class="overflow-x-auto -mx-6 -my-6">
        <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
          <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider">
            <tr>
              <th class="px-6 py-3.5">ID</th>
              <th class="px-6 py-3.5">Form Title</th>
              <th class="px-6 py-3.5">Form Key</th>
              <th class="px-6 py-3.5">Shortcode</th>
              <th class="px-6 py-3.5 text-center">Fields</th>
              <th class="px-6 py-3.5 text-center">Entries</th>
              <th class="px-6 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr
              v-for="form in filteredForms"
              :key="form.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="px-6 py-3.5 font-mono text-xs text-slate-500 font-semibold">
                #{{ form.id }}
              </td>
              <td class="px-6 py-3.5 font-bold text-slate-900">
                {{ form.name }}
              </td>
              <td class="px-6 py-3.5 font-mono text-xs text-slate-600">
                <code>{{ form.form_key }}</code>
              </td>
              <td class="px-6 py-3.5">
                <button
                  type="button"
                  @click="copyShortcode(form.id)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-mono text-xs font-semibold transition-all cursor-pointer"
                  title="Click to copy shortcode"
                >
                  <component :is="copiedId === form.id ? Check : Copy" class="w-3.5 h-3.5 text-blue-600" />
                  <span>[sm_form id="{{ form.id }}"]</span>
                </button>
              </td>
              <td class="px-6 py-3.5 text-center">
                <UiBadge variant="info">{{ form.field_count }} fields</UiBadge>
              </td>
              <td class="px-6 py-3.5 text-center">
                <UiBadge variant="neutral">{{ form.entry_count }} entries</UiBadge>
              </td>
              <td class="px-6 py-3.5 text-right space-x-2">
                <UiButton
                  variant="outline"
                  size="sm"
                  @click="emit('view-entries', form.id)"
                  title="View entries for this form"
                >
                  <ListOrdered class="w-3.5 h-3.5" />
                  <span>Entries</span>
                </UiButton>
                <UiButton
                  variant="primary"
                  size="sm"
                  @click="emit('select-form', form.id)"
                  title="Edit form settings and fields"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                  <span>Edit Fields</span>
                </UiButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UiCard>
  </div>
</template>
