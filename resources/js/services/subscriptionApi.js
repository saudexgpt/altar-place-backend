import api from './api';

function unwrap(response) {
  return response.data.data;
}

export const subscriptionApi = {
  async plans() {
    return unwrap(await api.get('/subscription-plans'));
  },

  async status() {
    return (await api.get('/subscription')).data;
  },

  async checkout(planId, provider) {
    return (await api.post('/subscription/checkout', { plan_id: planId, provider })).data;
  },

  async verify(reference) {
    return (await api.post('/subscription/verify', { reference })).data;
  },

  async cancel() {
    return (await api.post('/subscription/cancel')).data;
  },

  async familyMembers() {
    return (await api.get('/subscription/family-members')).data;
  },

  async inviteFamilyMember(email) {
    return (await api.post('/subscription/family-members', { email })).data;
  },

  async removeFamilyMember(id) {
    await api.delete(`/subscription/family-members/${id}`);
  },
};
