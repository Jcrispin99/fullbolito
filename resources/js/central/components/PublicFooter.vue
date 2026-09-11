<script setup lang="ts">
import { RouterLink } from "vue-router"
import { Goal } from "lucide-vue-next"
</script>

<template>
  <footer class="border-t border-white/10 bg-[#04110c] text-white">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-8 text-sm text-white/45 sm:flex-row sm:px-6">
      <div class="flex items-center gap-2">
        <div class="flex size-7 items-center justify-center rounded-lg border border-white/10 bg-white/[0.07]">
          <Goal class="size-4 text-secondary" />
        </div>
        <span>© {{ new Date().getFullYear() }} Fullbolito</span>
      </div>
      <div class="flex items-center gap-5">
        <RouterLink :to="{ name: 'Home' }" class="transition hover:text-white">Inicio</RouterLink>
        <RouterLink :to="{ name: 'Marketplace' }" class="transition hover:text-white">Reservar</RouterLink>
        <RouterLink :to="{ name: 'Home', hash: '#pricing' }" class="transition hover:text-white">Planes</RouterLink>
        <RouterLink :to="{ name: 'Login' }" class="transition hover:text-white">Ingresar</RouterLink>
      </div>
    </div>
  </footer>
</template>
