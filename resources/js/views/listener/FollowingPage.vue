<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-heading font-semibold mb-6">Following</h1>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <h3 class="font-heading font-semibold mb-3">Artists</h3>
      <RouterLink
        v-for="artist in artists"
        :key="artist.id"
        :to="{ name: 'listener.artist', params: { id: artist.id } }"
        class="flex items-center gap-3 py-2.5 hover:bg-navy-800 rounded-lg px-1 -mx-1"
      >
        <div class="w-12 h-12 rounded-full bg-navy-700 overflow-hidden shrink-0">
          <img v-if="artist.avatar_url" :src="artist.avatar_url" class="w-full h-full object-cover" alt="" />
          <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><UserRound :size="20" /></div>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-medium truncate">{{ artist.name }}</p>
          <p class="text-xs text-ink-muted">{{ artist.followers_count ?? 0 }} followers</p>
        </div>
      </RouterLink>
      <p v-if="!artists.length" class="text-sm text-ink-muted py-3">You're not following any artists yet.</p>

      <h3 class="font-heading font-semibold mb-3 mt-8">Playlists</h3>
      <RouterLink
        v-for="playlist in playlists"
        :key="playlist.id"
        :to="{ name: 'listener.library', query: { playlist: playlist.id } }"
        class="flex items-center gap-3 py-2.5 hover:bg-navy-800 rounded-lg px-1 -mx-1"
      >
        <div class="w-12 h-12 rounded-lg bg-navy-700 overflow-hidden shrink-0">
          <img v-if="playlist.cover_url" :src="playlist.cover_url" class="w-full h-full object-cover" alt="" />
          <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><ListMusic :size="20" /></div>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-medium truncate">{{ playlist.title }}</p>
          <p class="text-xs text-ink-muted">{{ playlist.tracks_count ?? 0 }} tracks</p>
        </div>
      </RouterLink>
      <p v-if="!playlists.length" class="text-sm text-ink-muted py-3">You're not following any playlists yet.</p>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ListMusic, UserRound } from '@lucide/vue';
import { profileApi } from '@/services/profileApi';
import LoadError from '@/components/ui/LoadError.vue';

const artists = ref([]);
const playlists = ref([]);
const loadError = ref('');

async function load() {
  loadError.value = '';
  try {
    const result = await profileApi.following();
    artists.value = result.artists;
    playlists.value = result.playlists;
  } catch {
    loadError.value = 'Could not load who you follow. Check your connection and try again.';
  }
}

load();
</script>
