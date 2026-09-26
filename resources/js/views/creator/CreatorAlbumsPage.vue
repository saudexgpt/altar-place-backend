<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-heading font-semibold">Albums</h1>
      <button
        type="button"
        class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors flex items-center gap-2"
        @click="showCreateModal = true"
      >
        <Plus :size="16" /> Create Album
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="loadAlbums" />
    <div v-else-if="isLoading" class="py-16 text-center text-ink-muted">Loading…</div>
    <p v-else-if="!albums.length" class="py-16 text-center text-ink-muted">No albums yet.</p>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="album in albums" :key="album.id" class="bg-navy-800 border border-navy-500/40 rounded-2xl p-4">
        <div class="aspect-square rounded-lg bg-navy-700 overflow-hidden mb-3">
          <img v-if="album.cover_url" :src="album.cover_url" class="w-full h-full object-cover" alt="" />
          <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Disc3 :size="24" /></div>
        </div>
        <p class="text-sm font-medium truncate">{{ album.title }}</p>
        <p class="text-xs text-ink-muted mb-3">{{ album.tracks_count ?? 0 }} tracks · {{ album.type === 'album' ? 'Album' : 'Podcast Show' }}</p>
        <div class="flex items-center gap-3">
          <button type="button" class="text-xs font-semibold text-gold hover:underline" @click="editingAlbum = album">Edit</button>
          <button type="button" class="text-xs font-semibold text-danger hover:underline" @click="albumPendingDelete = album">Delete</button>
        </div>
      </div>
    </div>

    <AlbumFormModal v-if="showCreateModal" @close="showCreateModal = false" @saved="onCreated" />
    <AlbumFormModal v-if="editingAlbum" :album="editingAlbum" @close="editingAlbum = null" @saved="onUpdated" />

    <ConfirmDialog
      v-if="albumPendingDelete"
      title="Delete this album?"
      :message="`“${albumPendingDelete.title}” will be permanently removed. Tracks in it are not deleted.`"
      confirm-label="Delete"
      danger
      @cancel="albumPendingDelete = null"
      @confirm="deleteAlbum"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Plus, Disc3 } from '@lucide/vue';
import { creatorApi } from '@/services/creatorApi';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import AlbumFormModal from '@/components/creator/AlbumFormModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const albums = ref([]);
const isLoading = ref(true);
const loadError = ref('');
const showCreateModal = ref(false);
const editingAlbum = ref(null);
const albumPendingDelete = ref(null);

async function loadAlbums() {
  isLoading.value = true;
  loadError.value = '';
  try {
    albums.value = await creatorApi.albums();
  } catch {
    loadError.value = 'Could not load your albums. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function onCreated() {
  showCreateModal.value = false;
  loadAlbums();
}

function onUpdated() {
  editingAlbum.value = null;
  loadAlbums();
}

async function deleteAlbum() {
  const album = albumPendingDelete.value;
  albumPendingDelete.value = null;
  await creatorApi.deleteAlbum(album.id);
  loadAlbums();
}

onMounted(loadAlbums);
</script>
