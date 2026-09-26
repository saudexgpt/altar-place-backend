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

  async createCampaign(payload) {
    return (await api.post('/advertiser/campaigns', toFormData(payload), {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data.advertisement;
  },

  async updateCampaign(id, payload) {
    const form = toFormData(payload);
    form.append('_method', 'PUT');
    return (await api.post(`/advertiser/campaigns/${id}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data.advertisement;
  },

  async deleteCampaign(id) {
    return (await api.delete(`/advertiser/campaigns/${id}`)).data;
  },
};
