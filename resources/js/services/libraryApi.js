import api from './api';

function unwrap(response) {
  return response.data.data;
}

export const libraryApi = {
  async favorites() {
    return unwrap(await api.get('/library/favorites'));
  },

  async favoriteTrack(id) {
    await api.post(`/tracks/${id}/favorite`);
  },

  async unfavoriteTrack(id) {
    await api.delete(`/tracks/${id}/favorite`);
  },

  async recentlyPlayed() {
    return unwrap(await api.get('/library/recently-played'));
  },

  async playlists() {
    return unwrap(await api.get('/library/playlists'));
  },

  async downloadQuota() {
    return (await api.get('/library/download-quota')).data;
  },
};
