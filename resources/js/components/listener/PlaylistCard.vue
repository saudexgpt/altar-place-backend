<template>
  <button type="button" class="text-left min-w-0 w-36 shrink-0" @click="$emit('open', playlist)">
    <div class="aspect-square rounded-lg bg-navy-700 overflow-hidden mb-2">
      <img v-if="playlist.cover_url" :src="playlist.cover_url" class="w-full h-full object-cover" alt="" />
      <div v-else class="w-full h-full flex items-center justify-center text-ink-muted">
        <ListMusic :size="24" />
      </div>
    </div>
    <p class="text-sm font-medium truncate">{{ playlist.title }}</p>
    <p class="text-xs text-ink-muted truncate">{{ playlist.tracks_count ?? 0 }} tracks</p>
  </button>
</template>

<script setup>
import { ListMusic } from '@lucide/vue';

defineProps({
  playlist: { type: Object, required: true },
});

defineEmits(['open']);
</script>
