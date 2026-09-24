<script setup lang="ts">
import { ref, watch } from 'vue';
import UiDialog from '../ui/UiDialog.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';

interface FormField {
  id: number;
  name: string;
  type: string;
  field_key: string;
}

const props = defineProps<{
  open: boolean;
  entryId: number | null;
  formId: number;
  fields: FormField[];
  metas: Record<number, any>;
  saving: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'save', payload: { entryId: number | null; formId: number; metas: Record<number, any> }): void;
}>();

const localMetas = ref<Record<number, any>>({});

watch(() => props.metas, (newMetas) => {
  localMetas.value = { ...newMetas };
}, { deep: true, immediate: true });

function handleSave() {
  emit('save', {
    entryId: props.entryId,
    formId: props.formId,
    metas: localMetas.value
  });
}
</script>

<template>
  <UiDialog
    :open="open"
    @update:open="emit('update:open', $event)"
    :title="entryId ? `Edit Entry #${entryId}` : 'Create New Entry'"
    :description="entryId ? 'Update field values stored in database' : 'Fill in the field values for this new record'"
    maxWidth="max-w-2xl"
  >
    <div class="space-y-4 py-2">
      <div v-if="fields.length === 0" class="text-center py-6 text-slate-400">
        Loading form fields...
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto pr-1">
        <div v-for="f in fields" :key="f.id">
          <UiInput
            v-model="localMetas[f.id]"
            :label="f.name"
            :placeholder="f.field_key"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <UiButton variant="outline" @click="emit('update:open', false)">Cancel</UiButton>
      <UiButton variant="scout" :disabled="saving" @click="handleSave">
        {{ saving ? 'Saving...' : 'Save Entry' }}
      </UiButton>
    </template>
  </UiDialog>
</template>
