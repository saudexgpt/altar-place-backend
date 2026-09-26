<template>
  <section v-if="isLoading || items.length" class="mb-8">
    <h2 class="text-lg font-heading font-semibold mb-3">{{ title }}</h2>
    <p v-if="isLoading" class="text-sm text-ink-muted">Loading…</p>
    <div v-else class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1">
      <slot />
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  items: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
});
</script>
