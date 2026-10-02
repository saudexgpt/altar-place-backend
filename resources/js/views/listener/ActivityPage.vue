<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-heading font-semibold mb-6">My Activity</h1>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-for="item in activities" :key="item.id" class="flex items-start gap-3 py-3 border-b border-navy-500/20 last:border-0">
        <component :is="iconFor(item.type)" :size="18" class="text-gold mt-0.5 shrink-0" />
        <div class="min-w-0">
          <p class="text-sm">{{ describe(item) }}</p>
          <p class="text-xs text-ink-muted mt-0.5">{{ relativeTime(item.created_at) }}</p>
        </div>
      </div>

      <p v-if="!isLoading && !activities.length" class="text-sm text-ink-muted py-10 text-center">
        Nothing here yet. Follow an artist, favorite a track, or leave a comment to see it here.
      </p>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Heart, MessageCircle, List, Users, Share2 } from '@lucide/vue';
import { activityApi } from '@/services/activityApi';
import LoadError from '@/components/ui/LoadError.vue';

const activities = ref([]);
const isLoading = ref(true);
const loadError = ref('');

const ICONS = {
  followed_artist: Users,
  followed_playlist: Users,
  favorited_track: Heart,
  commented_on_track: MessageCircle,
  shared_track: Share2,
  shared_playlist: Share2,
  created_playlist: List,
};

function iconFor(type) {
  return ICONS[type] ?? List;
}

function subjectLabel(item) {
  if (!item.subject) return '';
  return 'title' in item.subject ? item.subject.title : item.subject.name;
}

function describe(item) {
  const label = subjectLabel(item);
  switch (item.type) {
    case 'followed_artist': return `You followed ${label}`;
    case 'followed_playlist': return `You followed the playlist "${label}"`;
    case 'favorited_track': return `You favorited "${label}"`;
    case 'commented_on_track': return `You commented on "${label}"`;
    case 'shared_track': return `You shared "${label}"`;
    case 'shared_playlist': return `You shared the playlist "${label}"`;
    case 'created_playlist': return `You created the playlist "${label}"`;
    default: return 'Activity';
  }
}

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
    activities.value = await activityApi.list();
  } catch {
    loadError.value = 'Could not load your activity. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

onMounted(load);
</script>
