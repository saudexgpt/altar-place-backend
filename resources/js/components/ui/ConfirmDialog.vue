<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('cancel')">
    <div class="w-full max-w-sm bg-navy-800 border border-navy-500/60 rounded-2xl p-6">
      <h3 class="font-heading font-semibold text-lg mb-2">{{ title }}</h3>
      <p v-if="message" class="text-sm text-ink-muted mb-4">{{ message }}</p>

      <textarea
        v-if="withReason"
        v-model="reason"
        rows="2"
        placeholder="Reason (optional)"
        class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm mb-4 placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
      />

      <div class="flex justify-end gap-3">
        <button ref="cancelButton" type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="$emit('cancel')">
          Cancel
        </button>
        <button
          type="button"
          class="text-sm font-semibold px-4 py-2 rounded-full"
          :class="danger ? 'bg-danger text-white' : 'bg-gold text-navy-950'"
          @click="$emit('confirm', reason)"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useModalA11y } from '@/composables/useModalA11y';

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  danger: { type: Boolean, default: false },
  withReason: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const reason = ref('');
const cancelButton = ref(null);

useModalA11y(() => emit('cancel'), cancelButton);
</script>
