<template>
  <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="iconBg">
        <component :is="icon" :size="18" :class="iconColor" />
      </div>
      <p class="text-sm text-ink-muted">{{ label }}</p>
    </div>
    <p class="text-2xl font-heading font-semibold">{{ value }}</p>
    <p v-if="trend !== null" class="text-xs mt-1 flex items-center gap-1" :class="trend >= 0 ? 'text-success' : 'text-danger'">
      <component :is="trend >= 0 ? TrendingUp : TrendingDown" :size="12" />
      {{ Math.abs(trend) }}% vs last 7 days
    </p>
  </div>
</template>

<script setup>
import { TrendingUp, TrendingDown } from '@lucide/vue';

defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: [Object, Function], required: true },
  iconBg: { type: String, default: 'bg-gold/15' },
  iconColor: { type: String, default: 'text-gold' },
  trend: { type: Number, default: null },
});
</script>
