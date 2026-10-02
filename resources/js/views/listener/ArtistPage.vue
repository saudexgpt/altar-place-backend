<template>
  <div class="max-w-3xl">
    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <div v-else-if="artist" class="space-y-8">
      <div class="flex flex-col items-center text-center gap-2 py-6">
        <div class="w-24 h-24 rounded-full bg-navy-700 overflow-hidden">
          <img v-if="artist.avatar_url" :src="artist.avatar_url" class="w-full h-full object-cover" alt="" />
          <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><UserRound :size="32" /></div>
        </div>
        <h1 class="text-2xl font-heading font-semibold">{{ artist.name }}</h1>
        <p v-if="artist.is_verified" class="text-xs text-gold flex items-center gap-1"><BadgeCheck :size="14" /> Verified Artist</p>
        <p class="text-sm text-ink-muted">{{ artist.followers_count ?? 0 }} followers</p>
        <p v-if="artist.bio" class="text-sm text-ink-muted max-w-sm">{{ artist.bio }}</p>

        <button
          type="button"
          class="text-sm font-semibold rounded-full px-5 py-2 transition-colors"
          :class="artist.is_following ? 'border border-navy-500/60 hover:bg-navy-800' : 'bg-gold text-navy-950 hover:bg-gold-tint'"
          @click="toggleFollow"
        >
          {{ artist.is_following ? 'Following' : 'Follow' }}
        </button>
      </div>

      <section>
        <h3 class="font-heading font-semibold mb-3">Tracks</h3>
        <TrackListItem
          v-for="track in tracks"
          :key="track.id"
          :track="track"
          @play="playTrack(track)"
          @toggle-favorite="toggleFavorite(track)"
        />
        <p v-if="!tracks.length" class="text-sm text-ink-muted">No tracks yet.</p>
      </section>

      <section v-if="similarArtists.length">
        <h3 class="font-heading font-semibold mb-3">Similar Artists</h3>
        <div class="flex gap-4 overflow-x-auto pb-2">
          <ArtistCard v-for="similar in similarArtists" :key="similar.id" :artist="similar" @open="goToArtist(similar)" />
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BadgeCheck, UserRound } from '@lucide/vue';
import { catalogApi } from '@/services/catalogApi';
import { libraryApi } from '@/services/libraryApi';
import { usePlayerStore } from '@/stores/player';
import TrackListItem from '@/components/listener/TrackListItem.vue';
import ArtistCard from '@/components/listener/ArtistCard.vue';
import LoadError from '@/components/ui/LoadError.vue';

const route = useRoute();
const router = useRouter();
const player = usePlayerStore();

const artist = ref(null);
const tracks = ref([]);
const similarArtists = ref([]);
const loadError = ref('');

async function load() {
  loadError.value = '';
  const id = route.params.id;

  try {
    const [artistRes, tracksRes, similarRes] = await Promise.all([
      catalogApi.artist(id),
      catalogApi.artistTracks(id),
      catalogApi.similarArtists(id),
    ]);
    artist.value = artistRes;
    tracks.value = tracksRes;
    similarArtists.value = similarRes;
  } catch {
    loadError.value = 'Could not load this artist. Check your connection and try again.';
  }
}

function goToArtist(target) {
  router.push({ name: 'listener.artist', params: { id: target.id } });
}

function playTrack(track) {
  player.playQueue(tracks.value, tracks.value.findIndex((t) => t.id === track.id));
}

async function toggleFollow() {
  if (!artist.value) return;

  const wasFollowing = artist.value.is_following ?? false;
  artist.value.is_following = !wasFollowing;
  artist.value.followers_count = (artist.value.followers_count ?? 0) + (wasFollowing ? -1 : 1);

  try {
    if (wasFollowing) {
      await catalogApi.unfollowArtist(artist.value.id);
    } else {
      await catalogApi.followArtist(artist.value.id);
    }
  } catch {
    artist.value.is_following = wasFollowing;
    artist.value.followers_count = (artist.value.followers_count ?? 0) + (wasFollowing ? 1 : -1);
  }
}

async function toggleFavorite(track) {
  const wasFavorited = track.is_favorited ?? false;
  track.is_favorited = !wasFavorited;

  try {
    if (wasFavorited) {
      await libraryApi.unfavoriteTrack(track.id);
    } else {
      await libraryApi.favoriteTrack(track.id);
    }
  } catch {
    track.is_favorited = wasFavorited;
  }
}

load();
</script>
