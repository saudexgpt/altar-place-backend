<template>
  <div class="max-w-3xl">
    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <div v-else-if="album" class="space-y-6">
      <div class="flex flex-col items-center text-center gap-2 py-6">
        <div class="w-40 h-40 rounded-xl bg-navy-700 overflow-hidden">
          <img v-if="album.cover_url" :src="album.cover_url" class="w-full h-full object-cover" alt="" />
          <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="40" /></div>
        </div>
        <h1 class="text-2xl font-heading font-semibold">{{ album.title }}</h1>
        <RouterLink
          v-if="album.artist"
          :to="{ name: 'listener.artist', params: { id: album.artist.id } }"
          class="text-sm font-medium text-gold hover:underline"
        >
          {{ album.artist.name }}
        </RouterLink>
        <p v-if="album.release_year" class="text-xs text-ink-muted">{{ album.release_year }} · {{ album.tracks?.length ?? 0 }} tracks</p>
      </div>

      <section>
        <TrackListItem
          v-for="track in album.tracks"
          :key="track.id"
          :track="track"
          @play="playTrack(track)"
          @toggle-favorite="toggleFavorite(track)"
        />
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import { Music } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { libraryApi } from '@/services/libraryApi';
import { usePlayerStore } from '@/stores/player';
import TrackListItem from '@/components/listener/TrackListItem.vue';
import LoadError from '@/components/ui/LoadError.vue';

const route = useRoute();
const player = usePlayerStore();

const album = ref(null);
const loadError = ref('');

async function load() {
  loadError.value = '';
  try {
    album.value = await catalogApi.album(route.params.id);
  } catch {
    loadError.value = 'Could not load this album. Check your connection and try again.';
  }
}

function playTrack(track) {
  const tracks = album.value?.tracks ?? [];
  player.playQueue(tracks, tracks.findIndex((t) => t.id === track.id));
}

async function toggleFavorite(track) {
  const wasFavorited = track.is_favorited ?? false;
  track.is_favorited = !wasFavorited;

  try {
    if (wasFavorited) {
      await libraryApi.unfavoriteTrack(track.id);
    } else {
      await libraryApi.favoriteTrack(track.id);
    }
  } catch {
    track.is_favorited = wasFavorited;
  }
}

load();
</script>
