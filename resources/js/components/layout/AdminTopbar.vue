<template>
  <header class="h-16 shrink-0 flex items-center gap-4 px-6 border-b border-navy-500/60 bg-navy-950/80 backdrop-blur sticky top-0 z-10">
    <div class="flex-1 max-w-md relative">
      <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
      <input
        v-model="searchQuery"
        type="search"
        placeholder="Search songs, artists, sermons, playlists…"
        class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        @input="debouncedSearch"
        @focus="showSearchResults = true"
      />

      <div
        v-if="showSearchResults && (searchResults.tracks?.length || searchResults.playlists?.length)"
        class="absolute z-20 w-full bg-navy-800 border border-navy-500/60 rounded-xl mt-1 max-h-80 overflow-y-auto shadow-xl"
      >
        <div v-if="searchResults.tracks?.length" class="p-2">
          <p class="text-xs text-ink-muted px-2 py-1 uppercase tracking-wide">Tracks</p>
          <button
            v-for="track in searchResults.tracks"
            :key="track.id"
            type="button"
            class="w-full flex items-center gap-2 text-left px-2 py-2 rounded-lg hover:bg-navy-700 text-sm"
            @click="playTrack(track)"
          >
            <Music :size="14" class="text-ink-muted shrink-0" />
            <span class="truncate">{{ track.title }} <span class="text-ink-muted">· {{ track.artist?.name }}</span></span>
          </button>
        </div>
        <div v-if="searchResults.playlists?.length" class="p-2 border-t border-navy-500/40">
          <p class="text-xs text-ink-muted px-2 py-1 uppercase tracking-wide">Playlists</p>
          <button
            v-for="playlist in searchResults.playlists"
            :key="playlist.id"
            type="button"
            class="w-full flex items-center gap-2 text-left px-2 py-2 rounded-lg hover:bg-navy-700 text-sm"
            @click="goToPlaylists"
          >
            <ListMusic :size="14" class="text-ink-muted shrink-0" />
            <span class="truncate">{{ playlist.title }}</span>
          </button>
        </div>
      </div>
    </div>

    <div class="relative">
      <button
        type="button"
        aria-label="Notifications"
        class="relative w-9 h-9 rounded-full flex items-center justify-center text-ink-muted hover:bg-navy-700 hover:text-white transition-colors"
        @click="toggleNotifications"
      >
        <Bell :size="18" />
        <span v-if="unreadCount" class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-danger text-[10px] font-bold flex items-center justify-center text-white">
          {{ unreadCount }}
        </span>
      </button>

      <div v-if="showNotifications" class="absolute right-0 z-20 mt-2 w-80 bg-navy-800 border border-navy-500/60 rounded-xl shadow-xl max-h-96 overflow-y-auto">
        <div class="flex items-center justify-between px-4 py-3 border-b border-navy-500/40">
          <p class="text-sm font-semibold">Notifications</p>
          <button v-if="unreadCount" type="button" class="text-xs text-gold hover:underline" @click="markAllRead">Mark all read</button>
        </div>
        <div v-for="item in notifications" :key="item.id" class="px-4 py-3 border-b border-navy-500/20 last:border-0" :class="{ 'bg-navy-700/30': !item.read_at }">
          <p class="text-sm">{{ item.data?.message ?? 'New notification' }}</p>
          <p class="text-xs text-ink-muted mt-1">{{ relativeTime(item.created_at) }}</p>
        </div>
        <p v-if="notificationsError" class="text-sm text-danger text-center py-6">{{ notificationsError }}</p>
        <p v-else-if="!notifications.length" class="text-sm text-ink-muted text-center py-6">No notifications yet.</p>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-9 h-9 rounded-full object-cover" alt="" />
      <div v-else class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-xs font-semibold">
        {{ initials }}
      </div>
      <div class="hidden sm:block leading-tight">
        <p class="text-sm font-medium">{{ auth.user?.name }}</p>
        <p class="text-xs text-ink-muted">{{ roleLabel }}</p>
      </div>
      <ChevronDown :size="16" class="text-ink-muted" />
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Search, Bell, ChevronDown, Music, ListMusic } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { usePlayerStore } from '@/stores/player';
import { notificationApi } from '@/services/notificationApi';
import api from '@/services/api';

const auth = useAuthStore();
const player = usePlayerStore();
const router = useRouter();

const roleLabel = computed(() => {
  if (auth.hasRole('super-admin')) return 'Super Administrator';
  if (auth.hasRole('moderator')) return 'Moderator';
  return auth.roles[0] ?? 'Staff';
});

const initials = computed(() =>
  (auth.user?.name ?? '')
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
);

// --- Search ---
const searchQuery = ref('');
const searchResults = ref({});
const showSearchResults = ref(false);
let searchDebounce = null;

function debouncedSearch() {
  clearTimeout(searchDebounce);
  if (!searchQuery.value.trim()) {
    searchResults.value = {};
    return;
  }
  searchDebounce = setTimeout(async () => {
    try {
      const { data } = await api.get('/search', { params: { q: searchQuery.value } });
      searchResults.value = data;
    } catch {
      // A failed search just shows no results — same as a genuine
      // no-match, which is an acceptable degradation for a topbar search.
      searchResults.value = {};
    }
  }, 300);
}

function playTrack(track) {
  player.playQueue([track], 0);
  showSearchResults.value = false;
  searchQuery.value = '';
}

function goToPlaylists() {
  router.push({ name: 'admin.playlists' });
  showSearchResults.value = false;
  searchQuery.value = '';
}

function closeSearchOnOutsideClick(event) {
  if (!event.target.closest('.relative')) showSearchResults.value = false;
}

// --- Notifications ---
const notifications = ref([]);
const unreadCount = ref(0);
const showNotifications = ref(false);
const notificationsError = ref('');
let pollTimer = null;

async function loadNotifications() {
  try {
    const result = await notificationApi.list();
    notifications.value = result.data;
    unreadCount.value = result.unread_count;
    notificationsError.value = '';
  } catch {
    // Leave the last-known unread count/list in place — polling every 60s
    // means a single blip shouldn't wipe out real data the user could
    // still see; only surface an error once they open the dropdown.
    notificationsError.value = 'Could not load notifications.';
  }
}

function toggleNotifications() {
  showNotifications.value = !showNotifications.value;
}

async function markAllRead() {
  await notificationApi.markAllAsRead();
  await loadNotifications();
}

function relativeTime(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.floor(hours / 24)}d ago`;
}

onMounted(() => {
  loadNotifications();
  pollTimer = setInterval(loadNotifications, 60000);
  document.addEventListener('click', closeSearchOnOutsideClick);
});

onUnmounted(() => {
  clearInterval(pollTimer);
  document.removeEventListener('click', closeSearchOnOutsideClick);
});
</script>
