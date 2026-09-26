<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-lg bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <h3 class="font-heading font-semibold text-lg mb-4">{{ isNew ? 'Create Playlist' : 'Manage Playlist' }}</h3>

      <form class="space-y-3" @submit.prevent="save">
        <div>
          <label class="block text-xs text-ink-muted mb-1">Title</label>
          <input v-model="form.title" type="text" required class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>
        <div>
          <label class="block text-xs text-ink-muted mb-1">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>
        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_public" type="checkbox" /> Public
          </label>
          <label v-if="auth.isStaff()" class="flex items-center gap-2 text-sm">
            <input v-model="form.is_curated" type="checkbox" /> Curated / Featured
          </label>
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <div class="flex justify-between items-center pt-2">
          <div v-if="!isNew && confirmingDelete" class="flex items-center gap-2 text-sm">
            <span class="text-ink-muted">Delete for good?</span>
            <button type="button" class="text-danger font-semibold hover:underline" @click="destroy">Yes, delete</button>
            <button type="button" class="text-ink-muted hover:text-white" @click="confirmingDelete = false">Cancel</button>
          </div>
          <button v-else-if="!isNew" type="button" class="text-sm text-danger hover:underline" @click="confirmingDelete = true">Delete playlist</button>
          <div class="flex gap-3 ml-auto">
            <button type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="$emit('close')">Cancel</button>
            <button type="submit" :disabled="isSaving" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50">
              {{ isSaving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </form>

      <!-- Track management, only for existing playlists -->
      <div v-if="!isNew" class="border-t border-navy-500/40 mt-5 pt-4">
        <h4 class="text-sm font-semibold mb-2">Tracks ({{ tracks.length }})</h4>

        <ul class="space-y-2 mb-3 max-h-48 overflow-y-auto">
          <li v-for="track in tracks" :key="track.id" class="flex items-center justify-between text-sm bg-navy-700/50 rounded-lg px-3 py-2">
            <span class="truncate">{{ track.title }} <span class="text-ink-muted">· {{ track.artist?.name }}</span></span>
            <button type="button" class="text-danger hover:underline text-xs shrink-0 ml-2" @click="removeTrack(track)">Remove</button>
          </li>
          <li v-if="!tracks.length" class="text-sm text-ink-muted">No tracks yet.</li>
        </ul>

        <div class="relative">
          <input
            v-model="trackQuery"
            type="search"
            placeholder="Search a track to add…"
            class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
            @input="debouncedSearch"
          />
          <ul v-if="searchResults.length" class="absolute z-10 w-full bg-navy-700 border border-navy-500/60 rounded-lg mt-1 max-h-40 overflow-y-auto">
            <li
              v-for="track in searchResults"
              :key="track.id"
              class="px-3 py-2 text-sm hover:bg-navy-600 cursor-pointer"
              @click="addTrack(track)"
            >
              {{ track.title }} <span class="text-ink-muted">· {{ track.artist?.name }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { playlistApi } from '@/services/playlistApi';
import { useAuthStore } from '@/stores/auth';
import { useModalA11y } from '@/composables/useModalA11y';

const props = defineProps({
  playlist: { type: Object, default: null }, // null = creating new
});

const emit = defineEmits(['close', 'saved', 'deleted']);
useModalA11y(() => emit('close'));

const auth = useAuthStore();
const isNew = !props.playlist;
const isSaving = ref(false);
const error = ref('');
const confirmingDelete = ref(false);
const tracks = ref(props.playlist?.tracks ?? []);
const trackQuery = ref('');
const searchResults = ref([]);
let debounceTimer = null;

const form = reactive({
  title: props.playlist?.title ?? '',
  description: props.playlist?.description ?? '',
  is_public: props.playlist?.is_public ?? true,
  is_curated: props.playlist?.is_curated ?? false,
});

async function save() {
  isSaving.value = true;
  error.value = '';

  try {
    const saved = isNew
      ? await playlistApi.create(form)
      : await playlistApi.update(props.playlist.id, form);
    emit('saved', saved);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not save this playlist.';
  } finally {
    isSaving.value = false;
  }
}

async function destroy() {
  try {
    await playlistApi.destroy(props.playlist.id);
    emit('deleted', props.playlist.id);
  } catch (e) {
    confirmingDelete.value = false;
    error.value = e.response?.data?.message ?? 'Could not delete this playlist.';
  }
}

async function addTrack(track) {
  try {
    const updated = await playlistApi.addTrack(props.playlist.id, track.id);
    tracks.value = updated.tracks;
    trackQuery.value = '';
    searchResults.value = [];
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not add that track.';
  }
}

async function removeTrack(track) {
  try {
    const updated = await playlistApi.removeTrack(props.playlist.id, track.id);
    tracks.value = updated.tracks;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not remove that track.';
  }
}

function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(async () => {
    try {
      searchResults.value = await playlistApi.searchTracks(trackQuery.value);
    } catch {
      searchResults.value = [];
    }
  }, 300);
}
</script>
