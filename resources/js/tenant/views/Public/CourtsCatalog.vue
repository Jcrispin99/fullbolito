<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { RouterLink } from "vue-router"
import { apiClient } from "@tenant/lib/api"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Goal, MapPin, Users, Clock, Loader2, Frown, Search } from "lucide-vue-next"

interface Court {
  id: number
  name: string
  slug: string
  sport: string
  surface: string | null
  capacity: number | null
  slot_duration_minutes: number
  base_price: string | number | null
  company?: { id: number; name: string | null; address: string | null }
}

const courts = ref<Court[]>([])
const loading = ref(false)
const search = ref("")
const sport = ref("")

const sports = [
  { value: "", label: "Todos los deportes" },
  { value: "futbol", label: "Fútbol" },
  { value: "voley", label: "Vóley" },
  { value: "basquet", label: "Básquet" },
  { value: "padel", label: "Pádel" },
  { value: "tenis", label: "Tenis" },
]

const filtered = computed(() => {
  const s = search.value.trim().toLowerCase()
  return courts.value.filter((c) => {
    if (sport.value && c.sport !== sport.value) return false
    if (s && !c.name.toLowerCase().includes(s)) return false
    return true
  })
})

const tenantName = computed(
  () => courts.value[0]?.company?.name ?? "Nuestras canchas",
)

async function fetchCourts() {
  loading.value = true
  try {
    const { data } = await apiClient.get<any>("/v1/public/courts")
    courts.value = (data?.data?.courts ?? []) as Court[]
  } finally {
    loading.value = false
  }
}

function priceFmt(p: string | number | null) {
  if (p === null || p === undefined) return "Consultar"
  return `S/ ${Number(p).toFixed(2)}`
}

function sportLabel(v: string) {
  return sports.find((s) => s.value === v)?.label ?? v.charAt(0).toUpperCase() + v.slice(1)
}

onMounted(fetchCourts)
</script>

<template>
  <div class="min-h-svh bg-background text-foreground antialiased">
    <header class="border-b border-border/50 bg-background/80 backdrop-blur-md">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <div class="flex items-center gap-2">
          <Goal class="size-5 text-primary" />
          <span class="font-semibold">{{ tenantName }}</span>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-10">
      <h1 class="text-3xl font-bold tracking-tight">Reservá tu cancha</h1>
      <p class="mt-2 text-sm text-muted-foreground">
        Elegí entre nuestras canchas disponibles y reservá en segundos.
      </p>

      <div class="mt-6 grid gap-3 md:grid-cols-2">
        <div>
          <Label for="cat-search" class="text-xs">Buscar</Label>
          <div class="relative mt-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="cat-search"
              v-model="search"
              type="search"
              placeholder="Nombre de la cancha"
              class="pl-9"
            />
          </div>
        </div>
        <div>
          <Label for="cat-sport" class="text-xs">Deporte</Label>
          <select
            id="cat-sport"
            v-model="sport"
            class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option v-for="s in sports" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="mt-12 flex items-center justify-center py-12">
        <Loader2 class="size-6 animate-spin text-muted-foreground" />
      </div>

      <div
        v-else-if="!filtered.length"
        class="mt-12 flex flex-col items-center justify-center rounded-2xl border border-dashed py-20 text-center"
      >
        <Frown class="size-10 text-muted-foreground" />
        <p class="mt-4 text-sm font-medium">No encontramos canchas con esos filtros.</p>
      </div>

      <div v-else class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="c in filtered"
          :key="c.id"
          :to="{ name: 'CourtDetail', params: { slug: c.slug } }"
          class="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card transition hover:-translate-y-0.5 hover:shadow-lg"
        >
          <div class="relative h-32 overflow-hidden bg-gradient-to-br from-primary/15 via-primary/5 to-secondary/15">
            <div class="absolute left-3 top-3 flex flex-wrap gap-2">
              <Badge class="gap-1 bg-background/90 text-foreground hover:bg-background/90">
                <Goal class="size-3" /> {{ sportLabel(c.sport) }}
              </Badge>
            </div>
            <div class="absolute bottom-2 right-3 text-right">
              <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Desde</div>
              <div class="text-base font-bold leading-none">{{ priceFmt(c.base_price) }}</div>
            </div>
          </div>
          <div class="flex flex-1 flex-col p-4">
            <h3 class="text-base font-semibold leading-snug">{{ c.name }}</h3>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
              <span v-if="c.surface" class="inline-flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-primary"></span>
                {{ c.surface }}
              </span>
              <span v-if="c.capacity" class="inline-flex items-center gap-1">
                <Users class="size-3.5" /> {{ c.capacity }}
              </span>
              <span class="inline-flex items-center gap-1">
                <Clock class="size-3.5" /> {{ c.slot_duration_minutes }}'
              </span>
            </div>
            <p v-if="c.company?.address" class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground">
              <MapPin class="mt-0.5 size-3.5 shrink-0" />
              <span class="line-clamp-2">{{ c.company.address }}</span>
            </p>
          </div>
        </RouterLink>
      </div>
    </main>

    <footer class="mt-12 border-t border-border/50">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6 text-xs text-muted-foreground">
        <span>{{ tenantName }}</span>
        <span>Powered by Canchapp</span>
      </div>
    </footer>
  </div>
</template>
