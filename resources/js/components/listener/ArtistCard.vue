<template>
  <button type="button" class="text-center min-w-0 w-32 shrink-0" @click="$emit('open', artist)">
    <div class="w-28 h-28 mx-auto rounded-full bg-navy-700 overflow-hidden mb-2">
      <img v-if="artist.avatar_url" :src="artist.avatar_url" class="w-full h-full object-cover" alt="" />
      <div v-else class="w-full h-full flex items-center justify-center text-ink-muted">
        <UserRound :size="28" />
      </div>
    </div>
    <p class="text-sm font-medium truncate">{{ artist.name }}</p>
    <p class="text-xs text-ink-muted">{{ artist.is_verified ? 'Verified Artist' : 'Artist' }}</p>
  </button>
</template>

<script setup>
import { UserRound } from '@lucide/vue';

defineProps({
  artist: { type: Object, required: true },
});

defineEmits(['open']);
</script>
