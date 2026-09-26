<template>
  <div class="min-h-screen flex bg-navy-950 text-ink">
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-navy-600 via-navy-800 to-navy-950">
      <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 30% 20%, #f0b030, transparent 40%), radial-gradient(circle at 80% 80%, #0036d6, transparent 45%)"></div>
      <div class="relative z-10 flex flex-col items-start justify-center px-14 py-16">
        <img src="/images/logo-mark.png" alt="" class="h-32 w-48 mb-6" />
        <h1 class="text-3xl font-heading font-semibold mb-2">
          <span class="text-white">Altar</span><span class="text-gold">Place</span>
        </h1>
        <p class="text-ink-muted mb-10">Listen · Meditate · Grow</p>
        <p class="text-2xl font-heading text-white leading-snug max-w-sm">
          Your Journey of Faith &amp; Music <br /> Starts Here
        </p>
      </div>
    </div>

    <div class="flex-1 flex items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-sm">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup></script>
