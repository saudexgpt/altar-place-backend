import api from './api';

function unwrap(response) {
  return response.data.data;
}

export const playlistApi = {
  async create(payload) {
    return unwrap(await api.post('/playlists', payload));
  },

  async update(id, payload) {
    return unwrap(await api.put(`/playlists/${id}`, payload));
  },

  async destroy(id) {
    await api.delete(`/playlists/${id}`);
  },

  async addTrack(playlistId, trackId) {
    return unwrap(await api.post(`/playlists/${playlistId}/tracks/${trackId}`));
  },

  async removeTrack(playlistId, trackId) {
    return unwrap(await api.delete(`/playlists/${playlistId}/tracks/${trackId}`));
  },

  async searchTracks(query) {
    if (!query) return [];
    const { data } = await api.get('/search', { params: { q: query, type: 'track' } });
    return data.tracks ?? [];
  },
};
