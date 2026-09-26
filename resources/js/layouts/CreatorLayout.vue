<template>
  <div class="flex min-h-screen bg-navy-950 text-ink">
    <StudioSidebar studio-label="Creator Studio" :nav-items="navItems" />

    <main class="flex-1 min-w-0 p-6 pb-24 overflow-y-auto">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { LayoutDashboard, Music, Disc3, BarChart3 } from '@lucide/vue';
import StudioSidebar from '@/components/layout/StudioSidebar.vue';

const navItems = [
  { name: 'creator.dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { name: 'creator.tracks', label: 'My Tracks', icon: Music },
  { name: 'creator.albums', label: 'Albums', icon: Disc3 },
  { name: 'creator.analytics', label: 'Analytics', icon: BarChart3 },
];
</script>
