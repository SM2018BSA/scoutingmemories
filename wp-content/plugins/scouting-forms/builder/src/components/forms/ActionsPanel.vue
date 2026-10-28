<script setup lang="ts">
import { ref } from 'vue';
import { Mail, CheckCircle2, FileText, UserPlus, Save, Trash2, ChevronDown, ChevronRight, Plus } from 'lucide-vue-next';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiBadge from '../ui/UiBadge.vue';
import UiSwitch from '../ui/UiSwitch.vue';
import LogicRows from './LogicRows.vue';

/**
 * The form's actions (emails, confirmation, post, account). Emails and confirmations are edited
 * here; the post and account actions are shown and kept exactly as they are.
 */
const props = defineProps<{
  actions: any[];
  fields: any[];
  pages: { id: number; title: string }[];
  operators: string[];
  errors: Record<string, Record<string, string>>;
  saving: number | null;
  canEdit: boolean;
  canDelete: boolean;
}>();

const emit = defineEmits<{
  (e: 'save', action: any): void;
  (e: 'create', type: string): void;
  (e: 'remove', action: any): void;
}>();

const open = ref<Record<number, boolean>>({});
const icons: Record<string, any> = { email: Mail, on_submit: CheckCircle2, wppost: FileText, register: UserPlus };
const typeLabels: Record<string, string> = { email: 'Email', on_submit: 'Confirmation', wppost: 'Create post', register: 'Register user' };

function toggleEvent(action: any, ev: string) {
  const i = action.event.indexOf(ev);
  if (i >= 0) action.event.splice(i, 1); else action.event.push(ev);
}
function err(action: any, key: string) {
  return props.errors[action.id]?.[key] || '';
}
</script>

<template>
  <UiCard>
    <template #header>
      <div>
        <h3 class="text-base font-bold text-slate-900 tracking-tight">Actions after submit ({{ actions.length }})</h3>
        <p class="text-xs text-slate-500 mt-0.5">Emails and the confirmation shown after submitting. Inactive actions never run. Each action is saved with its own button.</p>
      </div>
      <div v-if="canEdit" class="flex items-center gap-2">
        <UiButton variant="outline" size="sm" @click="emit('create', 'email')"><Plus class="w-3.5 h-3.5" /><span>Email</span></UiButton>
        <UiButton variant="outline" size="sm" @click="emit('create', 'on_submit')"><Plus class="w-3.5 h-3.5" /><span>Confirmation</span></UiButton>
      </div>
    </template>

    <p v-if="!actions.length" class="text-sm text-slate-400 text-center py-6">This form has no actions.</p>

    <div class="space-y-3">
      <div v-for="action in actions" :key="action.id" class="border border-slate-200 rounded-xl bg-slate-50">
        <button type="button" class="w-full flex items-center gap-3 p-4 text-left cursor-pointer" :aria-expanded="!!open[action.id]" @click="open[action.id] = !open[action.id]">
          <component :is="open[action.id] ? ChevronDown : ChevronRight" class="w-4 h-4 text-slate-400" />
          <component :is="icons[action.type] || FileText" class="w-4 h-4 text-slate-600" />
          <span class="font-semibold text-sm text-slate-900">{{ action.name }}</span>
          <UiBadge variant="neutral">{{ typeLabels[action.type] || action.type }}</UiBadge>
          <UiBadge :variant="action.active ? 'success' : 'warning'">{{ action.active ? 'Active' : 'Inactive' }}</UiBadge>
          <span class="text-xs text-slate-500 truncate flex-1">{{ action.summary }}</span>
        </button>

        <div v-if="open[action.id]" class="px-4 pb-4 space-y-4 border-t border-slate-200 pt-4">
          <p v-if="!action.editable" class="text-sm text-slate-600">{{ action.summary }}</p>

          <template v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
              <UiInput v-model="action.name" label="Name" />
              <div class="flex flex-wrap items-center gap-5 pb-2">
                <UiSwitch v-model="action.active" label="Active" />
                <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <input type="checkbox" :checked="action.event.includes('create')" @change="toggleEvent(action, 'create')" /> New entries
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <input type="checkbox" :checked="action.event.includes('update')" @change="toggleEvent(action, 'update')" /> Edited entries
                </label>
              </div>
            </div>

            <template v-if="action.type === 'email'">
              <p class="text-xs text-slate-500">Field values: [123] (field ID). Also [admin_email], [sitename], [default-message]. Separate addresses with commas.</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <UiInput v-model="action.settings.email_to" label="To" required :error="err(action, 'email_to')" />
                <UiInput v-model="action.settings.from" label="From" placeholder="[sitename] <[admin_email]>" />
                <UiInput v-model="action.settings.cc" label="Cc" />
                <UiInput v-model="action.settings.bcc" label="Bcc" />
                <UiInput v-model="action.settings.reply_to" label="Reply to" />
                <UiInput v-model="action.settings.email_subject" label="Subject" placeholder="(site name: form name)" />
              </div>
              <div>
                <label class="sm-builder-label">Message</label>
                <textarea v-model="action.settings.email_message" rows="6" class="sm-builder-input font-mono text-xs" />
              </div>
              <UiSwitch v-model="action.settings.plain_text" label="Send as plain text" />
            </template>

            <template v-if="action.type === 'on_submit'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="sm-builder-label">After submitting</label>
                  <select v-model="action.settings.success_action" class="sm-builder-input">
                    <option value="message">Show a message</option>
                    <option value="redirect">Go to a web address</option>
                    <option value="page">Show a page's content</option>
                  </select>
                </div>
                <UiInput v-if="action.settings.success_action === 'redirect'" v-model="action.settings.success_url" label="Web address" placeholder="/thank-you/ or https://…" :error="err(action, 'success_url')" />
                <div v-if="action.settings.success_action === 'page'">
                  <label class="sm-builder-label">Page</label>
                  <select v-model.number="action.settings.success_page_id" class="sm-builder-input" :class="{ 'border-red-500': err(action, 'success_page_id') }">
                    <option :value="0">Choose a page…</option>
                    <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.title || '(no title)' }} (#{{ p.id }})</option>
                  </select>
                  <p v-if="err(action, 'success_page_id')" class="text-xs text-red-600 font-medium mt-1">{{ err(action, 'success_page_id') }}</p>
                </div>
              </div>
              <div v-if="action.settings.success_action === 'message'">
                <label class="sm-builder-label">Message</label>
                <textarea v-model="action.settings.success_msg" rows="3" class="sm-builder-input text-sm" />
              </div>
              <UiSwitch v-if="action.settings.success_action === 'message'" v-model="action.settings.show_form" label="Show the form again under the message" />
            </template>

            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2 text-sm">
                <select v-model="action.conditions.send_stop" class="sm-builder-input !w-auto" aria-label="Run or skip">
                  <option value="send">Run</option>
                  <option value="stop">Do not run</option>
                </select>
                <span>this action if</span>
                <select v-model="action.conditions.any_all" class="sm-builder-input !w-auto" aria-label="Any or all">
                  <option value="any">any</option>
                  <option value="all">all</option>
                </select>
                <span>of these rules match (no rules = always):</span>
              </div>
              <LogicRows :rows="action.conditions.rows" :fields="fields" :operators="operators" />
              <p v-if="err(action, 'conditions')" class="text-xs text-red-600 font-medium">{{ err(action, 'conditions') }}</p>
            </div>

            <div class="flex items-center justify-between pt-2">
              <UiButton v-if="canDelete" variant="ghost" size="sm" @click="emit('remove', action)">
                <Trash2 class="w-3.5 h-3.5" /><span>Move to trash</span>
              </UiButton>
              <span v-else />
              <UiButton v-if="canEdit" variant="scout" size="sm" :disabled="saving === action.id" @click="emit('save', action)">
                <Save class="w-3.5 h-3.5" /><span>{{ saving === action.id ? 'Saving…' : 'Save action' }}</span>
              </UiButton>
            </div>
          </template>
        </div>
      </div>
    </div>
  </UiCard>
</template>
