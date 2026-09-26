<template>
  <div class="bg-navy-800 border border-navy-500/40 rounded-2xl p-6 space-y-5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-gold/15 flex items-center justify-center shrink-0">
        <Crown :size="18" class="text-gold" />
      </div>
      <div>
        <h2 class="font-heading font-semibold">Subscription</h2>
        <p v-if="status" class="text-sm text-ink-muted">
          Current plan: <span class="text-white font-medium">{{ status.plan?.name }}</span>
        </p>
      </div>
    </div>

    <p v-if="error" class="text-sm text-danger">{{ error }}</p>
    <p v-if="actionMessage" class="text-sm text-success">{{ actionMessage }}</p>

    <div v-if="status?.subscription?.is_active" class="bg-navy-700/50 rounded-xl p-4 text-sm">
      <p>Renews/expires {{ formatDate(status.subscription.ends_at) }}</p>
      <p v-if="status.subscription.canceled_at" class="text-warning mt-1">
        Canceled — you'll keep access until it expires.
      </p>
      <button
        v-else
        type="button"
        class="text-xs font-semibold text-danger border border-danger/40 rounded-full px-4 py-1.5 mt-3 hover:bg-danger/10 transition-colors"
        :disabled="isCanceling"
        @click="cancel"
      >
        {{ isCanceling ? 'Canceling…' : 'Cancel subscription' }}
      </button>
    </div>

    <div v-if="!status?.is_premium" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-for="plan in payablePlans" :key="plan.id" class="border border-navy-500/40 rounded-xl p-4">
        <p class="font-semibold">{{ plan.name }}</p>
        <p class="text-lg font-heading font-semibold text-gold my-1">{{ formatMoney(plan.price, plan.currency) }}</p>
        <p class="text-xs text-ink-muted mb-3">per {{ plan.billing_interval }}</p>
        <button
          type="button"
          :disabled="checkingOutPlanId === plan.id"
          class="w-full text-sm font-semibold bg-gold text-navy-950 rounded-full py-2 hover:bg-gold-tint transition-colors disabled:opacity-50"
          @click="subscribe(plan)"
        >
          {{ checkingOutPlanId === plan.id ? 'Redirecting…' : 'Subscribe' }}
        </button>
      </div>
    </div>

    <!-- Family plan members -->
    <div v-if="status?.subscription?.plan?.slug === 'family' && status?.subscription?.is_active" class="border-t border-navy-500/40 pt-5">
      <h3 class="text-sm font-semibold mb-1 flex items-center gap-2"><Users2 :size="16" /> Family Members</h3>
      <p class="text-xs text-ink-muted mb-3">Up to {{ familyMembers?.max_family_members ?? 0 }} members.</p>

      <ul class="space-y-2 mb-3">
        <li v-for="member in familyMembers?.members ?? []" :key="member.id" class="flex items-center justify-between text-sm bg-navy-700/50 rounded-lg px-3 py-2">
          <span>{{ member.user?.name ?? member.invited_email }}</span>
          <button type="button" class="text-danger hover:underline text-xs" @click="removeMember(member)">Remove</button>
        </li>
        <li v-if="!familyMembers?.members?.length" class="text-sm text-ink-muted">No family members added yet.</li>
      </ul>

      <form class="flex gap-2" @submit.prevent="invite">
        <input
          v-model="inviteEmail"
          type="email"
          required
          placeholder="Family member's email"
          class="flex-1 bg-navy-700 border border-navy-500/60 rounded-lg px-3 py-2 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
        />
        <button type="submit" :disabled="isInviting" class="text-sm font-semibold bg-navy-700 rounded-lg px-4 py-2 hover:bg-navy-600 transition-colors disabled:opacity-50">
          Invite
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Crown, Users2 } from '@lucide/vue';
import { subscriptionApi } from '@/services/subscriptionApi';

const status = ref(null);
const plans = ref([]);
const familyMembers = ref(null);
const error = ref('');
const actionMessage = ref('');
const isCanceling = ref(false);
const checkingOutPlanId = ref(null);
const inviteEmail = ref('');
const isInviting = ref(false);

const payablePlans = computed(() => plans.value.filter((p) => p.price > 0));

function formatMoney(kobo, currency) {
  return `${currency === 'NGN' ? '₦' : currency + ' '}${(kobo / 100).toLocaleString()}`;
}

function formatDate(iso) {
  return iso ? new Date(iso).toLocaleDateString() : '—';
}

async function loadFamilyMembers() {
  try {
    familyMembers.value = await subscriptionApi.familyMembers();
  } catch {
    familyMembers.value = null;
  }
}

async function load() {
  error.value = '';
  try {
    const [statusRes, plansRes] = await Promise.all([subscriptionApi.status(), subscriptionApi.plans()]);
    status.value = statusRes;
    plans.value = plansRes;

    if (status.value?.subscription?.plan?.slug === 'family' && status.value.subscription.is_active) {
      await loadFamilyMembers();
    }
  } catch {
    error.value = 'Could not load your subscription details.';
  }
}

async function subscribe(plan) {
  checkingOutPlanId.value = plan.id;
  error.value = '';

  try {
    const result = await subscriptionApi.checkout(plan.id, 'paystack');
    window.location.href = result.authorization_url;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not start checkout.';
    checkingOutPlanId.value = null;
  }
}

async function cancel() {
  isCanceling.value = true;
  error.value = '';

  try {
    const result = await subscriptionApi.cancel();
    status.value.subscription = result.subscription;
    actionMessage.value = result.message;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not cancel your subscription.';
  } finally {
    isCanceling.value = false;
  }
}

async function invite() {
  isInviting.value = true;
  error.value = '';

  try {
    await subscriptionApi.inviteFamilyMember(inviteEmail.value);
    inviteEmail.value = '';
    await loadFamilyMembers();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not invite that person.';
  } finally {
    isInviting.value = false;
  }
}

async function removeMember(member) {
  await subscriptionApi.removeFamilyMember(member.id);
  await loadFamilyMembers();
}

onMounted(load);
</script>
