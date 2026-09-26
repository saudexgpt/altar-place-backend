<template>
  <div class="flex min-h-screen bg-navy-950 text-ink">
    <StudioSidebar studio-label="Advertiser Studio" :nav-items="navItems" />

    <main class="flex-1 min-w-0 p-6 pb-24 overflow-y-auto">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { LayoutDashboard, Megaphone } from '@lucide/vue';
import StudioSidebar from '@/components/layout/StudioSidebar.vue';

const navItems = [
  { name: 'advertiser.dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { name: 'advertiser.campaigns', label: 'Campaigns', icon: Megaphone },
];
</script>
