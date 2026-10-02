<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <form class="space-y-3" @submit.prevent="save">
        <h3 class="font-heading font-semibold text-lg mb-2">Upload Resource</h3>
        <p class="text-xs text-ink-muted -mt-2 mb-2">Published as "Altar Place" and made available to every listener on web and mobile.</p>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Title</label>
          <input v-model="form.title" type="text" required maxlength="255" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-ink-muted mb-1">Type</label>
            <select v-model="form.type" required class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none">
              <option v-for="option in typeOptions" :key="option" :value="option">{{ typeLabels[option] }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Genre</label>
            <select v-model="form.genre_id" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none">
              <option :value="null">None</option>
              <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-ink-muted mb-1">Language</label>
            <input v-model="form.language" type="text" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Release date</label>
            <input v-model="form.release_date" type="date" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Tags (comma separated)</label>
          <input v-model="tagsInput" type="text" placeholder="worship, live, acoustic" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_explicit" type="checkbox" />
          Explicit content
        </label>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Audio file (mp3, wav, m4a, ogg, aac — max 50MB)</label>
          <input type="file" accept="audio/*" required class="text-sm" @change="audio = $event.target.files?.[0] ?? null" />
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Cover image</label>
          <input type="file" accept="image/*" class="text-sm" @change="cover = $event.target.files?.[0] ?? null" />
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="$emit('close')">Cancel</button>
          <button type="submit" :disabled="isSaving" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50">
            {{ isSaving ? 'Uploading…' : 'Upload' }}
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
  // Which `type` values this library's upload button may create, and which
  // one the form starts on — Music Library passes just ['music'], Word
  // Library passes ['sermon', 'podcast'].
  typeOptions: { type: Array, required: true },
});

const emit = defineEmits(['close', 'uploaded']);
useModalA11y(() => emit('close'));

const typeLabels = { music: 'Music', podcast: 'Podcast', sermon: 'Sermon' };

const isSaving = ref(false);
const error = ref('');
const genres = ref([]);
const audio = ref(null);
const cover = ref(null);
const tagsInput = ref('');

const form = reactive({
  title: '',
  description: '',
  type: props.typeOptions[0],
  genre_id: null,
  language: 'English',
  release_date: '',
  is_explicit: false,
});

async function save() {
  isSaving.value = true;
  error.value = '';

  const tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean);

  try {
    const created = await adminApi.uploadTrack({
      title: form.title,
      description: form.description || null,
      type: form.type,
      genre_id: form.genre_id,
      language: form.language || null,
      release_date: form.release_date || null,
      is_explicit: form.is_explicit ? 1 : 0,
      tags,
      audio: audio.value,
      cover: cover.value,
    });
    emit('uploaded', created);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'Could not upload this resource.');
  } finally {
    isSaving.value = false;
  }
}

onMounted(async () => {
  try {
    genres.value = await catalogApi.genres();
  } catch {
    genres.value = [];
  }
});
</script>
