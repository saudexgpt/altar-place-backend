import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { maintenanceMessage, platformApi } from '@/services/platformApi';

export const routes = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/marketing/LandingPage.vue'),
    meta: { guestOnly: false },
  },
  {
    path: '/maintenance',
    name: 'maintenance',
    component: () => import('@/views/marketing/MaintenancePage.vue'),
    props: () => ({ message: maintenanceMessage.value }),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/oauth-callback',
    name: 'oauth-callback',
    component: () => import('@/views/auth/OAuthCallbackPage.vue'),
  },
  {
    path: '/subscription/callback',
    name: 'subscription.callback',
    component: () => import('@/views/listener/SubscriptionCallbackPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/listener-app',
    component: () => import('@/layouts/ListenerLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '/home', name: 'listener.home', component: () => import('@/views/listener/HomePage.vue') },
      { path: '/search', name: 'listener.search', component: () => import('@/views/listener/SearchPage.vue') },
      { path: '/library', name: 'listener.library', component: () => import('@/views/listener/LibraryPage.vue') },
      { path: '/profile', name: 'listener.profile', component: () => import('@/views/listener/ProfilePage.vue') },
    ],
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresStaff: true },
    children: [
      { path: '', redirect: { name: 'admin.dashboard' } },
      { path: 'dashboard', name: 'admin.dashboard', component: () => import('@/views/admin/DashboardPage.vue') },
      { path: 'music-library', name: 'admin.music-library', component: () => import('@/views/admin/MusicLibraryPage.vue') },
      { path: 'word-library', name: 'admin.word-library', component: () => import('@/views/admin/WordLibraryPage.vue') },
      { path: 'playlists', name: 'admin.playlists', component: () => import('@/views/admin/PlaylistsPage.vue') },
      { path: 'users', name: 'admin.users', component: () => import('@/views/admin/UsersPage.vue') },
      { path: 'content-management', name: 'admin.content-management', component: () => import('@/views/admin/ContentManagementPage.vue') },
      { path: 'analytics', name: 'admin.analytics', component: () => import('@/views/admin/AnalyticsPage.vue') },
      { path: 'settings', name: 'admin.settings', component: () => import('@/views/admin/SettingsPage.vue') },
    ],
  },
  {
    path: '/creator/apply',
    name: 'creator.apply',
    component: () => import('@/views/creator/CreatorApplyPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/creator',
    component: () => import('@/layouts/CreatorLayout.vue'),
    meta: { requiresAuth: true, requiresRole: 'creator' },
    children: [
      { path: '', redirect: { name: 'creator.dashboard' } },
      { path: 'dashboard', name: 'creator.dashboard', component: () => import('@/views/creator/CreatorDashboardPage.vue') },
      { path: 'tracks', name: 'creator.tracks', component: () => import('@/views/creator/CreatorTracksPage.vue') },
      { path: 'albums', name: 'creator.albums', component: () => import('@/views/creator/CreatorAlbumsPage.vue') },
      { path: 'analytics', name: 'creator.analytics', component: () => import('@/views/creator/CreatorAnalyticsPage.vue') },
    ],
  },
  {
    path: '/advertiser/apply',
    name: 'advertiser.apply',
    component: () => import('@/views/advertiser/AdvertiserApplyPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/advertiser',
    component: () => import('@/layouts/AdvertiserLayout.vue'),
    meta: { requiresAuth: true, requiresRole: 'advertiser' },
    children: [
      { path: '', redirect: { name: 'advertiser.dashboard' } },
      { path: 'dashboard', name: 'advertiser.dashboard', component: () => import('@/views/advertiser/AdvertiserDashboardPage.vue') },
      { path: 'campaigns', name: 'advertiser.campaigns', component: () => import('@/views/advertiser/AdvertiserCampaignsPage.vue') },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'landing' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

// Auth forms must stay reachable during maintenance so staff can sign in
// and lift it again — matches BlockDuringMaintenance's allowlist on the
// backend. Everything else a non-staff visitor could reach (landing, the
// listener SPA) shows the maintenance page instead, since their API calls
// would just fail with a 503 anyway.
const ALWAYS_ALLOWED_DURING_MAINTENANCE = ['login', 'register', 'forgot-password', 'reset-password', 'oauth-callback', 'maintenance'];

// Exported (rather than inlined into router.beforeEach) so it can be unit
// tested against a lightweight router built from stubbed components,
// without needing to actually resolve every lazy-loaded page.
export async function resolveNavigation(to) {
  const auth = useAuthStore();

  if (auth.isInitializing) {
    await auth.initialize();
  }

  if (!auth.isStaff() && !ALWAYS_ALLOWED_DURING_MAINTENANCE.includes(to.name)) {
    const status = await platformApi.fetchStatusOnce();
    if (status.maintenance_mode) {
      return { name: 'maintenance' };
    }
  }

  if (to.name === 'maintenance') {
    const status = await platformApi.fetchStatusOnce();
    if (!status.maintenance_mode) {
      return { name: 'landing' };
    }
  }

  // A logged-in listener has a real app to go to now — send them there
  // instead of the marketing page. Staff keep seeing it (e.g. to check how
  // it looks while signed in); they already have an "Admin Panel" link.
  if (to.name === 'landing' && auth.isAuthenticated && !auth.isStaff()) {
    return { name: 'listener.home' };
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (to.meta.requiresStaff && !auth.isStaff()) {
    return { name: 'landing' };
  }

  // Anyone can self-serve into the creator/advertiser role via the apply
  // endpoint (see CreatorController::apply / AdvertiserController::apply) —
  // there's no separate approval step, so "has the role" is the only gate.
  if (to.meta.requiresRole && !auth.hasRole(to.meta.requiresRole) && !auth.hasRole('super-admin')) {
    return { name: `${to.meta.requiresRole}.apply` };
  }

  if (to.name === 'creator.apply' && auth.hasRole('creator')) {
    return { name: 'creator.dashboard' };
  }

  if (to.name === 'advertiser.apply' && auth.hasRole('advertiser')) {
    return { name: 'advertiser.dashboard' };
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.isStaff() ? { name: 'admin.dashboard' } : { name: 'listener.home' };
  }

  return true;
}

router.beforeEach(resolveNavigation);

export default router;
