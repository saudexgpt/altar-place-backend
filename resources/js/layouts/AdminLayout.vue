<template>
  <div class="flex min-h-screen bg-navy-950 text-ink">
    <AdminSidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AdminTopbar />

      <main class="flex-1 p-6 pb-24 overflow-y-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import AdminSidebar from '@/components/layout/AdminSidebar.vue';
import AdminTopbar from '@/components/layout/AdminTopbar.vue';
</script>
