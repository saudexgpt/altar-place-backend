import api from './api';

function unwrap(response) {
  return response.data.data;
}

/** Keeps both the rows and Laravel's pagination meta (current_page, last_page, total, ...). */
function paginated(response) {
  return { data: response.data.data, meta: response.data.meta };
}

export const adminApi = {
  async overview() {
    return (await api.get('/admin/analytics/overview')).data;
  },

  async chart(days = 7) {
    return (await api.get('/admin/analytics/chart', { params: { days } })).data;
  },

  async topContent(metric = 'played', limit = 5, days = null) {
    return unwrap(await api.get('/admin/analytics/top-content', { params: { metric, limit, days: days ?? undefined } }));
  },

  async recentActivity(limit = 8) {
    return unwrap(await api.get('/admin/analytics/recent-activity', { params: { limit } }));
  },

  async users(params = {}) {
    return paginated(await api.get('/admin/users', { params }));
  },

  async suspendUser(id, reason) {
    return (await api.post(`/admin/users/${id}/suspend`, { reason })).data.user;
  },

  async banUser(id, reason) {
    return (await api.post(`/admin/users/${id}/ban`, { reason })).data.user;
  },

  async reactivateUser(id) {
    return (await api.post(`/admin/users/${id}/reactivate`)).data.user;
  },

  async verifyUser(id) {
    return (await api.post(`/admin/users/${id}/verify`)).data.user;
  },

  async userDetail(id) {
    return (await api.get(`/admin/users/${id}`)).data;
  },

  async updateUserRole(id, role, action) {
    return (await api.put(`/admin/users/${id}/roles`, { role, action })).data.user;
  },

  async tracks(params = {}) {
    return paginated(await api.get('/admin/tracks', { params }));
  },

  async uploadTrack(payload) {
    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      if (Array.isArray(value)) {
        value.forEach((item) => form.append(`${key}[]`, item));
        return;
      }
      form.append(key, value);
    });

    // No explicit Content-Type: the browser must compute its own (with the
    // multipart boundary) when sending a FormData body. Setting one here
    // overrides that with a boundary-less header, which makes PHP unable to
    // parse any field or file out of the request at all.
    return (await api.post('/admin/tracks', form)).data.data;
  },

  async approveTrack(id) {
    return (await api.post(`/admin/tracks/${id}/approve`)).data.track;
  },

  async rejectTrack(id, reason) {
    return (await api.post(`/admin/tracks/${id}/reject`, { reason })).data.track;
  },

  async updateTrack(id, payload) {
    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      form.append(key, value);
    });
    form.append('_method', 'PUT');

    return (await api.post(`/admin/tracks/${id}`, form)).data.data;
  },

  async reports(params = {}) {
    return paginated(await api.get('/admin/reports', { params }));
  },

  async resolveReport(id, action, note) {
    return (await api.post(`/admin/reports/${id}/resolve`, { action, note })).data.report;
  },
};
