<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <form class="space-y-3" @submit.prevent="save">
        <h3 class="font-heading font-semibold text-lg mb-2">{{ isEditing ? 'Edit Campaign' : 'Create Campaign' }}</h3>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Type</label>
          <select v-model="form.type" required :disabled="isEditing" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none disabled:opacity-60">
            <option value="banner">Banner</option>
            <option value="interstitial">Interstitial</option>
            <option value="audio">Audio</option>
            <option value="sponsored_playlist">Sponsored Playlist</option>
            <option value="sponsored_artist">Sponsored Artist</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Headline</label>
          <input v-model="form.headline" type="text" required maxlength="255" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Body</label>
          <textarea v-model="form.body" rows="2" maxlength="1000" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-ink-muted mb-1">Call-to-action label</label>
            <input v-model="form.cta_label" type="text" maxlength="60" placeholder="Learn More" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Call-to-action URL</label>
            <input v-model="form.cta_url" type="url" placeholder="https://…" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-ink-muted mb-1">Starts</label>
            <input v-model="form.starts_at" type="date" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
          <div>
            <label class="block text-xs text-ink-muted mb-1">Ends</label>
            <input v-model="form.ends_at" type="date" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
          </div>
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">Daily impression cap</label>
          <input v-model.number="form.daily_impression_cap" type="number" min="1" placeholder="Unlimited" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>

        <div v-if="isEditing">
          <label class="block text-xs text-ink-muted mb-1">Status</label>
          <select v-model="form.status" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-ink-muted mb-1">{{ isEditing ? 'Replace image' : 'Image' }}</label>
          <input type="file" accept="image/*" class="text-sm" @change="image = $event.target.files?.[0] ?? null" />
        </div>

        <div v-if="form.type === 'audio'">
          <label class="block text-xs text-ink-muted mb-1">{{ isEditing ? 'Replace audio' : 'Audio' }}</label>
          <input type="file" accept="audio/*" class="text-sm" @change="audio = $event.target.files?.[0] ?? null" />
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
import { advertiserApi } from '@/services/advertiserApi';
import { useModalA11y } from '@/composables/useModalA11y';

const props = defineProps({
  campaign: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);
useModalA11y(() => emit('close'));

const isEditing = computed(() => props.campaign !== null);
const isSaving = ref(false);
const error = ref('');
const image = ref(null);
const audio = ref(null);

const form = reactive({
  type: props.campaign?.type ?? 'banner',
  status: props.campaign?.status ?? 'draft',
  headline: props.campaign?.headline ?? '',
  body: props.campaign?.body ?? '',
  cta_label: props.campaign?.cta_label ?? '',
  cta_url: props.campaign?.cta_url ?? '',
  starts_at: props.campaign?.starts_at?.slice(0, 10) ?? '',
  ends_at: props.campaign?.ends_at?.slice(0, 10) ?? '',
  daily_impression_cap: props.campaign?.daily_impression_cap ?? null,
});

async function save() {
  isSaving.value = true;
  error.value = '';

  const payload = {
    headline: form.headline,
    body: form.body || null,
    cta_label: form.cta_label || null,
    cta_url: form.cta_url || null,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
    daily_impression_cap: form.daily_impression_cap || null,
    image: image.value,
    audio: audio.value,
  };

  try {
    let result;
    if (isEditing.value) {
      result = await advertiserApi.updateCampaign(props.campaign.id, { ...payload, status: form.status });
    } else {
      result = await advertiserApi.createCampaign({ ...payload, type: form.type });
    }
    emit('saved', result);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'Could not save this campaign.');
  } finally {
    isSaving.value = false;
  }
}
</script>
