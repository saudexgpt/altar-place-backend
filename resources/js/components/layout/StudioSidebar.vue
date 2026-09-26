<template>
  <aside class="w-64 shrink-0 bg-navy-900 border-r border-navy-500/60 flex flex-col h-screen sticky top-0">
    <router-link :to="{ name: 'landing' }" class="flex items-center gap-2 px-6 py-6">
      <img src="/images/logo-mark.png" alt="" class="h-8 w-8" />
      <span class="font-heading font-semibold text-lg">
        <span class="text-white">Altar</span><span class="text-gold">Place</span>
      </span>
    </router-link>

    <p class="px-6 mb-3 text-xs font-semibold tracking-widest uppercase text-ink-muted">{{ studioLabel }}</p>

    <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
      <RouterLink
        v-for="item in navItems"
        :key="item.name"
        :to="{ name: item.name }"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
        :class="isActive(item.name)
          ? 'bg-gold text-navy-950'
          : 'text-ink-muted hover:bg-navy-700 hover:text-white'"
      >
        <component :is="item.icon" :size="18" />
        {{ item.label }}
      </RouterLink>
    </nav>

    <div class="p-3 border-t border-navy-500/60">
      <RouterLink
        :to="{ name: 'landing' }"
        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-ink-muted hover:bg-navy-700 hover:text-white transition-colors mb-1"
      >
        <ArrowLeft :size="18" />
        Back to AltarPlace
      </RouterLink>
      <div class="flex items-center gap-3 px-3 py-2 mb-1">
        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-9 h-9 rounded-full object-cover" alt="" />
        <div v-else class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-xs font-semibold text-white">
          {{ initials }}
        </div>
        <div class="min-w-0">
          <p class="text-sm font-medium truncate">{{ auth.user?.name }}</p>
          <p class="text-xs text-ink-muted truncate">{{ auth.user?.email }}</p>
        </div>
      </div>
      <button
        type="button"
        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-ink-muted hover:bg-navy-700 hover:text-white transition-colors"
        @click="handleLogout"
      >
        <LogOut :size="18" />
        Log out
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, LogOut } from '@lucide/vue';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
  studioLabel: { type: String, required: true },
  navItems: { type: Array, required: true },
});

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

function isActive(name) {
  return route.name === name;
}

const initials = computed(() =>
  (auth.user?.name ?? '')
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
);

async function handleLogout() {
  await auth.logout();
  router.replace({ name: 'landing' });
}
</script>
