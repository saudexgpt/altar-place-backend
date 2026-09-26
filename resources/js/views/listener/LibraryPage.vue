<template>
  <div>
    <!-- Playlist detail view -->
    <div v-if="activePlaylistId">
      <button type="button" class="text-xs text-gold hover:underline mb-4 flex items-center gap-1" @click="closePlaylist">
        <ChevronLeft :size="14" /> Back to Library
      </button>

      <div v-if="playlistError" class="bg-danger/10 border border-danger/30 rounded-xl p-4 mb-6">
        <p class="text-sm text-danger">{{ playlistError }}</p>
      </div>
      <p v-else-if="!activePlaylist" class="text-sm text-ink-muted">Loading…</p>
      <div v-else>
        <div class="flex items-start gap-5 mb-6 flex-wrap">
          <div class="w-32 h-32 rounded-xl bg-navy-700 overflow-hidden shrink-0">
            <img v-if="activePlaylist.cover_url" :src="activePlaylist.cover_url" class="w-full h-full object-cover" alt="" />
            <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><ListMusic :size="28" /></div>
          </div>
          <div class="min-w-0">
            <p v-if="activePlaylist.is_curated" class="text-xs font-semibold text-gold uppercase tracking-wide mb-1">Curated</p>
            <h1 class="text-2xl font-heading font-semibold mb-1">{{ activePlaylist.title }}</h1>
            <p class="text-sm text-ink-muted mb-3">{{ activePlaylist.description }}</p>
            <p class="text-xs text-ink-muted mb-4">By {{ activePlaylist.owner?.name ?? 'AltarPlace' }} · {{ activePlaylist.tracks?.length ?? 0 }} tracks</p>
            <div class="flex items-center gap-3">
              <button
                v-if="activePlaylist.tracks?.length"
                type="button"
                class="inline-flex items-center gap-2 bg-gold text-navy-950 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-gold-tint transition-colors"
                @click="playFrom(activePlaylist.tracks, activePlaylist.tracks[0])"
              >
                <Play :size="16" /> Play
              </button>
              <button
                v-if="activePlaylist.is_owner"
                type="button"
                class="text-sm font-semibold border border-navy-500/60 rounded-full px-4 py-2 hover:bg-navy-800 transition-colors"
                @click="showManageModal = true"
              >
                Manage
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-1">
          <TrackListItem
            v-for="track in activePlaylist.tracks"
            :key="track.id"
            :track="track"
            @play="playFrom(activePlaylist.tracks, track)"
            @toggle-favorite="toggleFavorite(track)"
          />
          <p v-if="!activePlaylist.tracks?.length" class="text-sm text-ink-muted py-8 text-center">This playlist has no tracks yet.</p>
        </div>
      </div>

      <PlaylistDetailModal
        v-if="showManageModal"
        :playlist="activePlaylist"
        @close="showManageModal = false"
        @saved="onPlaylistSaved"
        @deleted="onPlaylistDeleted"
      />
    </div>

    <!-- Library tabs -->
    <div v-else>
      <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-heading font-semibold">Your Library</h1>
        <button
          type="button"
          class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors flex items-center gap-2"
          @click="showCreateModal = true"
        >
          <Plus :size="16" /> New Playlist
        </button>
      </div>

      <div class="flex gap-2 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          class="text-xs font-semibold px-4 py-2 rounded-full transition-colors"
          :class="activeTab === tab.value ? 'bg-gold text-navy-950' : 'bg-navy-800 text-ink-muted hover:bg-navy-700'"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="loadError" class="bg-danger/10 border border-danger/30 rounded-xl p-4 mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-danger">{{ loadError }}</p>
        <button type="button" class="text-xs font-semibold text-danger border border-danger/40 rounded-full px-4 py-1.5 hover:bg-danger/10 transition-colors shrink-0" @click="loadActiveTab">
          Retry
        </button>
      </div>

      <p v-else-if="isLoading" class="text-sm text-ink-muted">Loading…</p>

      <template v-else-if="activeTab === 'playlists'">
        <div v-if="playlists.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
          <PlaylistCard v-for="playlist in playlists" :key="playlist.id" :playlist="playlist" @open="openPlaylist" />
        </div>
        <p v-else class="text-sm text-ink-muted py-10 text-center">You haven't created any playlists yet.</p>
      </template>

      <template v-else>
        <div v-if="currentList.length" class="space-y-1">
          <TrackListItem
            v-for="track in currentList"
            :key="track.id"
            :track="track"
            @play="playFrom(currentList, track)"
            @toggle-favorite="toggleFavorite(track)"
          />
        </div>
        <p v-else class="text-sm text-ink-muted py-10 text-center">
          {{ activeTab === 'favorites' ? "You haven't favorited any tracks yet." : "You haven't played anything yet." }}
        </p>
      </template>
    </div>

    <PlaylistDetailModal v-if="showCreateModal" @close="showCreateModal = false" @saved="onPlaylistCreated" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ListMusic, Play, Plus } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { libraryApi } from '@/services/libraryApi';
import { usePlayerStore } from '@/stores/player';
import TrackListItem from '@/components/listener/TrackListItem.vue';
import PlaylistCard from '@/components/listener/PlaylistCard.vue';
import PlaylistDetailModal from '@/components/playlists/PlaylistDetailModal.vue';

const route = useRoute();
const router = useRouter();
const player = usePlayerStore();

const tabs = [
  { value: 'favorites', label: 'Favorites' },
  { value: 'recent', label: 'Recently Played' },
  { value: 'playlists', label: 'Playlists' },
];
const activeTab = ref('favorites');
const favorites = ref([]);
const recentlyPlayed = ref([]);
const playlists = ref([]);
const isLoading = ref(true);
const loadError = ref('');

const currentList = computed(() => (activeTab.value === 'favorites' ? favorites.value : recentlyPlayed.value));

const activePlaylistId = computed(() => {
  const id = route.query.playlist;
  return id ? Number(id) : null;
});
const activePlaylist = ref(null);
const playlistError = ref('');
const showManageModal = ref(false);
const showCreateModal = ref(false);

async function loadActiveTab() {
  isLoading.value = true;
  loadError.value = '';

  try {
    if (activeTab.value === 'favorites' && !favorites.value.length) favorites.value = await libraryApi.favorites();
    if (activeTab.value === 'recent') recentlyPlayed.value = await libraryApi.recentlyPlayed();
    if (activeTab.value === 'playlists') playlists.value = await libraryApi.playlists();
  } catch {
    loadError.value = 'Could not load your library. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

async function loadPlaylistDetail(id) {
  activePlaylist.value = null;
  playlistError.value = '';

  try {
    activePlaylist.value = await catalogApi.playlist(id);
  } catch {
    playlistError.value = 'Could not load this playlist. It may have been deleted, or you may not have access to it.';
  }
}

function openPlaylist(playlist) {
  router.push({ query: { ...route.query, playlist: playlist.id } });
}

function closePlaylist() {
  const query = { ...route.query };
  delete query.playlist;
  router.push({ query });
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
      favorites.value = favorites.value.filter((t) => t.id !== track.id);
    } else {
      await libraryApi.favoriteTrack(track.id);
    }
  } catch {
    track.is_favorited = wasFavorited;
  }
}

function onPlaylistCreated() {
  showCreateModal.value = false;
  playlists.value = [];
  if (activeTab.value === 'playlists') loadActiveTab();
}

function onPlaylistSaved(updated) {
  showManageModal.value = false;
  activePlaylist.value = updated;
}

function onPlaylistDeleted() {
  showManageModal.value = false;
  closePlaylist();
}

watch(activeTab, loadActiveTab);
watch(activePlaylistId, (id) => {
  if (id) loadPlaylistDetail(id);
});

onMounted(() => {
  if (activePlaylistId.value) {
    loadPlaylistDetail(activePlaylistId.value);
  } else {
    loadActiveTab();
  }
});
</script>
