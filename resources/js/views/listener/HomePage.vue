<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <h1 class="text-2xl font-heading font-semibold">Home</h1>
      <div class="flex items-center gap-3">
        <div class="flex gap-2">
          <button
            v-for="pill in categoryPills"
            :key="pill.value"
            type="button"
            class="text-xs font-semibold px-4 py-2 rounded-full transition-colors"
            :class="category === pill.value ? 'bg-gold text-navy-950' : 'bg-navy-800 text-ink-muted hover:bg-navy-700'"
            @click="category = pill.value"
          >
            {{ pill.label }}
          </button>
        </div>
        <RouterLink :to="{ name: 'listener.notifications' }" class="relative text-ink-muted hover:text-white" aria-label="Notifications">
          <Bell :size="20" />
          <span v-if="notifications.unreadCount > 0" class="absolute -top-1 -right-1 bg-gold text-navy-950 text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1">
            {{ notifications.unreadCount }}
          </span>
        </RouterLink>
      </div>
    </div>

    <div v-if="loadError" class="bg-danger/10 border border-danger/30 rounded-xl p-4 mb-6 flex items-center justify-between gap-4">
      <p class="text-sm text-danger">{{ loadError }}</p>
      <button type="button" class="text-xs font-semibold text-danger border border-danger/40 rounded-full px-4 py-1.5 hover:bg-danger/10 transition-colors shrink-0" @click="loadEverything">
        Retry
      </button>
    </div>

    <HomeSection title="Recommended For You" :items="recommended" :is-loading="isLoading.recommended">
      <TrackCard v-for="track in recommended" :key="track.id" :track="track" @play="playFrom(recommended, track)" />
    </HomeSection>

    <HomeSection title="Trending Now" :items="trending" :is-loading="isLoading.trending">
      <TrackCard v-for="track in trending" :key="track.id" :track="track" @play="playFrom(trending, track)" />
    </HomeSection>

    <HomeSection title="New Releases" :items="newReleases" :is-loading="isLoading.newReleases">
      <TrackCard v-for="track in newReleases" :key="track.id" :track="track" @play="playFrom(newReleases, track)" />
    </HomeSection>

    <HomeSection title="Your Daily Mix" :items="dailyMix" :is-loading="isLoading.dailyMix">
      <TrackCard v-for="track in dailyMix" :key="track.id" :track="track" @play="playFrom(dailyMix, track)" />
    </HomeSection>

    <HomeSection title="Discover Weekly" :items="discoverWeekly" :is-loading="isLoading.discoverWeekly">
      <TrackCard v-for="track in discoverWeekly" :key="track.id" :track="track" @play="playFrom(discoverWeekly, track)" />
    </HomeSection>

    <HomeSection title="Featured Artists" :items="featuredArtists" :is-loading="isLoading.featuredArtists">
      <ArtistCard v-for="artist in featuredArtists" :key="artist.id" :artist="artist" @open="goToArtist" />
    </HomeSection>

    <HomeSection title="Popular Playlists" :items="popularPlaylists" :is-loading="isLoading.popularPlaylists">
      <PlaylistCard v-for="playlist in popularPlaylists" :key="playlist.id" :playlist="playlist" @open="goToPlaylist" />
    </HomeSection>

    <HomeSection title="Featured Sermons" :items="featuredSermons" :is-loading="isLoading.featuredSermons">
      <TrackCard v-for="track in featuredSermons" :key="track.id" :track="track" @play="playFrom(featuredSermons, track)" />
    </HomeSection>

    <HomeSection title="Podcasts For You" :items="recommendedPodcasts" :is-loading="isLoading.recommendedPodcasts">
      <TrackCard v-for="track in recommendedPodcasts" :key="track.id" :track="track" @play="playFrom(recommendedPodcasts, track)" />
    </HomeSection>

    <p v-if="allEmpty" class="text-center text-ink-muted py-20">
      Nothing to show here yet — check back once more music and sermons are added.
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Bell } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { usePlayerStore } from '@/stores/player';
import { useNotificationsStore } from '@/stores/notifications';
import HomeSection from '@/components/listener/HomeSection.vue';
import TrackCard from '@/components/listener/TrackCard.vue';
import ArtistCard from '@/components/listener/ArtistCard.vue';
import PlaylistCard from '@/components/listener/PlaylistCard.vue';

const router = useRouter();
const player = usePlayerStore();
const notifications = useNotificationsStore();

const categoryPills = [
  { value: null, label: 'All' },
  { value: 'music', label: 'Music' },
  { value: 'the-word', label: 'The Word' },
  { value: 'podcasts', label: 'Podcasts' },
];
const category = ref(null);

const recommended = ref([]);
const trending = ref([]);
const newReleases = ref([]);
const dailyMix = ref([]);
const discoverWeekly = ref([]);
const featuredArtists = ref([]);
const popularPlaylists = ref([]);
const featuredSermons = ref([]);
const recommendedPodcasts = ref([]);
const loadError = ref('');

const isLoading = reactive({
  recommended: true,
  trending: true,
  newReleases: true,
  dailyMix: true,
  discoverWeekly: true,
  featuredArtists: true,
  popularPlaylists: true,
  featuredSermons: true,
  recommendedPodcasts: true,
});

const allEmpty = computed(() =>
  !Object.values(isLoading).some(Boolean) &&
  ![recommended, trending, newReleases, dailyMix, discoverWeekly, featuredArtists, popularPlaylists, featuredSermons, recommendedPodcasts]
    .some((list) => list.value.length)
);

function playFrom(list, track) {
  if (player.currentTrack?.id === track.id) {
    player.togglePlay();
    return;
  }

  const index = list.findIndex((t) => t.id === track.id);
  player.playQueue(list, index === -1 ? 0 : index);
}

function goToArtist(artist) {
  router.push({ name: 'listener.artist', params: { id: artist.id } });
}

function goToPlaylist(playlist) {
  router.push({ name: 'listener.library', query: { playlist: playlist.id } });
}

async function loadSection(key, target, fetcher) {
  isLoading[key] = true;
  try {
    target.value = await fetcher();
  } catch {
    loadError.value = 'Some sections could not be loaded. Check your connection and try again.';
  } finally {
    isLoading[key] = false;
  }
}

function loadEverything() {
  loadError.value = '';
  loadSection('recommended', recommended, () => catalogApi.recommended(category.value));
  loadSection('trending', trending, () => catalogApi.trending(category.value));
  loadSection('newReleases', newReleases, () => catalogApi.newReleases(category.value));
  loadSection('dailyMix', dailyMix, () => catalogApi.dailyMix());
  loadSection('discoverWeekly', discoverWeekly, () => catalogApi.discoverWeekly());
  loadSection('featuredArtists', featuredArtists, () => catalogApi.featuredArtists());
  loadSection('popularPlaylists', popularPlaylists, () => catalogApi.popularPlaylists());
  loadSection('featuredSermons', featuredSermons, () => catalogApi.featuredSermons());
  loadSection('recommendedPodcasts', recommendedPodcasts, () => catalogApi.recommendedPodcasts());
}

function reloadCategorySections() {
  loadSection('recommended', recommended, () => catalogApi.recommended(category.value));
  loadSection('trending', trending, () => catalogApi.trending(category.value));
  loadSection('newReleases', newReleases, () => catalogApi.newReleases(category.value));
}

watch(category, reloadCategorySections);
onMounted(() => {
  loadEverything();
  // Best-effort — a failed unread count just leaves the bell showing no
  // badge rather than blocking the rest of the home feed from loading.
  notifications.refresh().catch(() => {});
});
</script>
