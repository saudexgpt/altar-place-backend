<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <form class="space-y-3" @submit.prevent="save">
        <h3 class="font-heading font-semibold text-lg mb-2">{{ isEditing ? 'Edit Album' : 'Create Album' }}</h3>

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
              <option value="album">Album</option>
              <option value="podcast_show">Podcast Show</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Release year</label>
            <input v-model.number="form.release_year" type="number" min="1900" max="2100" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">{{ isEditing ? 'Replace cover image' : 'Cover image' }}</label>
          <input type="file" accept="image/*" class="text-sm" @change="cover = $event.target.files?.[0] ?? null" />
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="$emit('close')">Cancel</button>
          <button type="submit" :disabled="isSaving" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50">
            {{ isSaving ? 'Saving…' : isEditing ? 'Save changes' : 'Create' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { creatorApi } from '@/services/creatorApi';
import { useModalA11y } from '@/composables/useModalA11y';

const props = defineProps({
  album: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);
useModalA11y(() => emit('close'));

const isEditing = computed(() => props.album !== null);
const isSaving = ref(false);
const error = ref('');
const cover = ref(null);

const form = reactive({
  title: props.album?.title ?? '',
  description: props.album?.description ?? '',
  type: props.album?.type ?? 'album',
  release_year: props.album?.release_year ?? null,
});

async function save() {
  isSaving.value = true;
  error.value = '';

  const payload = {
    title: form.title,
    description: form.description || null,
    type: form.type,
    release_year: form.release_year || null,
    cover: cover.value,
  };

  try {
    const result = isEditing.value
      ? await creatorApi.updateAlbum(props.album.id, payload)
      : await creatorApi.createAlbum(payload);
    emit('saved', result);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'Could not save this album.');
  } finally {
    isSaving.value = false;
  }
}
</script>
