<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-heading font-semibold">Campaigns</h1>
      <button
        type="button"
        class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2 hover:bg-gold-tint transition-colors flex items-center gap-2"
        @click="showCreateModal = true"
      >
        <Plus :size="16" /> Create Campaign
      </button>
    </div>

    <LoadError v-if="loadError" :message="loadError" @retry="loadCampaigns" />

    <div v-else class="bg-navy-800 border border-navy-500/40 rounded-2xl overflow-hidden">
      <div v-if="isLoading" class="py-16 text-center text-ink-muted">Loading…</div>
      <p v-else-if="!campaigns.length" class="py-16 text-center text-ink-muted">
        No campaigns yet. Create your first campaign to get started.
      </p>
      <table v-else class="w-full text-sm">
        <thead class="text-left text-xs text-ink-muted uppercase tracking-wide border-b border-navy-500/40">
          <tr>
            <th class="px-5 py-3">Campaign</th>
            <th class="px-5 py-3">Type</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Impressions</th>
            <th class="px-5 py-3">Clicks</th>
            <th class="px-5 py-3">CTR</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="campaign in campaigns" :key="campaign.id" class="border-b border-navy-500/20 last:border-0">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-navy-700 overflow-hidden shrink-0">
                  <img v-if="campaign.image_url" :src="campaign.image_url" class="w-full h-full object-cover" alt="" />
                  <div v-else class="w-full h-full flex items-center justify-center text-ink-muted"><Megaphone :size="14" /></div>
                </div>
                <span class="font-medium truncate">{{ campaign.headline }}</span>
              </div>
            </td>
            <td class="px-5 py-3 text-ink-muted capitalize">{{ campaign.type.replace('_', ' ') }}</td>
            <td class="px-5 py-3"><Badge :label="campaign.status" :tone="statusTone(campaign.status)" /></td>
            <td class="px-5 py-3 text-ink-muted">{{ format(campaign.impressions_count) }}</td>
            <td class="px-5 py-3 text-ink-muted">{{ format(campaign.clicks_count) }}</td>
            <td class="px-5 py-3 text-ink-muted">{{ campaign.click_through_rate ?? 0 }}%</td>
            <td class="px-5 py-3 text-right">
              <button type="button" class="text-xs font-semibold text-gold hover:underline mr-3" @click="editingCampaign = campaign">Edit</button>
              <button type="button" class="text-xs font-semibold text-danger hover:underline" @click="campaignPendingDelete = campaign">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <CampaignFormModal v-if="showCreateModal" @close="showCreateModal = false" @saved="onCreated" />
    <CampaignFormModal v-if="editingCampaign" :campaign="editingCampaign" @close="editingCampaign = null" @saved="onUpdated" />

    <ConfirmDialog
      v-if="campaignPendingDelete"
      title="Delete this campaign?"
      :message="`“${campaignPendingDelete.headline}” will be permanently removed.`"
      confirm-label="Delete"
      danger
      @cancel="campaignPendingDelete = null"
      @confirm="deleteCampaign"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Plus, Megaphone } from '@lucide/vue';
import { advertiserApi } from '@/services/advertiserApi';
import Badge from '@/components/ui/Badge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import CampaignFormModal from '@/components/advertiser/CampaignFormModal.vue';
import LoadError from '@/components/ui/LoadError.vue';

const campaigns = ref([]);
const isLoading = ref(true);
const loadError = ref('');
const showCreateModal = ref(false);
const editingCampaign = ref(null);
const campaignPendingDelete = ref(null);

function format(value) {
  return new Intl.NumberFormat().format(value ?? 0);
}

function statusTone(status) {
  return { active: 'success', paused: 'warning', completed: 'info', draft: 'neutral' }[status] ?? 'neutral';
}

async function loadCampaigns() {
  isLoading.value = true;
  loadError.value = '';
  try {
    campaigns.value = await advertiserApi.campaigns();
  } catch {
    loadError.value = 'Could not load your campaigns. Check your connection and try again.';
  } finally {
    isLoading.value = false;
  }
}

function onCreated() {
  showCreateModal.value = false;
  loadCampaigns();
}

function onUpdated() {
  editingCampaign.value = null;
  loadCampaigns();
}

async function deleteCampaign() {
  const campaign = campaignPendingDelete.value;
  campaignPendingDelete.value = null;
  await advertiserApi.deleteCampaign(campaign.id);
  loadCampaigns();
}

onMounted(loadCampaigns);
</script>
