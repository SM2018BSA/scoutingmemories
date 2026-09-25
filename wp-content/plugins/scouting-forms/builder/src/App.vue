<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
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
      hook?: string;
      can?: Record<string, boolean>;
    };
  }
}

const restBase = window.smBuilderConfig?.restUrl || '/wp-json/scouting-forms/v1';
const nonce = window.smBuilderConfig?.nonce || '';
const adminUrl = window.smBuilderConfig?.adminUrl || '/wp-admin/';
const ajaxUrl = adminUrl + 'admin-ajax.php';

// What this person may do (the REST API checks the same capabilities)
const can = {
  viewForms: true, editForms: true, deleteForms: false, views: true, viewEntries: true,
  createEntries: true, editEntries: true, deleteEntries: true,
  ...(window.smBuilderConfig?.can || {})
};

// Global State: open the tab of the admin page that was clicked
const hook = window.smBuilderConfig?.hook || '';
const currentTab = ref(
  hook.includes('scouting-forms-entries') && can.viewEntries ? 'entries'
    : hook.includes('scouting-forms-views') && can.views ? 'views'
    : can.viewForms ? 'forms' : 'entries'
);
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
    const error: Error & { errors?: Record<string, string> } = new Error(err.message || 'API error');
    error.errors = err.errors || undefined;
    throw error;
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
const formErrors = ref<Record<string, string>>({});
const actionErrors = ref<Record<string, Record<string, string>>>({});
const actionSaving = ref<number | null>(null);
const formEditorRef = ref<any>(null);

// The builder's copy of a form: fields carry a "ref" the server uses to point at problems
function useBuilderData(res: any) {
  activeFormData.value = {
    form: res.form,
    fields: (res.fields || []).map((f: any) => ({ ...f, ref: String(f.id) })),
    actions: res.actions || [],
    pages: res.pages || [],
    operators: res.operators || [],
    new_field_types: res.new_field_types || [],
    can_delete: res.can_delete ?? activeFormData.value?.can_delete ?? can.deleteForms
  };
}

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
    const res = await apiFetch(`/forms/${formId}/builder`);
    formErrors.value = {};
    actionErrors.value = {};
    useBuilderData(res);
    activeFormId.value = formId;
  } catch (err: any) {
    showToast(`Failed to load form #${formId}: ${err.message}`, 'error');
  } finally {
    formsLoading.value = false;
  }
}

async function saveForm(payload: { form: any; fields: any[]; deleted: number[] }) {
  formSaving.value = true;
  formErrors.value = {};
  try {
    // 1. Form settings
    await apiFetch(`/forms/${payload.form.id}`, {
      method: 'POST',
      body: JSON.stringify({
        name: payload.form.name,
        form_key: payload.form.form_key,
        description: payload.form.description,
        submit_value: payload.form.submit_value,
        edit_value: payload.form.edit_value
      })
    });

    // 2. Fields: all checked by the server before anything is written
    const res = await apiFetch(`/forms/${payload.form.id}/fields`, {
      method: 'POST',
      body: JSON.stringify({ fields: payload.fields, deleted: payload.deleted })
    });
    useBuilderData(res);
    formEditorRef.value?.afterSave();
    showToast('Form saved.');
    await loadForms();
  } catch (err: any) {
    formErrors.value = err.errors || { form: err.message };
    showToast(err.errors ? 'Nothing was saved: some fields need attention.' : `Error saving form: ${err.message}`, 'error');
  } finally {
    formSaving.value = false;
  }
}

// Actions keep their open/closed state; only the saved one is replaced
function mergeActions(res: any) {
  if (activeFormData.value) activeFormData.value.actions = res.actions || [];
}

async function saveAction(action: any) {
  actionSaving.value = action.id;
  actionErrors.value = { ...actionErrors.value, [action.id]: {} };
  try {
    const res = await apiFetch(`/actions/${action.id}`, {
      method: 'POST',
      body: JSON.stringify({
        name: action.name, active: action.active, event: action.event,
        settings: action.settings, conditions: action.conditions
      })
    });
    mergeActions(res);
    showToast(`"${action.name}" saved.`);
  } catch (err: any) {
    actionErrors.value = { ...actionErrors.value, [action.id]: err.errors || {} };
    showToast(err.message || 'Error saving the action', 'error');
  } finally {
    actionSaving.value = null;
  }
}

async function createAction(type: string) {
  if (!activeFormId.value) return;
  try {
    const res = await apiFetch(`/forms/${activeFormId.value}/actions`, { method: 'POST', body: JSON.stringify({ type }) });
    mergeActions(res);
    showToast('Action added (inactive until you switch it on and save).');
  } catch (err: any) {
    showToast(`Error adding the action: ${err.message}`, 'error');
  }
}

async function removeAction(action: any) {
  if (!confirm(`Move the action "${action.name}" to the trash?`)) return;
  try {
    const res = await apiFetch(`/actions/${action.id}`, { method: 'DELETE' });
    mergeActions(res);
    showToast('Action moved to the trash.');
  } catch (err: any) {
    showToast(`Error: ${err.message}`, 'error');
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
      body: JSON.stringify({ title: 'New Custom View', form_id: selectedFormIdForEntries.value || forms.value[0]?.id })
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
const selectedFormIdForEntries = ref(0);
const entriesTotal = ref(0);
const entriesPage = ref(1);
const entriesPages = ref(1);
const entriesColumns = ref<{ id: number; name: string }[]>([]);
const entriesSearch = ref('');
const entriesOrder = ref<'asc' | 'desc'>('desc');
const exportUrl = computed(() => `${restBase}/entries/export?form_id=${selectedFormIdForEntries.value}&_wpnonce=${encodeURIComponent(nonce)}`);

// Entry Edit Modal
const isEntryModalOpen = ref(false);
const editingEntryId = ref<number | null>(null);
const editingEntryFields = ref<any[]>([]);
const editingEntryMetas = ref<Record<string, any>>({});
const editingEntryDisplay = ref<Record<string, string>>({});
const editingEntryErrors = ref<Record<string, string>>({});
const entrySaving = ref(false);

async function loadEntries(formId: number, page: number = 1) {
  selectedFormIdForEntries.value = formId;
  entriesPage.value = page;
  entriesLoading.value = true;

  try {
    const params = new URLSearchParams({
      form_id: String(formId), page: String(page), limit: '25',
      search: entriesSearch.value, orderby: 'created_at', order: entriesOrder.value
    });
    const res = await apiFetch(`/entries?${params.toString()}`);
    entries.value = res.entries || [];
    entriesColumns.value = res.columns || [];
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
    editingEntryDisplay.value = res.display || {};
    editingEntryErrors.value = {};
    isEntryModalOpen.value = true;
  } catch (err: any) {
    showToast(`Failed to load entry #${entryId}: ${err.message}`, 'error');
  }
}

function openCreateEntry() {
  editingEntryId.value = null;
  editingEntryMetas.value = {};
  editingEntryDisplay.value = {};
  editingEntryErrors.value = {};
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

async function saveEntryData(payload: { entryId: number | null; formId: number; metas: Record<string, any> }) {
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
    editingEntryErrors.value = {};
    await loadEntries(selectedFormIdForEntries.value, entriesPage.value);
  } catch (err: any) {
    // Field problems stay in the dialog next to the fields
    editingEntryErrors.value = err.errors || {};
    showToast(err.errors ? 'Some fields need attention.' : `Error saving entry: ${err.message}`, 'error');
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

function searchEntries(term: string) {
  entriesSearch.value = term;
  loadEntries(selectedFormIdForEntries.value, 1);
}

function toggleEntriesOrder() {
  entriesOrder.value = entriesOrder.value === 'desc' ? 'asc' : 'desc';
  loadEntries(selectedFormIdForEntries.value, 1);
}

// Initial Mount: the first form that has entries opens in the entries manager
onMounted(async () => {
  await loadForms();
  if (can.views) loadViews();
  if (can.viewEntries) {
    const first = forms.value.find((f) => !Number(f.parent_form_id) && Number(f.entry_count) > 0) || forms.value[0];
    if (first) loadEntries(Number(first.id), 1);
  }
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
          Forms, views and entries of the Scouting Memories site, kept in the same tables Formidable uses.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <a
          :href="adminUrl + 'admin.php?page=scouting-archives'"
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
          v-if="can.viewForms"
          value="forms"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 data-[state=active]:bg-blue-600 data-[state=active]:text-white transition-all cursor-pointer"
        >
          <FileText class="w-4 h-4" />
          <span>Forms & Fields ({{ forms.length }})</span>
        </TabsTrigger>

        <TabsTrigger
          v-if="can.views"
          value="views"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 data-[state=active]:bg-blue-600 data-[state=active]:text-white transition-all cursor-pointer"
        >
          <Eye class="w-4 h-4" />
          <span>Views & Templates ({{ views.length }})</span>
        </TabsTrigger>

        <TabsTrigger
          v-if="can.viewEntries"
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
            ref="formEditorRef"
            :key="activeFormId"
            :builder="activeFormData"
            :saving="formSaving"
            :errors="formErrors"
            :action-errors="actionErrors"
            :action-saving="actionSaving"
            :can-edit="can.editForms"
            @back="activeFormId = null; activeFormData = null"
            @save="saveForm"
            @save-action="saveAction"
            @create-action="createAction"
            @remove-action="removeAction"
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
            :key="activeViewId"
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
          :columns="entriesColumns"
          :total="entriesTotal"
          :page="entriesPage"
          :pages="entriesPages"
          :loading="entriesLoading"
          :search="entriesSearch"
          :order="entriesOrder"
          :export-url="exportUrl"
          :can-create="can.createEntries"
          :can-edit="can.editEntries"
          :can-delete="can.deleteEntries"
          @change-form="entriesSearch = ''; loadEntries($event, 1)"
          @change-page="loadEntries(selectedFormIdForEntries, $event)"
          @search="searchEntries"
          @toggle-order="toggleEntriesOrder"
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
          :display="editingEntryDisplay"
          :errors="editingEntryErrors"
          :saving="entrySaving"
          :ajax-url="ajaxUrl"
          @save="saveEntryData"
        />
      </TabsContent>
    </TabsRoot>
  </div>
</template>
