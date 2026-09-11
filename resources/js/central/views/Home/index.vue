<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { RouterLink } from "vue-router"
import { apiClient } from "@central/lib/api"
import { useAuthStore } from "@central/stores/auth"
import { Button } from "@/components/ui/button"
import PublicFooter from "@central/components/PublicFooter.vue"
import PublicHeader from "@central/components/PublicHeader.vue"
import authSportsBackground from "../../../../images/auth-sports-complex.webp"
import {
  CalendarCheck,
  LayoutGrid,
  Wallet,
  Goal,
  Bell,
  BarChart3,
  Sparkles,
  Check,
  ArrowRight,
} from "lucide-vue-next"

interface PlanCard {
  id?: number
  slug: string
  name: string
  description?: string | null
  price: number
  duration_days: number
  features: string[]
  highlight?: boolean
}

const authStore = useAuthStore()
const isAuthenticated = computed(() => authStore.isAuthenticated)

const plans = ref<PlanCard[]>([
  {
    slug: "free-trial",
    name: "Prueba gratuita",
    description: "Prueba Fullbolito gratis por 14 días, sin tarjeta.",
    price: 0,
    duration_days: 14,
    features: ["1 sede, hasta 2 canchas", "Reservas en línea ilimitadas", "Soporte por correo electrónico"],
  },
  {
    slug: "starter-mensual",
    name: "Básico",
    description: "Para complejos que están comenzando a gestionar reservas en línea.",
    price: 49,
    duration_days: 30,
    features: [
      "1 sede, hasta 4 canchas",
      "Pagos en línea (Mercado Pago / Yape)",
      "Recordatorios por correo electrónico",
      "Reportes básicos",
    ],
  },
  {
    slug: "pro-mensual",
    name: "Pro",
    description: "Lo más elegido por complejos en operación.",
    price: 99,
    duration_days: 30,
    features: [
      "Hasta 3 sedes, canchas ilimitadas",
      "Recordatorios por WhatsApp",
      "Adelantos y reglas de cancelación",
      "Página pública personalizable",
      "Soporte prioritario",
    ],
    highlight: true,
  },
  {
    slug: "enterprise-anual",
    name: "Empresarial",
    description: "Cadenas y operaciones grandes.",
    price: 499,
    duration_days: 365,
    features: [
      "Sedes y canchas ilimitadas",
      "Varios usuarios con roles",
      "API y exportaciones",
      "Configuración inicial personalizada y SLA",
    ],
  },
])

onMounted(async () => {
  try {
    const { data } = await apiClient.get<any>("/v1/plans", {
      params: { status: "active", per_page: "total" },
    })
    const fetched = (data?.data?.data ?? data?.data ?? []) as Array<any>
    if (Array.isArray(fetched) && fetched.length) {
      plans.value = fetched.slice(0, 4).map((p: any, idx: number) => ({
        id: p.id,
        slug: p.slug,
        name: p.name,
        description: p.description,
        price: Number(p.price ?? 0),
        duration_days: Number(p.duration_days ?? 30),
        features: Array.isArray(p.features) && p.features.length
          ? p.features
          : ["Acceso completo a la plataforma"],
        highlight: p.slug === "pro-mensual" || idx === 2,
      }))
    }
  } catch {
    // Silent fallback to hardcoded plans.
  }
})

const formatPrice = (p: PlanCard) => {
  if (p.price === 0) return "Gratis"
  return `S/ ${p.price.toFixed(2)}`
}
const intervalLabel = (p: PlanCard) => {
  if (p.price === 0) return `por ${p.duration_days} días`
  return p.duration_days >= 365 ? "por año" : "por mes"
}

const features = [
  {
    icon: CalendarCheck,
    title: "Reservas en línea 24/7",
    body: "Tus clientes reservan desde el celular, sin llamadas ni mensajes de WhatsApp. Recibes una notificación al instante.",
    tone: "primary" as const,
  },
  {
    icon: LayoutGrid,
    title: "Calendario visual de horarios",
    body: "Consulta la ocupación de todas tus canchas en una sola pantalla. Arrastra, reprograma o cancela reservas fácilmente.",
    tone: "secondary" as const,
  },
  {
    icon: Wallet,
    title: "Cobros y adelantos integrados",
    body: "Cobra el total o solo un adelanto al reservar. Mercado Pago, Yape, Plin y transferencia.",
    tone: "primary" as const,
  },
  {
    icon: Goal,
    title: "Multi-cancha y multi-deporte",
    body: "Fútbol 5/7/11, pádel, vóley, básquet. Cada cancha con su precio, horario y reglas.",
    tone: "secondary" as const,
  },
  {
    icon: Bell,
    title: "Recordatorios automáticos",
    body: "WhatsApp y correo electrónico antes del partido. Menos ausencias y menos canchas vacías.",
    tone: "primary" as const,
  },
  {
    icon: BarChart3,
    title: "Reportes que importan",
    body: "Ocupación por horario, ingresos por cancha, clientes recurrentes. Decisiones con datos.",
    tone: "secondary" as const,
  },
]

const steps = [
  {
    n: "01",
    title: "Crea tu complejo",
    body: "Regístrate gratis y agrega tus canchas, horarios y precios en minutos.",
  },
  {
    n: "02",
    title: "Comparte tu enlace de reservas",
    body: "Tu página pública queda lista para compartirla en Instagram, WhatsApp o Google.",
  },
  {
    n: "03",
    title: "Recibe reservas y pagos",
    body: "Los jugadores reservan y pagan en línea. Tú solo preparas la cancha.",
  },
]

const mockCourts = ["Cancha 1", "Cancha 2", "Cancha 3"]
const mockHours = ["17", "18", "19", "20", "21", "22", "23", "00"]
const mockGrid: number[][] = [
  [0, 1, 1, 1, 0, 1, 1, 0],
  [0, 0, 1, 1, 1, 1, 0, 0],
  [1, 1, 0, 1, 2, 1, 1, 0],
]
</script>

<template>
  <div class="relative min-h-svh overflow-x-hidden bg-[#f4f7f5] text-foreground antialiased">
    <PublicHeader active="home" />

    <section class="relative isolate overflow-hidden bg-[#04110c] text-white">
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <img :src="authSportsBackground" alt="" class="size-full object-cover object-[center_58%] opacity-35" />
        <div class="absolute inset-0 bg-gradient-to-b from-[#04110c]/65 via-[#04110c]/78 to-[#04110c]" />
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,rgba(45,212,191,0.13),transparent_42%)]" />
      </div>

      <div class="mx-auto max-w-6xl px-5 pb-20 pt-16 text-center sm:px-6 sm:pb-24 sm:pt-24">
        <div class="mx-auto inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.07] px-3 py-1.5 text-xs font-medium text-white/70 backdrop-blur-xl">
          <Sparkles class="size-3.5 text-secondary" />
          Gestión y reservas deportivas en un solo lugar
        </div>

        <h1 class="mx-auto mt-6 max-w-4xl text-balance text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl">
          Tu complejo lleno.<br>
          <span class="text-primary">Tu operación bajo control.</span>
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-7 text-white/60 sm:text-lg">
          Administra canchas, horarios, reservas y pagos desde un solo panel. Tus clientes encuentran dónde jugar y reservan sin llamadas ni seguimientos manuales.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Button v-if="!isAuthenticated" as-child size="lg" class="h-12 rounded-xl px-6 shadow-xl shadow-primary/25">
            <RouterLink :to="{ name: 'Register' }">
              Gestionar mi complejo
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button v-else as-child size="lg" class="h-12 rounded-xl px-6 shadow-xl shadow-primary/25">
            <RouterLink :to="{ name: 'Dashboard' }">
              Ir al panel
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button as-child variant="outline" size="lg" class="h-12 rounded-xl border-white/20 bg-white/[0.07] text-white hover:bg-white/15 hover:text-white">
            <RouterLink :to="{ name: 'Marketplace' }">Buscar una cancha</RouterLink>
          </Button>
        </div>

        <div class="mx-auto mt-16 max-w-5xl sm:mt-20">
          <div class="relative">
            <div class="absolute -inset-8 rounded-[2.5rem] bg-gradient-to-br from-primary/20 via-transparent to-secondary/20 blur-3xl" />
            <div class="home-dashboard-preview relative overflow-hidden rounded-3xl border border-white/15 bg-[#081a13]/90 text-left shadow-2xl shadow-black/40 backdrop-blur-2xl">
              <div class="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-3">
                <span class="size-3 rounded-full bg-red-400/70"></span>
                <span class="size-3 rounded-full bg-amber-400/70"></span>
                <span class="size-3 rounded-full bg-emerald-400/70"></span>
                <div class="ml-4 hidden flex-1 sm:block">
                  <div class="mx-auto h-6 w-2/3 max-w-md rounded-md bg-background/80 ring-1 ring-border"></div>
                </div>
              </div>
              <div class="grid gap-4 p-6 md:grid-cols-12">
                <aside class="hidden flex-col gap-2 md:col-span-3 md:flex">
                  <div class="h-7 rounded-md bg-primary/10"></div>
                  <div class="h-7 rounded-md bg-muted"></div>
                  <div class="h-7 rounded-md bg-muted"></div>
                  <div class="h-7 rounded-md bg-muted"></div>
                  <div class="mt-4 h-7 rounded-md bg-muted"></div>
                  <div class="h-7 rounded-md bg-muted"></div>
                </aside>
                <div class="min-w-0 space-y-4 md:col-span-9">
                  <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Reservas hoy</div>
                      <div class="mt-1 text-lg font-bold">12</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Ocupación</div>
                      <div class="mt-1 text-lg font-bold">78%</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Ingresos</div>
                      <div class="mt-1 text-lg font-bold">S/ 1,240</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Próxima</div>
                      <div class="mt-1 text-lg font-bold text-primary">19:00</div>
                    </div>
                  </div>
                  <div class="rounded-xl border border-border bg-background/60 p-4">
                    <div class="grid items-center gap-1.5 text-[10px]" style="grid-template-columns: 4.5rem repeat(8, minmax(0, 1fr));">
                      <div></div>
                      <div v-for="h in mockHours" :key="'h' + h" class="text-center text-muted-foreground">{{ h }}h</div>
                      <template v-for="(row, ri) in mockGrid" :key="'r' + ri">
                        <div class="text-muted-foreground">{{ mockCourts[ri] }}</div>
                        <div
                          v-for="(cell, ci) in row"
                          :key="'c' + ri + '-' + ci"
                          class="h-7 rounded"
                          :class="cell === 1 ? 'bg-primary/80' : cell === 2 ? 'bg-amber-500/70' : 'bg-muted'"
                        ></div>
                      </template>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <div class="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="min-w-0 flex-1 truncate">Cancha 1 · 19:00–20:00 — Juan Pérez · Pagado</div>
                      <div class="shrink-0 font-medium">S/ 80.00</div>
                    </div>
                    <div class="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="min-w-0 flex-1 truncate">Cancha 3 · 20:00–21:00 — Los Halcones FC · Adelanto</div>
                      <div class="shrink-0 font-medium">S/ 120.00</div>
                    </div>
                    <div class="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-amber-500"></div>
                      <div class="min-w-0 flex-1 truncate">Cancha 2 · 21:00–22:00 — Pendiente confirmación</div>
                      <div class="shrink-0 font-medium">S/ 50.00</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="relative scroll-mt-16 bg-[#f4f7f5]">
      <div class="mx-auto max-w-6xl px-5 py-20 sm:px-6 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Todo en un solo lugar</span>
          <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
            Más tiempo en cancha.<br class="hidden sm:block"> Menos tiempo coordinando.
          </h2>
          <p class="mt-4 text-muted-foreground">
            Reemplaza la agenda de papel, las hojas de cálculo y los mensajes sueltos por una sola plataforma.
          </p>
        </div>

        <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="f in features"
            :key="f.title"
            class="group relative overflow-hidden rounded-3xl border border-border/70 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-xl hover:shadow-[#143425]/10"
          >
            <div class="absolute -top-16 -right-16 size-36 rounded-full bg-secondary/0 blur-3xl transition group-hover:bg-secondary/15" />
            <div
              class="relative grid size-11 place-items-center rounded-xl ring-1"
              :class="f.tone === 'primary'
                ? 'bg-primary/10 text-primary ring-primary/20'
                : 'bg-secondary/15 text-secondary-foreground ring-secondary/30'"
            >
              <component :is="f.icon" class="size-5" />
            </div>
            <h3 class="relative mt-5 text-lg font-semibold">{{ f.title }}</h3>
            <p class="relative mt-1 text-sm leading-6 text-muted-foreground">{{ f.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <section id="how" class="relative scroll-mt-16 overflow-hidden border-y border-white/10 bg-[#06170f] text-white">
      <div class="pointer-events-none absolute -top-40 left-1/2 size-[34rem] -translate-x-1/2 rounded-full bg-secondary/10 blur-3xl" />
      <div class="relative mx-auto max-w-6xl px-5 py-20 sm:px-6 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-xs font-semibold uppercase tracking-[0.16em] text-secondary">Cómo funciona</span>
          <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
            De cero a recibir reservas en menos de 10 minutos.
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-white/55 sm:text-base">
            Configura lo esencial una sola vez y deja que Fullbolito se encargue del flujo diario.
          </p>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-3">
          <div
            v-for="(s, i) in steps"
            :key="s.n"
            class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.055] p-6 backdrop-blur-xl transition hover:-translate-y-1 hover:bg-white/[0.08]"
          >
            <div class="absolute -right-12 -bottom-12 size-32 rounded-full bg-primary/0 blur-2xl transition group-hover:bg-primary/15" />
            <div class="flex items-center justify-between">
              <span
                class="bg-gradient-to-br from-primary to-secondary bg-clip-text text-3xl font-bold text-transparent"
              >
                {{ s.n }}
              </span>
              <ArrowRight v-if="i < steps.length - 1" class="size-5 text-white/25" />
            </div>
            <h3 class="relative mt-4 text-lg font-semibold">{{ s.title }}</h3>
            <p class="relative mt-1 text-sm leading-6 text-white/55">{{ s.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <section id="pricing" class="relative scroll-mt-16 bg-[#f4f7f5]">
      <div class="mx-auto max-w-6xl px-5 py-20 sm:px-6 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Planes</span>
          <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
            Paga solo por lo que usas. Cancela cuando quieras.
          </h2>
          <p class="mt-4 text-muted-foreground">
            Empieza con 14 días gratis, sin tarjeta. Luego elige el plan que mejor se adapte a tu negocio.
          </p>
        </div>

        <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="p in plans"
            :key="p.slug"
            class="relative flex flex-col overflow-hidden rounded-3xl border p-6 transition duration-300 hover:-translate-y-1"
            :class="p.highlight
              ? 'home-plan-highlight border-white/10 bg-[#071a13] text-white shadow-2xl shadow-[#143425]/20 ring-1 ring-secondary/20'
              : 'border-border/70 bg-white shadow-sm hover:border-primary/20 hover:shadow-xl hover:shadow-[#143425]/10'"
          >
            <div v-if="p.highlight" class="absolute -top-20 -right-16 size-52 rounded-full bg-secondary/15 blur-3xl" />
            <span
              v-if="p.highlight"
              class="relative mb-4 w-fit rounded-full bg-primary px-3 py-1 text-[11px] font-medium text-primary-foreground shadow-sm"
            >
              Más elegido
            </span>

            <div class="relative">
              <h3 class="text-lg font-semibold">{{ p.name }}</h3>
              <p v-if="p.description" class="mt-1 text-sm text-muted-foreground">{{ p.description }}</p>
            </div>

            <div class="relative mt-5 flex items-baseline gap-1">
              <span class="text-3xl font-bold tracking-tight">{{ formatPrice(p) }}</span>
              <span class="text-xs text-muted-foreground">{{ intervalLabel(p) }}</span>
            </div>

            <ul class="relative mt-5 space-y-2 text-sm">
              <li v-for="(f, i) in p.features" :key="i" class="flex items-start gap-2">
                <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                <span class="text-muted-foreground">{{ f }}</span>
              </li>
            </ul>

            <div class="relative mt-auto pt-7">
              <Button
                as-child
                :variant="p.highlight ? 'default' : 'outline'"
                class="h-11 w-full rounded-xl"
              >
                <RouterLink :to="{ name: 'Register' }">
                  {{ p.price === 0 ? 'Probar gratis' : 'Elegir plan' }}
                </RouterLink>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="relative bg-[#f4f7f5]">
      <div class="mx-auto max-w-6xl px-5 pb-20 sm:px-6 sm:pb-24">
        <div class="relative isolate overflow-hidden rounded-[2rem] border border-white/10 bg-[#06170f] p-8 text-white shadow-2xl shadow-[#143425]/15 sm:p-14">
          <img :src="authSportsBackground" alt="" class="absolute inset-0 -z-20 size-full object-cover object-center opacity-20" />
          <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#06170f] via-[#06170f]/90 to-[#06170f]/55" />
          <div class="absolute -top-20 -right-20 -z-10 size-72 rounded-full bg-primary/20 blur-3xl" />
          <div class="absolute -bottom-20 -left-20 -z-10 size-72 rounded-full bg-secondary/15 blur-3xl" />
          <div class="relative grid gap-6 md:grid-cols-[1.5fr_1fr] md:items-center">
            <div>
              <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                El siguiente gran partido<br>
                <span class="text-primary">empieza con una mejor gestión.</span>
              </h2>
              <p class="mt-4 max-w-md text-white/55">
                Disfruta 14 días gratis del plan completo. Si no te convence, no pagas nada.
              </p>
            </div>
            <div class="flex flex-wrap gap-3 md:justify-end">
              <Button v-if="!isAuthenticated" as-child size="lg" class="h-12 rounded-xl shadow-xl shadow-primary/25">
                <RouterLink :to="{ name: 'Register' }">Crear cuenta</RouterLink>
              </Button>
              <Button v-else as-child size="lg" class="h-12 rounded-xl shadow-xl shadow-primary/25">
                <RouterLink :to="{ name: 'Dashboard' }">Ir al panel</RouterLink>
              </Button>
              <Button as-child variant="outline" size="lg" class="h-12 rounded-xl border-white/20 bg-white/[0.07] text-white hover:bg-white/15 hover:text-white">
                <RouterLink :to="{ name: 'Marketplace' }">Explorar canchas</RouterLink>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <PublicFooter />
  </div>
</template>

<style>
.home-dashboard-preview,
.home-plan-highlight {
  --background: rgb(255 255 255 / 0.07);
  --foreground: rgb(248 252 250);
  --card: rgb(8 26 19 / 0.92);
  --card-foreground: rgb(248 252 250);
  --muted: rgb(255 255 255 / 0.1);
  --muted-foreground: rgb(178 199 189);
  --border: rgb(255 255 255 / 0.12);
  --input: rgb(255 255 255 / 0.16);
  --accent: rgb(255 255 255 / 0.1);
  --accent-foreground: rgb(255 255 255);
}
</style>
