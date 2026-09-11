<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { apiClient } from "@central/lib/api"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import UbigeoSelector from "@central/components/UbigeoSelector.vue"
import PublicFooter from "@central/components/PublicFooter.vue"
import PublicHeader from "@central/components/PublicHeader.vue"
import authSportsBackground from "../../../../images/auth-sports-complex.webp"
import {
  ArrowLeft,
  ArrowRight,
  Building2,
  Calendar as CalendarIcon,
  ChevronDown,
  Clock,
  Frown,
  Goal,
  MapPin,
  RotateCcw,
  Search,
  SlidersHorizontal,
  Sparkles,
  Users,
  X,
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

type FilterKey = "sport" | "ubigeo" | "search" | "date" | "time"

const route = useRoute()
const router = useRouter()

const sport = ref("")
const ubigeo = ref("")
const search = ref("")
const date = ref("")
const time = ref("")
const page = ref(1)
const items = ref<CourtRow[]>([])
const meta = ref<Meta | null>(null)
const loading = ref(false)
const errored = ref(false)
const desktopFiltersOpen = ref(false)
const mobileFiltersOpen = ref(false)

const sports = [
  { value: "", label: "Todos los deportes" },
  { value: "futbol_7", label: "Fútbol 7" },
  { value: "futsal", label: "Futsal" },
  { value: "padel", label: "Pádel" },
]

const popularSports = sports.filter((item) => item.value)
const todayIso = new Date().toISOString().slice(0, 10)

const hasActiveFilters = computed(
  () => !!(sport.value || ubigeo.value || search.value || date.value || time.value),
)
const additionalFilterCount = computed(
  () => [sport.value, ubigeo.value, time.value].filter(Boolean).length,
)
const showingFrom = computed(() => {
  if (!meta.value || !items.value.length) return 0
  return (meta.value.current_page - 1) * meta.value.per_page + 1
})
const showingTo = computed(() => {
  if (!meta.value) return 0
  return Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)
})

function sportLabel(value: string) {
  const found = sports.find((item) => item.value === value)
  return found ? found.label : value.replaceAll("_", " ")
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
      ...(date.value && time.value ? { time: time.value } : {}),
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
    items.value = payload?.data ?? []
    meta.value = payload?.meta ?? null
  } catch {
    errored.value = true
    items.value = []
    meta.value = null
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  mobileFiltersOpen.value = false
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

function removeFilter(key: FilterKey) {
  if (key === "sport") sport.value = ""
  if (key === "ubigeo") ubigeo.value = ""
  if (key === "search") search.value = ""
  if (key === "date") {
    date.value = ""
    time.value = ""
  }
  if (key === "time") time.value = ""
  page.value = 1
  pushQuery()
}

function selectSport(value: string) {
  sport.value = sport.value === value ? "" : value
  page.value = 1
  pushQuery()
}

function nextPage() {
  if (!meta.value || page.value >= meta.value.last_page) return
  page.value += 1
  pushQuery()
}

function prevPage() {
  if (page.value <= 1) return
  page.value -= 1
  pushQuery()
}

function formatPrice(price: number | null) {
  if (price === null || price === undefined) return "Consultar"
  const value = Number(price)
  return `S/ ${Number.isInteger(value) ? value.toFixed(0) : value.toFixed(2)}`
}

function formatDate(value: string) {
  if (!value) return ""
  return new Intl.DateTimeFormat("es-PE", {
    day: "numeric",
    month: "short",
  }).format(new Date(`${value}T12:00:00`))
}

function sportVisualClass(value: string) {
  if (value === "padel") return "from-[#123c4a] via-[#0b655e] to-[#06291f]"
  if (value === "futsal") return "from-[#17382d] via-[#167255] to-[#09251c]"
  return "from-[#143425] via-[#187044] to-[#08251a]"
}

function reserveUrl(row: CourtRow) {
  if (!row.tenant_domain) return null
  const domain = row.tenant_domain
  const isLocal =
    domain.includes("localhost") || domain.endsWith(".test") || domain.includes("127.0.0.1")
  const protocol = isLocal ? "http" : "https"
  const params = new URLSearchParams()
  if (date.value) params.set("date", date.value)
  if (time.value) params.set("time", time.value)
  const query = params.toString()
  return `${protocol}://${domain}/canchas/${row.court.slug}${query ? `?${query}` : ""}`
}

watch(
  () => route.query,
  () => {
    syncFromQuery()
    fetchCourts()
  },
  { immediate: true },
)

watch(date, (value) => {
  if (!value) time.value = ""
})
</script>

<template>
  <div class="min-h-svh overflow-x-hidden bg-[#f4f7f5] text-foreground antialiased">
    <PublicHeader active="marketplace" />

    <section class="relative isolate overflow-hidden bg-[#04110c] text-white">
      <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <img :src="authSportsBackground" alt="" class="size-full object-cover object-[center_58%] opacity-30" />
        <div class="absolute inset-0 bg-gradient-to-b from-[#04110c]/75 via-[#04110c]/65 to-[#04110c]" />
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_15%,rgba(45,212,191,0.12),transparent_42%)]" />
      </div>

      <div class="mx-auto max-w-6xl px-5 py-12 sm:px-6 sm:py-16">
        <div class="mx-auto max-w-3xl text-center">
          <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.07] px-3 py-1.5 text-xs font-medium text-white/70 backdrop-blur-xl">
            <Sparkles class="size-3.5 text-secondary" />
            Reserva canchas deportivas en tu ciudad
          </div>
          <h1 class="mt-5 text-balance text-4xl font-bold leading-[1.04] tracking-tight sm:text-5xl lg:text-6xl">
            Tu próximo partido
            <span class="text-primary">empieza aquí.</span>
          </h1>
          <p class="mx-auto mt-4 max-w-2xl text-pretty text-sm leading-6 text-white/60 sm:text-base">
            Encuentra el espacio ideal, confirma su disponibilidad y pasa de la búsqueda a la cancha en pocos minutos.
          </p>
        </div>

        <form class="mx-auto mt-8 max-w-5xl rounded-3xl border border-white/15 bg-white/[0.08] p-4 shadow-2xl shadow-black/25 backdrop-blur-2xl sm:p-5" @submit.prevent="applyFilters">
          <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
              <Label for="mp-search" class="text-xs text-white/65">Complejo o cancha</Label>
              <div class="relative mt-1.5">
                <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-500" />
                <Input id="mp-search" v-model="search" type="search" placeholder="¿Dónde quieres jugar?" class="h-11 rounded-xl border-white/10 bg-white pl-10 text-[#10221a] shadow-lg shadow-black/10 placeholder:text-slate-400" />
              </div>
            </div>

            <div class="md:col-span-2">
              <Label for="mp-date" class="text-xs text-white/65">Día preferido</Label>
              <Input id="mp-date" v-model="date" type="date" :min="todayIso" class="mt-1.5 h-11 rounded-xl border-white/10 bg-white text-[#10221a] shadow-lg shadow-black/10 [color-scheme:light]" />
            </div>

            <div class="hidden md:col-span-2 md:block">
              <Label for="mp-time" class="text-xs text-white/65">Hora</Label>
              <Input id="mp-time" v-model="time" type="time" step="900" :disabled="!date" class="mt-1.5 h-11 rounded-xl border-white/10 bg-white text-[#10221a] shadow-lg shadow-black/10 [color-scheme:light] disabled:bg-white/75" />
            </div>

            <div class="grid grid-cols-2 gap-3 md:col-span-4 md:grid-cols-2 md:items-end">
              <Button type="button" variant="outline" class="h-11 rounded-xl border-white/20 bg-white/[0.08] text-white hover:bg-white/15 hover:text-white md:hidden" @click="mobileFiltersOpen = true">
                <SlidersHorizontal class="size-4" />
                Filtros
                <span v-if="additionalFilterCount" class="flex size-5 items-center justify-center rounded-full bg-secondary text-[10px] font-bold text-secondary-foreground">{{ additionalFilterCount }}</span>
              </Button>
              <Button type="button" variant="outline" class="hidden h-11 rounded-xl border-white/20 bg-white/[0.08] text-white hover:bg-white/15 hover:text-white md:inline-flex" @click="desktopFiltersOpen = !desktopFiltersOpen">
                <SlidersHorizontal class="size-4" />
                Más filtros
                <span v-if="additionalFilterCount" class="flex size-5 items-center justify-center rounded-full bg-secondary text-[10px] font-bold text-secondary-foreground">{{ additionalFilterCount }}</span>
                <ChevronDown class="size-3.5 transition-transform" :class="desktopFiltersOpen ? 'rotate-180' : ''" />
              </Button>
              <Button type="submit" class="h-11 rounded-xl shadow-lg shadow-primary/25">
                <Search class="size-4" />
                Buscar
              </Button>
            </div>
          </div>

          <div v-if="desktopFiltersOpen" class="mt-5 hidden grid-cols-12 gap-4 border-t border-white/10 pt-5 md:grid">
            <div class="col-span-3">
              <Label for="mp-sport" class="text-xs text-white/65">Deporte</Label>
              <select id="mp-sport" v-model="sport" class="mt-1.5 flex h-11 w-full rounded-xl border border-white/10 bg-white px-3 text-sm text-[#10221a] shadow-lg shadow-black/10 focus:outline-none focus:ring-2 focus:ring-primary">
                <option v-for="item in sports" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
            <div class="col-span-9">
              <Label class="text-xs text-white/65">Ubicación</Label>
              <UbigeoSelector v-model="ubigeo" :hide-labels="true" class="marketplace-location mt-0.5 [&_select]:h-11 [&_select]:rounded-xl [&_select]:border-white/10 [&_select]:shadow-lg" />
            </div>
            <p v-if="date && !time" class="col-span-12 text-xs text-amber-200/80">
              Añade una hora si deseas mostrar únicamente las canchas libres en ese momento.
            </p>
          </div>
        </form>

        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
          <span class="mr-1 hidden text-xs text-white/45 sm:inline">Explora por deporte</span>
          <button v-for="item in popularSports" :key="item.value" type="button" class="rounded-full border px-3 py-1.5 text-xs font-medium transition" :class="sport === item.value ? 'border-secondary bg-secondary text-secondary-foreground' : 'border-white/15 bg-black/15 text-white/65 hover:border-white/30 hover:text-white'" @click="selectSport(item.value)">
            {{ item.label }}
          </button>
        </div>
      </div>
    </section>

    <Sheet v-model:open="mobileFiltersOpen">
      <SheetContent side="bottom" class="max-h-[92svh] overflow-y-auto rounded-t-[2rem] border-border px-5 pb-6 pt-8 sm:px-7">
        <SheetHeader class="text-left">
          <SheetTitle class="flex items-center gap-2 text-2xl">
            <SlidersHorizontal class="size-5 text-primary" />
            Ajusta tu búsqueda
          </SheetTitle>
          <SheetDescription>Elige el deporte, la hora y la zona donde quieres jugar.</SheetDescription>
        </SheetHeader>

        <form class="mt-6 space-y-5" @submit.prevent="applyFilters">
          <div>
            <Label for="mp-mobile-sport">Deporte</Label>
            <select id="mp-mobile-sport" v-model="sport" class="mt-1.5 flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring">
              <option v-for="item in sports" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <Label for="mp-mobile-date">Fecha</Label>
              <Input id="mp-mobile-date" v-model="date" type="date" :min="todayIso" class="mt-1.5 h-11 rounded-xl" />
            </div>
            <div>
              <Label for="mp-mobile-time">Hora</Label>
              <Input id="mp-mobile-time" v-model="time" type="time" step="900" :disabled="!date" class="mt-1.5 h-11 rounded-xl" />
            </div>
          </div>

          <div>
            <Label>Ubicación</Label>
            <p class="mt-1 text-xs text-muted-foreground">Puedes buscar en todo el Perú o precisar hasta el distrito.</p>
            <UbigeoSelector v-model="ubigeo" class="mt-2 [&_select]:h-11 [&_select]:rounded-xl" />
          </div>

          <p v-if="date && !time" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
            Selecciona también una hora para comprobar disponibilidad exacta.
          </p>

          <SheetFooter class="gap-2 pt-2 sm:space-x-0">
            <Button v-if="hasActiveFilters" type="button" variant="ghost" class="rounded-xl" @click="clearFilters">
              <RotateCcw class="size-4" />
              Limpiar todo
            </Button>
            <Button type="submit" class="h-11 rounded-xl">
              Ver resultados
              <ArrowRight class="size-4" />
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>

    <main class="mx-auto max-w-6xl px-5 py-10 sm:px-6 sm:py-12">
      <div class="flex flex-col gap-5 border-b border-border/70 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Espacios para jugar</p>
          <h2 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Canchas disponibles</h2>
          <p class="mt-1.5 text-sm text-muted-foreground" aria-live="polite">
            <template v-if="loading">Actualizando resultados…</template>
            <template v-else-if="errored">No pudimos consultar las canchas.</template>
            <template v-else-if="meta && meta.total > 0">
              Mostrando <span class="font-medium text-foreground">{{ showingFrom }}–{{ showingTo }}</span>
              de <span class="font-medium text-foreground">{{ meta.total }}</span>
              en {{ meta.iterated_tenants }} {{ meta.iterated_tenants === 1 ? "complejo" : "complejos" }}.
            </template>
            <template v-else>Prueba una búsqueda nueva o amplía la ubicación.</template>
          </p>
        </div>

        <Button v-if="hasActiveFilters" variant="outline" size="sm" class="w-fit rounded-xl bg-white" @click="clearFilters">
          <RotateCcw class="size-3.5" />
          Limpiar filtros
        </Button>
      </div>

      <div v-if="hasActiveFilters" class="flex flex-wrap gap-2 py-5" aria-label="Filtros activos">
        <button v-if="sport" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1.5 text-xs font-medium shadow-sm transition hover:border-primary/30" @click="removeFilter('sport')">
          <Goal class="size-3.5 text-primary" /> {{ sportLabel(sport) }} <X class="size-3 text-muted-foreground" />
        </button>
        <button v-if="ubigeo" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1.5 text-xs font-medium shadow-sm transition hover:border-primary/30" @click="removeFilter('ubigeo')">
          <MapPin class="size-3.5 text-primary" /> {{ meta?.resolved_ubigeo?.label ?? "Ubicación seleccionada" }} <X class="size-3 text-muted-foreground" />
        </button>
        <button v-if="search" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1.5 text-xs font-medium shadow-sm transition hover:border-primary/30" @click="removeFilter('search')">
          <Search class="size-3.5 text-primary" /> “{{ search }}” <X class="size-3 text-muted-foreground" />
        </button>
        <button v-if="date" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1.5 text-xs font-medium shadow-sm transition hover:border-primary/30" @click="removeFilter('date')">
          <CalendarIcon class="size-3.5 text-primary" /> {{ formatDate(date) }} <X class="size-3 text-muted-foreground" />
        </button>
        <button v-if="time" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1.5 text-xs font-medium shadow-sm transition hover:border-primary/30" @click="removeFilter('time')">
          <Clock class="size-3.5 text-primary" /> {{ time }} <X class="size-3 text-muted-foreground" />
        </button>
      </div>

      <div v-if="loading" class="grid gap-6 pt-6 md:grid-cols-2 lg:grid-cols-3" aria-label="Cargando canchas">
        <article v-for="index in 6" :key="index" class="overflow-hidden rounded-3xl border border-border/70 bg-white shadow-sm">
          <div class="h-48 animate-pulse bg-slate-200" />
          <div class="space-y-4 p-5">
            <div class="h-5 w-2/3 animate-pulse rounded-md bg-slate-200" />
            <div class="h-4 w-1/2 animate-pulse rounded-md bg-slate-100" />
            <div class="flex gap-2">
              <div class="h-7 w-20 animate-pulse rounded-full bg-slate-100" />
              <div class="h-7 w-16 animate-pulse rounded-full bg-slate-100" />
            </div>
            <div class="h-11 animate-pulse rounded-xl bg-slate-200" />
          </div>
        </article>
      </div>

      <div v-else-if="errored" class="mt-6 flex flex-col items-center justify-center rounded-3xl border border-dashed border-destructive/25 bg-white px-6 py-20 text-center">
        <Frown class="size-10 text-destructive/70" />
        <p class="mt-4 font-semibold">No pudimos cargar las canchas.</p>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">Comprueba tu conexión o vuelve a intentarlo en unos segundos.</p>
        <Button variant="outline" class="mt-5 rounded-xl" @click="fetchCourts">Intentar nuevamente</Button>
      </div>

      <div v-else-if="!items.length" class="mt-6 flex flex-col items-center justify-center rounded-3xl border border-dashed border-border bg-white px-6 py-20 text-center">
        <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
          <Goal class="size-7" />
        </div>
        <p class="mt-4 font-semibold">No encontramos canchas para esos filtros.</p>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">Amplía la zona, cambia de deporte o elimina el horario seleccionado.</p>
        <Button v-if="hasActiveFilters" variant="outline" class="mt-5 rounded-xl" @click="clearFilters">Ampliar búsqueda</Button>
      </div>

      <div v-else class="grid gap-6 pt-6 md:grid-cols-2 lg:grid-cols-3">
        <article v-for="row in items" :key="`${row.tenant_id}-${row.court.id}`" class="group flex min-w-0 flex-col overflow-hidden rounded-3xl border border-border/70 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-xl hover:shadow-[#143425]/10">
          <div class="relative h-48 overflow-hidden bg-gradient-to-br" :class="sportVisualClass(row.court.sport)">
            <div class="absolute -top-20 -right-14 size-52 rounded-full bg-secondary/20 blur-3xl" />
            <div class="absolute inset-5 rounded-2xl border border-white/25 opacity-80">
              <div class="absolute inset-y-0 left-1/2 border-l border-white/25" />
              <div class="absolute top-1/2 left-1/2 size-16 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/25" />
              <div class="absolute inset-y-[26%] -left-px w-[18%] border border-l-0 border-white/25" />
              <div class="absolute inset-y-[26%] -right-px w-[18%] border border-r-0 border-white/25" />
              <span class="absolute top-[30%] left-[36%] size-2 rounded-full bg-primary shadow-[0_0_12px_rgba(255,77,77,0.8)]" />
              <span class="absolute right-[30%] bottom-[25%] size-2 rounded-full bg-secondary shadow-[0_0_12px_rgba(45,212,191,0.8)]" />
              <span class="absolute bottom-[32%] left-[22%] size-1.5 rounded-full bg-white/70" />
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-black/10" />

            <Badge class="absolute top-4 left-4 gap-1 border-white/15 bg-black/35 text-white backdrop-blur-md hover:bg-black/35">
              <Goal class="size-3" /> {{ sportLabel(row.court.sport) }}
            </Badge>
            <Badge v-if="row.court.available_at_requested_time" class="absolute top-4 right-4 gap-1 bg-secondary text-secondary-foreground hover:bg-secondary">
              Disponible
            </Badge>

            <div class="absolute right-4 bottom-4 rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-right text-white backdrop-blur-lg">
              <p class="text-[9px] font-medium uppercase tracking-[0.14em] text-white/55">Desde</p>
              <p class="text-xl font-bold leading-none">{{ formatPrice(row.court.base_price) }}</p>
              <p v-if="row.court.base_price !== null" class="mt-0.5 text-[10px] text-white/55">por turno</p>
            </div>
          </div>

          <div class="flex flex-1 flex-col p-5 sm:p-6">
            <h3 class="text-xl font-bold leading-tight tracking-tight">{{ row.court.name }}</h3>
            <p v-if="row.court.company_name" class="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
              <Building2 class="size-4 shrink-0" />
              <span class="truncate">{{ row.court.company_name }}</span>
            </p>

            <div class="mt-4 space-y-1.5">
              <p v-if="row.court.ubigeo_label" class="flex items-start gap-1.5 text-sm font-medium text-foreground/80">
                <MapPin class="mt-0.5 size-4 shrink-0 text-primary" />
                {{ row.court.ubigeo_label }}
              </p>
              <p v-if="row.court.company_address" class="line-clamp-1 pl-[1.375rem] text-xs text-muted-foreground">{{ row.court.company_address }}</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
              <span v-if="row.court.surface" class="inline-flex items-center gap-1.5 rounded-full bg-[#f0f5f2] px-2.5 py-1.5 text-xs text-muted-foreground">
                <span class="size-1.5 rounded-full bg-primary" /> {{ row.court.surface.replaceAll("_", " ") }}
              </span>
              <span v-if="row.court.capacity" class="inline-flex items-center gap-1.5 rounded-full bg-[#f0f5f2] px-2.5 py-1.5 text-xs text-muted-foreground">
                <Users class="size-3.5" /> {{ row.court.capacity }} jugadores
              </span>
              <span v-if="row.court.slot_duration_minutes" class="inline-flex items-center gap-1.5 rounded-full bg-[#f0f5f2] px-2.5 py-1.5 text-xs text-muted-foreground">
                <Clock class="size-3.5" /> {{ row.court.slot_duration_minutes }} min
              </span>
            </div>

            <p v-if="row.court.description" class="mt-4 line-clamp-2 text-sm leading-5 text-muted-foreground">{{ row.court.description }}</p>

            <div class="mt-auto pt-5">
              <Button v-if="reserveUrl(row)" as-child class="h-11 w-full rounded-xl shadow-lg shadow-primary/15">
                <a :href="reserveUrl(row)!">
                  Ver disponibilidad
                  <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                </a>
              </Button>
              <Button v-else variant="outline" class="h-11 w-full rounded-xl" disabled>Próximamente</Button>
            </div>
          </div>
        </article>
      </div>

      <div v-if="meta && meta.last_page > 1 && !loading" class="mt-10 flex items-center justify-between gap-4 border-t border-border/70 pt-6">
        <Button variant="outline" size="sm" :disabled="page <= 1" class="rounded-xl bg-white" @click="prevPage">
          <ArrowLeft class="size-4" />
          Anterior
        </Button>
        <p class="text-xs text-muted-foreground">
          Página <span class="font-medium text-foreground">{{ meta.current_page }}</span>
          de <span class="font-medium text-foreground">{{ meta.last_page }}</span>
        </p>
        <Button variant="outline" size="sm" :disabled="page >= meta.last_page" class="rounded-xl bg-white" @click="nextPage">
          Siguiente
          <ArrowRight class="size-4" />
        </Button>
      </div>
    </main>

    <PublicFooter />
  </div>
</template>
