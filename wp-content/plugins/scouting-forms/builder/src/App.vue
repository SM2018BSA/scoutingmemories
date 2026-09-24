<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  TabsRoot,
  TabsList,
  TabsTrigger,
  TabsContent
} from 'reka-ui';
import {
  FileText,
  Eye,
  ListOrdered,
  BookOpen,
  CheckCircle2,
  AlertCircle
} from 'lucide-vue-next';

// Components
import FormsList from './components/forms/FormsList.vue';
import FormEditor from './components/forms/FormEditor.vue';
import ViewsList from './components/views/ViewsList.vue';
import ViewEditor from './components/views/ViewEditor.vue';
import EntriesList from './components/entries/EntriesList.vue';
import EntryEditorModal from './components/entries/EntryEditorModal.vue';

// REST Config from WordPress wp_localize_script
declare global {
  interface Window {
    smBuilderConfig?: {
      restUrl: string;
      nonce: string;
      adminUrl: string;
      activeTheme: string;
    };
  }
}

const restBase = window.smBuilderConfig?.restUrl || '/wp-json/scouting-forms/v1';
const nonce = window.smBuilderConfig?.nonce || '';

// Global State
const currentTab = ref('forms');
const toastMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

function showToast(text: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = { text, type };
  setTimeout(() => {
    toastMessage.value = null;
  }, 4000);
}

// REST Helper
async function apiFetch(endpoint: string, options: RequestInit = {}) {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce,
    ...(options.headers as Record<string, string> || {})
  };

  const response = await fetch(`${restBase}${endpoint}`, {
    ...options,
    headers
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(err.message || 'API error');
  }

  return response.json();
}

// ----------------------------------------------------
// FORMS STATE
// ----------------------------------------------------
const forms = ref<any[]>([]);
const formsLoading = ref(false);
const activeFormId = ref<number | null>(null);
const activeFormData = ref<any | null>(null);
const formSaving = ref(false);

async function loadForms() {
  formsLoading.value = true;
  try {
    const res = await apiFetch('/forms');
    forms.value = res.forms || [];
  } catch (err: any) {
    showToast(`Error loading forms: ${err.message}`, 'error');
  } finally {
    formsLoading.value = false;
  }
}

async function selectForm(formId: number) {
  try {
    formsLoading.value = true;
    const res = await apiFetch(`/forms/${formId}`);
    activeFormData.value = {
      ...res.form,
      fields: res.fields || []
    };
    activeFormId.value = formId;
  } catch (err: any) {
    showToast(`Failed to load form #${formId}: ${err.message}`, 'error');
  } finally {
    formsLoading.value = false;
  }
}

async function saveForm(payload: { form: any; fields: any[] }) {
  formSaving.value = true;
  try {
    // 1. Update form settings
    await apiFetch(`/forms/${payload.form.id}`, {
      method: 'POST',
      body: JSON.stringify({
        name: payload.form.name,
        form_key: payload.form.form_key,
        submit_value: payload.form.submit_value,
        success_msg: payload.form.success_msg
      })
    });

    // 2. Save fields
    await apiFetch(`/forms/${payload.form.id}/fields`, {
      method: 'POST',
      body: JSON.stringify({ fields: payload.fields })
    });

    showToast('Form and fields saved successfully!');
    await loadForms();
  } catch (err: any) {
    showToast(`Error saving form: ${err.message}`, 'error');
  } finally {
    formSaving.value = false;
  }
}

// ----------------------------------------------------
// VIEWS STATE
// ----------------------------------------------------
const views = ref<any[]>([]);
const viewsLoading = ref(false);
const activeViewId = ref<number | null>(null);
const activeViewData = ref<any | null>(null);
const availableFields = ref<any[]>([]);
const viewSaving = ref(false);

async function loadViews() {
  viewsLoading.value = true;
  try {
    const res = await apiFetch('/views');
    views.value = res.views || [];
  } catch (err: any) {
    showToast(`Error loading views: ${err.message}`, 'error');
  } finally {
    viewsLoading.value = false;
  }
}

async function selectView(viewId: number) {
  viewsLoading.value = true;
  try {
    const res = await apiFetch(`/views/${viewId}`);
    activeViewData.value = res.view;
    availableFields.value = res.available_fields || [];
    activeViewId.value = viewId;
  } catch (err: any) {
    showToast(`Failed to load view #${viewId}: ${err.message}`, 'error');
  } finally {
    viewsLoading.value = false;
  }
}

async function saveView(view: any) {
  viewSaving.value = true;
  try {
    await apiFetch(`/views/${view.id}`, {
      method: 'POST',
      body: JSON.stringify(view)
    });
    showToast('View template updated successfully!');
    await loadViews();
  } catch (err: any) {
    showToast(`Error saving view: ${err.message}`, 'error');
  } finally {
    viewSaving.value = false;
  }
}

async function createNewView() {
  try {
    const res = await apiFetch('/views', {
      method: 'POST',
      body: JSON.stringify({ title: 'New Custom View', form_id: 8 })
    });
    showToast('Created new view!');
    await loadViews();
    if (res.id) selectView(res.id);
  } catch (err: any) {
    showToast(`Error creating view: ${err.message}`, 'error');
  }
}

// ----------------------------------------------------
// ENTRIES STATE
// ----------------------------------------------------
const entries = ref<any[]>([]);
const entriesLoading = ref(false);
const selectedFormIdForEntries = ref(8);
const entriesTotal = ref(0);
const entriesPage = ref(1);
const entriesPages = ref(1);

// Entry Edit Modal
const isEntryModalOpen = ref(false);
const editingEntryId = ref<number | null>(null);
const editingEntryFields = ref<any[]>([]);
const editingEntryMetas = ref<Record<number, any>>({});
const entrySaving = ref(false);

async function loadEntries(formId: number, page: number = 1) {
  selectedFormIdForEntries.value = formId;
  entriesPage.value = page;
  entriesLoading.value = true;

  try {
    const res = await apiFetch(`/entries?form_id=${formId}&page=${page}&limit=25`);
    entries.value = res.entries || [];
    entriesTotal.value = res.total || 0;
    entriesPages.value = res.pages || 1;
  } catch (err: any) {
    showToast(`Error loading entries: ${err.message}`, 'error');
  } finally {
    entriesLoading.value = false;
  }
}

async function openEditEntry(entryId: number) {
  editingEntryId.value = entryId;
  entrySaving.value = false;
  try {
    const res = await apiFetch(`/entries/${entryId}`);
    editingEntryFields.value = res.fields || [];
    editingEntryMetas.value = res.metas || {};
    isEntryModalOpen.value = true;
  } catch (err: any) {
    showToast(`Failed to load entry #${entryId}: ${err.message}`, 'error');
  }
}

function openCreateEntry() {
  editingEntryId.value = null;
  editingEntryMetas.value = {};
  // Find fields from active form
  const currentForm = forms.value.find(f => f.id === selectedFormIdForEntries.value);
  if (currentForm) {
    apiFetch(`/forms/${currentForm.id}`).then(res => {
      editingEntryFields.value = res.fields || [];
      isEntryModalOpen.value = true;
    });
  } else {
    isEntryModalOpen.value = true;
  }
}

async function saveEntryData(payload: { entryId: number | null; formId: number; metas: Record<number, any> }) {
  entrySaving.value = true;
  try {
    if (payload.entryId) {
      await apiFetch(`/entries/${payload.entryId}`, {
        method: 'POST',
        body: JSON.stringify({ metas: payload.metas })
      });
      showToast(`Entry #${payload.entryId} updated!`);
    } else {
      await apiFetch('/entries', {
        method: 'POST',
        body: JSON.stringify({
          form_id: selectedFormIdForEntries.value,
          metas: payload.metas
        })
      });
      showToast('New entry created!');
    }
    isEntryModalOpen.value = false;
    await loadEntries(selectedFormIdForEntries.value, entriesPage.value);
  } catch (err: any) {
    showToast(`Error saving entry: ${err.message}`, 'error');
  } finally {
    entrySaving.value = false;
  }
}

async function deleteEntry(entryId: number) {
  if (!confirm(`Are you sure you want to delete entry #${entryId}?`)) return;
  try {
    await apiFetch(`/entries/${entryId}`, { method: 'DELETE' });
    showToast(`Entry #${entryId} deleted`);
    await loadEntries(selectedFormIdForEntries.value, entriesPage.value);
  } catch (err: any) {
    showToast(`Error deleting entry: ${err.message}`, 'error');
  }
}

function switchToEntriesForForm(formId: number) {
  currentTab.value = 'entries';
  loadEntries(formId, 1);
}

// Initial Mount
onMounted(() => {
  loadForms();
  loadViews();
  loadEntries(8, 1);
});
</script>

<template>
  <div id="sm-builder-app" class="font-sans text-slate-800 max-w-7xl pb-16">

    <!-- Toast Notification Banner -->
    <div
      v-if="toastMessage"
      class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg border transition-all animate-in slide-in-from-bottom-4"
      :class="toastMessage.type === 'success' ? 'bg-emerald-800 text-white border-emerald-700' : 'bg-red-800 text-white border-red-700'"
    >
      <component :is="toastMessage.type === 'success' ? CheckCircle2 : AlertCircle" class="w-5 h-5 flex-shrink-0" />
      <span class="text-sm font-semibold">{{ toastMessage.text }}</span>
    </div>

    <!-- Top Hero Header -->
    <header class="bg-gradient-to-r from-slate-900 via-slate-800 to-[#025600] text-white p-6 rounded-2xl shadow-md mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight flex items-center gap-2.5">
          <span>Scouting Forms & Views Studio</span>
          <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-white/20 text-white border border-white/25">
            Tailwind v4 + Reka UI
          </span>
        </h1>
        <p class="text-xs text-slate-300 mt-1 max-w-2xl">
          Visual Form Builder & View Template Editor. Directly manages your 23 forms, 266 fields, 14 views, and 15,913 entries with zero data loss.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <a
          :href="window.smBuilderConfig?.adminUrl + 'admin.php?page=scouting-archives'"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-white/10 hover:bg-white/20 text-white border border-white/20 transition-all cursor-pointer"
        >
          <BookOpen class="w-4 h-4" />
          <span>User Guide</span>
        </a>
      </div>
    </header>

    <!-- Main Navigation Tabs using Reka UI -->
    <TabsRoot v-model="currentTab" class="w-full">
      <TabsList class="flex items-center gap-1 bg-white p-1.5 rounded-xl border border-slate-200 shadow-2xs mb-6 select-none">
        <TabsTrigger
          value="forms"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 data-[state=active]:bg-blue-600 data-[state=active]:text-white transition-all cursor-pointer"
        >
          <FileText class="w-4 h-4" />
          <span>Forms & Fields ({{ forms.length }})</span>
        </TabsTrigger>

        <TabsTrigger
          value="views"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 data-[state=active]:bg-blue-600 data-[state=active]:text-white transition-all cursor-pointer"
        >
          <Eye class="w-4 h-4" />
          <span>Views & Templates ({{ views.length }})</span>
        </TabsTrigger>

        <TabsTrigger
          value="entries"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 data-[state=active]:bg-blue-600 data-[state=active]:text-white transition-all cursor-pointer"
        >
          <ListOrdered class="w-4 h-4" />
          <span>Entries Manager</span>
        </TabsTrigger>
      </TabsList>

      <!-- TAB 1: FORMS & FIELDS -->
      <TabsContent value="forms" class="focus:outline-none">
        <div v-if="activeFormId && activeFormData">
          <FormEditor
            :form="activeFormData"
            :saving="formSaving"
            @back="activeFormId = null; activeFormData = null"
            @save="saveForm"
          />
        </div>
        <div v-else>
          <FormsList
            :forms="forms"
            :loading="formsLoading"
            @select-form="selectForm"
            @view-entries="switchToEntriesForForm"
          />
        </div>
      </TabsContent>

      <!-- TAB 2: VIEWS & TEMPLATES -->
      <TabsContent value="views" class="focus:outline-none">
        <div v-if="activeViewId && activeViewData">
          <ViewEditor
            :view="activeViewData"
            :available-fields="availableFields"
            :saving="viewSaving"
            @back="activeViewId = null; activeViewData = null"
            @save="saveView"
          />
        </div>
        <div v-else>
          <ViewsList
            :views="views"
            :loading="viewsLoading"
            @select-view="selectView"
            @create-view="createNewView"
          />
        </div>
      </TabsContent>

      <!-- TAB 3: ENTRIES MANAGER -->
      <TabsContent value="entries" class="focus:outline-none">
        <EntriesList
          :forms="forms"
          :selected-form-id="selectedFormIdForEntries"
          :entries="entries"
          :total="entriesTotal"
          :page="entriesPage"
          :pages="entriesPages"
          :loading="entriesLoading"
          @change-form="loadEntries($event, 1)"
          @change-page="loadEntries(selectedFormIdForEntries, $event)"
          @edit-entry="openEditEntry"
          @create-entry="openCreateEntry"
          @delete-entry="deleteEntry"
        />

        <!-- Edit/Create Entry Dialog -->
        <EntryEditorModal
          v-model:open="isEntryModalOpen"
          :entry-id="editingEntryId"
          :form-id="selectedFormIdForEntries"
          :fields="editingEntryFields"
          :metas="editingEntryMetas"
          :saving="entrySaving"
          @save="saveEntryData"
        />
      </TabsContent>
    </TabsRoot>
  </div>
</template>
