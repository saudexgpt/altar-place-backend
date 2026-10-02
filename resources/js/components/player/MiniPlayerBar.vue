<template>
  <div
    v-if="player.currentTrack"
    class="fixed bottom-0 left-0 right-0 z-40 bg-navy-900 border-t border-navy-500/50 h-20 px-4 sm:px-6 flex items-center gap-4"
  >
    <div class="flex items-center gap-3 min-w-0 w-1/3">
      <div class="w-12 h-12 rounded-lg bg-navy-700 overflow-hidden shrink-0">
        <img v-if="player.currentTrack.cover_url" :src="player.currentTrack.cover_url" class="w-full h-full object-cover" alt="" />
        <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="18" /></div>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium truncate">{{ player.currentTrack.title }}</p>
        <p class="text-xs text-ink-muted truncate">{{ player.currentTrack.artist?.name }}</p>
      </div>
    </div>

    <div class="flex-1 flex flex-col items-center gap-1 max-w-xl mx-auto">
      <div class="flex items-center gap-5">
        <button type="button" class="text-ink-muted hover:text-white" :class="{ 'text-gold': player.shuffle }" @click="player.toggleShuffle()">
          <Shuffle :size="16" />
        </button>
        <button type="button" class="text-ink-muted hover:text-white" :disabled="!player.hasPrevious" @click="player.previous()">
          <SkipBack :size="18" />
        </button>
        <button
          type="button"
          class="w-9 h-9 rounded-full bg-gold text-navy-950 flex items-center justify-center hover:bg-gold-tint transition-colors"
          @click="player.togglePlay()"
        >
          <component :is="player.isPlaying ? Pause : Play" :size="16" />
        </button>
        <button type="button" class="text-ink-muted hover:text-white" :disabled="!player.hasNext" @click="player.next()">
          <SkipForward :size="18" />
        </button>
        <button type="button" class="text-ink-muted hover:text-white" :class="{ 'text-gold': player.repeat !== 'off' }" @click="player.cycleRepeat()">
          <Repeat1 v-if="player.repeat === 'one'" :size="16" />
          <Repeat v-else :size="16" />
        </button>
      </div>

      <div class="w-full flex items-center gap-2 text-xs text-ink-muted">
        <span class="w-9 text-right">{{ formatTime(player.currentTime) }}</span>
        <input
          type="range"
          min="0"
          :max="player.duration || 0"
          :value="player.currentTime"
          class="flex-1 accent-gold h-1"
          @input="player.seek(Number($event.target.value))"
        />
        <span class="w-9">{{ formatTime(player.duration) }}</span>
      </div>
    </div>

    <div class="hidden sm:flex items-center gap-3 w-1/3 justify-end">
      <RouterLink
        :to="{ name: 'listener.comments', params: { id: player.currentTrack.id } }"
        class="text-ink-muted hover:text-white"
        aria-label="View comments"
      >
        <MessageCircle :size="16" />
      </RouterLink>
      <Volume2 :size="16" class="text-ink-muted" />
      <input
        type="range"
        min="0"
        max="1"
        step="0.05"
        :value="player.volume"
        class="w-24 accent-gold h-1"
        @input="player.setVolume(Number($event.target.value))"
      />
    </div>
  </div>
</template>

<script setup>
import { Music, Play, Pause, SkipBack, SkipForward, Shuffle, Repeat, Repeat1, Volume2, MessageCircle } from '@lucide/vue';
import { usePlayerStore } from '@/stores/player';

const player = usePlayerStore();

function formatTime(seconds) {
  if (!seconds || !Number.isFinite(seconds)) return '0:00';
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
  return `${mins}:${secs}`;
}
</script>
