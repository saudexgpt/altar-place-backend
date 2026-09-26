<template>
  <div>
    <LoadError v-if="loadError" :message="loadError" @retry="load" />
    <p v-else-if="!dashboard" class="text-sm text-ink-muted">Loading…</p>

    <div v-else class="space-y-6">
      <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-navy-600 via-navy-800 to-navy-950 p-8">
        <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-2">Advertiser Studio</p>
        <h1 class="text-3xl font-heading font-semibold mb-1">{{ dashboard.advertiser.company_name }}</h1>
        <p class="text-ink-muted max-w-md">Manage your ad campaigns and see how they're performing.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Campaigns" :value="format(dashboard.total_campaigns)" :icon="Megaphone" icon-bg="bg-gold/15" icon-color="text-gold" />
        <StatCard label="Active Campaigns" :value="format(dashboard.active_campaigns)" :icon="Zap" icon-bg="bg-success/15" icon-color="text-success" />
        <StatCard label="Impressions" :value="format(dashboard.total_impressions)" :icon="Eye" icon-bg="bg-navy-400/20" icon-color="text-navy-300" />
        <StatCard label="Clicks" :value="format(dashboard.total_clicks)" :icon="MousePointerClick" icon-bg="bg-violet-500/15" icon-color="text-violet-400" />
      </div>

      <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-5">
        <h2 class="font-heading font-semibold flex items-center gap-2 mb-4">
          <Zap :size="18" class="text-gold" /> Quick Actions
        </h2>
        <RouterLink
          :to="{ name: 'advertiser.campaigns' }"
          class="inline-flex items-center gap-2 bg-gold text-navy-950 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-gold-tint transition-colors"
        >
          <Plus :size="16" /> Create a Campaign
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Megaphone, Zap, Eye, MousePointerClick, Plus } from '@lucide/vue';
import { advertiserApi } from '@/services/advertiserApi';
import StatCard from '@/components/ui/StatCard.vue';
import LoadError from '@/components/ui/LoadError.vue';

const dashboard = ref(null);
const loadError = ref('');

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

async function load() {
  loadError.value = '';
  try {
    dashboard.value = await advertiserApi.dashboard();
  } catch {
    loadError.value = 'Could not load your dashboard. Check your connection and try again.';
  }
}

onMounted(load);
</script>
