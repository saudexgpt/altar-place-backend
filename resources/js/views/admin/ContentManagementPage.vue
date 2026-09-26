<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-heading font-semibold">Content Management</h1>
      <select v-model="status" class="bg-navy-800 border border-navy-500/60 rounded-full text-xs px-3 py-2 focus:outline-none" @change="page = 1; load()">
        <option value="pending">Pending</option>
        <option value="actioned">Actioned</option>
        <option value="dismissed">Dismissed</option>
        <option value="">All</option>
      </select>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <div v-else class="space-y-3">
      <div v-for="report in reports" :key="report.id" class="bg-navy-800 border border-navy-500/40 rounded-xl p-4 flex items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <Badge :label="report.reportable_type" tone="neutral" />
            <Badge :label="report.status" :tone="statusTone(report.status)" />
          </div>
          <p class="font-medium truncate">{{ report.reportable_summary ?? `#${report.reportable_id}` }}</p>
          <p class="text-xs text-ink-muted">Reason: {{ report.reason }} · reported by {{ report.reporter?.name ?? 'Unknown' }}</p>
          <p v-if="report.details" class="text-xs text-ink-muted italic mt-1">{{ report.details }}</p>
        </div>
        <div v-if="report.status === 'pending'" class="flex gap-3 shrink-0">
          <button class="text-xs font-medium text-ink-muted hover:text-white" @click="dismiss(report)">Dismiss</button>
          <button class="text-xs font-medium text-danger hover:underline" @click="promptAction(report)">Take Action</button>
        </div>
      </div>

      <p v-if="!isLoading && !reports.length" class="text-ink-muted text-center py-10">No reports to review.</p>
    </div>

    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl mt-3" v-if="reports.length">
      <Pagination :meta="meta" @change="goToPage" />
    </div>

    <ConfirmDialog
      v-if="dialog"
      title="Take action on this report?"
      message="This removes the reported content (rejects the track, or deletes the comment)."
      confirm-label="Take Action"
      :danger="true"
      :with-reason="true"
      @confirm="dialog.onConfirm"
      @cancel="dialog = null"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { adminApi } from '@/services/adminApi';
import Badge from '@/components/ui/Badge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import Pagination from '@/components/ui/Pagination.vue';
import LoadError from '@/components/ui/LoadError.vue';

const reports = ref([]);
const meta = ref(null);
const page = ref(1);
const status = ref('pending');
const isLoading = ref(true);
const loadError = ref('');
const dialog = ref(null);

function statusTone(value) {
  return { pending: 'warning', actioned: 'danger', dismissed: 'neutral' }[value] ?? 'neutral';
}

async function load() {
  isLoading.value = true;
  loadError.value = '';
  try {
    const result = await adminApi.reports({ status: status.value || undefined, page: page.value });
    reports.value = result.data;
    meta.value = result.meta;
  } catch {
    loadError.value = 'Could not load reports. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function goToPage(newPage) {
  page.value = newPage;
  load();
}

async function dismiss(report) {
  await adminApi.resolveReport(report.id, 'dismiss');
  reports.value = reports.value.filter((r) => r.id !== report.id);
}

function promptAction(report) {
  dialog.value = {
    onConfirm: async (note) => {
      await adminApi.resolveReport(report.id, 'action', note);
      reports.value = reports.value.filter((r) => r.id !== report.id);
      dialog.value = null;
    },
  };
}

onMounted(load);
</script>
