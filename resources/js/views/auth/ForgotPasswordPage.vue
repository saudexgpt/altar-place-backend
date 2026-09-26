<template>
  <AuthSplitLayout>
    <h2 class="text-2xl font-heading font-semibold mb-1">Forgot Password</h2>
    <p class="text-sm text-ink-muted mb-6">We'll email you a link to reset it.</p>

    <form v-if="!sent" class="space-y-4" @submit.prevent="handleSubmit">
      <div class="relative">
        <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="email" type="email" required placeholder="Email" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>

      <p v-if="error" class="text-sm text-danger">{{ error }}</p>

      <button type="submit" :disabled="isSubmitting" class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60">
        {{ isSubmitting ? 'Sending…' : 'Send Reset Link' }}
      </button>
    </form>

    <p v-else class="text-sm text-ink-muted">
      If an account exists for that email, a reset link is on its way — check your inbox.
    </p>

    <p class="text-center text-sm text-ink-muted mt-6">
      <RouterLink :to="{ name: 'login' }" class="text-gold hover:underline">Back to Sign In</RouterLink>
    </p>
  </AuthSplitLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Mail } from '@lucide/vue';
import AuthSplitLayout from '@/layouts/AuthSplitLayout.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const email = ref('');
const isSubmitting = ref(false);
const error = ref('');
const sent = ref(false);

async function handleSubmit() {
  error.value = '';
  isSubmitting.value = true;

  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>
