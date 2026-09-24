<script setup lang="ts">
import { ref } from 'vue';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiBadge from '../ui/UiBadge.vue';
import {
  ArrowLeft,
  Save,
  Tag,
  Code,
  Layers,
  FileText
} from 'lucide-vue-next';

interface FormField {
  id: number;
  name: string;
  field_key: string;
  type: string;
}

interface ViewData {
  id: number;
  title: string;
  slug: string;
  form_id: number;
  show_count: string;
  content: string;
  before_content: string;
  after_content: string;
  empty_msg: string;
  page_size: number;
}

const props = defineProps<{
  view: ViewData;
  availableFields: FormField[];
  saving: boolean;
}>();

const emit = defineEmits<{
  (e: 'back'): void;
  (e: 'save', view: ViewData): void;
}>();

const rowEditorRef = ref<HTMLTextAreaElement | null>(null);

function insertTag(tag: string) {
  if (!rowEditorRef.value) {
    props.view.content += `[${tag}]`;
    return;
  }

  const el = rowEditorRef.value;
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const text = props.view.content;
  const insertion = `[${tag}]`;

  props.view.content = text.substring(0, start) + insertion + text.substring(end);

  setTimeout(() => {
    el.focus();
    el.selectionStart = el.selectionEnd = start + insertion.length;
  }, 50);
}
</script>

<template>
  <div class="space-y-6">
    <!-- Action Header -->
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
          <p class="text-xs text-slate-500 font-mono mt-0.5">Shortcode: [sm_view id="{{ view.id }}"]</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UiButton variant="scout" :disabled="saving" @click="emit('save', view)">
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving View...' : 'Save View Template' }}</span>
        </UiButton>
      </div>
    </div>

    <!-- View Settings -->
    <UiCard title="View Settings & Source Form" subtitle="Specify the data source and pagination options">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <UiInput v-model="view.title" label="View Title" required />

        <div>
          <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
            Display Format
          </label>
          <select
            v-model="view.show_count"
            class="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all"
          >
            <option value="all">All Entries (Listing Table / Directory)</option>
            <option value="one">Single Entry (Detailed Profile)</option>
            <option value="dynamic">Dynamic (Listing + Detail)</option>
          </select>
        </div>

        <UiInput
          v-model.number="view.page_size"
          type="number"
          label="Page Size (Pagination Limit)"
          placeholder="25"
        />
      </div>
    </UiCard>

    <!-- Tag Insertion Palette -->
    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-4">
      <div class="flex items-center gap-2 mb-2 text-xs font-bold text-blue-900 uppercase tracking-wider">
        <Tag class="w-3.5 h-3.5 text-blue-600" />
        <span>Click Any Tag Below to Insert into Row Template:</span>
      </div>
      <div class="flex flex-wrap gap-1.5">
        <!-- Common Tags -->
        <button
          type="button"
          v-for="t in ['id', 'created-at', 'editlink', 'detaillink']"
          :key="t"
          @click="insertTag(t)"
          class="px-2.5 py-1 bg-white hover:bg-blue-600 hover:text-white text-blue-800 border border-blue-200 rounded-md font-mono text-xs font-semibold shadow-2xs transition-all cursor-pointer"
        >
          [{{ t }}]
        </button>

        <!-- Form Field Tags -->
        <button
          type="button"
          v-for="f in availableFields"
          :key="f.id"
          @click="insertTag(f.field_key || String(f.id))"
          class="px-2.5 py-1 bg-white hover:bg-[#025600] hover:text-white text-slate-800 border border-slate-200 rounded-md font-mono text-xs font-medium shadow-2xs transition-all cursor-pointer"
          :title="`${f.name} (${f.type})`"
        >
          [{{ f.field_key || f.id }}]
        </button>
      </div>
    </div>

    <!-- Template Editors Grid -->
    <div class="space-y-4">
      <!-- Before Content -->
      <UiCard title="Before Content (Header HTML)" subtitle="HTML rendered before all entries (e.g. table header opening tags)">
        <textarea
          v-model="view.before_content"
          rows="3"
          class="w-full font-mono text-xs p-3.5 bg-slate-900 text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
          placeholder="<table class='table'><thead><tr><th>...</tr></thead><tbody>"
        />
      </UiCard>

      <!-- Row / Repeated Content -->
      <UiCard title="Row Content (Repeated Entry Template)" subtitle="HTML template rendered for each record with shortcodes / tags">
        <textarea
          ref="rowEditorRef"
          v-model="view.content"
          rows="8"
          class="w-full font-mono text-xs p-3.5 bg-slate-900 text-emerald-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all leading-relaxed"
          placeholder="<tr><td>[council_name]</td><td>[council_start] – [council_end]</td></tr>"
        />
      </UiCard>

      <!-- After Content -->
      <UiCard title="After Content (Footer HTML)" subtitle="HTML rendered after all entries (e.g. table closing tags)">
        <textarea
          v-model="view.after_content"
          rows="3"
          class="w-full font-mono text-xs p-3.5 bg-slate-900 text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
          placeholder="</tbody></table>"
        />
      </UiCard>

      <!-- Empty Message -->
      <UiCard title="Empty Message" subtitle="Text or HTML displayed when no matching records exist">
        <UiInput v-model="view.empty_msg" placeholder="No Entries Found" />
      </UiCard>
    </div>
  </div>
</template>
