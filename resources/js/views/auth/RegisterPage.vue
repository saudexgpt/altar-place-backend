<template>
  <AuthSplitLayout>
    <h2 class="text-2xl font-heading font-semibold mb-1">Create Your Account</h2>
    <p class="text-sm text-ink-muted mb-6">Start your journey of faith &amp; music</p>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="relative">
        <User :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="name" type="text" required placeholder="Full name" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>
      <div class="relative">
        <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="email" type="email" required placeholder="Email" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>
      <div class="relative">
        <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="password" type="password" required placeholder="Password" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>
      <div class="relative">
        <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input v-model="passwordConfirmation" type="password" required placeholder="Confirm password" class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50" />
      </div>

      <p v-if="error" class="text-sm text-danger">{{ error }}</p>

      <button
        type="submit"
        :disabled="isSubmitting"
        class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60"
      >
        {{ isSubmitting ? 'Creating account…' : 'Sign Up' }}
      </button>

      <p class="text-center text-sm text-ink-muted">
        Already have an account?
        <RouterLink :to="{ name: 'login' }" class="text-gold hover:underline">Sign In</RouterLink>
      </p>
    </form>
  </AuthSplitLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { User, Mail, Lock } from '@lucide/vue';
import AuthSplitLayout from '@/layouts/AuthSplitLayout.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const isSubmitting = ref(false);
const error = ref('');

async function handleSubmit() {
  error.value = '';
  isSubmitting.value = true;

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    router.replace(auth.isStaff() ? { name: 'admin.dashboard' } : { name: 'listener.home' });
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors)[0][0] : 'Unable to create your account.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>
