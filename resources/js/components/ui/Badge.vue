<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
    :class="colorClasses"
  >
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' }, // success | warning | danger | info | neutral
});

const colorClasses = computed(() => ({
  success: 'bg-success/15 text-success',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
  info: 'bg-navy-400/20 text-navy-300',
  neutral: 'bg-navy-500/40 text-ink-muted',
}[props.tone] ?? 'bg-navy-500/40 text-ink-muted'));
</script>
