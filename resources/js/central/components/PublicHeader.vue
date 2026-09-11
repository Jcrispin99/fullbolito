<script setup lang="ts">
import { computed } from "vue"
import { RouterLink } from "vue-router"
import { Goal } from "lucide-vue-next"
import { Button } from "@/components/ui/button"
import { useAuthStore } from "@central/stores/auth"

withDefaults(defineProps<{
  active?: "home" | "marketplace"
}>(), {
  active: "home",
})

const authStore = useAuthStore()
const isAuthenticated = computed(() => authStore.isAuthenticated)
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-white/10 bg-[#04110c]/92 text-white shadow-lg shadow-black/10 backdrop-blur-xl">
    <div class="mx-auto flex h-[4.5rem] max-w-6xl items-center justify-between px-5 sm:px-6">
      <RouterLink :to="{ name: 'Home' }" class="group flex shrink-0 items-center gap-2.5 font-semibold tracking-tight">
        <div class="flex size-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 shadow-lg transition-transform group-hover:-rotate-3 group-hover:scale-105">
          <Goal class="size-5 text-secondary" />
        </div>
        <span class="text-lg">Fullbolito</span>
      </RouterLink>

      <nav class="hidden items-center gap-7 text-sm text-white/55 md:flex" aria-label="Navegación principal">
        <RouterLink :to="{ name: 'Home' }" class="transition hover:text-white" :class="active === 'home' ? 'font-medium text-white' : ''">
          Inicio
        </RouterLink>
        <RouterLink :to="{ name: 'Marketplace' }" class="transition hover:text-white" :class="active === 'marketplace' ? 'font-medium text-white' : ''">
          Reservar
        </RouterLink>
        <RouterLink :to="{ name: 'Home', hash: '#features' }" class="transition hover:text-white">Producto</RouterLink>
        <RouterLink :to="{ name: 'Home', hash: '#pricing' }" class="transition hover:text-white">Planes</RouterLink>
      </nav>

      <div class="flex items-center gap-1.5 sm:gap-2">
        <Button v-if="isAuthenticated" as-child variant="ghost" size="sm" class="text-white hover:bg-white/10 hover:text-white">
          <RouterLink :to="{ name: 'Dashboard' }">Panel</RouterLink>
        </Button>
        <template v-else>
          <Button as-child variant="ghost" size="sm" class="px-2.5 text-white/75 hover:bg-white/10 hover:text-white sm:px-3">
            <RouterLink :to="{ name: 'Login' }">Ingresar</RouterLink>
          </Button>
          <Button as-child size="sm" class="rounded-xl px-3 shadow-lg shadow-primary/20 sm:px-4">
            <RouterLink :to="{ name: 'Register' }">Soy un complejo</RouterLink>
          </Button>
        </template>
      </div>
    </div>
  </header>
</template>
