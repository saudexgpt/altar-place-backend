<template>
  <div class="max-w-lg space-y-6">
    <h1 class="text-2xl font-heading font-semibold">Settings</h1>

    <!-- Profile -->
    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6 space-y-4">
      <div class="flex items-center gap-4">
        <button type="button" class="relative group shrink-0" @click="avatarInput?.click()">
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
          <div class="flex gap-1 mt-1 flex-wrap">
            <Badge v-for="role in auth.roles" :key="role" :label="role" tone="info" />
          </div>
        </div>
      </div>

      <p v-if="avatarError" class="text-sm text-danger">{{ avatarError }}</p>
      <p v-if="avatarSaved" class="text-sm text-success">Avatar updated.</p>

      <form class="border-t border-navy-500/40 pt-4 space-y-3" @submit.prevent="saveProfile">
        <label class="block text-xs text-ink-muted mb-1">Full name</label>
        <input
          v-model="name"
          type="text"
          class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <p v-if="profileError" class="text-sm text-danger">{{ profileError }}</p>
        <p v-if="profileSaved" class="text-sm text-success">Profile updated.</p>
        <button
          type="submit"
          :disabled="isSavingProfile || name === auth.user?.name"
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

    <!-- Maintenance mode -->
    <div v-if="isSuperAdmin" class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6">
      <h2 class="font-heading font-semibold mb-1">Maintenance Mode</h2>
      <p class="text-sm text-ink-muted mb-4">
        When on, the whole platform (web and mobile) shows a maintenance notice to everyone except staff.
      </p>
      <p v-if="maintenanceStatusUnknown" class="text-sm text-danger mb-3">
        Could not check the current status. Check your connection and reload before toggling this.
      </p>
      <label v-else class="flex items-center justify-between gap-4 py-2">
        <span class="text-sm font-medium">{{ maintenanceEnabled ? 'Currently on' : 'Currently off' }}</span>
        <input
          type="checkbox"
          :checked="maintenanceEnabled"
          :disabled="isSavingMaintenance"
          class="w-5 h-5 rounded accent-danger shrink-0"
          @change="toggleMaintenance($event.target.checked)"
        />
      </label>
      <textarea
        v-model="maintenanceMessageDraft"
        rows="2"
        placeholder="Optional message shown to visitors, e.g. 'Back online at 3pm WAT.'"
        class="w-full bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50 mt-2"
      ></textarea>
      <div class="flex items-center gap-3 mt-2">
        <button
          type="button"
          :disabled="isSavingMaintenance || maintenanceStatusUnknown"
          class="text-xs font-semibold border border-navy-500/60 rounded-full px-4 py-1.5 hover:bg-navy-700 transition-colors disabled:opacity-50"
          @click="saveMaintenanceMessage"
        >
          Save message
        </button>
        <p v-if="maintenanceError" class="text-sm text-danger">{{ maintenanceError }}</p>
        <p v-if="maintenanceSaved" class="text-sm text-success">Saved.</p>
      </div>
    </div>

    <!-- Staff notifications -->
    <div v-if="isStaff" class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6">
      <h2 class="font-heading font-semibold mb-1">Staff Notifications</h2>
      <p class="text-sm text-ink-muted mb-4">Choose what shows up in your notification bell.</p>
      <label class="flex items-center justify-between gap-4 py-2">
        <span class="text-sm">
          New report filed
          <span class="block text-xs text-ink-muted">Get notified when a user reports a track or comment</span>
        </span>
        <input
          type="checkbox"
          :checked="newReportsEnabled"
          :disabled="isSavingPreferences"
          class="w-5 h-5 rounded accent-gold shrink-0"
          @change="toggleNewReports($event.target.checked)"
        />
      </label>
      <p v-if="preferencesError" class="text-sm text-danger mt-2">{{ preferencesError }}</p>
      <p v-if="preferencesSaved" class="text-sm text-success mt-2">Preference saved.</p>
    </div>

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
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Camera } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { profileApi } from '@/services/profileApi';
import { platformApi } from '@/services/platformApi';
import Badge from '@/components/ui/Badge.vue';

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

// --- Profile (name) ---
const name = ref(auth.user?.name ?? '');
watch(() => auth.user?.name, (value) => { name.value = value ?? ''; });

const isSavingProfile = ref(false);
const profileError = ref('');
const profileSaved = ref(false);

async function saveProfile() {
  isSavingProfile.value = true;
  profileError.value = '';
  profileSaved.value = false;

  try {
    auth.setUser(await profileApi.updateProfile({ name: name.value }));
    profileSaved.value = true;
    setTimeout(() => (profileSaved.value = false), 2500);
  } catch (e) {
    profileError.value = e.response?.data?.message ?? 'Could not save your changes.';
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

async function handleLogout() {
  await auth.logout();
  router.replace({ name: 'landing' });
}

// --- Maintenance mode ---
const isSuperAdmin = computed(() => auth.hasRole('super-admin'));
const maintenanceEnabled = ref(false);
const maintenanceMessageDraft = ref('');
const isSavingMaintenance = ref(false);
const maintenanceError = ref('');
const maintenanceSaved = ref(false);
const maintenanceStatusUnknown = ref(false);

async function loadMaintenanceStatus() {
  try {
    const status = await platformApi.status();
    maintenanceEnabled.value = status.maintenance_mode;
    maintenanceMessageDraft.value = status.maintenance_message ?? '';
  } catch {
    // Don't let a failed fetch silently read as "maintenance is off" —
    // that's actively misleading for the person who'd toggle it.
    maintenanceStatusUnknown.value = true;
  }
}

async function persistMaintenance(enabled) {
  isSavingMaintenance.value = true;
  maintenanceError.value = '';
  maintenanceSaved.value = false;

  try {
    const result = await platformApi.updateMaintenance({ enabled, message: maintenanceMessageDraft.value || null });
    maintenanceEnabled.value = result.maintenance_mode;
    maintenanceSaved.value = true;
    setTimeout(() => (maintenanceSaved.value = false), 2500);
  } catch (e) {
    maintenanceError.value = e.response?.data?.message ?? 'Could not update maintenance mode.';
  } finally {
    isSavingMaintenance.value = false;
  }
}

function toggleMaintenance(checked) {
  persistMaintenance(checked);
}

function saveMaintenanceMessage() {
  persistMaintenance(maintenanceEnabled.value);
}

onMounted(() => {
  if (isSuperAdmin.value) {
    loadMaintenanceStatus();
  }
});

// --- Staff notification preferences ---
const isStaff = computed(() => auth.isStaff());
const newReportsEnabled = computed(() => auth.user?.notification_preferences?.new_reports ?? true);
const isSavingPreferences = ref(false);
const preferencesError = ref('');
const preferencesSaved = ref(false);

async function toggleNewReports(checked) {
  isSavingPreferences.value = true;
  preferencesError.value = '';
  preferencesSaved.value = false;

  try {
    auth.setUser(await profileApi.updateNotificationPreferences({ new_reports: checked }));
    preferencesSaved.value = true;
    setTimeout(() => (preferencesSaved.value = false), 2500);
  } catch (e) {
    preferencesError.value = e.response?.data?.message ?? 'Could not save that preference.';
  } finally {
    isSavingPreferences.value = false;
  }
}
</script>
