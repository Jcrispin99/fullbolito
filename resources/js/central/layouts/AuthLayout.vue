<script setup lang="ts">
import { computed } from 'vue'
import { CalendarCheck, Goal, Sparkles, Trophy } from 'lucide-vue-next'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import authSportsBackground from '../../../images/auth-sports-complex.webp'

const route = useRoute()
const isRegister = computed(() => route.name === 'Register')
const transitionName = computed(() => isRegister.value ? 'auth-card-forward' : 'auth-card-back')
</script>

<template>
  <main class="relative min-h-svh overflow-x-hidden bg-[#030c08]">
    <div class="pointer-events-none fixed inset-0" aria-hidden="true">
      <img
        :src="authSportsBackground"
        alt=""
        fetchpriority="high"
        class="auth-scene-image absolute inset-0 size-full object-cover"
        :class="isRegister ? 'auth-scene-image--register' : 'auth-scene-image--login'"
      >
      <div class="absolute inset-0 bg-black/20" />
      <div
        class="absolute inset-0 transition-[background] duration-700"
        :class="isRegister
          ? 'bg-gradient-to-l from-[#020906]/75 via-[#06150d]/20 to-[#020906]/45'
          : 'bg-gradient-to-r from-[#020906]/75 via-[#06150d]/20 to-[#020906]/45'"
      />
      <div class="absolute inset-0 bg-gradient-to-b from-black/45 via-transparent to-black/75" />
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_25%,rgba(1,8,5,0.38)_100%)]" />
    </div>

    <div class="relative z-10 mx-auto flex min-h-svh w-full max-w-[100rem] flex-col px-5 py-5 sm:px-8 sm:py-7 lg:px-12 xl:px-16">
      <header class="flex items-center justify-between gap-4 text-white">
        <RouterLink :to="{ name: 'Home' }" class="group flex shrink-0 items-center gap-2.5 font-semibold tracking-tight">
          <div class="flex size-10 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white shadow-lg backdrop-blur-md transition-transform group-hover:-rotate-3 group-hover:scale-105">
            <Goal class="size-5 text-secondary" />
          </div>
          <div>
            <span class="block text-lg leading-none">Fullbolito</span>
            <span class="mt-1 hidden text-[9px] font-medium uppercase tracking-[0.22em] text-white/45 sm:block">Sports manager</span>
          </div>
        </RouterLink>

        <nav class="flex shrink-0 items-center rounded-xl border border-white/15 bg-black/25 p-1 text-[11px] shadow-xl backdrop-blur-xl sm:text-sm" aria-label="Autenticación">
          <RouterLink
            :to="{ name: 'Login' }"
            class="rounded-lg px-2.5 py-1.5 font-medium transition-all sm:px-4"
            :class="!isRegister ? 'bg-white text-[#092016] shadow-sm' : 'text-white/60 hover:text-white'"
          >
            Ingresar
          </RouterLink>
          <RouterLink
            :to="{ name: 'Register' }"
            class="rounded-lg px-2.5 py-1.5 font-medium transition-all sm:px-4"
            :class="isRegister ? 'bg-white text-[#092016] shadow-sm' : 'text-white/60 hover:text-white'"
          >
            Crear cuenta
          </RouterLink>
        </nav>
      </header>

      <div class="flex flex-1 items-center py-8 lg:py-10">
        <RouterView v-slot="{ Component, route: currentRoute }">
          <Transition :name="transitionName" mode="out-in" appear>
            <div
              :key="currentRoute.name"
              class="grid min-w-0 w-full grid-cols-[minmax(0,1fr)] items-center gap-12 lg:grid-cols-2 xl:gap-20"
            >
              <section
                class="auth-glass-card min-w-0 w-full max-w-full rounded-[2rem] p-6 sm:p-8 xl:p-9"
                :class="currentRoute.name === 'Register'
                  ? 'max-w-2xl lg:order-2 lg:justify-self-end'
                  : 'max-w-md lg:order-1 lg:justify-self-start'"
              >
                <component :is="Component" />
              </section>

              <aside
                class="hidden max-w-xl text-white lg:block"
                :class="currentRoute.name === 'Register' ? 'lg:order-1' : 'lg:order-2 lg:justify-self-end'"
              >
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/20 px-3 py-1.5 text-xs font-medium text-white/75 backdrop-blur-xl">
                  <Sparkles class="size-3.5 text-secondary" />
                  {{ currentRoute.name === 'Register' ? 'Tu negocio entra a una nueva liga' : 'El partido se gestiona aquí' }}
                </div>

                <h2 class="mt-5 max-w-lg text-balance text-4xl font-bold leading-[1.08] tracking-tight drop-shadow-lg xl:text-5xl">
                  {{ currentRoute.name === 'Register' ? 'Convierte cada horario libre en una oportunidad.' : 'Que cada hora de tu cancha cuente.' }}
                </h2>
                <p class="mt-4 max-w-md text-pretty text-sm leading-6 text-white/65 xl:text-base">
                  {{ currentRoute.name === 'Register'
                    ? 'Crea tu espacio, publica tus horarios y empieza a recibir reservas desde cualquier dispositivo.'
                    : 'Ten reservas, cobros, clientes y próximos partidos siempre visibles desde un solo panel.' }}
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                  <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-white/75 backdrop-blur-xl">
                    <CalendarCheck class="size-4 text-secondary" />
                    Reservas 24/7
                  </div>
                  <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-white/75 backdrop-blur-xl">
                    <Trophy class="size-4 text-primary" />
                    Todo bajo control
                  </div>
                </div>
              </aside>
            </div>
          </Transition>
        </RouterView>
      </div>

      <footer class="flex items-center justify-between gap-4 text-xs text-white/45">
        <span>© {{ new Date().getFullYear() }} Fullbolito</span>
        <span class="hidden items-center gap-2 sm:flex">
          <span class="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]" />
          Tu cancha nunca se detiene
        </span>
      </footer>
    </div>
  </main>
</template>

<style>
.auth-glass-card {
  --background: rgb(255 255 255 / 0.075);
  --foreground: rgb(248 252 250);
  --muted: rgb(255 255 255 / 0.12);
  --muted-foreground: rgb(190 207 199);
  --accent: rgb(255 255 255 / 0.12);
  --accent-foreground: rgb(255 255 255);
  --border: rgb(255 255 255 / 0.14);
  --input: rgb(255 255 255 / 0.18);

  position: relative;
  isolation: isolate;
  color: var(--foreground);
  border: 1px solid rgb(255 255 255 / 0.16);
  background:
    linear-gradient(145deg, rgb(13 34 25 / 0.88), rgb(3 15 10 / 0.78));
  box-shadow:
    0 32px 100px -28px rgb(0 0 0 / 0.82),
    inset 0 1px 0 rgb(255 255 255 / 0.08);
  backdrop-filter: blur(26px) saturate(135%);
}

.auth-glass-card::before {
  position: absolute;
  z-index: -1;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  background: radial-gradient(circle at 15% 0%, rgb(59 211 165 / 0.09), transparent 34%);
  content: '';
}

.auth-glass-card input {
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.035);
}

.auth-glass-card input::placeholder {
  color: rgb(168 190 180);
}

.auth-scene-image {
  transform: scale(1.035);
  transition:
    object-position 900ms cubic-bezier(0.22, 1, 0.36, 1),
    transform 1200ms cubic-bezier(0.22, 1, 0.36, 1),
    filter 700ms ease;
}

.auth-scene-image--login {
  object-position: 58% center;
  transform: scale(1.035);
}

.auth-scene-image--register {
  object-position: 42% center;
  transform: scale(1.075);
}

.auth-card-forward-enter-active,
.auth-card-forward-leave-active,
.auth-card-back-enter-active,
.auth-card-back-leave-active {
  transition:
    opacity 260ms ease,
    transform 420ms cubic-bezier(0.22, 1, 0.36, 1),
    filter 320ms ease;
}

.auth-card-forward-enter-from {
  opacity: 0;
  filter: blur(5px);
  transform: translateX(64px) scale(0.985);
}

.auth-card-forward-leave-to {
  opacity: 0;
  filter: blur(3px);
  transform: translateX(-42px) scale(0.985);
}

.auth-card-back-enter-from {
  opacity: 0;
  filter: blur(5px);
  transform: translateX(-64px) scale(0.985);
}

.auth-card-back-leave-to {
  opacity: 0;
  filter: blur(3px);
  transform: translateX(42px) scale(0.985);
}

@media (prefers-reduced-motion: reduce) {
  .auth-scene-image,
  .auth-card-forward-enter-active,
  .auth-card-forward-leave-active,
  .auth-card-back-enter-active,
  .auth-card-back-leave-active {
    transition-duration: 1ms;
  }
}
</style>
