<template>
  <header class="sticky top-0 z-20 bg-navy-950/90 backdrop-blur border-b border-navy-500/40">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
      <RouterLink :to="{ name: 'landing' }" class="flex items-center gap-2">
        <img src="/images/logo-mark.png" alt="" class="h-8 w-8" />
        <span class="font-heading font-semibold text-lg">
          <span class="text-white">Altar</span><span class="text-gold">Place</span>
        </span>
      </RouterLink>

      <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
        <a v-for="link in links" :key="link.label" :href="link.href" class="text-ink-muted hover:text-white transition-colors">
          {{ link.label }}
        </a>
      </nav>

      <div class="flex items-center gap-3">
        <button
          type="button"
          aria-label="Search"
          class="hidden sm:flex w-9 h-9 items-center justify-center rounded-full text-ink-muted hover:bg-navy-800 hover:text-white transition-colors"
          @click="goToSearch"
        >
          <Search :size="18" />
        </button>

        <template v-if="auth.isAuthenticated">
          <RouterLink
            v-if="auth.isStaff()"
            :to="{ name: 'admin.dashboard' }"
            class="hidden sm:inline-flex items-center border border-navy-500/60 rounded-full px-4 py-2 text-sm font-medium hover:bg-navy-800 transition-colors"
          >
            Admin Panel
          </RouterLink>
          <template v-else>
            <RouterLink
              :to="{ name: auth.hasRole('creator') ? 'creator.dashboard' : 'creator.apply' }"
              class="hidden sm:inline-flex items-center border border-navy-500/60 rounded-full px-4 py-2 text-sm font-medium hover:bg-navy-800 transition-colors"
            >
              {{ auth.hasRole('creator') ? 'Creator Studio' : 'Become a Creator' }}
            </RouterLink>
            <RouterLink
              :to="{ name: auth.hasRole('advertiser') ? 'advertiser.dashboard' : 'advertiser.apply' }"
              class="hidden sm:inline-flex items-center border border-navy-500/60 rounded-full px-4 py-2 text-sm font-medium hover:bg-navy-800 transition-colors"
            >
              {{ auth.hasRole('advertiser') ? 'Advertiser Studio' : 'Become an Advertiser' }}
            </RouterLink>
          </template>
          <button
            type="button"
            class="inline-flex items-center bg-gold text-navy-950 rounded-full px-4 py-2 text-sm font-semibold hover:bg-gold-tint transition-colors"
            @click="handleLogout"
          >
            Log out
          </button>
        </template>

        <template v-else>
          <RouterLink
            :to="{ name: 'login' }"
            class="hidden sm:inline-flex items-center border border-navy-500/60 rounded-full px-4 py-2 text-sm font-medium hover:bg-navy-800 transition-colors"
          >
            Login
          </RouterLink>
          <RouterLink
            :to="{ name: 'register' }"
            class="inline-flex items-center bg-gold text-navy-950 rounded-full px-4 py-2 text-sm font-semibold hover:bg-gold-tint transition-colors"
          >
            Get Started
          </RouterLink>
        </template>
      </div>
    </div>
  </header>
</template>

<script setup>
import { Search } from '@lucide/vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const links = [
  { label: 'Home', href: '#' },
  { label: 'Music', href: '#explore' },
  { label: 'Word', href: '#explore' },
  { label: 'Features', href: '#explore' },
  { label: 'About', href: '#' },
];

async function handleLogout() {
  await auth.logout();
  router.replace({ name: 'landing' });
}

function goToSearch() {
  // A guest has nowhere to search yet (the listener search page requires
  // sign-in) — send them to sign in first, then straight to search.
  router.push(auth.isAuthenticated && !auth.isStaff() ? { name: 'listener.search' } : { name: 'login', query: { redirect: '/search' } });
}
</script>
