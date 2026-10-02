import api from './api';

function unwrap(response) {
  return response.data.data;
}

export const commentsApi = {
  async list(trackId) {
    return unwrap(await api.get(`/tracks/${trackId}/comments`));
  },

  async create(trackId, body) {
    return unwrap(await api.post(`/tracks/${trackId}/comments`, { body }));
  },

  async destroy(commentId) {
    await api.delete(`/comments/${commentId}`);
  },
};
