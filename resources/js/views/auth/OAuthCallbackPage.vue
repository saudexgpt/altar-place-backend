<template>
  <div class="min-h-screen flex items-center justify-center bg-navy-950 text-ink">
    <div class="text-center">
      <p v-if="error" class="text-danger mb-2">{{ error }}</p>
      <p class="text-ink-muted">{{ error ? '' : 'Signing you in…' }}</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const error = ref('');

onMounted(async () => {
  const token = route.query.token;
  const oauthError = route.query.error;

  if (oauthError) {
    error.value = 'Sign-in was cancelled or failed. Please try again.';
    setTimeout(() => router.replace({ name: 'login' }), 2000);
    return;
  }

  if (!token) {
    error.value = 'Missing sign-in token.';
    setTimeout(() => router.replace({ name: 'login' }), 2000);
    return;
  }

  try {
    await api.post('/auth/social/exchange', { token });
    await auth.fetchCurrentUser();
    router.replace(auth.isStaff() ? { name: 'admin.dashboard' } : { name: 'listener.home' });
  } catch {
    error.value = 'This sign-in link is invalid or has expired.';
    setTimeout(() => router.replace({ name: 'login' }), 2000);
  }
});
</script>
