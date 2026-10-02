import api from './api';

export const profileApi = {
  async updateProfile(payload) {
    return (await api.put('/profile', payload)).data.data;
  },

  async uploadAvatar(file) {
    const form = new FormData();
    form.append('avatar', file);
    return (await api.post('/profile/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data.data;
  },

  async changePassword(payload) {
    return (await api.put('/auth/change-password', payload)).data;
  },

  async updateNotificationPreferences(payload) {
    return (await api.put('/profile/notification-preferences', payload)).data.data;
  },

  async following() {
    return (await api.get('/profile/following')).data;
  },
};
