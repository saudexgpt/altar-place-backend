<template>
  <div class="bg-navy-950 text-ink min-h-screen flex items-center justify-center px-6">
    <div class="max-w-md text-center">
      <div class="w-16 h-16 rounded-2xl bg-gold/15 flex items-center justify-center mx-auto mb-6">
        <Wrench :size="28" class="text-gold" />
      </div>
      <h1 class="text-2xl font-heading font-semibold mb-3">We'll be right back</h1>
      <p class="text-ink-muted mb-1">AltarPlace is undergoing scheduled maintenance.</p>
      <p v-if="message" class="text-ink-muted">{{ message }}</p>
      <RouterLink :to="{ name: 'login' }" class="inline-block mt-8 text-xs text-gold hover:underline">Staff sign in</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { Wrench } from '@lucide/vue';

defineProps({
  message: { type: String, default: '' },
});
</script>
