<template>
  <div class="bg-danger/10 border border-danger/30 rounded-xl p-4 mb-6 flex items-center justify-between gap-4">
    <p class="text-sm text-danger">{{ message }}</p>
    <button
      type="button"
      class="text-xs font-semibold text-danger border border-danger/40 rounded-full px-4 py-1.5 hover:bg-danger/10 transition-colors shrink-0"
      @click="$emit('retry')"
    >
      Retry
    </button>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Something went wrong loading this page. Check your connection and try again.' },
});

defineEmits(['retry']);
</script>
