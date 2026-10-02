import api from './api';

function unwrap(response) {
  return response.data.data;
}

function toFormData(payload) {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    form.append(key, value);
  });
  return form;
}

export const advertiserApi = {
  async apply(payload) {
    return (await api.post('/advertiser/apply', payload)).data;
  },

  async dashboard() {
    return (await api.get('/advertiser/dashboard')).data;
  },

  async campaigns() {
    return unwrap(await api.get('/advertiser/campaigns'));
  },

  // No explicit Content-Type on either of these: the browser must compute
  // its own (with the multipart boundary) for a FormData body. Setting one
  // overrides that with a boundary-less header, which makes PHP unable to
  // parse any field or file out of the request at all.
  async createCampaign(payload) {
    return (await api.post('/advertiser/campaigns', toFormData(payload))).data.advertisement;
  },

  async updateCampaign(id, payload) {
    const form = toFormData(payload);
    form.append('_method', 'PUT');
    return (await api.post(`/advertiser/campaigns/${id}`, form)).data.advertisement;
  },

  async deleteCampaign(id) {
    return (await api.delete(`/advertiser/campaigns/${id}`)).data;
  },
};
