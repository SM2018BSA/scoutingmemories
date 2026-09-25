<script setup lang="ts">
import { ArrowUp, ArrowDown, Trash2, Plus } from 'lucide-vue-next';
import UiSwitch from '../ui/UiSwitch.vue';

/**
 * Choices of a dropdown, radio or checkbox field. Existing choices keep their Formidable key
 * (and any extra settings on the server); saved answers hold the choice's value.
 */
const props = defineProps<{ field: any }>();

function move(i: number, d: number) {
  const list = props.field.choices;
  const j = i + d;
  if (j < 0 || j >= list.length) return;
  [list[i], list[j]] = [list[j], list[i]];
}

function add() {
  props.field.choices.push({ key: '', label: '', value: '', array: !!props.field.separate_value });
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between">
      <span class="sm-builder-label !mb-0">Choices</span>
      <UiSwitch v-model="field.separate_value" label="Separate saved values" />
    </div>
    <p class="text-xs text-amber-700">Changing a choice's saved value does not change answers already saved with the old value.</p>
    <div v-for="(c, i) in field.choices" :key="i" class="flex items-center gap-2">
      <div class="flex flex-col">
        <button type="button" class="p-0.5 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer" :disabled="i === 0" title="Move up" @click="move(i, -1)"><ArrowUp class="w-3 h-3" /></button>
        <button type="button" class="p-0.5 text-slate-400 hover:text-slate-800 disabled:opacity-30 cursor-pointer" :disabled="i === field.choices.length - 1" title="Move down" @click="move(i, 1)"><ArrowDown class="w-3 h-3" /></button>
      </div>
      <input v-model="c.label" class="sm-builder-input" placeholder="Label" :aria-label="`Choice ${i + 1} label`" />
      <input v-if="field.separate_value" v-model="c.value" class="sm-builder-input" placeholder="Saved value" :aria-label="`Choice ${i + 1} value`" />
      <button type="button" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer" title="Remove choice" @click="field.choices.splice(i, 1)">
        <Trash2 class="w-4 h-4" />
      </button>
    </div>
    <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900 cursor-pointer" @click="add">
      <Plus class="w-3.5 h-3.5" /> Add a choice
    </button>
  </div>
</template>
