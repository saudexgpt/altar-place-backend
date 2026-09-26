import { ref } from 'vue';
import api from './api';

// A single in-memory cache the router guard and MaintenancePage both read,
// so the status is only fetched once per page load rather than on every
// navigation.
export const maintenanceMessage = ref('');
let statusPromise = null;

export const platformApi = {
  async status() {
    return (await api.get('/platform/status')).data;
  },

  /** Cached: safe to call from the router guard on every navigation. */
  async fetchStatusOnce() {
    if (!statusPromise) {
      statusPromise = platformApi.status().catch(() => ({ maintenance_mode: false, maintenance_message: '' }));
    }

    const status = await statusPromise;
    maintenanceMessage.value = status.maintenance_message ?? '';
    return status;
  },

  async updateMaintenance(payload) {
    const result = (await api.put('/admin/platform-settings/maintenance', payload)).data;
    statusPromise = null; // let the next navigation/toggle re-check
    return result;
  },
};
