import api from './api';

function unwrap(response) {
  return response.data.data;
}

function paginated(response) {
  return { data: response.data.data, meta: response.data.meta };
}

function toFormData(payload) {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (Array.isArray(value)) {
      value.forEach((item) => form.append(`${key}[]`, item));
      return;
    }
    form.append(key, value);
  });
  return form;
}

export const creatorApi = {
  async apply(payload) {
    return (await api.post('/creator/apply', payload)).data;
  },

  async dashboard() {
    return (await api.get('/creator/dashboard')).data;
  },

  async analytics() {
    return (await api.get('/creator/analytics')).data;
  },

  async tracks(params = {}) {
    return paginated(await api.get('/creator/tracks', { params }));
  },

  // No explicit Content-Type on any of these: the browser must compute its
  // own (with the multipart boundary) for a FormData body. Setting one
  // overrides that with a boundary-less header, which makes PHP unable to
  // parse any field or file out of the request at all.
  async uploadTrack(payload) {
    return unwrap(await api.post('/creator/tracks', toFormData(payload)));
  },

  async updateTrack(id, payload) {
    const form = toFormData(payload);
    form.append('_method', 'PUT');
    return unwrap(await api.post(`/creator/tracks/${id}`, form));
  },

  async deleteTrack(id) {
    return (await api.delete(`/creator/tracks/${id}`)).data;
  },

  async albums() {
    return unwrap(await api.get('/creator/albums'));
  },

  async createAlbum(payload) {
    return unwrap(await api.post('/creator/albums', toFormData(payload)));
  },

  async updateAlbum(id, payload) {
    const form = toFormData(payload);
    form.append('_method', 'PUT');
    return unwrap(await api.post(`/creator/albums/${id}`, form));
  },

  async deleteAlbum(id) {
    return (await api.delete(`/creator/albums/${id}`)).data;
  },
};
