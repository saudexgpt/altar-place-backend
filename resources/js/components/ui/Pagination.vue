<template>
  <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between px-5 py-3 text-sm text-ink-muted">
    <p>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</p>
    <div class="flex items-center gap-2">
      <button
        type="button"
        aria-label="Previous page"
        class="w-8 h-8 rounded-lg border border-navy-500/60 flex items-center justify-center disabled:opacity-40 hover:bg-navy-700 transition-colors"
        :disabled="meta.current_page <= 1"
        @click="$emit('change', meta.current_page - 1)"
      >
        <ChevronLeft :size="16" />
      </button>
      <button
        type="button"
        aria-label="Next page"
        class="w-8 h-8 rounded-lg border border-navy-500/60 flex items-center justify-center disabled:opacity-40 hover:bg-navy-700 transition-colors"
        :disabled="meta.current_page >= meta.last_page"
        @click="$emit('change', meta.current_page + 1)"
      >
        <ChevronRight :size="16" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';

defineProps({
  meta: { type: Object, default: null },
});

defineEmits(['change']);
</script>
