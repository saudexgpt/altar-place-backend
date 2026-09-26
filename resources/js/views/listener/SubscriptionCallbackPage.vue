<template>
  <div class="bg-navy-950 text-ink min-h-screen flex items-center justify-center px-6">
    <div class="max-w-sm text-center">
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-6" :class="iconBg">
        <component :is="icon" :size="24" :class="iconColor" />
      </div>
      <h1 class="text-xl font-heading font-semibold mb-2">{{ title }}</h1>
      <p class="text-sm text-ink-muted mb-6">{{ message }}</p>
      <RouterLink :to="{ name: 'listener.profile' }" class="inline-block bg-gold text-navy-950 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-gold-tint transition-colors">
        Back to Profile
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { CheckCircle2, XCircle, Loader2 } from '@lucide/vue';
import { subscriptionApi } from '@/services/subscriptionApi';

const route = useRoute();
const status = ref('loading'); // loading | success | failed

const icon = computed(() => ({ loading: Loader2, success: CheckCircle2, failed: XCircle }[status.value]));
const iconBg = computed(() => ({ loading: 'bg-navy-700', success: 'bg-success/15', failed: 'bg-danger/15' }[status.value]));
const iconColor = computed(() => ({ loading: 'text-ink-muted animate-spin', success: 'text-success', failed: 'text-danger' }[status.value]));
const title = computed(() => ({ loading: 'Confirming payment…', success: 'You are subscribed!', failed: 'Payment not confirmed' }[status.value]));
const message = computed(() => ({
  loading: 'Hang tight while we confirm your payment with the provider.',
  success: 'Your subscription is now active. Enjoy full access to AltarPlace.',
  failed: 'We could not confirm this payment. If you were charged, contact support — otherwise, try again from your profile.',
}[status.value]));

onMounted(async () => {
  const reference = typeof route.query.reference === 'string' ? route.query.reference : null;

  if (!reference) {
    status.value = 'failed';
    return;
  }

  try {
    await subscriptionApi.verify(reference);
    status.value = 'success';
  } catch {
    status.value = 'failed';
  }
});
</script>
