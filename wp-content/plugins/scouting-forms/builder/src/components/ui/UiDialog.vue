<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogClose
} from 'reka-ui';
import { X } from 'lucide-vue-next';

interface Props {
  open: boolean;
  title: string;
  description?: string;
  maxWidth?: string;
}

const props = withDefaults(defineProps<Props>(), {
  description: '',
  maxWidth: 'max-w-lg'
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 transition-opacity animate-in fade-in" />
      <DialogContent
        class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-2xl border border-slate-200 z-50 w-full max-h-[90vh] overflow-y-auto p-6 focus:outline-none animate-in zoom-in-95"
        :class="maxWidth"
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
          <div>
            <DialogTitle class="text-lg font-bold text-slate-900 tracking-tight">
              {{ title }}
            </DialogTitle>
            <DialogDescription v-if="description" class="text-xs text-slate-500 mt-0.5">
              {{ description }}
            </DialogDescription>
          </div>
          <DialogClose class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-all cursor-pointer">
            <X class="w-4 h-4" />
          </DialogClose>
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
          <slot name="footer" />
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
