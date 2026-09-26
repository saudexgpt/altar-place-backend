<template>
  <AuthSplitLayout>
    <h2 class="text-2xl font-heading font-semibold mb-1">Set a New Password</h2>
    <p class="text-sm text-ink-muted mb-6">Choose a new password for your account.</p>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="relative">
        <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="password" type="password" required placeholder="New password" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>
      <div class="relative">
        <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="passwordConfirmation" type="password" required placeholder="Confirm new password" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>

      <p v-if="error" class="text-sm text-danger">{{ error }}</p>

      <button type="submit" :disabled="isSubmitting" class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60">
        {{ isSubmitting ? 'Saving…' : 'Reset Password' }}
      </button>
    </form>
  </AuthSplitLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Lock } from '@lucide/vue';
import AuthSplitLayout from '@/layouts/AuthSplitLayout.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const password = ref('');
const passwordConfirmation = ref('');
const isSubmitting = ref(false);
const error = ref('');

async function handleSubmit() {
  error.value = '';
  isSubmitting.value = true;

  try {
    await auth.resetPassword({
      token: route.query.token,
      email: route.query.email,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    router.replace({ name: 'login' });
  } catch (e) {
    error.value = e.response?.data?.message ?? 'This reset link is invalid or has expired.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>
