import api from './api';

export const activityApi = {
  async list() {
    return (await api.get('/activity')).data.data;
  },
};
