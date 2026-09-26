<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <div v-if="isLoading" class="py-10 text-center text-ink-muted">Loading…</div>

      <form v-else class="space-y-3" @submit.prevent="save">
        <h3 class="font-heading font-semibold text-lg mb-2">Edit Track</h3>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Title</label>
          <input v-model="form.title" type="text" required class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-ink-muted mb-1">Genre</label>
            <select v-model="form.genre_id" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none">
              <option :value="null">None</option>
              <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Language</label>
            <input v-model="form.language" type="text" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_explicit" type="checkbox" />
          Explicit content
        </label>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Replace cover image</label>
          <input type="file" accept="image/*" class="text-sm" @change="cover = $event.target.files?.[0] ?? null" />
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="$emit('close')">Cancel</button>
          <button type="submit" :disabled="isSaving" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50">
            {{ isSaving ? 'Saving…' : 'Save changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { catalogApi } from '@/services/catalogApi';
import { adminApi } from '@/services/adminApi';
import { useModalA11y } from '@/composables/useModalA11y';

const props = defineProps({
  trackId: { type: Number, required: true },
});

const emit = defineEmits(['close', 'saved']);
useModalA11y(() => emit('close'));

const isLoading = ref(true);
const isSaving = ref(false);
const error = ref('');
const genres = ref([]);
const cover = ref(null);

const form = reactive({
  title: '',
  description: '',
  genre_id: null,
  language: '',
  is_explicit: false,
});

async function save() {
  isSaving.value = true;
  error.value = '';

  try {
    const updated = await adminApi.updateTrack(props.trackId, {
      title: form.title,
      description: form.description,
      genre_id: form.genre_id,
      language: form.language,
      is_explicit: form.is_explicit ? 1 : 0,
      cover: cover.value,
    });
    emit('saved', updated);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not save these changes.';
  } finally {
    isSaving.value = false;
  }
}

onMounted(async () => {
  try {
    const [track, genreList] = await Promise.all([
      catalogApi.track(props.trackId),
      catalogApi.genres(),
    ]);

    genres.value = genreList;
    form.title = track.title;
    form.description = track.description ?? '';
    form.genre_id = track.genre?.id ?? null;
    form.language = track.language ?? '';
    form.is_explicit = track.is_explicit;
  } finally {
    isLoading.value = false;
  }
});
</script>
