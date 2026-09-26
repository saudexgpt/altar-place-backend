import { defineStore } from 'pinia';
import api, { ensureCsrfCookie } from '@/services/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isInitializing: true,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.user),
    isEmailVerified: (state) => Boolean(state.user?.email_verified),
    roles: (state) => state.user?.roles ?? [],
  },

  actions: {
    hasRole(role) {
      return this.roles.includes(role);
    },

    isStaff() {
      return this.hasRole('moderator') || this.hasRole('super-admin');
    },

    async login(payload) {
      await ensureCsrfCookie();
      const { data } = await api.post('/auth/login', { ...payload, device_name: 'web' });
      this.user = data.user;
    },

    async register(payload) {
      await ensureCsrfCookie();
      const { data } = await api.post('/auth/register', { ...payload, device_name: 'web' });
      this.user = data.user;
    },

    async forgotPassword(email) {
      await ensureCsrfCookie();
      await api.post('/auth/forgot-password', { email });
    },

    async resetPassword(payload) {
      await ensureCsrfCookie();
      await api.post('/auth/reset-password', payload);
    },

    async logout() {
      try {
        await api.post('/auth/logout');
      } finally {
        this.user = null;
      }
    },

    async fetchCurrentUser() {
      try {
        const { data } = await api.get('/auth/me');
        this.user = data.data;
      } catch {
        this.user = null;
      }
    },

    async initialize() {
      this.isInitializing = true;
      await this.fetchCurrentUser();
      this.isInitializing = false;
    },

    /**
     * Called when any API request comes back 401 (an expired/invalid
     * session or token). Returns whether the user was actually signed in
     * beforehand — the same 401 fires for the harmless "am I logged in?"
     * check on app boot, which must NOT redirect a guest away from a
     * public page like the landing page.
     */
    forceLogout() {
      const wasAuthenticated = this.user !== null;
      this.user = null;
      return wasAuthenticated;
    },

    /** Lets other parts of the app sync back the shared user object after a profile update. */
    setUser(updated) {
      this.user = updated;
    },
  },
});
