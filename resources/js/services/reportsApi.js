import api from './api';

export const reportsApi = {
  async report(reportableType, reportableId, reason, details) {
    await api.post('/reports', {
      reportable_type: reportableType,
      reportable_id: reportableId,
      reason,
      details,
    });
  },
};
