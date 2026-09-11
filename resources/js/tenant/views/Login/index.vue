<script setup lang="ts">
import { Goal } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import AuthSportsPanel from '@/components/AuthSportsPanel.vue'
import LoginForm from "@tenant/components/LoginForm.vue"
</script>

<template>
  <main class="relative grid min-h-svh overflow-hidden bg-background lg:grid-cols-[minmax(0,0.88fr)_minmax(34rem,1.12fr)]">
    <div class="pointer-events-none absolute inset-0 overflow-hidden lg:hidden" aria-hidden="true">
      <div class="absolute -top-28 -right-28 size-72 rounded-full bg-primary/12 blur-3xl" />
      <div class="absolute -bottom-24 -left-24 size-72 rounded-full bg-secondary/15 blur-3xl" />
    </div>

    <section class="relative z-10 flex min-h-svh flex-col px-5 py-6 sm:px-10 sm:py-8 lg:px-12 xl:px-16">
      <div class="flex items-center justify-between">
        <RouterLink :to="{ name: 'Home' }" class="group flex items-center gap-2.5 font-semibold tracking-tight">
          <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg shadow-primary/20 transition-transform group-hover:-rotate-3 group-hover:scale-105">
            <Goal class="size-5" />
          </div>
          <span class="text-lg">Fullbolito</span>
        </RouterLink>
        <span class="hidden text-xs text-muted-foreground sm:block">Panel de administración</span>
      </div>

      <div class="flex flex-1 items-center justify-center">
        <div class="my-10 w-full max-w-md rounded-3xl border border-border/70 bg-card/80 p-6 shadow-2xl shadow-foreground/[0.04] backdrop-blur-sm sm:p-8 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
          <LoginForm />
        </div>
      </div>

      <p class="text-center text-xs text-muted-foreground lg:text-left">
        Gestión simple para equipos que no paran
      </p>
    </section>

    <AuthSportsPanel
      eyebrow="Todo tu negocio en un solo equipo"
      title="El control del partido empieza aquí."
      description="Organiza tu operación diaria, mantén cada horario visible y ofrece una experiencia más rápida a tus clientes."
    />
  </main>
</template>
