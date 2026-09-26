import { afterEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import api, { UNAUTHORIZED_EVENT } from './api';

// The interceptor is registered once at module load; grab the same rejected
// handler axios itself calls, so this test exercises the real code path
// instead of a re-implementation of it.
const rejectedHandler = () => api.interceptors.response.handlers[0].rejected;

afterEach(() => {
  vi.restoreAllMocks();
});

describe('api response interceptor', () => {
  it('dispatches UNAUTHORIZED_EVENT when a request comes back 401', async () => {
    const listener = vi.fn();
    window.addEventListener(UNAUTHORIZED_EVENT, listener);

    const error = { response: { status: 401 }, config: {} };
    await expect(rejectedHandler()(error)).rejects.toBe(error);

    expect(listener).toHaveBeenCalledTimes(1);
    window.removeEventListener(UNAUTHORIZED_EVENT, listener);
  });

  it('does not treat a 419 (stale CSRF token) as an auth failure', async () => {
    const listener = vi.fn();
    window.addEventListener(UNAUTHORIZED_EVENT, listener);

    vi.spyOn(axios, 'get').mockResolvedValue({});

    // A stub adapter so the retried request resolves locally instead of
    // trying to reach a real server — only the CSRF-refresh step and the
    // "don't treat this as a 401" behavior are under test here.
    const config = { url: '/some-form', adapter: () => Promise.resolve({ data: {}, status: 200, config: {}, headers: {} }) };
    const error = { response: { status: 419 }, config };

    await rejectedHandler()(error);

    expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', expect.objectContaining({ withCredentials: true }));
    expect(listener).not.toHaveBeenCalled();
    window.removeEventListener(UNAUTHORIZED_EVENT, listener);
  });

  it('only retries a 419 once, to avoid looping forever against a genuinely broken session', async () => {
    vi.spyOn(axios, 'get').mockResolvedValue({});

    const config = { url: '/some-form', _retriedAfterCsrf: true };
    const error = { response: { status: 419 }, config };

    await expect(rejectedHandler()(error)).rejects.toBe(error);
    expect(axios.get).not.toHaveBeenCalled();
  });

  it('leaves other error statuses untouched', async () => {
    const listener = vi.fn();
    window.addEventListener(UNAUTHORIZED_EVENT, listener);

    const error = { response: { status: 500 }, config: {} };
    await expect(rejectedHandler()(error)).rejects.toBe(error);

    expect(listener).not.toHaveBeenCalled();
    window.removeEventListener(UNAUTHORIZED_EVENT, listener);
  });
});
