import { defineStore } from 'pinia';
import { notificationApi } from '@/services/notificationApi';

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    unreadCount: 0,
  }),

  actions: {
    async refresh() {
      const result = await notificationApi.list();
      this.notifications = result.data;
      this.unreadCount = result.unread_count;
    },

    async markAsRead(id) {
      await notificationApi.markAsRead(id);
      const item = this.notifications.find((n) => n.id === id);
      if (item && !item.read_at) {
        item.read_at = new Date().toISOString();
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      }
    },

    async markAllAsRead() {
      await notificationApi.markAllAsRead();
      this.notifications.forEach((n) => { n.read_at ??= new Date().toISOString(); });
      this.unreadCount = 0;
    },
  },
});
