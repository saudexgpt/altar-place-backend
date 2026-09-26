<template>
  <div class="max-w-lg space-y-6">
    <h1 class="text-2xl font-heading font-semibold">Profile</h1>

    <!-- Profile -->
    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6 space-y-4">
      <div class="flex items-center gap-4">
        <button type="button" class="relative group shrink-0" aria-label="Change avatar" @click="avatarInput?.click()">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-16 h-16 rounded-full object-cover" alt="" />
          <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-lg font-semibold">
            {{ initials }}
          </div>
          <div class="absolute inset-0 rounded-full bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <Camera :size="18" />
          </div>
        </button>
        <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="handleAvatarChange" />

        <div class="min-w-0">
          <p class="font-heading font-semibold truncate">{{ auth.user?.name }}</p>
          <p class="text-sm text-ink-muted truncate">{{ auth.user?.email }}</p>
        </div>
      </div>

      <p v-if="avatarError" class="text-sm text-danger">{{ avatarError }}</p>
      <p v-if="avatarSaved" class="text-sm text-success">Avatar updated.</p>

      <form class="border-t border-navy-500/40 pt-4 space-y-3" @submit.prevent="saveProfile">
        <div>
          <label class="block text-xs text-ink-muted mb-1">Full name</label>
          <input v-model="form.name" type="text" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>
        <div>
          <label class="block text-xs text-ink-muted mb-1">Username</label>
          <input v-model="form.username" type="text" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>
        <div>
          <label class="block text-xs text-ink-muted mb-1">Bio</label>
          <textarea v-model="form.bio" rows="2" class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50" />
        </div>
        <p v-if="profileError" class="text-sm text-danger">{{ profileError }}</p>
        <p v-if="profileSaved" class="text-sm text-success">Profile updated.</p>
        <button
          type="submit"
          :disabled="isSavingProfile"
          class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50"
        >
          {{ isSavingProfile ? 'Saving…' : 'Save changes' }}
        </button>
      </form>
    </div>

    <!-- Password -->
    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6">
      <h2 class="font-heading font-semibold mb-4">Change Password</h2>
      <form class="space-y-3" @submit.prevent="changePassword">
        <input
          v-model="currentPassword"
          type="password"
          required
          placeholder="Current password"
          class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <input
          v-model="newPassword"
          type="password"
          required
          placeholder="New password"
          class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <input
          v-model="newPasswordConfirmation"
          type="password"
          required
          placeholder="Confirm new password"
          class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <p v-if="passwordError" class="text-sm text-danger">{{ passwordError }}</p>
        <p v-if="passwordSaved" class="text-sm text-success">Password updated.</p>
        <button
          type="submit"
          :disabled="isSavingPassword"
          class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors disabled:opacity-50"
        >
          {{ isSavingPassword ? 'Saving…' : 'Update password' }}
        </button>
      </form>
    </div>

    <!-- Notification preferences -->
    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6">
      <h2 class="font-heading font-semibold mb-4">Notifications</h2>
      <div class="space-y-3">
        <label v-for="pref in notificationPrefs" :key="pref.key" class="flex items-center justify-between gap-4">
          <span class="text-sm">{{ pref.label }}</span>
          <input
            type="checkbox"
            :checked="preferences[pref.key]"
            class="w-5 h-5 rounded accent-gold"
            @change="togglePreference(pref.key, $event.target.checked)"
          />
        </label>
      </div>
      <p v-if="preferencesSaved" class="text-sm text-success mt-3">Saved.</p>
    </div>

    <SubscriptionSection />

    <button
      type="button"
      class="w-full text-sm font-semibold text-danger border border-danger/40 rounded-full py-2.5 hover:bg-danger/10 transition-colors"
      @click="handleLogout"
    >
      Log out
    </button>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Camera } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { profileApi } from '@/services/profileApi';
import SubscriptionSection from '@/components/listener/SubscriptionSection.vue';

const auth = useAuthStore();
const router = useRouter();

const initials = computed(() =>
  (auth.user?.name ?? '').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()
);

// --- Avatar ---
const avatarInput = ref(null);
const avatarError = ref('');
const avatarSaved = ref(false);

async function handleAvatarChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  avatarError.value = '';
  avatarSaved.value = false;

  try {
    auth.setUser(await profileApi.uploadAvatar(file));
    avatarSaved.value = true;
    setTimeout(() => (avatarSaved.value = false), 2500);
  } catch (e) {
    avatarError.value = e.response?.data?.message ?? 'Could not upload that image.';
  } finally {
    event.target.value = '';
  }
}

// --- Profile ---
const form = reactive({
  name: auth.user?.name ?? '',
  username: auth.user?.username ?? '',
  bio: auth.user?.bio ?? '',
});

const isSavingProfile = ref(false);
const profileError = ref('');
const profileSaved = ref(false);

async function saveProfile() {
  isSavingProfile.value = true;
  profileError.value = '';
  profileSaved.value = false;

  try {
    auth.setUser(await profileApi.updateProfile({ ...form }));
    profileSaved.value = true;
    setTimeout(() => (profileSaved.value = false), 2500);
  } catch (e) {
    const errors = e.response?.data?.errors;
    profileError.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'Could not save your changes.');
  } finally {
    isSavingProfile.value = false;
  }
}

// --- Password ---
const currentPassword = ref('');
const newPassword = ref('');
const newPasswordConfirmation = ref('');
const isSavingPassword = ref(false);
const passwordError = ref('');
const passwordSaved = ref(false);

async function changePassword() {
  isSavingPassword.value = true;
  passwordError.value = '';
  passwordSaved.value = false;

  try {
    await profileApi.changePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPasswordConfirmation.value,
    });
    passwordSaved.value = true;
    currentPassword.value = '';
    newPassword.value = '';
    newPasswordConfirmation.value = '';
    setTimeout(() => (passwordSaved.value = false), 2500);
  } catch (e) {
    const errors = e.response?.data?.errors;
    passwordError.value = errors ? Object.values(errors)[0][0] : 'Could not update your password.';
  } finally {
    isSavingPassword.value = false;
  }
}

// --- Notification preferences ---
const notificationPrefs = [
  { key: 'new_releases', label: 'New releases from followed artists' },
  { key: 'followed_artist_uploads', label: 'Uploads from artists you follow' },
  { key: 'comments_and_likes', label: 'Comments and likes on your activity' },
  { key: 'email_digest', label: 'Weekly email digest' },
];
const preferences = reactive({ ...auth.user?.notification_preferences });
const preferencesSaved = ref(false);

async function togglePreference(key, checked) {
  preferences[key] = checked;
  auth.setUser(await profileApi.updateNotificationPreferences({ [key]: checked }));
  preferencesSaved.value = true;
  setTimeout(() => (preferencesSaved.value = false), 2000);
}

async function handleLogout() {
  await auth.logout();
  router.replace({ name: 'landing' });
}
</script>
