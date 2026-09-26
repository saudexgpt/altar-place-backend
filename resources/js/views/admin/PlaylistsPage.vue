<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-heading font-semibold">Playlists</h1>
      <button
        type="button"
        class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors"
        @click="showCreateModal = true"
      >
        Create Playlist
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
    <p v-if="!isLoading && !playlists.length" class="text-ink-muted">No playlists yet.</p>

    <p v-if="manageError" class="text-sm text-danger mb-3">{{ manageError }}</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <div v-for="playlist in playlists" :key="playlist.id" class="bg-navy-800 border border-navy-500/40 rounded-xl p-4 group relative">
        <button type="button" class="w-full text-left" @click="playPlaylist(playlist)">
          <div class="relative aspect-square rounded-lg bg-navy-700 overflow-hidden mb-3">
            <img v-if="playlist.cover_url" :src="playlist.cover_url" class="w-full h-full object-cover" alt="" />
            <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><ListMusic :size="24" /></div>
            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <Loader2 v-if="loadingPlaylistId === playlist.id" :size="18" class="animate-spin text-white" />
              <component v-else :is="isPlayingThisPlaylist(playlist) && player.isPlaying ? Pause : Play" :size="18" class="text-white" />
            </div>
          </div>
          <p class="text-sm font-medium truncate">{{ playlist.title }}</p>
          <p class="text-xs text-ink-muted truncate">{{ playlist.tracks_count ?? 0 }} songs · {{ playlist.owner?.name ?? 'Curated' }}</p>
        </button>
        <div class="flex items-center gap-2 mt-2">
          <Badge v-if="playlist.is_curated" label="curated" tone="info" />
          <button type="button" class="text-xs text-gold hover:underline ml-auto" :disabled="managingPlaylistId === playlist.id" @click="manage(playlist)">
            {{ managingPlaylistId === playlist.id ? 'Loading…' : 'Manage' }}
          </button>
        </div>
      </div>
    </div>
    </template>

    <PlaylistDetailModal
      v-if="showCreateModal"
      @close="showCreateModal = false"
      @saved="handleCreated"
    />

    <PlaylistDetailModal
      v-if="managingPlaylist"
      :playlist="managingPlaylist"
      @close="managingPlaylist = null"
      @saved="handleUpdated"
      @deleted="handleDeleted"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { ListMusic, Play, Pause, Loader2 } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { usePlayerStore } from '@/stores/player';
import Badge from '@/components/ui/Badge.vue';
import PlaylistDetailModal from '@/components/playlists/PlaylistDetailModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const player = usePlayerStore();
const playlists = ref([]);
const isLoading = ref(true);
const loadError = ref('');
const loadingPlaylistId = ref(null);
const activePlaylistId = ref(null);
const showCreateModal = ref(false);
const managingPlaylist = ref(null);
const managingPlaylistId = ref(null);
const manageError = ref('');

function isPlayingThisPlaylist(playlist) {
  return activePlaylistId.value === playlist.id && player.queue.some((t) => t.id === player.currentTrack?.id);
}

async function load() {
  isLoading.value = true;
  loadError.value = '';
  try {
    playlists.value = await catalogApi.playlists();
  } catch {
    loadError.value = 'Could not load playlists. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

async function playPlaylist(playlist) {
  if (activePlaylistId.value === playlist.id && player.currentTrack) {
    player.togglePlay();
    return;
  }

  loadingPlaylistId.value = playlist.id;

  try {
    const full = await catalogApi.playlist(playlist.id);
    if (!full.tracks?.length) return;

    activePlaylistId.value = playlist.id;
    player.playQueue(full.tracks, 0);
  } finally {
    loadingPlaylistId.value = null;
  }
}

async function manage(playlist) {
  managingPlaylistId.value = playlist.id;
  manageError.value = '';

  try {
    managingPlaylist.value = await catalogApi.playlist(playlist.id);
  } catch {
    manageError.value = `Could not open "${playlist.title}". Check your connection and try again.`;
  } finally {
    managingPlaylistId.value = null;
  }
}

function handleCreated() {
  showCreateModal.value = false;
  load();
}

function handleUpdated() {
  managingPlaylist.value = null;
  load();
}

function handleDeleted() {
  managingPlaylist.value = null;
  load();
}

onMounted(load);
</script>
