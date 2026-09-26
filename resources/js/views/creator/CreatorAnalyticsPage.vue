<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-heading font-semibold">Analytics</h1>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />
    <p v-else-if="!analytics" class="text-sm text-ink-muted">Loading…</p>

    <template v-else>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard label="Streams" :value="format(analytics.streams)" :icon="Play" icon-bg="bg-gold/15" icon-color="text-gold" />
      <StatCard label="Unique Listeners" :value="format(analytics.unique_listeners)" :icon="Users" icon-bg="bg-success/15" icon-color="text-success" />
      <StatCard label="Followers" :value="format(analytics.followers)" :icon="Heart" icon-bg="bg-navy-400/20" icon-color="text-navy-300" />
      <StatCard label="Downloads" :value="format(analytics.downloads)" :icon="Download" icon-bg="bg-violet-500/15" icon-color="text-violet-400" />
    </div>

    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
      <p class="text-sm text-ink-muted">
        Estimated listening time: <span class="text-white font-medium">{{ format(analytics.estimated_listening_minutes) }} minutes</span>
      </p>
      <p class="text-xs text-ink-muted mt-1">{{ analytics.revenue_note }}</p>
    </div>

    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl overflow-hidden">
      <h2 class="font-heading font-semibold p-5 pb-0">Per-Track Performance</h2>
      <table class="w-full text-sm mt-3">
        <thead class="text-left text-xs text-ink-muted uppercase tracking-wide border-b border-navy-500/40">
          <tr>
            <th class="px-5 py-3">Track</th>
            <th class="px-5 py-3">Streams</th>
            <th class="px-5 py-3">Downloads</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="track in analytics.tracks" :key="track.id" class="border-b border-navy-500/20 last:border-0">
            <td class="px-5 py-3 font-medium">{{ track.title }}</td>
            <td class="px-5 py-3 text-ink-muted">{{ format(track.streams) }}</td>
            <td class="px-5 py-3 text-ink-muted">{{ format(track.downloads) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!analytics.tracks.length" class="text-center text-ink-muted py-10">No tracks yet.</p>
    </div>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Play, Users, Heart, Download } from '@lucide/vue';
import { creatorApi } from '@/services/creatorApi';
import StatCard from '@/components/ui/StatCard.vue';
import LoadError from '@/components/ui/LoadError.vue';

const analytics = ref(null);
const loadError = ref('');

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

async function load() {
  loadError.value = '';
  try {
    analytics.value = await creatorApi.analytics();
  } catch {
    loadError.value = 'Could not load your analytics. Check your connection and try again.';
  }
}

onMounted(load);
</script>
