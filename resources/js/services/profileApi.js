import api from './api';

export const profileApi = {
  async updateProfile(payload) {
    return (await api.put('/profile', payload)).data.data;
  },

  async uploadAvatar(file) {
    const form = new FormData();
    form.append('avatar', file);
    // No explicit Content-Type: the browser must compute its own (with the
    // multipart boundary) for a FormData body. Setting one overrides that
    // with a boundary-less header, which makes PHP unable to parse any
    // field or file out of the request at all.
    return (await api.post('/profile/avatar', form)).data.data;
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
