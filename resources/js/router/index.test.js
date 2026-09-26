import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';

// The router calls platformApi.fetchStatusOnce() on nearly every navigation
// (the maintenance-mode check) — stub it so tests control the outcome
// directly instead of hitting a real backend.
vi.mock('@/services/platformApi', () => ({
  maintenanceMessage: { value: '' },
  platformApi: {
    fetchStatusOnce: vi.fn().mockResolvedValue({ maintenance_mode: false }),
  },
}));

const { platformApi } = await import('@/services/platformApi');
const { useAuthStore } = await import('@/stores/auth');
const { routes, resolveNavigation } = await import('./index');

const STUB = { template: '<div/>' };

// Real route tree (names, paths, meta) with every lazy `() => import(...)`
// component swapped for a trivial stub — the guard only ever reads meta and
// names, but Vue Router still needs *something* synchronous to resolve, and
// the real page components pull in things (static asset URLs, etc.) that
// this module runner isn't set up to render outside a browser.
function stubComponents(routeList) {
  return routeList.map((route) => ({
    ...route,
    component: route.component ? STUB : undefined,
    children: route.children ? stubComponents(route.children) : undefined,
  }));
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: stubComponents(routes),
});
router.beforeEach(resolveNavigation);

async function goTo(name, options = {}) {
  await router.push({ name, ...options });
  return router.currentRoute.value;
}

beforeEach(() => {
  setActivePinia(createPinia());
  const auth = useAuthStore();
  auth.isInitializing = false; // skip the real /auth/me network call
  platformApi.fetchStatusOnce.mockClear();
  platformApi.fetchStatusOnce.mockResolvedValue({ maintenance_mode: false });
});

describe('router guards', () => {
  it('sends a guest to login when visiting an auth-required route', async () => {
    const route = await goTo('listener.home');

    expect(route.name).toBe('login');
    expect(route.query.redirect).toBe('/home');
  });

  it('lets an authenticated listener reach the listener app', async () => {
    useAuthStore().user = { id: 1, roles: ['listener'] };

    const route = await goTo('listener.home');

    expect(route.name).toBe('listener.home');
  });

  it('bounces a non-staff user away from admin routes (via landing, on to their own home)', async () => {
    useAuthStore().user = { id: 1, roles: ['listener'] };

    // requiresStaff sends them to landing; the landing-redirect rule then
    // immediately sends an authenticated non-staff visitor on to listener.home.
    const route = await goTo('admin.dashboard');

    expect(route.name).toBe('listener.home');
  });

  it('lets staff into admin routes', async () => {
    useAuthStore().user = { id: 1, roles: ['moderator'] };

    const route = await goTo('admin.dashboard');

    expect(route.name).toBe('admin.dashboard');
  });

  it('redirects a signed-in guest-only page (login) to the listener home for non-staff', async () => {
    useAuthStore().user = { id: 1, roles: ['listener'] };

    const route = await goTo('login');

    expect(route.name).toBe('listener.home');
  });

  it('redirects a signed-in guest-only page (login) to the admin dashboard for staff', async () => {
    useAuthStore().user = { id: 1, roles: ['super-admin'] };

    const route = await goTo('login');

    expect(route.name).toBe('admin.dashboard');
  });

  it('sends an authenticated listener from the landing page to /home', async () => {
    useAuthStore().user = { id: 1, roles: ['listener'] };

    const route = await goTo('landing');

    expect(route.name).toBe('listener.home');
  });

  it('lets staff stay on the landing page', async () => {
    useAuthStore().user = { id: 1, roles: ['super-admin'] };

    const route = await goTo('landing');

    expect(route.name).toBe('landing');
  });

  it('sends a user without the creator role to the apply page', async () => {
    useAuthStore().user = { id: 1, roles: ['listener'] };

    const route = await goTo('creator.dashboard');

    expect(route.name).toBe('creator.apply');
  });

  it('lets a user with the creator role into the creator studio', async () => {
    useAuthStore().user = { id: 1, roles: ['creator'] };

    const route = await goTo('creator.dashboard');

    expect(route.name).toBe('creator.dashboard');
  });

  it('a super-admin bypasses the creator-role requirement', async () => {
    useAuthStore().user = { id: 1, roles: ['super-admin'] };

    const route = await goTo('creator.dashboard');

    expect(route.name).toBe('creator.dashboard');
  });

  it('redirects an already-a-creator away from the apply page', async () => {
    useAuthStore().user = { id: 1, roles: ['creator'] };

    const route = await goTo('creator.apply');

    expect(route.name).toBe('creator.dashboard');
  });

  it('redirects everyone except staff to the maintenance page when it is on', async () => {
    platformApi.fetchStatusOnce.mockResolvedValue({ maintenance_mode: true });
    useAuthStore().user = { id: 1, roles: ['listener'] };

    const route = await goTo('listener.home');

    expect(route.name).toBe('maintenance');
  });

  it('still lets a guest reach the login page during maintenance', async () => {
    platformApi.fetchStatusOnce.mockResolvedValue({ maintenance_mode: true });

    const route = await goTo('login');

    expect(route.name).toBe('login');
  });

  it('lets staff through during maintenance', async () => {
    platformApi.fetchStatusOnce.mockResolvedValue({ maintenance_mode: true });
    useAuthStore().user = { id: 1, roles: ['moderator'] };

    const route = await goTo('admin.dashboard');

    expect(route.name).toBe('admin.dashboard');
  });

  it('bounces away from /maintenance once maintenance mode is back off', async () => {
    platformApi.fetchStatusOnce.mockResolvedValue({ maintenance_mode: false });

    const route = await goTo('maintenance');

    expect(route.name).toBe('landing');
  });
});
