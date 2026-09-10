<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { RouterLink } from "vue-router"
import { apiClient } from "@central/lib/api"
import { useAuthStore } from "@central/stores/auth"
import { Button } from "@/components/ui/button"
import {
  GalleryVerticalEnd,
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
  <div class="relative min-h-svh overflow-x-hidden bg-background text-foreground antialiased">
    <header class="sticky top-0 z-40 border-b border-border/50 bg-background/80 backdrop-blur-md">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
        <RouterLink :to="{ name: 'Home' }" class="flex items-center gap-2 font-semibold">
          <div class="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg shadow-md shadow-primary/20">
            <GalleryVerticalEnd class="size-4" />
          </div>
          <span>Fullbolito</span>
        </RouterLink>

        <nav class="hidden items-center gap-7 text-sm text-muted-foreground md:flex">
          <RouterLink :to="{ name: 'Marketplace' }" class="transition hover:text-foreground">Reservar cancha</RouterLink>
          <a href="#features" class="transition hover:text-foreground">Producto</a>
          <a href="#how" class="transition hover:text-foreground">Cómo funciona</a>
          <a href="#pricing" class="transition hover:text-foreground">Planes</a>
        </nav>

        <div class="flex items-center gap-2">
          <template v-if="isAuthenticated">
            <Button as-child variant="ghost" size="sm">
              <RouterLink :to="{ name: 'Dashboard' }">Ir al panel</RouterLink>
            </Button>
          </template>
          <template v-else>
            <Button as-child variant="ghost" size="sm">
              <RouterLink :to="{ name: 'Login' }">Iniciar sesión</RouterLink>
            </Button>
            <Button as-child size="sm">
              <RouterLink :to="{ name: 'Register' }">Empezar gratis</RouterLink>
            </Button>
          </template>
        </div>
      </div>
    </header>

    <section class="relative">
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-primary/15 blur-3xl"></div>
        <div class="absolute top-40 -right-40 h-[480px] w-[480px] rounded-full bg-secondary/20 blur-3xl"></div>
        <div
          class="absolute inset-0 opacity-[0.05]"
          style="background-image: radial-gradient(var(--foreground) 1px, transparent 1px); background-size: 28px 28px;"
        ></div>
      </div>

      <div class="mx-auto max-w-6xl px-6 pb-20 pt-20 text-center sm:pt-28">
        <div class="mx-auto inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary">
          <Sparkles class="size-3" />
          Nuevo · Reservas en línea 24/7 sin llamadas ni mensajes de WhatsApp
        </div>

        <h1 class="mx-auto mt-6 max-w-3xl text-balance text-5xl font-bold tracking-tight sm:text-6xl lg:text-7xl">
          El sistema operativo<br>
          <span class="bg-gradient-to-r from-primary via-primary to-secondary bg-clip-text text-transparent">
            de tu complejo deportivo.
          </span>
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-balance text-base text-muted-foreground sm:text-lg">
          Administra canchas, horarios, reservas y pagos desde un solo lugar. Tus clientes reservan en línea y tú recibes los pagos sin hacer seguimientos manuales.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Button v-if="!isAuthenticated" as-child size="lg" class="gap-2">
            <RouterLink :to="{ name: 'Register' }">
              Empieza gratis
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button v-else as-child size="lg" class="gap-2">
            <RouterLink :to="{ name: 'Dashboard' }">
              Ir al panel
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button as-child variant="outline" size="lg">
            <a href="#features">Ver demo</a>
          </Button>
        </div>

        <div class="mx-auto mt-16 max-w-5xl">
          <div class="relative">
            <div class="absolute -inset-6 rounded-[2rem] bg-gradient-to-br from-primary/20 via-transparent to-secondary/20 blur-3xl"></div>
            <div class="relative overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
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
                <div class="md:col-span-9 space-y-4">
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
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="flex-1 truncate">Cancha 1 · 19:00–20:00 — Juan Pérez · Pagado</div>
                      <div class="font-medium">S/ 80.00</div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="flex-1 truncate">Cancha 3 · 20:00–21:00 — Los Halcones FC · Adelanto</div>
                      <div class="font-medium">S/ 120.00</div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-amber-500"></div>
                      <div class="flex-1 truncate">Cancha 2 · 21:00–22:00 — Pendiente confirmación</div>
                      <div class="font-medium">S/ 50.00</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="relative border-t border-border/50">
      <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-sm font-medium text-primary">Todo en un solo lugar</span>
          <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
            Todo lo que necesitas para mantener tus canchas ocupadas.
          </h2>
          <p class="mt-4 text-muted-foreground">
            Reemplaza la agenda de papel, las hojas de cálculo y los mensajes sueltos por una sola plataforma.
          </p>
        </div>

        <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="f in features"
            :key="f.title"
            class="group relative overflow-hidden rounded-2xl border border-border bg-card p-6 transition hover:-translate-y-0.5 hover:shadow-lg"
          >
            <div
              class="grid size-11 place-items-center rounded-xl ring-1"
              :class="f.tone === 'primary'
                ? 'bg-primary/10 text-primary ring-primary/20'
                : 'bg-secondary/15 text-secondary-foreground ring-secondary/30'"
            >
              <component :is="f.icon" class="size-5" />
            </div>
            <h3 class="mt-5 text-lg font-semibold">{{ f.title }}</h3>
            <p class="mt-1 text-sm text-muted-foreground">{{ f.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <section id="how" class="relative border-t border-border/50 bg-muted/30">
      <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-sm font-medium text-primary">Cómo funciona</span>
          <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
            De cero a recibir reservas en menos de 10 minutos.
          </h2>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-3">
          <div
            v-for="(s, i) in steps"
            :key="s.n"
            class="relative rounded-2xl border border-border bg-card p-6"
          >
            <div class="flex items-center justify-between">
              <span
                class="bg-gradient-to-br from-primary to-secondary bg-clip-text text-3xl font-bold text-transparent"
              >
                {{ s.n }}
              </span>
              <ArrowRight v-if="i < steps.length - 1" class="size-5 text-muted-foreground" />
            </div>
            <h3 class="mt-4 text-lg font-semibold">{{ s.title }}</h3>
            <p class="mt-1 text-sm text-muted-foreground">{{ s.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <section id="pricing" class="relative border-t border-border/50">
      <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-sm font-medium text-primary">Planes</span>
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
            class="relative flex flex-col rounded-2xl border bg-card p-6"
            :class="p.highlight
              ? 'border-primary/50 shadow-lg shadow-primary/10 ring-1 ring-primary/20'
              : 'border-border'"
          >
            <span
              v-if="p.highlight"
              class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-[11px] font-medium text-primary-foreground shadow-sm"
            >
              Más elegido
            </span>

            <div>
              <h3 class="text-lg font-semibold">{{ p.name }}</h3>
              <p v-if="p.description" class="mt-1 text-sm text-muted-foreground">{{ p.description }}</p>
            </div>

            <div class="mt-5 flex items-baseline gap-1">
              <span class="text-3xl font-bold tracking-tight">{{ formatPrice(p) }}</span>
              <span class="text-xs text-muted-foreground">{{ intervalLabel(p) }}</span>
            </div>

            <ul class="mt-5 space-y-2 text-sm">
              <li v-for="(f, i) in p.features" :key="i" class="flex items-start gap-2">
                <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                <span class="text-muted-foreground">{{ f }}</span>
              </li>
            </ul>

            <div class="mt-6 pt-1">
              <Button
                as-child
                :variant="p.highlight ? 'default' : 'outline'"
                class="w-full"
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

    <section class="relative border-t border-border/50">
      <div class="mx-auto max-w-6xl px-6 py-20">
        <div class="relative overflow-hidden rounded-3xl border border-border bg-card p-10 sm:p-14">
          <div class="absolute -top-20 -right-20 size-72 rounded-full bg-primary/15 blur-3xl"></div>
          <div class="absolute -bottom-20 -left-20 size-72 rounded-full bg-secondary/20 blur-3xl"></div>
          <div class="relative grid gap-6 md:grid-cols-[1.5fr_1fr] md:items-center">
            <div>
              <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                Empieza hoy.<br>
                <span class="bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                  Sin tarjeta, sin instalar nada.
                </span>
              </h2>
              <p class="mt-4 max-w-md text-muted-foreground">
                Disfruta 14 días gratis del plan completo. Si no te convence, no pagas nada.
              </p>
            </div>
            <div class="flex flex-wrap gap-3 md:justify-end">
              <Button v-if="!isAuthenticated" as-child size="lg">
                <RouterLink :to="{ name: 'Register' }">Crear cuenta</RouterLink>
              </Button>
              <Button v-else as-child size="lg">
                <RouterLink :to="{ name: 'Dashboard' }">Ir al panel</RouterLink>
              </Button>
              <Button as-child variant="outline" size="lg">
                <a href="#features">Ver demo</a>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="border-t border-border/50">
      <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row">
        <div class="flex items-center gap-2">
          <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md">
            <GalleryVerticalEnd class="size-3" />
          </div>
          <span>© {{ new Date().getFullYear() }} Fullbolito. Todos los derechos reservados.</span>
        </div>
        <div class="flex items-center gap-5">
          <a href="#features" class="transition hover:text-foreground">Producto</a>
          <a href="#pricing" class="transition hover:text-foreground">Planes</a>
          <RouterLink :to="{ name: 'Login' }" class="transition hover:text-foreground">Iniciar sesión</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
