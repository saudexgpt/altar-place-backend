<template>
  <button
    type="button"
    class="group text-left min-w-0 w-36 shrink-0"
    :aria-label="`Play ${track.title} by ${track.artist?.name ?? 'Unknown artist'}`"
    @click="$emit('play', track)"
  >
    <div class="relative aspect-square rounded-lg bg-navy-700 overflow-hidden mb-2">
      <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
      <div v-else class="w-full h-full flex items-center justify-center text-ink-muted">
        <Music :size="24" />
      </div>
      <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity">
        <component :is="isCurrentAndPlaying ? Pause : Play" :size="18" class="text-white" />
      </div>
    </div>
    <p class="text-sm font-medium truncate">{{ track.title }}</p>
    <p class="text-xs text-ink-muted truncate">{{ track.artist?.name }}</p>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { Music, Play, Pause } from '@lucide/vue';
import { usePlayerStore } from '@/stores/player';

const props = defineProps({
  track: { type: Object, required: true },
});

defineEmits(['play']);

const player = usePlayerStore();
const isCurrentAndPlaying = computed(() => player.currentTrack?.id === props.track.id && player.isPlaying);
</script>
