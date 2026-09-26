<template>
  <div class="space-y-6">
    <!-- Welcome banner -->
    <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-navy-600 via-navy-800 to-navy-950 p-8">
      <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-2">Welcome Back</p>
      <h1 class="text-3xl font-heading font-semibold mb-1">{{ auth.user?.name?.split(' ')[0] ?? 'Admin' }}</h1>
      <p class="text-ink-muted max-w-md">Here's what's happening with your AltarPlace platform today.</p>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard label="Music Songs" :value="format(overview?.music_tracks_count)" :icon="Music" icon-bg="bg-gold/15" icon-color="text-gold" />
      <StatCard label="Word Resources" :value="format(overview?.word_tracks_count)" :icon="BookOpen" icon-bg="bg-navy-400/20" icon-color="text-navy-300" />
      <StatCard label="Active Users" :value="format(overview?.total_users)" :icon="Users" icon-bg="bg-success/15" icon-color="text-success" />
      <StatCard label="Total Plays" :value="format(overview?.total_plays)" :icon="Play" icon-bg="bg-violet-500/15" icon-color="text-violet-400" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <!-- Platform overview chart -->
        <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold flex items-center gap-2">
              <BarChart3 :size="18" class="text-gold" /> Platform Overview
            </h2>
            <select
              v-model.number="chartDays"
              class="bg-navy-700 border border-navy-500/60 rounded-full text-xs px-3 py-1.5 focus:outline-none"
            >
              <option :value="7">Last 7 Days</option>
              <option :value="14">Last 14 Days</option>
              <option :value="30">Last 30 Days</option>
            </select>
          </div>
          <PlatformOverviewChart
            v-if="chart"
            :labels="chart.labels"
            :music-plays="chart.music_plays"
            :word-plays="chart.word_plays"
          />
        </div>

        <!-- Recent uploads -->
        <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold">Recent Uploads</h2>
            <RouterLink :to="{ name: 'admin.music-library' }" class="text-xs text-gold hover:underline">View All</RouterLink>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <button
              v-for="track in recentUploads"
              :key="track.id"
              type="button"
              class="group text-left min-w-0"
              @click="playFrom(recentUploads, track)"
            >
              <div class="relative aspect-square rounded-lg bg-navy-700 overflow-hidden mb-2">
                <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
                <div v-else class="w-full h-full flex items-center justify-center text-ink-muted">
                  <Music :size="24" />
                </div>
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                  <component :is="player.currentTrack?.id === track.id && player.isPlaying ? Pause : Play" :size="18" class="text-white" />
                </div>
              </div>
              <p class="text-sm font-medium truncate">{{ track.title }}</p>
              <p class="text-xs text-ink-muted truncate">{{ track.type === 'music' ? 'Worship Song' : 'Sermon' }}</p>
            </button>
          </div>
        </div>

        <!-- Make an impact promo -->
        <div class="rounded-2xl bg-gradient-to-r from-gold/20 via-navy-800 to-navy-800 border border-gold/20 p-6 flex items-center justify-between gap-6 flex-wrap">
          <div>
            <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-2">Make an Impact</p>
            <h3 class="text-xl font-heading font-semibold mb-1">Spreading the Gospel Through Music &amp; Word</h3>
            <p class="text-ink-muted text-sm">Together, we can reach more people.</p>
          </div>
          <RouterLink
            :to="{ name: 'admin.analytics' }"
            class="inline-flex items-center gap-2 bg-gold text-navy-950 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-gold-tint transition-colors shrink-0"
          >
            View Reports <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>

      <div class="space-y-6">
        <!-- Quick actions -->
        <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
          <h2 class="font-heading font-semibold flex items-center gap-2 mb-4">
            <Zap :size="18" class="text-gold" /> Quick Actions
          </h2>
          <div class="space-y-2">
            <RouterLink
              v-for="action in quickActions"
              :key="action.label"
              :to="action.to"
              class="flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-colors"
              :class="action.primary ? 'bg-gold text-navy-950' : 'bg-navy-700 hover:bg-navy-600'"
            >
              <span class="flex items-center gap-2">
                <component :is="action.icon" :size="16" />
                {{ action.label }}
              </span>
              <ChevronRight :size="16" />
            </RouterLink>
          </div>
        </div>

        <!-- Top content -->
        <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
          <h2 class="font-heading font-semibold flex items-center gap-2 mb-1">
            <Crown :size="18" class="text-gold" /> Top Content
          </h2>
          <p class="text-xs text-ink-muted mb-3">Last {{ chartDays }} days · matches the range above</p>
          <div class="flex gap-4 text-xs font-medium border-b border-navy-500/40 mb-3">
            <button
              v-for="tab in topContentTabs"
              :key="tab.metric"
              type="button"
              class="pb-2 -mb-px border-b-2 transition-colors"
              :class="tab.metric === topContentMetric ? 'border-gold text-gold' : 'border-transparent text-ink-muted hover:text-white'"
              @click="topContentMetric = tab.metric"
            >
              {{ tab.label }}
            </button>
          </div>
          <ol class="space-y-3">
            <li v-for="(track, index) in topContent" :key="track.id" class="flex items-center gap-3">
              <span class="w-5 text-center text-sm text-ink-muted">{{ index + 1 }}</span>
              <button type="button" class="w-9 h-9 rounded-lg bg-navy-700 overflow-hidden shrink-0 relative group" @click="playFrom(topContent, track)">
                <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                  <component :is="player.currentTrack?.id === track.id && player.isPlaying ? Pause : Play" :size="14" class="text-white" />
                </div>
              </button>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate">{{ track.title }}</p>
                <p class="text-xs text-ink-muted truncate">{{ track.artist?.name }}</p>
              </div>
              <span class="text-xs text-ink-muted shrink-0">{{ format(track.plays_count) }} plays</span>
            </li>
          </ol>
        </div>

        <!-- Recent activity -->
        <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-heading font-semibold flex items-center gap-2">
              <Clock :size="18" class="text-gold" /> Recent Activity
            </h2>
          </div>
          <ul class="space-y-3">
            <li v-for="(item, index) in recentActivity" :key="index" class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="activityIconBg(item.type)">
                <component :is="activityIcon(item.type)" :size="14" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium">{{ item.title }}</p>
                <p class="text-xs text-ink-muted truncate">{{ item.subtitle }}</p>
                <p class="text-xs text-ink-muted/70">{{ relativeTime(item.at) }}</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
  Music, BookOpen, Users, Play, Pause, BarChart3, Zap, Crown, Clock,
  ArrowRight, ChevronRight, UserPlus, Music2, ListMusic, PenSquare,
} from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';
import { usePlayerStore } from '@/stores/player';
import { adminApi } from '@/services/adminApi';
import StatCard from '@/components/ui/StatCard.vue';
import PlatformOverviewChart from '@/components/charts/PlatformOverviewChart.vue';
import LoadError from '@/components/ui/LoadError.vue';

const auth = useAuthStore();
const player = usePlayerStore();

const overview = ref(null);
const chart = ref(null);
const chartDays = ref(7);
const recentUploads = ref([]);
const topContent = ref([]);
const topContentMetric = ref('played');
const recentActivity = ref([]);
const loadError = ref('');

const topContentTabs = [
  { metric: 'played', label: 'Most Played' },
  { metric: 'played', label: 'Most Viewed' },
  { metric: 'liked', label: 'Most Liked' },
];

const quickActions = [
  { label: 'Add New Song', icon: Music2, to: { name: 'admin.music-library' }, primary: true },
  { label: 'Add New Sermon', icon: BookOpen, to: { name: 'admin.word-library' } },
  { label: 'Create Playlist', icon: ListMusic, to: { name: 'admin.playlists' } },
  { label: 'Manage Users', icon: Users, to: { name: 'admin.users' } },
];

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

function playFrom(list, track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = list.findIndex((t) => t.id === track.id);
  player.playQueue(list, index === -1 ? 0 : index);
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

function activityIcon(type) {
  return { user_registered: UserPlus, song_uploaded: Music2, sermon_added: BookOpen, song_updated: PenSquare, playlist_updated: ListMusic }[type] ?? Clock;
}

function activityIconBg(type) {
  return {
    user_registered: 'bg-success/15 text-success',
    song_uploaded: 'bg-gold/15 text-gold',
    sermon_added: 'bg-navy-400/20 text-navy-300',
    song_updated: 'bg-gold/15 text-gold',
    playlist_updated: 'bg-violet-500/15 text-violet-400',
  }[type] ?? 'bg-navy-500/40 text-ink-muted';
}

async function loadChart() {
  chart.value = await adminApi.chart(chartDays.value);
}

async function loadTopContent() {
  topContent.value = await adminApi.topContent(topContentMetric.value, 5, chartDays.value);
}

watch(chartDays, () => {
  loadChart();
  loadTopContent();
});
watch(topContentMetric, loadTopContent);

async function load() {
  loadError.value = '';

  try {
    const [overviewRes, uploads] = await Promise.all([
      adminApi.overview(),
      adminApi.tracks(),
    ]);

    overview.value = overviewRes;
    recentUploads.value = uploads.data.slice(0, 5);

    await Promise.all([loadChart(), loadTopContent()]);
    recentActivity.value = await adminApi.recentActivity(6);
  } catch {
    loadError.value = 'Could not load the dashboard. Check your connection and try again.';
  }
}

onMounted(load);
</script>
