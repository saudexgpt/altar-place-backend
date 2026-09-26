<template>
  <AuthSplitLayout>
    <h2 class="text-2xl font-heading font-semibold mb-1">Welcome Back</h2>
    <p class="text-sm text-ink-muted mb-6">Sign in to your account</p>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="relative">
        <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input
          v-model="email"
          type="email"
          required
          placeholder="Email"
          class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
      </div>

      <div class="relative">
        <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
        <input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          required
          placeholder="Password"
          class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-10 py-3 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-muted" @click="showPassword = !showPassword">
          <component :is="showPassword ? EyeOff : Eye" :size="16" />
        </button>
      </div>

      <p v-if="error" class="text-sm text-danger">{{ error }}</p>

      <div class="text-right">
        <RouterLink :to="{ name: 'forgot-password' }" class="text-xs text-gold hover:underline">Forgot Password?</RouterLink>
      </div>

      <button
        type="submit"
        :disabled="isSubmitting"
        class="w-full bg-gold text-navy-950 font-semibold rounded-full py-3 hover:bg-gold-tint transition-colors disabled:opacity-60"
      >
        {{ isSubmitting ? 'Signing in…' : 'Sign In' }}
      </button>

      <p class="text-center text-sm text-ink-muted">
        Don't have an account?
        <RouterLink :to="{ name: 'register' }" class="text-gold hover:underline">Sign Up</RouterLink>
      </p>

      <div class="flex items-center gap-3 py-2">
        <div class="flex-1 h-px bg-navy-500/50"></div>
        <span class="text-xs text-ink-muted">OR</span>
        <div class="flex-1 h-px bg-navy-500/50"></div>
      </div>

      <button type="button" class="w-full flex items-center justify-center gap-2 border border-navy-500/60 rounded-full py-2.5 text-sm hover:bg-navy-800 transition-colors" @click="socialLogin('google')">
        <img src="/images/google-icon.svg" class="w-4 h-4" alt="" /> Continue with Google
      </button>
      <button type="button" class="w-full flex items-center justify-center gap-2 border border-navy-500/60 rounded-full py-2.5 text-sm hover:bg-navy-800 transition-colors" @click="socialLogin('facebook')">
        <img src="/images/facebook-icon.svg" class="w-4 h-4" alt="" /> Continue with Facebook
      </button>
    </form>
  </AuthSplitLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Mail, Lock, Eye, EyeOff } from '@lucide/vue';
import AuthSplitLayout from '@/layouts/AuthSplitLayout.vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const isSubmitting = ref(false);
const error = ref('');

async function handleSubmit() {
  error.value = '';
  isSubmitting.value = true;

  try {
    await auth.login({ email: email.value, password: password.value });
    redirectAfterLogin();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Unable to sign in with those credentials.';
  } finally {
    isSubmitting.value = false;
  }
}

function redirectAfterLogin() {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null;
  router.replace(redirect ?? (auth.isStaff() ? { name: 'admin.dashboard' } : { name: 'listener.home' }));
}

async function socialLogin(provider) {
  const { data } = await api.get(`/auth/social/${provider}/redirect`, {
    params: { redirect_uri: `${window.location.origin}/oauth-callback` },
  });
  window.location.href = data.url;
}
</script>
