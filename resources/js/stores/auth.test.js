import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth';

beforeEach(() => {
  setActivePinia(createPinia());
});

describe('auth store', () => {
  it('isAuthenticated is false until a user is set', () => {
    const auth = useAuthStore();

    expect(auth.isAuthenticated).toBe(false);
    auth.user = { id: 1, roles: [] };
    expect(auth.isAuthenticated).toBe(true);
  });

  it('hasRole and isStaff read from the user roles list', () => {
    const auth = useAuthStore();
    auth.user = { id: 1, roles: ['listener', 'creator'] };

    expect(auth.hasRole('creator')).toBe(true);
    expect(auth.hasRole('moderator')).toBe(false);
    expect(auth.isStaff()).toBe(false);

    auth.user = { id: 2, roles: ['moderator'] };
    expect(auth.isStaff()).toBe(true);
  });

  it('setUser replaces the current user object', () => {
    const auth = useAuthStore();
    auth.user = { id: 1, name: 'Old Name', roles: [] };

    auth.setUser({ id: 1, name: 'New Name', roles: [] });

    expect(auth.user.name).toBe('New Name');
  });

  describe('forceLogout', () => {
    // Regression coverage: forceLogout() must distinguish "a signed-in user's
    // session just expired" (should redirect to /login) from "the boot-time
    // /auth/me check found no one signed in" (must NOT redirect a guest away
    // from the landing page). See app.js's UNAUTHORIZED_EVENT handler.
    it('reports the user was authenticated when a real session is cleared', () => {
      const auth = useAuthStore();
      auth.user = { id: 1, roles: [] };

      const wasAuthenticated = auth.forceLogout();

      expect(wasAuthenticated).toBe(true);
      expect(auth.user).toBeNull();
    });

    it('reports false when called on an already-guest session', () => {
      const auth = useAuthStore();

      const wasAuthenticated = auth.forceLogout();

      expect(wasAuthenticated).toBe(false);
      expect(auth.user).toBeNull();
    });
  });
});
