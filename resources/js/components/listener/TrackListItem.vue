<template>
  <div class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-navy-800 group">
    <button
      type="button"
      class="w-10 h-10 rounded-lg bg-navy-700 overflow-hidden shrink-0 relative"
      :aria-label="`Play ${track.title}`"
      @click="$emit('play', track)"
    >
      <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
      <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="16" /></div>
      <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
        <component :is="isCurrentAndPlaying ? Pause : Play" :size="14" class="text-white" />
      </div>
    </button>

    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium truncate">{{ track.title }}</p>
      <p class="text-xs text-ink-muted truncate">{{ track.artist?.name }}</p>
    </div>

    <button
      v-if="showFavorite"
      type="button"
      :aria-label="track.is_favorited ? 'Remove from favorites' : 'Add to favorites'"
      class="text-ink-muted hover:text-gold transition-colors shrink-0"
      @click="$emit('toggle-favorite', track)"
    >
      <Heart :size="18" :class="{ 'fill-gold text-gold': track.is_favorited }" />
    </button>

    <span class="text-xs text-ink-muted shrink-0 w-10 text-right">{{ formatDuration(track.duration_seconds) }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Music, Play, Pause, Heart } from '@lucide/vue';
import { usePlayerStore } from '@/stores/player';

const props = defineProps({
  track: { type: Object, required: true },
  showFavorite: { type: Boolean, default: true },
});

defineEmits(['play', 'toggle-favorite']);

const player = usePlayerStore();
const isCurrentAndPlaying = computed(() => player.currentTrack?.id === props.track.id && player.isPlaying);

function formatDuration(seconds) {
  if (!seconds) return '--:--';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${String(s).padStart(2, '0')}`;
}
</script>
