<template>
  <div>
    <h1 class="text-2xl font-heading font-semibold mb-4">Search</h1>

    <div class="relative max-w-lg mb-4">
      <SearchIcon :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
      <input
        v-model="query"
        type="search"
        placeholder="Search songs, artists, sermons, playlists…"
        class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        @input="debouncedSearch"
      />
    </div>

    <div class="flex gap-2 mb-6 flex-wrap">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="text-xs font-semibold px-4 py-2 rounded-full transition-colors"
        :class="type === tab.value ? 'bg-gold text-navy-950' : 'bg-navy-800 text-ink-muted hover:bg-navy-700'"
        @click="changeType(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <p v-if="error" class="text-sm text-danger mb-4">{{ error }}</p>
    <p v-else-if="isLoading" class="text-sm text-ink-muted">Searching…</p>
    <p v-else-if="!query.trim()" class="text-sm text-ink-muted">Start typing to search AltarPlace.</p>
    <p v-else-if="isEmpty" class="text-sm text-ink-muted">No results for "{{ query }}".</p>

    <div v-else class="space-y-8">
      <section v-if="results.artists?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Artists</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
          <ArtistCard v-for="artist in results.artists" :key="artist.id" :artist="artist" @open="() => {}" />
        </div>
      </section>

      <section v-if="results.tracks?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Songs</h2>
        <div class="space-y-1">
          <TrackListItem
            v-for="track in results.tracks"
            :key="track.id"
            :track="track"
            @play="playFrom(results.tracks, track)"
            @toggle-favorite="toggleFavorite(track)"
          />
        </div>
      </section>

      <section v-if="results.sermons?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Sermons</h2>
        <div class="space-y-1">
          <TrackListItem
            v-for="track in results.sermons"
            :key="track.id"
            :track="track"
            @play="playFrom(results.sermons, track)"
            @toggle-favorite="toggleFavorite(track)"
          />
        </div>
      </section>

      <section v-if="results.podcasts?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Podcasts</h2>
        <div class="space-y-1">
          <TrackListItem
            v-for="track in results.podcasts"
            :key="track.id"
            :track="track"
            @play="playFrom(results.podcasts, track)"
            @toggle-favorite="toggleFavorite(track)"
          />
        </div>
      </section>

      <section v-if="results.playlists?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Playlists</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
          <PlaylistCard v-for="playlist in results.playlists" :key="playlist.id" :playlist="playlist" @open="goToPlaylist" />
        </div>
      </section>

      <section v-if="results.genres?.length">
        <h2 class="text-lg font-heading font-semibold mb-3">Genres</h2>
        <div class="flex gap-2 flex-wrap">
          <span v-for="genre in results.genres" :key="genre.id" class="text-sm bg-navy-800 border border-navy-500/40 rounded-full px-4 py-2">
            {{ genre.name }}
          </span>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Search as SearchIcon } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { libraryApi } from '@/services/libraryApi';
import { usePlayerStore } from '@/stores/player';
import ArtistCard from '@/components/listener/ArtistCard.vue';
import PlaylistCard from '@/components/listener/PlaylistCard.vue';
import TrackListItem from '@/components/listener/TrackListItem.vue';

const route = useRoute();
const router = useRouter();
const player = usePlayerStore();

const tabs = [
  { value: 'all', label: 'All' },
  { value: 'artist', label: 'Artists' },
  { value: 'track', label: 'Songs' },
  { value: 'sermon', label: 'Sermons' },
  { value: 'podcast', label: 'Podcasts' },
  { value: 'playlist', label: 'Playlists' },
  { value: 'genre', label: 'Genres' },
];

const query = ref(typeof route.query.q === 'string' ? route.query.q : '');
const type = ref(typeof route.query.type === 'string' ? route.query.type : 'all');
const results = ref({});
const isLoading = ref(false);
const error = ref('');
let debounceTimer = null;

const isEmpty = computed(() => Object.values(results.value).every((list) => !list?.length));

async function runSearch() {
  if (!query.value.trim()) {
    results.value = {};
    return;
  }

  isLoading.value = true;
  error.value = '';

  try {
    results.value = await catalogApi.search(query.value, type.value);
  } catch {
    error.value = 'Search failed. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(runSearch, 300);
}

function changeType(value) {
  type.value = value;
  runSearch();
}

function playFrom(list, track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = list.findIndex((t) => t.id === track.id);
  player.playQueue(list, index === -1 ? 0 : index);
}

async function toggleFavorite(track) {
  const wasFavorited = track.is_favorited;
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

function goToPlaylist(playlist) {
  router.push({ name: 'listener.library', query: { playlist: playlist.id } });
}

onMounted(() => {
  if (query.value.trim()) runSearch();
});
</script>
