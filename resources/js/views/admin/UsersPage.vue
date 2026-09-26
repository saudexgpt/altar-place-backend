<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <h1 class="text-2xl font-heading font-semibold">User Management</h1>
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="text-xs font-medium border border-navy-500/60 rounded-full px-4 py-2 hover:bg-navy-800 transition-colors disabled:opacity-50"
          :disabled="isExporting"
          @click="exportCsv"
        >
          {{ isExporting ? 'Exporting…' : 'Export CSV' }}
        </button>
        <div class="relative w-72">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
          <input
            v-model="query"
            type="search"
            placeholder="Search name or email"
            class="w-full bg-navy-800 border border-navy-500/60 rounded-full pl-9 pr-4 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
            @input="debouncedLoad"
          />
        </div>
      </div>
    </div>

    <div v-if="selectedIds.size" class="flex items-center justify-between bg-navy-800 border border-gold/30 rounded-xl px-4 py-2.5 mb-3 text-sm">
      <span>{{ selectedIds.size }} selected</span>
      <div class="flex gap-3">
        <button type="button" class="text-warning font-medium hover:underline" @click="promptBulkSuspend">Suspend selected</button>
        <button type="button" class="text-ink-muted hover:text-white" @click="selectedIds.clear()">Clear</button>
      </div>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <div class="bg-navy-800 border border-navy-500/40 rounded-2xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-navy-500/40 text-left text-ink-muted text-xs uppercase tracking-wide">
            <th class="px-5 py-3 w-8">
              <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
            </th>
            <th class="px-5 py-3">User</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Roles</th>
            <th class="px-5 py-3">Joined</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-navy-500/20 last:border-0">
            <td class="px-5 py-3">
              <input type="checkbox" :checked="selectedIds.has(user.id)" @change="toggleSelect(user.id)" />
            </td>
            <td class="px-5 py-3">
              <button type="button" class="flex items-center gap-3 text-left hover:opacity-80" @click="openDetail(user)">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-xs font-semibold shrink-0">
                  {{ initialsFor(user.name) }}
                </div>
                <div class="min-w-0">
                  <p class="font-medium truncate flex items-center gap-1">
                    {{ user.name }}
                    <CheckCircle2 v-if="user.is_verified" :size="13" class="text-gold" />
                  </p>
                  <p class="text-xs text-ink-muted truncate">{{ user.email }}</p>
                </div>
              </button>
            </td>
            <td class="px-5 py-3">
              <Badge :label="user.status" :tone="statusTone(user.status)" />
              <p v-if="user.status_reason" class="text-xs text-ink-muted mt-1 max-w-[160px] truncate">{{ user.status_reason }}</p>
            </td>
            <td class="px-5 py-3">
              <div class="flex gap-1 flex-wrap">
                <Badge v-for="role in user.roles" :key="role" :label="role" tone="info" />
              </div>
            </td>
            <td class="px-5 py-3 text-ink-muted whitespace-nowrap">{{ formatDate(user.created_at) }}</td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <button v-if="!user.is_verified" class="text-xs font-medium text-gold hover:underline mr-3" @click="verify(user)">Verify</button>
              <button v-if="user.status !== 'active'" class="text-xs font-medium text-success hover:underline mr-3" @click="reactivate(user)">Reactivate</button>
              <button v-if="user.status === 'active'" class="text-xs font-medium text-warning hover:underline mr-3" @click="promptSuspend(user)">Suspend</button>
              <button v-if="user.status !== 'banned'" class="text-xs font-medium text-danger hover:underline" @click="promptBan(user)">Ban</button>
            </td>
          </tr>
          <tr v-if="!isLoading && !loadError && !users.length">
            <td colspan="6" class="px-5 py-10 text-center text-ink-muted">No users match this search.</td>
          </tr>
        </tbody>
      </table>

      <Pagination :meta="meta" @change="goToPage" />
    </div>

    <ConfirmDialog
      v-if="dialog"
      :title="dialog.title"
      :message="dialog.message"
      :confirm-label="dialog.confirmLabel"
      :danger="dialog.danger"
      :with-reason="true"
      @confirm="dialog.onConfirm"
      @cancel="dialog = null"
    />

    <UserDetailModal
      v-if="detailUserId"
      :user-id="detailUserId"
      @close="detailUserId = null"
      @updated="handleUserUpdated"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Search, CheckCircle2 } from '@lucide/vue';
import { adminApi } from '@/services/adminApi';
import Badge from '@/components/ui/Badge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import Pagination from '@/components/ui/Pagination.vue';
import UserDetailModal from '@/components/admin/UserDetailModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const users = ref([]);
const meta = ref(null);
const page = ref(1);
const query = ref('');
const isLoading = ref(true);
const loadError = ref('');
const isExporting = ref(false);
const dialog = ref(null);
const detailUserId = ref(null);
const selectedIds = reactive(new Set());
let debounceTimer = null;

const allSelected = computed(() => users.value.length > 0 && users.value.every((u) => selectedIds.has(u.id)));

function initialsFor(name) {
  return (name ?? '').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function statusTone(status) {
  return { active: 'success', suspended: 'warning', banned: 'danger' }[status] ?? 'neutral';
}

async function load() {
  isLoading.value = true;
  loadError.value = '';
  try {
    const result = await adminApi.users({ q: query.value || undefined, page: page.value });
    users.value = result.data;
    meta.value = result.meta;
    selectedIds.clear();
  } catch {
    loadError.value = 'Could not load users. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function goToPage(newPage) {
  page.value = newPage;
  load();
}

function debouncedLoad() {
  clearTimeout(debounceTimer);
  page.value = 1;
  debounceTimer = setTimeout(load, 300);
}

function toggleSelect(id) {
  if (selectedIds.has(id)) selectedIds.delete(id);
  else selectedIds.add(id);
}

function toggleSelectAll() {
  if (allSelected.value) {
    users.value.forEach((u) => selectedIds.delete(u.id));
  } else {
    users.value.forEach((u) => selectedIds.add(u.id));
  }
}

function replaceUser(updated) {
  const index = users.value.findIndex((u) => u.id === updated.id);
  if (index !== -1) users.value[index] = updated;
}

function handleUserUpdated(updated) {
  replaceUser(updated);
}

function openDetail(user) {
  detailUserId.value = user.id;
}

async function verify(user) {
  replaceUser(await adminApi.verifyUser(user.id));
}

async function reactivate(user) {
  replaceUser(await adminApi.reactivateUser(user.id));
}

function promptSuspend(user) {
  dialog.value = {
    title: `Suspend ${user.name}?`,
    message: 'They will be signed out and unable to log in until reactivated.',
    confirmLabel: 'Suspend',
    danger: true,
    onConfirm: async (reason) => {
      replaceUser(await adminApi.suspendUser(user.id, reason));
      dialog.value = null;
    },
  };
}

function promptBan(user) {
  dialog.value = {
    title: `Ban ${user.name}?`,
    message: 'This immediately revokes all of their active sessions and tokens.',
    confirmLabel: 'Ban',
    danger: true,
    onConfirm: async (reason) => {
      replaceUser(await adminApi.banUser(user.id, reason));
      dialog.value = null;
    },
  };
}

function promptBulkSuspend() {
  const ids = Array.from(selectedIds);
  dialog.value = {
    title: `Suspend ${ids.length} user${ids.length === 1 ? '' : 's'}?`,
    message: 'Each will be signed out and unable to log in until reactivated.',
    confirmLabel: 'Suspend All',
    danger: true,
    onConfirm: async (reason) => {
      await Promise.all(ids.map((id) => adminApi.suspendUser(id, reason)));
      dialog.value = null;
      await load();
    },
  };
}

/** Walks every page matching the current search so the export isn't limited to the visible 20 rows. */
async function exportCsv() {
  isExporting.value = true;

  try {
    const rows = [];
    let currentPage = 1;
    let lastPage = 1;

    do {
      const result = await adminApi.users({ q: query.value || undefined, page: currentPage });
      rows.push(...result.data);
      lastPage = result.meta.last_page;
      currentPage += 1;
    } while (currentPage <= lastPage && currentPage <= 50);

    const header = ['Name', 'Email', 'Status', 'Roles', 'Verified', 'Joined'];
    const lines = rows.map((u) => [
      u.name, u.email, u.status, u.roles.join('|'), u.is_verified ? 'yes' : 'no', u.created_at,
    ].map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(','));

    const csv = [header.join(','), ...lines].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `users-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } finally {
    isExporting.value = false;
  }
}

onMounted(load);
</script>
