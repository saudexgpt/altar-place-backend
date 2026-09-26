<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-heading font-semibold">My Tracks</h1>
      <button
        type="button"
        class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors flex items-center gap-2"
        @click="showUploadModal = true"
      >
        <Upload :size="16" /> Upload Track
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="() => loadTracks()" />

    <div v-else class="bg-navy-800 border border-navy-500/40 rounded-2xl overflow-hidden">
      <div v-if="isLoading" class="py-16 text-center text-ink-muted">Loading…</div>
      <p v-else-if="!tracks.length" class="py-16 text-center text-ink-muted">
        No tracks yet. Upload your first track to get started.
      </p>
      <table v-else class="w-full text-sm">
        <thead class="text-left text-xs text-ink-muted uppercase tracking-wide border-b border-navy-500/40">
          <tr>
            <th class="px-5 py-3">Title</th>
            <th class="px-5 py-3">Type</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Plays</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="track in tracks" :key="track.id" class="border-b border-navy-500/20 last:border-0">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <button type="button" class="w-9 h-9 rounded-lg bg-navy-700 overflow-hidden shrink-0 relative group" :aria-label="`Play ${track.title}`" @click="play(track)">
                  <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
                  <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="14" /></div>
                  <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <component :is="player.currentTrack?.id === track.id && player.isPlaying ? Pause : Play" :size="14" class="text-white" />
                  </div>
                </button>
                <span class="font-medium truncate">{{ track.title }}</span>
              </div>
            </td>
            <td class="px-5 py-3 capitalize text-ink-muted">{{ track.type }}</td>
            <td class="px-5 py-3">
              <Badge :label="track.status" :tone="statusTone(track.status)" />
              <Badge v-if="track.transcoding_status !== 'completed'" :label="`transcoding: ${track.transcoding_status}`" tone="warning" class="ml-1" />
            </td>
            <td class="px-5 py-3 text-ink-muted">{{ format(track.plays_count) }}</td>
            <td class="px-5 py-3 text-right">
              <button type="button" class="text-xs font-semibold text-gold hover:underline mr-3" @click="editingTrackId = track.id">Edit</button>
              <button type="button" class="text-xs font-semibold text-danger hover:underline" @click="trackPendingDelete = track">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <Pagination :meta="meta" @change="loadTracks" />
    </div>

    <TrackFormModal v-if="showUploadModal" @close="showUploadModal = false" @saved="onUploaded" />
    <TrackFormModal v-if="editingTrackId" :track-id="editingTrackId" @close="editingTrackId = null" @saved="onUpdated" />

    <ConfirmDialog
      v-if="trackPendingDelete"
      title="Delete this track?"
      :message="`“${trackPendingDelete.title}” will be permanently removed.`"
      confirm-label="Delete"
      danger
      @cancel="trackPendingDelete = null"
      @confirm="deleteTrack"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Upload, Music, Play, Pause } from '@lucide/vue';
import { creatorApi } from '@/services/creatorApi';
import { usePlayerStore } from '@/stores/player';
import Badge from '@/components/ui/Badge.vue';
import Pagination from '@/components/ui/Pagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import TrackFormModal from '@/components/creator/TrackFormModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const player = usePlayerStore();

const tracks = ref([]);
const meta = ref(null);
const isLoading = ref(true);
const loadError = ref('');
const showUploadModal = ref(false);
const editingTrackId = ref(null);
const trackPendingDelete = ref(null);

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

function statusTone(status) {
  return { approved: 'success', pending: 'warning', rejected: 'danger' }[status] ?? 'neutral';
}

function play(track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = tracks.value.findIndex((t) => t.id === track.id);
  player.playQueue(tracks.value, index === -1 ? 0 : index);
}

async function loadTracks(page = 1) {
  isLoading.value = true;
  loadError.value = '';
  try {
    const result = await creatorApi.tracks({ page });
    tracks.value = result.data;
    meta.value = result.meta;
  } catch {
    loadError.value = 'Could not load your tracks. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function onUploaded() {
  showUploadModal.value = false;
  loadTracks(meta.value?.current_page ?? 1);
}

function onUpdated() {
  editingTrackId.value = null;
  loadTracks(meta.value?.current_page ?? 1);
}

async function deleteTrack() {
  const track = trackPendingDelete.value;
  trackPendingDelete.value = null;
  await creatorApi.deleteTrack(track.id);
  loadTracks(meta.value?.current_page ?? 1);
}

onMounted(() => loadTracks());
</script>
