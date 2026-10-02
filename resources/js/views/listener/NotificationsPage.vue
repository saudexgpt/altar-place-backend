<template>
  <div class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-heading font-semibold">Notifications</h1>
      <button v-if="notifications.unreadCount > 0" type="button" class="text-xs text-gold hover:underline" @click="notifications.markAllAsRead">
        Mark all read
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <button
        v-for="item in notifications.notifications"
        :key="item.id"
        type="button"
        class="w-full flex items-start gap-3 py-3 px-3 rounded-lg text-left border-b border-navy-500/20 last:border-0"
        :class="{ 'bg-navy-800/60': !item.read_at }"
        @click="notifications.markAsRead(item.id)"
      >
        <span v-if="!item.read_at" class="w-2 h-2 rounded-full bg-gold mt-1.5 shrink-0" />
        <div class="min-w-0">
          <p class="text-sm">{{ item.data.message ?? 'You have a new notification.' }}</p>
          <p class="text-xs text-ink-muted mt-0.5">{{ relativeTime(item.created_at) }}</p>
        </div>
      </button>

      <p v-if="!isLoading && !notifications.notifications.length" class="text-sm text-ink-muted py-10 text-center">
        You're all caught up. New followers and comments will show up here.
      </p>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import LoadError from '@/components/ui/LoadError.vue';

const notifications = useNotificationsStore();
const isLoading = ref(true);
const loadError = ref('');

function relativeTime(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
}

async function load() {
  isLoading.value = true;
  loadError.value = '';
  try {
    await notifications.refresh();
  } catch {
    loadError.value = 'Could not load notifications. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

onMounted(load);
</script>
