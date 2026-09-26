import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
});

let csrfCookiePromise = null;

/**
 * Sanctum's SPA (cookie session) flow requires an XSRF-TOKEN cookie before
 * any state-changing request. axios already reads that cookie and sends it
 * back as X-XSRF-TOKEN automatically (its defaults match Laravel's names),
 * so this just needs to run once before the first login/register call.
 */
export function ensureCsrfCookie() {
  if (!csrfCookiePromise) {
    csrfCookiePromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true });
  }

  return csrfCookiePromise;
}

export const UNAUTHORIZED_EVENT = 'api:unauthorized';

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error;

    // A 419 (CSRF token mismatch) usually just means the XSRF-TOKEN cookie
    // went stale — e.g. the tab sat open long enough for the session to
    // rotate. Refreshing the cookie and retrying once transparently
    // recovers from that instead of surfacing a confusing failure on
    // whatever form the user happened to be submitting.
    if (response?.status === 419 && config && !config._retriedAfterCsrf) {
      config._retriedAfterCsrf = true;
      csrfCookiePromise = null;
      await ensureCsrfCookie();

      return api(config);
    }

    if (response?.status === 401) {
      window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT));
    }

    return Promise.reject(error);
  }
);

export default api;
