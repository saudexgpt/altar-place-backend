<template>
  <div class="bg-navy-950 text-ink min-h-screen flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-md">
      <div class="w-14 h-14 rounded-2xl bg-gold/15 flex items-center justify-center mx-auto mb-6">
        <Megaphone :size="24" class="text-gold" />
      </div>
      <h1 class="text-2xl font-heading font-semibold text-center mb-2">Become an Advertiser</h1>
      <p class="text-sm text-ink-muted text-center mb-8">
        Set up your advertiser account to run campaigns on AltarPlace.
      </p>

      <form class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-xs text-ink-muted mb-1">Company / organization name</label>
          <input
            v-model="companyName"
            type="text"
            required
            maxlength="255"
            placeholder="e.g. Grace Media Group"
            class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
          />
        </div>
        <div>
          <label class="block text-xs text-ink-muted mb-1">Website (optional)</label>
          <input
            v-model="website"
            type="url"
            maxlength="255"
            placeholder="https://example.com"
            class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
          />
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60"
        >
          {{ isSubmitting ? 'Setting up your account…' : 'Create Advertiser Account' }}
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
import { Megaphone } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { advertiserApi } from '@/services/advertiserApi';

const auth = useAuthStore();
const router = useRouter();

const companyName = ref('');
const website = ref('');
const isSubmitting = ref(false);
const error = ref('');

async function submit() {
  isSubmitting.value = true;
  error.value = '';

  try {
    const result = await advertiserApi.apply({ company_name: companyName.value, website: website.value || null });
    auth.setUser(result.user);
    router.replace({ name: 'advertiser.dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not create your advertiser account.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>
