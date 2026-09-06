<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { RouterLink, useRoute, useRouter } from "vue-router"
import { apiClient } from "@central/lib/api"
import { useAuthStore } from "@central/stores/auth"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import UbigeoSelector from "@central/components/UbigeoSelector.vue"
import {
  GalleryVerticalEnd,
  Search,
  MapPin,
  Calendar as CalendarIcon,
  Clock,
  Users,
  Goal,
  Building2,
  ArrowRight,
  ArrowLeft,
  Loader2,
  ExternalLink,
  Frown,
  Filter,
  RotateCcw,
} from "lucide-vue-next"

interface UbigeoNames {
  department: string | null
  province: string | null
  district: string | null
}

interface CourtRow {
  tenant_id: string
  tenant_domain: string | null
  court: {
    id: number
    name: string
    slug: string
    sport: string
    surface: string | null
    capacity: number | null
    slot_duration_minutes: number
    description: string | null
    base_price: number | null
    company_id: number | null
    company_name: string | null
    ubigeo: string | null
    ubigeo_names: UbigeoNames | null
    ubigeo_label: string | null
    company_address: string | null
    available_at_requested_time?: boolean
    requested_slot?: { start: string; end: string }
  }
}

interface Meta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  filters_applied: Record<string, string>
  resolved_ubigeo: (UbigeoNames & { label: string | null }) | null
  iterated_tenants: number
}

const authStore = useAuthStore()
const isAuthenticated = computed(() => authStore.isAuthenticated)
const route = useRoute()
const router = useRouter()

const sport = ref<string>("")
const ubigeo = ref<string>("")
const search = ref<string>("")
const date = ref<string>("")
const time = ref<string>("")
const page = ref<number>(1)

const items = ref<CourtRow[]>([])
const meta = ref<Meta | null>(null)
const loading = ref<boolean>(false)
const errored = ref<boolean>(false)

const sports = [
  { value: "", label: "Todos los deportes" },
  { value: "futbol_7", label: "Fútbol 7" },
  { value: "futsal", label: "Futsal" },
  { value: "padel", label: "Pádel" },
]

const sportLabel = (v: string) => {
  const f = sports.find((s) => s.value === v)
  return f ? f.label : v.charAt(0).toUpperCase() + v.slice(1)
}

function syncFromQuery() {
  sport.value = String(route.query.sport ?? "")
  ubigeo.value = String(route.query.ubigeo ?? "")
  search.value = String(route.query.search ?? "")
  date.value = String(route.query.date ?? "")
  time.value = String(route.query.time ?? "")
  page.value = Number(route.query.page ?? 1) || 1
}

function pushQuery() {
  router.push({
    name: "Marketplace",
    query: {
      ...(sport.value ? { sport: sport.value } : {}),
      ...(ubigeo.value ? { ubigeo: ubigeo.value } : {}),
      ...(search.value ? { search: search.value } : {}),
      ...(date.value ? { date: date.value } : {}),
      ...(time.value ? { time: time.value } : {}),
      ...(page.value > 1 ? { page: String(page.value) } : {}),
    },
  })
}

async function fetchCourts() {
  loading.value = true
  errored.value = false
  try {
    const params: Record<string, string> = {
      per_page: "12",
      page: String(page.value),
    }
    if (sport.value) params.sport = sport.value
    if (ubigeo.value) params.ubigeo = ubigeo.value
    if (search.value) params.search = search.value
    if (date.value && time.value) {
      params.date = date.value
      params.time = time.value
    }
    const { data } = await apiClient.get<any>("/v1/marketplace/courts", { params })
    const payload = (data?.data ?? data) as { data?: CourtRow[]; meta?: Meta }
    items.value = (payload?.data ?? []) as CourtRow[]
    meta.value = (payload?.meta ?? null) as Meta | null
  } catch {
    errored.value = true
    items.value = []
    meta.value = null
  } finally {
    loading.value = false
  }
}

watch(
  () => route.query,
  () => {
    syncFromQuery()
    fetchCourts()
  },
  { immediate: true },
)

function applyFilters() {
  page.value = 1
  pushQuery()
}

function clearFilters() {
  sport.value = ""
  ubigeo.value = ""
  search.value = ""
  date.value = ""
  time.value = ""
  page.value = 1
  pushQuery()
}

function nextPage() {
  if (!meta.value) return
  if (page.value >= meta.value.last_page) return
  page.value += 1
  pushQuery()
}
function prevPage() {
  if (page.value <= 1) return
  page.value -= 1
  pushQuery()
}

function formatPrice(p: number | null) {
  if (p === null || p === undefined) return "Consultar"
  return `S/ ${Number(p).toFixed(2)}`
}

function reserveUrl(row: CourtRow) {
  if (!row.tenant_domain) return null
  const d = row.tenant_domain
  const isLocal =
    d.includes("localhost") || d.endsWith(".test") || d.includes("127.0.0.1")
  const proto = isLocal ? "http" : "https"
  // Pasa fecha/hora si están en los filtros — la página del tenant los lee
  // y pre-selecciona el slot, así el cliente confirma sin re-elegir.
  const params = new URLSearchParams()
  if (date.value) params.set("date", date.value)
  if (time.value) params.set("time", time.value)
  const qs = params.toString()
  return `${proto}://${d}/canchas/${row.court.slug}${qs ? `?${qs}` : ""}`
}

const hasActiveFilters = computed(
  () => !!(sport.value || ubigeo.value || search.value || date.value || time.value),
)
const showingFrom = computed(() => {
  if (!meta.value || !items.value.length) return 0
  return (meta.value.current_page - 1) * meta.value.per_page + 1
})
const showingTo = computed(() => {
  if (!meta.value) return 0
  return Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)
})

const todayIso = new Date().toISOString().slice(0, 10)
</script>

<template>
  <div class="relative min-h-svh overflow-x-hidden bg-background text-foreground antialiased">
    <header class="sticky top-0 z-40 border-b border-border/50 bg-background/80 backdrop-blur-md">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
        <RouterLink :to="{ name: 'Home' }" class="flex items-center gap-2 font-semibold">
          <div class="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg shadow-md shadow-primary/20">
            <GalleryVerticalEnd class="size-4" />
          </div>
          <span>Canchapp</span>
        </RouterLink>

        <nav class="hidden items-center gap-7 text-sm text-muted-foreground md:flex">
          <RouterLink :to="{ name: 'Home' }" class="transition hover:text-foreground">Inicio</RouterLink>
          <RouterLink :to="{ name: 'Marketplace' }" class="text-foreground">Reservar</RouterLink>
          <RouterLink :to="{ name: 'Home', hash: '#pricing' }" class="transition hover:text-foreground">Planes</RouterLink>
        </nav>

        <div class="flex items-center gap-2">
          <template v-if="isAuthenticated">
            <Button as-child variant="ghost" size="sm">
              <RouterLink :to="{ name: 'Dashboard' }">Dashboard</RouterLink>
            </Button>
          </template>
          <template v-else>
            <Button as-child variant="ghost" size="sm">
              <RouterLink :to="{ name: 'Login' }">Iniciar sesión</RouterLink>
            </Button>
            <Button as-child size="sm">
              <RouterLink :to="{ name: 'Register' }">Soy un complejo</RouterLink>
            </Button>
          </template>
        </div>
      </div>
    </header>

    <section class="relative">
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 h-[420px] w-[820px] -translate-x-1/2 rounded-full bg-primary/15 blur-3xl"></div>
        <div class="absolute top-40 -right-40 h-[360px] w-[360px] rounded-full bg-secondary/20 blur-3xl"></div>
      </div>

      <div class="mx-auto max-w-6xl px-6 pb-10 pt-16 text-center sm:pt-20">
        <h1 class="mx-auto max-w-3xl text-balance text-4xl font-bold tracking-tight sm:text-5xl">
          Encontrá tu cancha y
          <span class="bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
            reservá en segundos.
          </span>
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-balance text-base text-muted-foreground">
          Buscá entre todos los complejos que ya operan con Canchapp. Filtrá por deporte, zona y horario.
        </p>
      </div>

      <div class="mx-auto max-w-6xl px-6 pb-8">
        <form
          class="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6"
          @submit.prevent="applyFilters"
        >
          <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-5">
              <Label for="mp-search" class="text-xs">Buscar</Label>
              <div class="relative mt-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="mp-search"
                  v-model="search"
                  type="search"
                  placeholder="Nombre del complejo o cancha"
                  class="pl-9"
                />
              </div>
            </div>

            <div class="md:col-span-4">
              <Label for="mp-sport" class="text-xs">Deporte</Label>
              <select
                id="mp-sport"
                v-model="sport"
                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option v-for="s in sports" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <Label for="mp-date" class="text-xs">Fecha</Label>
              <Input id="mp-date" v-model="date" type="date" :min="todayIso" class="mt-1" />
            </div>

            <div class="md:col-span-1">
              <Label for="mp-time" class="text-xs">Hora</Label>
              <Input id="mp-time" v-model="time" type="time" step="900" class="mt-1" />
            </div>

            <div class="md:col-span-12">
              <Label class="text-xs">Ubicación</Label>
              <UbigeoSelector v-model="ubigeo" :hide-labels="true" class="mt-1" />
            </div>
          </div>

          <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
              Filtrá por departamento, provincia o distrito. Cuanto más específico, menos resultados.
            </p>
            <div class="flex flex-wrap items-center gap-2">
              <Button
                v-if="hasActiveFilters"
                type="button"
                variant="ghost"
                size="sm"
                class="gap-1"
                @click="clearFilters"
              >
                <RotateCcw class="size-3.5" />
                Limpiar
              </Button>
              <Button type="submit" size="sm" class="gap-1">
                <Filter class="size-3.5" />
                Aplicar filtros
              </Button>
            </div>
          </div>
        </form>
      </div>
    </section>

    <section class="relative border-t border-border/50">
      <div class="mx-auto max-w-6xl px-6 py-10">
        <div class="flex flex-wrap items-end justify-between gap-3 pb-6">
          <div>
            <h2 class="text-xl font-semibold tracking-tight">Canchas disponibles</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              <template v-if="loading">Buscando…</template>
              <template v-else-if="errored">No pudimos cargar las canchas. Probá de nuevo en un rato.</template>
              <template v-else-if="meta && meta.total > 0">
                Mostrando <span class="font-medium text-foreground">{{ showingFrom }}–{{ showingTo }}</span>
                de <span class="font-medium text-foreground">{{ meta.total }}</span>
                en {{ meta.iterated_tenants }} complejos.
              </template>
              <template v-else>Sin resultados con los filtros actuales.</template>
            </p>
          </div>

          <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
            <Badge v-if="sport" variant="secondary" class="gap-1">
              <Goal class="size-3" /> {{ sportLabel(sport) }}
            </Badge>
            <Badge v-if="ubigeo" variant="secondary" class="gap-1">
              <MapPin class="size-3" />
              {{ meta?.resolved_ubigeo?.label ?? ubigeo }}
            </Badge>
            <Badge v-if="search" variant="secondary" class="gap-1">
              <Search class="size-3" /> "{{ search }}"
            </Badge>
            <Badge v-if="date && time" variant="secondary" class="gap-1">
              <CalendarIcon class="size-3" /> {{ date }} {{ time }}
            </Badge>
          </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-24">
          <Loader2 class="size-6 animate-spin text-muted-foreground" />
        </div>

        <div
          v-else-if="!loading && !items.length"
          class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-20 text-center"
        >
          <Frown class="size-10 text-muted-foreground" />
          <p class="mt-4 text-sm font-medium">No encontramos canchas para esos filtros.</p>
          <p class="mt-1 max-w-sm text-xs text-muted-foreground">
            Probá ampliar la zona, cambiar de deporte o quitar el filtro de horario.
          </p>
          <Button v-if="hasActiveFilters" variant="outline" size="sm" class="mt-5" @click="clearFilters">
            Limpiar filtros
          </Button>
        </div>

        <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="row in items"
            :key="row.tenant_id + '-' + row.court.id"
            class="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card transition hover:-translate-y-0.5 hover:shadow-lg"
          >
            <div class="relative h-36 overflow-hidden bg-gradient-to-br from-primary/15 via-primary/5 to-secondary/15">
              <div
                class="absolute inset-0 opacity-30"
                style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 18px 18px;"
              ></div>
              <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                <Badge class="gap-1 bg-background/90 text-foreground hover:bg-background/90">
                  <Goal class="size-3" /> {{ sportLabel(row.court.sport) }}
                </Badge>
                <Badge
                  v-if="row.court.available_at_requested_time"
                  class="gap-1 bg-emerald-500/90 text-white hover:bg-emerald-500/90"
                >
                  Libre {{ date }} {{ time }}
                </Badge>
              </div>
              <div class="absolute bottom-3 right-4 text-right">
                <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Desde</div>
                <div class="text-lg font-bold leading-none">{{ formatPrice(row.court.base_price) }}</div>
              </div>
            </div>

            <div class="flex flex-1 flex-col p-5">
              <h3 class="text-base font-semibold leading-snug">{{ row.court.name }}</h3>
              <p v-if="row.court.company_name" class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                <Building2 class="size-3.5" /> {{ row.court.company_name }}
              </p>
              <p v-if="row.court.ubigeo_label" class="mt-1 flex items-center gap-1.5 text-xs font-medium text-foreground/80">
                <MapPin class="size-3.5 shrink-0" />
                {{ row.court.ubigeo_label }}
              </p>
              <p v-if="row.court.company_address" class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                <span class="line-clamp-2">{{ row.court.company_address }}</span>
              </p>

              <div class="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
                <span v-if="row.court.surface" class="inline-flex items-center gap-1">
                  <span class="size-1.5 rounded-full bg-primary"></span>
                  {{ row.court.surface }}
                </span>
                <span v-if="row.court.capacity" class="inline-flex items-center gap-1">
                  <Users class="size-3.5" /> {{ row.court.capacity }}
                </span>
                <span v-if="row.court.slot_duration_minutes" class="inline-flex items-center gap-1">
                  <Clock class="size-3.5" /> {{ row.court.slot_duration_minutes }}'
                </span>
              </div>

              <p v-if="row.court.description" class="mt-3 line-clamp-2 text-sm text-muted-foreground">
                {{ row.court.description }}
              </p>

              <div class="mt-auto pt-5">
                <Button
                  v-if="reserveUrl(row)"
                  as-child
                  class="w-full gap-2"
                >
                  <a :href="reserveUrl(row)!" target="_blank" rel="noopener">
                    Reservar
                    <ExternalLink class="size-3.5" />
                  </a>
                </Button>
                <Button v-else variant="outline" class="w-full" disabled>
                  Sin dominio configurado
                </Button>
              </div>
            </div>
          </article>
        </div>

        <div
          v-if="meta && meta.last_page > 1 && !loading"
          class="mt-10 flex items-center justify-between gap-4 border-t border-border/50 pt-6"
        >
          <Button variant="outline" size="sm" :disabled="page <= 1" class="gap-1" @click="prevPage">
            <ArrowLeft class="size-4" />
            Anterior
          </Button>
          <p class="text-xs text-muted-foreground">
            Página <span class="font-medium text-foreground">{{ meta.current_page }}</span>
            de <span class="font-medium text-foreground">{{ meta.last_page }}</span>
          </p>
          <Button
            variant="outline"
            size="sm"
            :disabled="page >= meta.last_page"
            class="gap-1"
            @click="nextPage"
          >
            Siguiente
            <ArrowRight class="size-4" />
          </Button>
        </div>
      </div>
    </section>

    <footer class="border-t border-border/50">
      <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row">
        <div class="flex items-center gap-2">
          <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md">
            <GalleryVerticalEnd class="size-3" />
          </div>
          <span>© {{ new Date().getFullYear() }} Canchapp. Todos los derechos reservados.</span>
        </div>
        <div class="flex items-center gap-5">
          <RouterLink :to="{ name: 'Home' }" class="transition hover:text-foreground">Inicio</RouterLink>
          <RouterLink :to="{ name: 'Home', hash: '#pricing' }" class="transition hover:text-foreground">Planes</RouterLink>
          <RouterLink :to="{ name: 'Login' }" class="transition hover:text-foreground">Iniciar sesión</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
