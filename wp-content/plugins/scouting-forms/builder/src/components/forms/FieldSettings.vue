<script setup lang="ts">
import { computed } from 'vue';
import { Info } from 'lucide-vue-next';
import UiInput from '../ui/UiInput.vue';
import UiSwitch from '../ui/UiSwitch.vue';
import ChoicesEditor from './ChoicesEditor.vue';
import LogicRows from './LogicRows.vue';

const props = defineProps<{ field: any; fields: any[]; operators: string[] }>();

const t = computed(() => props.field.type);
const isStructure = computed(() => ['divider', 'end_divider', 'break'].includes(t.value));
const hasInput = computed(() => !['html', 'divider', 'end_divider', 'break', 'captcha', 'submit'].includes(t.value));
const hasPlaceholder = computed(() => ['text', 'textarea', 'email', 'url', 'number', 'phone', 'date', 'select', 'data', 'password'].includes(t.value));
const hasInvalid = computed(() => ['email', 'url', 'number', 'phone', 'date', 'password', 'file'].includes(t.value));
const canUnique = computed(() => ['text', 'email', 'url', 'number', 'phone', 'date', 'hidden', 'user_id'].includes(t.value));
</script>

<template>
  <div class="space-y-5 pt-4 mt-3 border-t border-slate-200">
    <div v-if="field.theme_constant || (field.info && field.info.length)" class="rounded-lg bg-slate-100 border border-slate-200 p-3 text-xs text-slate-600 space-y-1">
      <p v-if="field.theme_constant" class="flex items-center gap-1.5">
        <Info class="w-3.5 h-3.5 text-amber-600" />
        The theme uses this field as <code class="font-mono">{{ field.theme_constant }}</code>: it cannot be deleted.
      </p>
      <p v-for="(line, i) in field.info" :key="i">{{ line }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <UiInput v-model="field.name" :label="t === 'end_divider' ? 'Label (not shown)' : 'Label'" />
      <UiInput v-model="field.field_key" label="Field key" helper="Letters, numbers, - and _. Used by [key] tags." />
    </div>

    <div v-if="t !== 'end_divider'">
      <label class="sm-builder-label">{{ t === 'html' ? 'Content (HTML)' : 'Description' }}</label>
      <textarea v-model="field.description" :rows="t === 'html' ? 6 : 2" class="sm-builder-input font-mono text-xs" />
    </div>

    <div v-if="hasInput && !isStructure" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <UiInput v-if="hasPlaceholder" v-model="field.placeholder" label="Placeholder" />
      <div>
        <UiInput
          v-model="field.default_value"
          label="Default value"
          :disabled="field.default_locked"
          :helper="field.default_locked ? 'This default is a list; it is kept as it is.' : 'Shortcodes such as [user_id] or [get param=x] work here.'"
        />
      </div>
      <UiInput v-model="field.classes" label="CSS classes" helper="e.g. frm_half frm_first" />
    </div>

    <div v-if="hasInput && !isStructure" class="space-y-3">
      <div class="flex flex-wrap items-center gap-6">
        <UiSwitch v-model="field.required" label="Required" />
        <UiSwitch v-if="canUnique" v-model="field.unique" label="Unique (no two entries alike)" />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <UiInput v-if="field.required" v-model="field.blank" label="Message when blank" placeholder="This field cannot be blank." />
        <UiInput v-if="hasInvalid" v-model="field.invalid" label="Message when invalid" />
        <UiInput v-if="field.unique" v-model="field.unique_msg" label="Message when not unique" />
      </div>
    </div>

    <ChoicesEditor v-if="field.choices" :field="field" />

    <div v-if="t !== 'end_divider'" class="space-y-2">
      <span class="sm-builder-label">Conditional logic</span>
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <select v-model="field.logic.show_hide" class="sm-builder-input !w-auto" aria-label="Show or hide">
          <option value="show">Show</option>
          <option value="hide">Hide</option>
        </select>
        <span>this field if</span>
        <select v-model="field.logic.any_all" class="sm-builder-input !w-auto" aria-label="Any or all">
          <option value="any">any</option>
          <option value="all">all</option>
        </select>
        <span>of these rules match (no rules = always shown):</span>
      </div>
      <LogicRows :rows="field.logic.rows" :fields="fields" :operators="operators" :exclude-id="field.id" />
    </div>
  </div>
</template>
