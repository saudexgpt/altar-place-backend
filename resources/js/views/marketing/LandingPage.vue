<template>
  <div class="bg-navy-950 text-ink min-h-screen">
    <PublicNav />

    <!-- Hero -->
    <section class="relative overflow-hidden border-b border-navy-500/30">
      <div class="absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 15% 20%, #f0b030, transparent 35%), radial-gradient(circle at 85% 60%, #0036d6, transparent 45%)"></div>
      <div class="relative max-w-7xl mx-auto px-6 py-20 grid lg:grid-cols-2 gap-12 items-center">
        <div>
          <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-4">Worship &middot; Listen &middot; Grow</p>
          <h1 class="text-4xl sm:text-5xl font-heading font-semibold leading-tight mb-5">
            <span class="text-gold">AltarPlace</span> Music and the Word for a Deeper You.
          </h1>
          <p class="text-ink-muted max-w-md mb-8">
            Stream inspiring gospel music, powerful teachings and daily devotionals — all in one place.
          </p>

          <div class="flex flex-wrap gap-3 mb-10">
            <RouterLink :to="{ name: 'register' }" class="inline-flex items-center gap-2 bg-gold text-navy-950 font-semibold px-6 py-3 rounded-full hover:bg-gold-tint transition-colors">
              Get Started Free <ArrowRight :size="16" />
            </RouterLink>
            <a href="#explore" class="inline-flex items-center gap-2 border border-navy-500/60 px-6 py-3 rounded-full font-medium hover:bg-navy-800 transition-colors">
              <PlayCircle :size="16" /> Watch Video
            </a>
          </div>

          <div class="grid grid-cols-2 gap-4 max-w-md text-sm">
            <div v-for="item in features" :key="item.label" class="flex items-center gap-2 text-ink-muted">
              <component :is="item.icon" :size="16" class="text-gold shrink-0" />
              {{ item.label }}
            </div>
          </div>
        </div>

        <div class="flex justify-center">
          <div class="w-64 bg-navy-900 border border-navy-500/50 rounded-[2rem] p-4 shadow-2xl">
            <div class="flex items-center gap-2 mb-6 justify-center">
              <img src="/images/logo-mark.png" class="h-6 w-6" alt="" />
              <span class="font-heading font-semibold text-sm">AltarPlace</span>
            </div>
            <div class="aspect-square rounded-xl bg-gradient-to-br from-navy-600 to-navy-800 mb-4 overflow-hidden flex items-center justify-center">
              <img v-if="displayedTrack?.cover_url" :src="displayedTrack.cover_url" class="w-full h-full object-cover" alt="" />
              <Music v-else :size="40" class="text-gold/60" />
            </div>
            <p class="font-medium text-sm truncate">{{ displayedTrack?.title ?? 'Nothing playing yet' }}</p>
            <p class="text-xs text-ink-muted truncate mb-4">{{ displayedTrack?.artist?.name ?? 'Pick a track below to start' }}</p>
            <div class="h-1 bg-navy-700 rounded-full mb-4">
              <div class="h-1 bg-gold rounded-full" :style="{ width: `${progressPercent}%` }"></div>
            </div>
            <div class="flex items-center justify-center gap-5 text-ink-muted">
              <button type="button" :class="{ 'text-gold': player.shuffle }" @click="player.toggleShuffle()"><Shuffle :size="16" /></button>
              <button type="button" @click="player.previous()"><SkipBack :size="18" /></button>
              <button
                type="button"
                class="w-9 h-9 rounded-full bg-gold text-navy-950 flex items-center justify-center"
                @click="handleHeroPlayClick"
              >
                <component :is="player.currentTrack && player.isPlaying ? Pause : Play" :size="16" />
              </button>
              <button type="button" @click="player.next()"><SkipForward :size="18" /></button>
              <button type="button" :class="{ 'text-gold': player.repeat !== 'off' }" @click="player.cycleRepeat()"><Repeat :size="16" /></button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Explore -->
    <section id="explore" class="max-w-7xl mx-auto px-6 py-16">
      <h2 class="text-2xl font-heading font-semibold mb-2">Explore AltarPlace</h2>
      <p class="text-ink-muted mb-10 max-w-xl">Your all-in-one platform for uplifting music and life-transforming messages.</p>

      <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 grid sm:grid-cols-3 gap-4">
          <div v-for="card in exploreCards" :key="card.title" class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
            <div class="w-10 h-10 rounded-full bg-gold/15 text-gold flex items-center justify-center mb-4">
              <component :is="card.icon" :size="18" />
            </div>
            <h3 class="font-heading font-semibold mb-2">{{ card.title }}</h3>
            <p class="text-sm text-ink-muted mb-4">{{ card.description }}</p>
            <button type="button" class="w-8 h-8 rounded-full border border-navy-500/60 flex items-center justify-center text-gold hover:bg-navy-700 transition-colors">
              <ArrowRight :size="14" />
            </button>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-heading font-semibold">Popular on AltarPlace</h3>
              <RouterLink :to="{ name: 'register' }" class="text-xs text-gold hover:underline">View All</RouterLink>
            </div>
            <ul class="space-y-3">
              <li v-for="track in popularTracks" :key="track.id" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-navy-700 overflow-hidden shrink-0">
                  <img v-if="track.cover_url" :src="track.cover_url" class="w-full h-full object-cover" alt="" />
                  <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Music :size="14" /></div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ track.title }}</p>
                  <p class="text-xs text-ink-muted truncate">{{ track.artist?.name }}</p>
                </div>
                <button
                  type="button"
                  class="w-7 h-7 rounded-full bg-gold/15 text-gold flex items-center justify-center shrink-0"
                  @click="playFromPopular(track)"
                >
                  <component :is="player.currentTrack?.id === track.id && player.isPlaying ? Pause : Play" :size="12" />
                </button>
              </li>
            </ul>
          </div>

          <div class="bg-gradient-to-br from-navy-600 to-navy-800 border border-navy-500/40 rounded-2xl p-5">
            <h3 class="font-heading font-semibold mb-3">Verse of the Day</h3>
            <p class="italic text-ink-muted mb-2">&ldquo;{{ verseOfTheDay.text }}&rdquo;</p>
            <p class="text-gold text-sm font-medium">{{ verseOfTheDay.reference }}</p>
          </div>
        </div>
      </div>
    </section>

    <footer class="border-t border-navy-500/30 py-8 pb-28 text-center text-xs text-ink-muted">
      © {{ new Date().getFullYear() }} AltarPlace. All rights reserved.
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
  ArrowRight, PlayCircle, Music, BookOpen, User, Download,
  Shuffle, SkipBack, SkipForward, Repeat, Play, Pause,
} from '@lucide/vue';
import PublicNav from '@/components/layout/PublicNav.vue';
import { catalogApi } from '@/services/catalogApi';
import { usePlayerStore } from '@/stores/player';

const player = usePlayerStore();
const popularTracks = ref([]);
const nowPlaying = ref(null);

// Shows whatever is actually loaded in the player once something has been
// played; falls back to the first popular track as a preview before that.
const displayedTrack = computed(() => player.currentTrack ?? nowPlaying.value);
const progressPercent = computed(() => (player.duration ? (player.currentTime / player.duration) * 100 : 0));

function handleHeroPlayClick() {
  if (player.currentTrack) {
    player.togglePlay();
  } else if (nowPlaying.value) {
    player.playQueue(popularTracks.value, 0);
  }
}

function playFromPopular(track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = popularTracks.value.findIndex((t) => t.id === track.id);
  player.playQueue(popularTracks.value, index === -1 ? 0 : index);
}

const features = [
  { label: 'Thousands of Worship Songs', icon: Music },
  { label: 'Daily Devotionals', icon: BookOpen },
  { label: 'Personalized Playlists', icon: User },
  { label: 'Offline Listening', icon: Download },
];

const exploreCards = [
  { title: 'Uplifting Music', description: 'Discover a rich library of gospel music, curated to lift your spirit and draw you closer to God.', icon: Music },
  { title: 'Powerful Word', description: 'Access inspiring sermons, bible teachings and daily devotionals from trusted voices.', icon: BookOpen },
  { title: 'Personalized Experience', description: 'Create playlists, save your favourites and enjoy offline listening, anywhere, anytime.', icon: User },
];

// No "verse of the day" feature exists in the backend yet — a small, honest,
// client-side rotation rather than inventing a fake API-backed feature.
const verses = [
  { text: 'Let everything that has breath praise the Lord.', reference: 'Psalm 150:6' },
  { text: 'I can do all things through him who strengthens me.', reference: 'Philippians 4:13' },
  { text: 'The Lord is my shepherd; I shall not want.', reference: 'Psalm 23:1' },
];
const verseOfTheDay = verses[new Date().getDate() % verses.length];

onMounted(async () => {
  try {
    const tracks = await catalogApi.trending('music');
    popularTracks.value = tracks.slice(0, 4);
    nowPlaying.value = tracks[0] ?? null;
  } catch {
    popularTracks.value = [];
  }
});
</script>
