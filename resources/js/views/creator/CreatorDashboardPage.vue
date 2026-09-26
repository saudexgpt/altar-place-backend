<template>
  <div>
    <LoadError v-if="loadError" :message="loadError" @retry="load" />
    <p v-else-if="!dashboard" class="text-sm text-ink-muted">Loading…</p>

    <div v-else class="space-y-6">
      <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-navy-600 via-navy-800 to-navy-950 p-8">
        <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-2">Creator Studio</p>
        <h1 class="text-3xl font-heading font-semibold mb-1">{{ dashboard.artist.name }}</h1>
        <p class="text-ink-muted max-w-md">Manage your tracks, albums, and see how your content is performing.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Tracks" :value="format(dashboard.total_tracks)" :icon="Music" icon-bg="bg-gold/15" icon-color="text-gold" />
        <StatCard label="Albums" :value="format(dashboard.total_albums)" :icon="Disc3" icon-bg="bg-navy-400/20" icon-color="text-navy-300" />
        <StatCard label="Followers" :value="format(dashboard.total_followers)" :icon="Users" icon-bg="bg-success/15" icon-color="text-success" />
        <StatCard label="Total Streams" :value="format(dashboard.total_streams)" :icon="Play" icon-bg="bg-violet-500/15" icon-color="text-violet-400" />
      </div>

      <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
        <h2 class="font-heading font-semibold flex items-center gap-2 mb-4">
          <Zap :size="18" class="text-gold" /> Quick Actions
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <RouterLink
            :to="{ name: 'creator.tracks' }"
            class="flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium bg-gold text-navy-950 transition-colors"
          >
            <span class="flex items-center gap-2"><Upload :size="16" /> Upload a Track</span>
            <ChevronRight :size="16" />
          </RouterLink>
          <RouterLink
            :to="{ name: 'creator.albums' }"
            class="flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium bg-navy-700 hover:bg-navy-600 transition-colors"
          >
            <span class="flex items-center gap-2"><Disc3 :size="16" /> Create an Album</span>
            <ChevronRight :size="16" />
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Music, Disc3, Users, Play, Zap, Upload, ChevronRight } from '@lucide/vue';
import { creatorApi } from '@/services/creatorApi';
import StatCard from '@/components/ui/StatCard.vue';
import LoadError from '@/components/ui/LoadError.vue';

const dashboard = ref(null);
const loadError = ref('');

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

async function load() {
  loadError.value = '';
  try {
    dashboard.value = await creatorApi.dashboard();
  } catch {
    loadError.value = 'Could not load your dashboard. Check your connection and try again.';
  }
}

onMounted(load);
</script>
