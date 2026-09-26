<template>
  <div class="bg-navy-950 text-ink min-h-screen flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-md">
      <div class="w-14 h-14 rounded-2xl bg-gold/15 flex items-center justify-center mx-auto mb-6">
        <Music :size="24" class="text-gold" />
      </div>
      <h1 class="text-2xl font-heading font-semibold text-center mb-2">Become a Creator</h1>
      <p class="text-sm text-ink-muted text-center mb-8">
        Set up your artist profile to upload worship songs and sermons to AltarPlace.
      </p>

      <form class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-xs text-ink-muted mb-1">Artist / ministry name</label>
          <input
            v-model="artistName"
            type="text"
            required
            maxlength="255"
            placeholder="e.g. Grace Chapel Choir"
            class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
          />
        </div>
        <div>
          <label class="block text-xs text-ink-muted mb-1">Bio (optional)</label>
          <textarea
            v-model="bio"
            rows="3"
            maxlength="1000"
            placeholder="A short introduction listeners will see on your profile."
            class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
          />
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60"
        >
          {{ isSubmitting ? 'Setting up your studio…' : 'Create Artist Profile' }}
        </button>
      </form>

      <RouterLink :to="{ name: 'landing' }" class="block text-center text-xs text-gold hover:underline mt-6">
        Back to AltarPlace
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Music } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { creatorApi } from '@/services/creatorApi';

const auth = useAuthStore();
const router = useRouter();

const artistName = ref('');
const bio = ref('');
const isSubmitting = ref(false);
const error = ref('');

async function submit() {
  isSubmitting.value = true;
  error.value = '';

  try {
    const result = await creatorApi.apply({ artist_name: artistName.value, bio: bio.value || null });
    auth.setUser(result.user);
    router.replace({ name: 'creator.dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not create your artist profile.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>
