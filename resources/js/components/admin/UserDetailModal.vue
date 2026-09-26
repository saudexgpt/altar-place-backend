<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md bg-navy-800 border border-navy-500/60 rounded-2xl p-6 max-h-[85vh] overflow-y-auto">
      <div v-if="isLoading" class="py-10 text-center text-ink-muted">Loading…</div>
      <div v-else-if="loadFailed" class="py-10 text-center">
        <p class="text-sm text-danger mb-3">Could not load this user. Check your connection and try again.</p>
        <button type="button" class="text-sm text-ink-muted hover:text-white" @click="$emit('close')">Close</button>
      </div>

      <template v-else-if="detail">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-sm font-semibold shrink-0">
            {{ initialsFor(detail.user.name) }}
          </div>
          <div class="min-w-0">
            <p class="font-heading font-semibold truncate">{{ detail.user.name }}</p>
            <p class="text-xs text-ink-muted truncate">{{ detail.user.email }}</p>
          </div>
        </div>

        <div class="space-y-4 text-sm">
          <div>
            <p class="text-xs text-ink-muted uppercase tracking-wide mb-2">Roles</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="role in assignableRoles"
                :key="role"
                type="button"
                class="px-3 py-1 rounded-full text-xs font-medium border transition-colors"
                :class="detail.user.roles.includes(role)
                  ? 'bg-gold text-navy-950 border-gold'
                  : 'border-navy-500/60 text-ink-muted hover:border-gold/50'"
                :disabled="isUpdatingRole"
                @click="toggleRole(role)"
              >
                {{ role }}
              </button>
              <span v-if="detail.user.roles.includes('super-admin')" class="px-3 py-1 rounded-full text-xs font-medium bg-navy-500/40 text-ink-muted">
                super-admin (locked)
              </span>
            </div>
          </div>

          <div>
            <p class="text-xs text-ink-muted uppercase tracking-wide mb-1">Subscription</p>
            <p v-if="detail.subscription">{{ detail.subscription.plan }} · {{ detail.subscription.status }}</p>
            <p v-else class="text-ink-muted">Free plan</p>
          </div>

          <div v-if="detail.artist">
            <p class="text-xs text-ink-muted uppercase tracking-wide mb-1">Creator Profile</p>
            <p>{{ detail.artist.name }} · {{ detail.artist.tracks_count }} tracks · {{ detail.artist.followers_count }} followers</p>
          </div>

          <div v-if="detail.advertiser">
            <p class="text-xs text-ink-muted uppercase tracking-wide mb-1">Advertiser Profile</p>
            <p>{{ detail.advertiser.company_name }} · {{ detail.advertiser.campaigns_count }} campaigns</p>
          </div>
        </div>

        <button type="button" class="w-full mt-6 text-sm text-ink-muted hover:text-white border border-navy-500/60 rounded-full py-2" @click="$emit('close')">
          Close
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { adminApi } from '@/services/adminApi';
import { useModalA11y } from '@/composables/useModalA11y';

const props = defineProps({
  userId: { type: Number, required: true },
});

const emit = defineEmits(['close', 'updated']);
useModalA11y(() => emit('close'));

const assignableRoles = ['listener', 'creator', 'advertiser', 'moderator'];
const detail = ref(null);
const isLoading = ref(true);
const loadFailed = ref(false);
const isUpdatingRole = ref(false);

function initialsFor(name) {
  return (name ?? '').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

async function toggleRole(role) {
  isUpdatingRole.value = true;
  try {
    const action = detail.value.user.roles.includes(role) ? 'remove' : 'assign';
    const updatedUser = await adminApi.updateUserRole(detail.value.user.id, role, action);
    detail.value.user = updatedUser;
    emit('updated', updatedUser);
  } finally {
    isUpdatingRole.value = false;
  }
}

onMounted(async () => {
  try {
    detail.value = await adminApi.userDetail(props.userId);
  } catch {
    loadFailed.value = true;
  } finally {
    isLoading.value = false;
  }
});
</script>
