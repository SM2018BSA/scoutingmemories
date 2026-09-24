<script setup lang="ts">
import { ref, computed } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiBadge from '../ui/UiBadge.vue';
import { Search, Edit3, Plus, Copy, Check, Eye } from 'lucide-vue-next';

interface ViewItem {
  id: number;
  title: string;
  slug: string;
  form_id: number;
  form_name: string;
  show_count: string;
}

const props = defineProps<{
  views: ViewItem[];
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: 'select-view', viewId: number): void;
  (e: 'create-view'): void;
}>();

const searchQuery = ref('');
const copiedId = ref<number | null>(null);

const filteredViews = computed(() => {
  if (!searchQuery.value.trim()) return props.views;
  const q = searchQuery.value.toLowerCase();
  return props.views.filter(v =>
    v.title.toLowerCase().includes(q) ||
    v.slug.toLowerCase().includes(q) ||
    (v.form_name && v.form_name.toLowerCase().includes(q)) ||
    String(v.id).includes(q)
  );
});

function copyShortcode(id: number) {
  const code = `[sm_view id="${id}"]`;
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
          placeholder="Search views by title, form, or ID..."
          class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all"
        />
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-slate-500">
          Showing {{ filteredViews.length }} of {{ views.length }} views
        </span>
        <UiButton variant="primary" size="sm" @click="emit('create-view')">
          <Plus class="w-4 h-4" />
          <span>Create New View</span>
        </UiButton>
      </div>
    </div>

    <!-- Views Data Table -->
    <UiCard>
      <div v-if="loading" class="text-center py-12 text-slate-400">
        <div class="inline-block w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin mb-2" />
        <p class="text-sm">Loading views and templates from database...</p>
      </div>

      <div v-else-if="filteredViews.length === 0" class="text-center py-12 text-slate-400">
        <p class="text-sm">No views found matching your query.</p>
      </div>

      <div v-else class="overflow-x-auto -mx-6 -my-6">
        <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
          <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider">
            <tr>
              <th class="px-6 py-3.5">ID</th>
              <th class="px-6 py-3.5">View Title</th>
              <th class="px-6 py-3.5">Connected Form</th>
              <th class="px-6 py-3.5">Shortcode</th>
              <th class="px-6 py-3.5 text-center">Format</th>
              <th class="px-6 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr
              v-for="view in filteredViews"
              :key="view.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="px-6 py-3.5 font-mono text-xs text-slate-500 font-semibold">
                #{{ view.id }}
              </td>
              <td class="px-6 py-3.5 font-bold text-slate-900">
                {{ view.title }}
              </td>
              <td class="px-6 py-3.5 text-slate-700">
                <span v-if="view.form_name" class="font-medium">{{ view.form_name }}</span>
                <span class="text-xs text-slate-400 font-mono ml-1.5">(Form #{{ view.form_id }})</span>
              </td>
              <td class="px-6 py-3.5">
                <button
                  type="button"
                  @click="copyShortcode(view.id)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-mono text-xs font-semibold transition-all cursor-pointer"
                  title="Click to copy shortcode"
                >
                  <component :is="copiedId === view.id ? Check : Copy" class="w-3.5 h-3.5 text-blue-600" />
                  <span>[sm_view id="{{ view.id }}"]</span>
                </button>
              </td>
              <td class="px-6 py-3.5 text-center">
                <UiBadge :variant="view.show_count === 'all' ? 'info' : 'warning'">
                  {{ view.show_count }}
                </UiBadge>
              </td>
              <td class="px-6 py-3.5 text-right">
                <UiButton
                  variant="primary"
                  size="sm"
                  @click="emit('select-view', view.id)"
                  title="Edit view template and query filters"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                  <span>Edit View</span>
                </UiButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UiCard>
  </div>
</template>
