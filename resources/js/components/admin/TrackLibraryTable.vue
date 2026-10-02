<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <h1 class="text-2xl font-heading font-semibold">{{ title }}</h1>
      <div class="flex items-center gap-3">
        <button type="button" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-4 py-2 hover:bg-gold-tint transition-colors" @click="isUploading = true">
          Upload
        </button>
        <select v-model="status" class="bg-navy-800 border border-navy-500/60 rounded-full text-xs px-3 py-2 focus:outline-none" @change="reload">
          <option value="">All statuses</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <div class="relative w-60">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
          <input
            v-model="query"
            type="search"
            placeholder="Search title"
            class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
            @input="debouncedLoad"
          />
        </div>
      </div>
    </div>

    <div v-if="selectedIds.size" class="flex items-center justify-between bg-navy-800 border border-gold/30 rounded-xl px-4 py-2.5 mb-3 text-sm">
      <span>{{ selectedIds.size }} selected</span>
      <div class="flex gap-3">
        <button type="button" class="text-success font-medium hover:underline" @click="bulkApprove">Approve selected</button>
        <button type="button" class="text-danger font-medium hover:underline" @click="promptBulkReject">Reject selected</button>
        <button type="button" class="text-ink-muted hover:text-white" @click="selectedIds.clear()">Clear</button>
      </div>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-navy-500/40 text-left text-ink-muted text-xs uppercase tracking-wide">
            <th class="px-5 py-3 w-8">
              <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
            </th>
            <th class="px-5 py-3">Title</th>
            <th class="px-5 py-3">Artist</th>
            <th class="px-5 py-3">Plays</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="track in tracks" :key="track.id" class="border-b border-navy-500/20 last:border-0">
            <td class="px-5 py-3">
              <input type="checkbox" :checked="selectedIds.has(track.id)" @change="toggleSelect(track.id)" />
            </td>
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="relative w-9 h-9 rounded-lg bg-navy-700 overflow-hidden shrink-0 group"
                  :aria-label="`Play ${track.title}`"
                  @click="playFrom(track)"
                >
                  <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
                  <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="16" /></div>
                  <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <component :is="player.currentTrack?.id === track.id && player.isPlaying ? Pause : Play" :size="14" class="text-white" />
                  </div>
                </button>
                <p class="font-medium truncate max-w-[220px]">{{ track.title }}</p>
              </div>
            </td>
            <td class="px-5 py-3 text-ink-muted">{{ track.artist?.name ?? '—' }}</td>
            <td class="px-5 py-3 text-ink-muted">{{ format(track.plays_count) }}</td>
            <td class="px-5 py-3">
              <Badge :label="track.status" :tone="track.status === 'approved' ? 'success' : 'danger'" />
              <p v-if="track.rejection_reason" class="text-xs text-ink-muted mt-1 max-w-[160px] truncate">{{ track.rejection_reason }}</p>
            </td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <button class="text-xs font-medium text-gold hover:underline mr-3" @click="editingTrackId = track.id">Edit</button>
              <button v-if="track.status !== 'approved'" class="text-xs font-medium text-success hover:underline mr-3" @click="approve(track)">Approve</button>
              <button v-if="track.status !== 'rejected'" class="text-xs font-medium text-danger hover:underline" @click="promptReject(track)">Reject</button>
            </td>
          </tr>
          <tr v-if="!isLoading && !loadError && !tracks.length">
            <td colspan="6" class="px-5 py-10 text-center text-ink-muted">No tracks found.</td>
          </tr>
        </tbody>
      </table>

      <Pagination :meta="meta" @change="goToPage" />
    </div>

    <ConfirmDialog
      v-if="dialog"
      :title="dialog.title"
      confirm-label="Reject"
      :danger="true"
      :with-reason="true"
      @confirm="dialog.onConfirm"
      @cancel="dialog = null"
    />

    <TrackEditModal
      v-if="editingTrackId"
      :track-id="editingTrackId"
      @close="editingTrackId = null"
      @saved="handleTrackSaved"
    />

    <TrackUploadModal
      v-if="isUploading"
      :type-options="props.type.split(',')"
      @close="isUploading = false"
      @uploaded="handleTrackUploaded"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Search, Music, Play, Pause } from '@lucide/vue';
import { adminApi } from '@/services/adminApi';
import { usePlayerStore } from '@/stores/player';
import Badge from '@/components/ui/Badge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import Pagination from '@/components/ui/Pagination.vue';
import TrackEditModal from '@/components/admin/TrackEditModal.vue';
import TrackUploadModal from '@/components/admin/TrackUploadModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const props = defineProps({
  title: { type: String, required: true },
  type: { type: String, required: true }, // 'music' or 'sermon,podcast' (Word Library)
});

const isUploading = ref(false);

const player = usePlayerStore();
const tracks = ref([]);
const meta = ref(null);
const page = ref(1);
const query = ref('');
const status = ref('');
const isLoading = ref(true);
const loadError = ref('');
const dialog = ref(null);
const editingTrackId = ref(null);
const selectedIds = reactive(new Set());
let debounceTimer = null;

const allSelected = computed(() => tracks.value.length > 0 && tracks.value.every((t) => selectedIds.has(t.id)));

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

async function load() {
  isLoading.value = true;
  loadError.value = '';
  try {
    const result = await adminApi.tracks({
      type: props.type,
      status: status.value || undefined,
      q: query.value || undefined,
      page: page.value,
    });
    tracks.value = result.data;
    meta.value = result.meta;
    selectedIds.clear();
  } catch {
    loadError.value = 'Could not load tracks. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function reload() {
  page.value = 1;
  load();
}

function goToPage(newPage) {
  page.value = newPage;
  load();
}

function debouncedLoad() {
  clearTimeout(debounceTimer);
  page.value = 1;
  debounceTimer = setTimeout(load, 300);
}

// Reset to page 1 and reload whenever the parent switches which library
// (Music vs Word) this component instance represents.
watch(() => props.type, reload);

function toggleSelect(id) {
  if (selectedIds.has(id)) selectedIds.delete(id);
  else selectedIds.add(id);
}

function toggleSelectAll() {
  if (allSelected.value) {
    tracks.value.forEach((t) => selectedIds.delete(t.id));
  } else {
    tracks.value.forEach((t) => selectedIds.add(t.id));
  }
}

function playFrom(track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = tracks.value.findIndex((t) => t.id === track.id);
  player.playQueue(tracks.value, index === -1 ? 0 : index);
}

function replaceTrack(updated) {
  const index = tracks.value.findIndex((t) => t.id === updated.id);
  if (index !== -1) tracks.value[index] = updated;
}

function handleTrackSaved(updated) {
  replaceTrack(updated);
  editingTrackId.value = null;
}

function handleTrackUploaded(created) {
  tracks.value.unshift(created);
  isUploading.value = false;
}

async function approve(track) {
  replaceTrack(await adminApi.approveTrack(track.id));
}

function promptReject(track) {
  dialog.value = {
    title: `Reject "${track.title}"?`,
    onConfirm: async (reason) => {
      if (!reason) return;
      replaceTrack(await adminApi.rejectTrack(track.id, reason));
      dialog.value = null;
    },
  };
}

async function bulkApprove() {
  const ids = Array.from(selectedIds);
  await Promise.all(ids.map((id) => adminApi.approveTrack(id)));
  await load();
}

function promptBulkReject() {
  const ids = Array.from(selectedIds);
  dialog.value = {
    title: `Reject ${ids.length} track${ids.length === 1 ? '' : 's'}?`,
    onConfirm: async (reason) => {
      if (!reason) return;
      await Promise.all(ids.map((id) => adminApi.rejectTrack(id, reason)));
      dialog.value = null;
      await load();
    },
  };
}

onMounted(load);
</script>
