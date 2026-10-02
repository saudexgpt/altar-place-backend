import api from './api';

function unwrap(response) {
  return response.data.data;
}

export const catalogApi = {
  async trending(category) {
    return unwrap(await api.get('/discovery/trending', { params: { category } }));
  },

  async newReleases(category) {
    return unwrap(await api.get('/discovery/new-releases', { params: { category } }));
  },

  async recommended(category) {
    return unwrap(await api.get('/discovery/recommended', { params: { category } }));
  },

  async dailyMix() {
    return unwrap(await api.get('/discovery/daily-mix'));
  },

  async discoverWeekly() {
    return unwrap(await api.get('/discovery/discover-weekly'));
  },

  async recommendedPodcasts() {
    return unwrap(await api.get('/discovery/recommended-podcasts'));
  },

  async featuredArtists() {
    return unwrap(await api.get('/discovery/featured-artists'));
  },

  async featuredPodcasts() {
    return unwrap(await api.get('/discovery/featured-podcasts'));
  },

  async featuredSermons() {
    return unwrap(await api.get('/discovery/featured-sermons'));
  },

  async popularPlaylists() {
    return unwrap(await api.get('/discovery/popular-playlists'));
  },

  async search(query, type = 'all') {
    const { data } = await api.get('/search', { params: { q: query, type } });
    return data;
  },

  async track(id) {
    return unwrap(await api.get(`/tracks/${id}`));
  },

  async artist(id) {
    return unwrap(await api.get(`/artists/${id}`));
  },

  async artistTracks(id) {
    return unwrap(await api.get(`/artists/${id}/tracks`));
  },

  async similarArtists(id) {
    return unwrap(await api.get(`/artists/${id}/similar`));
  },

  async album(id) {
    return unwrap(await api.get(`/albums/${id}`));
  },

  async genres() {
    return unwrap(await api.get('/genres'));
  },

  async playlists() {
    return unwrap(await api.get('/playlists'));
  },

  async playlist(id) {
    return unwrap(await api.get(`/playlists/${id}`));
  },

  async followArtist(id) {
    await api.post(`/artists/${id}/follow`);
  },

  async unfollowArtist(id) {
    await api.delete(`/artists/${id}/follow`);
  },
};
