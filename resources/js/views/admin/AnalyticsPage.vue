<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-heading font-semibold">Business Analytics</h1>
      <button
        v-if="overview"
        type="button"
        class="text-xs font-medium border border-navy-500/60 rounded-full px-4 py-2 hover:bg-navy-800 transition-colors"
        @click="exportCsv"
      >
        Export CSV
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />
    <p v-else-if="!overview" class="text-sm text-ink-muted">Loading…</p>

    <template v-else>

    <section>
      <h2 class="font-heading font-semibold mb-3">Active Users</h2>
      <div class="grid grid-cols-2 gap-4 max-w-md">
        <MetricTile label="DAU" :value="overview.dau" />
        <MetricTile label="MAU" :value="overview.mau" />
      </div>
      <p class="text-xs text-ink-muted mt-2">{{ overview.dau_mau_note }}</p>
    </section>

    <section>
      <h2 class="font-heading font-semibold mb-3">Revenue</h2>
      <div class="grid grid-cols-2 gap-4 max-w-md">
        <MetricTile label="Total" :value="formatMoney(overview.revenue.total)" />
        <MetricTile label="Last 30 Days" :value="formatMoney(overview.revenue.last_30_days)" />
      </div>
      <p class="text-xs text-ink-muted mt-2">{{ overview.revenue.note }}</p>
    </section>

    <section>
      <h2 class="font-heading font-semibold mb-3">Subscriptions</h2>
      <div class="grid grid-cols-2 gap-4 max-w-md">
        <MetricTile label="Conversion Rate" :value="`${overview.conversion_rate}%`" />
        <MetricTile label="Churn Rate" :value="`${overview.churn_rate}%`" />
      </div>
      <p class="text-xs text-ink-muted mt-2">{{ overview.churn_note }}</p>
    </section>

    <section>
      <h2 class="font-heading font-semibold mb-3">Platform</h2>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl">
        <MetricTile label="Users" :value="overview.total_users" />
        <MetricTile label="Creators" :value="overview.total_creators" />
        <MetricTile label="Advertisers" :value="overview.total_advertisers" />
        <MetricTile label="Tracks" :value="overview.total_tracks" />
      </div>
    </section>
    </template>
  </div>
</template>

<script setup>
import { h, onMounted, ref } from 'vue';
import { adminApi } from '@/services/adminApi';
import LoadError from '@/components/ui/LoadError.vue';

const overview = ref(null);
const loadError = ref('');

function formatMoney(kobo) {
  return `₦${(kobo / 100).toLocaleString()}`;
}

const MetricTile = {
  props: { label: String, value: [String, Number] },
  render() {
    return h('div', { class: 'bg-navy-800 border border-navy-500/40 rounded-xl p-4 text-center' }, [
      h('p', { class: 'text-xl font-heading font-semibold text-gold' }, String(this.value)),
      h('p', { class: 'text-xs text-ink-muted mt-1' }, this.label),
    ]);
  },
};

async function load() {
  loadError.value = '';
  try {
    overview.value = await adminApi.overview();
  } catch {
    loadError.value = 'Could not load analytics. Check your connection and try again.';
  }
}

onMounted(load);

function exportCsv() {
  const rows = [
    ['Metric', 'Value'],
    ['DAU', overview.value.dau],
    ['MAU', overview.value.mau],
    ['Revenue (total, kobo)', overview.value.revenue.total],
    ['Revenue (last 30 days, kobo)', overview.value.revenue.last_30_days],
    ['Conversion Rate (%)', overview.value.conversion_rate],
    ['Churn Rate (%)', overview.value.churn_rate],
    ['Total Users', overview.value.total_users],
    ['Total Creators', overview.value.total_creators],
    ['Total Advertisers', overview.value.total_advertisers],
    ['Total Tracks', overview.value.total_tracks],
  ];

  const csv = rows.map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `analytics-${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}
</script>
