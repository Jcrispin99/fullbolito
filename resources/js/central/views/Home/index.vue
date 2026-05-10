<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { RouterLink } from "vue-router"
import { apiClient } from "@central/lib/api"
import { useAuthStore } from "@central/stores/auth"
import { Button } from "@/components/ui/button"
import {
  GalleryVerticalEnd,
  ShieldCheck,
  Zap,
  LayoutGrid,
  Boxes,
  HeartHandshake,
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
    name: "Free Trial",
    description: "Probá la plataforma 14 días sin tarjeta.",
    price: 0,
    duration_days: 14,
    features: ["1 sede", "Hasta 50 comprobantes", "Soporte por email"],
  },
  {
    slug: "basico-mensual",
    name: "Básico",
    description: "Para negocios que recién arrancan.",
    price: 29.99,
    duration_days: 30,
    features: ["1 sede", "Comprobantes ilimitados", "POS offline", "Reportes básicos"],
  },
  {
    slug: "pro-mensual",
    name: "Pro",
    description: "Lo más elegido por restaurantes en operación.",
    price: 59.99,
    duration_days: 30,
    features: ["Hasta 3 sedes", "Inventario + lotes", "Loyalty incluido", "Soporte prioritario"],
    highlight: true,
  },
  {
    slug: "enterprise-anual",
    name: "Enterprise",
    description: "Cadenas y operaciones grandes.",
    price: 499.99,
    duration_days: 365,
    features: ["Sedes ilimitadas", "Todos los módulos", "SLA 99.9%", "Onboarding dedicado"],
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
    icon: ShieldCheck,
    title: "Facturación SUNAT",
    body: "Boletas, facturas, notas y guías electrónicas. Anulación en un click.",
    tone: "primary" as const,
  },
  {
    icon: Zap,
    title: "POS offline-first",
    body: "Vendé sin internet. La sincronización corre sola cuando vuelve.",
    tone: "secondary" as const,
  },
  {
    icon: LayoutGrid,
    title: "Multi-sede",
    body: "Stock por sucursal, transferencias, consolidado central.",
    tone: "primary" as const,
  },
  {
    icon: Boxes,
    title: "Inventario con lotes",
    body: "Trazabilidad de vencimientos, alertas y bloqueo automático.",
    tone: "secondary" as const,
  },
  {
    icon: HeartHandshake,
    title: "Loyalty integrado",
    body: "Programa de puntos opcional, conectado al POS desde el día uno.",
    tone: "primary" as const,
  },
  {
    icon: BarChart3,
    title: "Reportes en vivo",
    body: "Ventas, márgenes y movimientos en tiempo real desde cualquier lado.",
    tone: "secondary" as const,
  },
]

const steps = [
  {
    n: "01",
    title: "Creá tu cuenta",
    body: "Registrate gratis y tu workspace queda listo en segundos.",
  },
  {
    n: "02",
    title: "Configurá tu negocio",
    body: "Cargá tus productos, sedes y certificado SUNAT desde un wizard guiado.",
  },
  {
    n: "03",
    title: "Empezá a operar",
    body: "Vendé desde el POS, emití comprobantes y mirá tus reportes.",
  },
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
          <span>RestOP</span>
        </RouterLink>

        <nav class="hidden items-center gap-7 text-sm text-muted-foreground md:flex">
          <a href="#features" class="transition hover:text-foreground">Producto</a>
          <a href="#how" class="transition hover:text-foreground">Cómo funciona</a>
          <a href="#pricing" class="transition hover:text-foreground">Planes</a>
        </nav>

        <div class="flex items-center gap-2">
          <template v-if="isAuthenticated">
            <Button as-child variant="ghost" size="sm">
              <RouterLink :to="{ name: 'Dashboard' }">Ir al Dashboard</RouterLink>
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
          Nueva integración SUNAT — sin papeleo
        </div>

        <h1 class="mx-auto mt-6 max-w-3xl text-balance text-5xl font-bold tracking-tight sm:text-6xl lg:text-7xl">
          El sistema operativo<br>
          <span class="bg-gradient-to-r from-primary via-primary to-secondary bg-clip-text text-transparent">
            de tu negocio.
          </span>
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-balance text-base text-muted-foreground sm:text-lg">
          Facturación electrónica, POS offline, inventario y multi-sede en una sola plataforma. Sin instalar nada.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Button v-if="!isAuthenticated" as-child size="lg" class="gap-2">
            <RouterLink :to="{ name: 'Register' }">
              Empezar gratis
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button v-else as-child size="lg" class="gap-2">
            <RouterLink :to="{ name: 'Dashboard' }">
              Ir al Dashboard
              <ArrowRight class="size-4" />
            </RouterLink>
          </Button>
          <Button as-child variant="outline" size="lg">
            <a href="#features">Ver el producto</a>
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
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Ventas hoy</div>
                      <div class="mt-1 text-lg font-bold">S/ 4,820</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Comprobantes</div>
                      <div class="mt-1 text-lg font-bold">68</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Ticket prom.</div>
                      <div class="mt-1 text-lg font-bold">S/ 70.8</div>
                    </div>
                    <div class="rounded-xl border border-border bg-background/60 p-3">
                      <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Stock crítico</div>
                      <div class="mt-1 text-lg font-bold text-primary">3</div>
                    </div>
                  </div>
                  <div class="flex h-44 items-end gap-2 rounded-xl border border-border bg-background/60 p-4">
                    <div v-for="(h, i) in [40, 65, 50, 80, 55, 90, 70, 95, 60, 75, 88, 100]" :key="i"
                      class="flex-1 rounded-md"
                      :class="i % 3 === 0 ? 'bg-primary/80' : 'bg-secondary/70'"
                      :style="{ height: h + '%' }"
                    ></div>
                  </div>
                  <div class="space-y-2">
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="flex-1 truncate">Boleta B001-00231 — Cliente final</div>
                      <div class="font-medium">S/ 89.00</div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-emerald-500"></div>
                      <div class="flex-1 truncate">Factura F001-00118 — Inversiones SAC</div>
                      <div class="font-medium">S/ 1,240.00</div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3 text-sm">
                      <div class="size-2 rounded-full bg-amber-500"></div>
                      <div class="flex-1 truncate">Boleta B001-00230 — pendiente firma</div>
                      <div class="font-medium">S/ 45.50</div>
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
            Lo que necesitás para operar, sin frankenstein de apps.
          </h2>
          <p class="mt-4 text-muted-foreground">
            Reemplazá facturador, POS, control de stock y reportes con una sola plataforma.
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
            De cero a facturando en menos de 10 minutos.
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
            Pagás lo que usás. Cancelás cuando quieras.
          </h2>
          <p class="mt-4 text-muted-foreground">
            Empezá gratis 14 días, sin tarjeta. Cuando crezcas, pasás a pago.
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
                Empezá hoy.<br>
                <span class="bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                  Sin tarjeta, sin compromiso.
                </span>
              </h2>
              <p class="mt-4 max-w-md text-muted-foreground">
                14 días gratis del plan completo. Si no te convence, no pagás nada.
              </p>
            </div>
            <div class="flex flex-wrap gap-3 md:justify-end">
              <Button v-if="!isAuthenticated" as-child size="lg">
                <RouterLink :to="{ name: 'Register' }">Crear cuenta</RouterLink>
              </Button>
              <Button v-else as-child size="lg">
                <RouterLink :to="{ name: 'Dashboard' }">Ir al Dashboard</RouterLink>
              </Button>
              <Button as-child variant="outline" size="lg">
                <a href="#features">Ver el producto</a>
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
          <span>© {{ new Date().getFullYear() }} RestOP. Todos los derechos reservados.</span>
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
