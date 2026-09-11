<script setup lang="ts">
import { CalendarCheck, Clock3, Goal, Sparkles, Trophy, Users } from 'lucide-vue-next'

withDefaults(defineProps<{
  eyebrow?: string
  title?: string
  description?: string
}>(), {
  eyebrow: 'Gestión deportiva, sin fricción',
  title: 'Tu cancha llena. Tu operación bajo control.',
  description: 'Reservas, horarios, cobros y clientes en un solo lugar, para que tú puedas concentrarte en hacer crecer el juego.',
})
</script>

<template>
  <aside class="relative hidden min-h-svh overflow-hidden bg-[#071a13] text-white lg:flex lg:flex-col">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
      <div class="absolute -top-32 -right-28 size-[32rem] rounded-full bg-primary/30 blur-3xl" />
      <div class="absolute -bottom-40 -left-32 size-[34rem] rounded-full bg-secondary/25 blur-3xl" />
      <div class="auth-sport-grid absolute inset-0 opacity-30" />
      <div class="absolute inset-0 bg-gradient-to-b from-transparent via-[#071a13]/10 to-[#071a13]/80" />
    </div>

    <div class="relative z-10 flex h-full min-h-svh flex-col px-10 py-9 xl:px-14 xl:py-12">
      <div class="flex items-center justify-between">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.07] px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur-md">
          <Sparkles class="size-3.5 text-secondary" />
          {{ eyebrow }}
        </div>
        <Trophy class="size-5 text-white/45" />
      </div>

      <div class="flex flex-1 items-center py-10">
        <div class="mx-auto w-full max-w-xl">
          <div class="relative mx-auto max-w-lg">
            <div class="absolute -inset-8 rounded-[2.5rem] bg-gradient-to-br from-primary/20 to-secondary/15 blur-3xl" />

            <div class="relative aspect-[1.18/1] overflow-hidden rounded-[2rem] border border-white/15 bg-white/[0.07] p-5 shadow-2xl shadow-black/30 backdrop-blur-xl xl:p-7">
              <div class="absolute top-5 right-5 z-20 flex items-center gap-2 rounded-full border border-white/10 bg-black/20 px-3 py-1.5 text-[11px] font-medium text-white/75 backdrop-blur-md">
                <span class="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.9)]" />
                En vivo
              </div>

              <div class="absolute inset-5 overflow-hidden rounded-[1.4rem] border border-white/20 bg-emerald-500/[0.08] xl:inset-7">
                <div class="absolute inset-4 rounded-lg border border-white/20">
                  <div class="absolute inset-y-0 left-1/2 border-l border-white/20" />
                  <div class="absolute top-1/2 left-1/2 size-24 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/20" />
                  <div class="absolute top-1/2 left-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/30" />
                  <div class="absolute inset-y-[27%] -left-px w-[18%] border border-l-0 border-white/20" />
                  <div class="absolute inset-y-[27%] -right-px w-[18%] border border-r-0 border-white/20" />
                </div>
              </div>

              <div class="absolute top-[24%] left-[7%] z-10 rounded-2xl border border-white/15 bg-[#0d251c]/90 p-3.5 shadow-xl backdrop-blur-md xl:p-4">
                <div class="flex items-center gap-3">
                  <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <CalendarCheck class="size-4" />
                  </div>
                  <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-white/45">Reservas hoy</p>
                    <p class="mt-0.5 text-xl font-bold">12</p>
                  </div>
                </div>
              </div>

              <div class="absolute top-[43%] right-[6%] z-10 rounded-2xl border border-white/15 bg-[#0d251c]/90 p-3.5 shadow-xl backdrop-blur-md xl:p-4">
                <div class="flex items-center gap-3">
                  <div class="flex size-9 items-center justify-center rounded-xl bg-secondary text-secondary-foreground">
                    <Users class="size-4" />
                  </div>
                  <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-white/45">Ocupación</p>
                    <p class="mt-0.5 text-xl font-bold">78%</p>
                  </div>
                </div>
              </div>

              <div class="absolute right-[12%] bottom-[9%] left-[12%] z-10 flex items-center gap-3 rounded-2xl border border-white/15 bg-[#0d251c]/95 p-4 shadow-2xl backdrop-blur-md">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10">
                  <Goal class="size-5 text-secondary" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-[10px] uppercase tracking-[0.16em] text-white/45">Próximo partido</p>
                  <p class="mt-0.5 truncate text-sm font-semibold">Cancha 2 · Los Halcones FC</p>
                </div>
                <div class="flex shrink-0 items-center gap-1.5 text-sm font-semibold text-secondary">
                  <Clock3 class="size-3.5" />
                  20:00
                </div>
              </div>
            </div>
          </div>

          <div class="mx-auto mt-10 max-w-lg">
            <h2 class="text-balance text-4xl font-bold tracking-tight xl:text-5xl">
              {{ title }}
            </h2>
            <p class="mt-4 max-w-md text-pretty text-sm leading-6 text-white/60 xl:text-base">
              {{ description }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between border-t border-white/10 pt-5 text-xs text-white/40">
        <span>Hecho para complejos que quieren crecer</span>
        <span class="flex items-center gap-1.5">
          <span class="size-1.5 rounded-full bg-secondary" />
          Reservas 24/7
        </span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.auth-sport-grid {
  background-image:
    linear-gradient(rgb(255 255 255 / 0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgb(255 255 255 / 0.035) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: linear-gradient(to bottom, black, transparent 90%);
}
</style>
