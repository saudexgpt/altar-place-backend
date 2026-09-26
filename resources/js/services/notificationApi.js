import api from './api';

export const notificationApi = {
  async list() {
    const { data } = await api.get('/notifications');
    return data; // { data: [...], unread_count }
  },

  async markAsRead(id) {
    await api.post(`/notifications/${id}/read`);
  },

  async markAllAsRead() {
    await api.post('/notifications/read-all');
  },
};
