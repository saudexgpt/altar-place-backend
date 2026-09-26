<template>
  <div class="flex min-h-screen bg-navy-950 text-ink">
    <StudioSidebar studio-label="Menu" :nav-items="navItems" />

    <main class="flex-1 min-w-0 p-6 pb-28 overflow-y-auto">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { Home, Search, Library, User } from '@lucide/vue';
import StudioSidebar from '@/components/layout/StudioSidebar.vue';

const navItems = [
  { name: 'listener.home', label: 'Home', icon: Home },
  { name: 'listener.search', label: 'Search', icon: Search },
  { name: 'listener.library', label: 'Your Library', icon: Library },
  { name: 'listener.profile', label: 'Profile', icon: User },
];
</script>
