<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { useRoute, RouterLink } from "vue-router"
import { apiClient } from "@tenant/lib/api"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  CheckCircle2,
  Clock,
  XCircle,
  AlertTriangle,
  Loader2,
  ArrowLeft,
  Goal,
  MessageCircle,
} from "lucide-vue-next"

interface ReservationStatus {
  code: string
  court: { id: number; name: string; sport: string } | null
  start_at: string | null
  end_at: string | null
  status: string
  total: string | number
  held_until: string | null
  customer_name: string | null
}

const route = useRoute()
const code = computed(() => String(route.params.code ?? ""))

const reservation = ref<ReservationStatus | null>(null)
const loading = ref(false)
const errored = ref(false)

const statusInfo = computed(() => {
  const s = reservation.value?.status
  switch (s) {
    case "held":
      return { label: "En espera de pago", color: "amber", icon: Clock }
    case "confirmed":
      return { label: "Confirmada", color: "blue", icon: CheckCircle2 }
    case "paid":
      return { label: "Pagada", color: "emerald", icon: CheckCircle2 }
    case "played":
      return { label: "Completada", color: "emerald", icon: CheckCircle2 }
    case "cancelled":
      return { label: "Cancelada", color: "red", icon: XCircle }
    case "no_show":
      return { label: "No se presentó", color: "red", icon: AlertTriangle }
    default:
      return { label: s ?? "—", color: "gray", icon: Clock }
  }
})

function fmtDateTime(iso: string | null) {
  if (!iso) return "—"
  const d = new Date(iso)
  return d.toLocaleString("es-PE", {
    weekday: "long",
    day: "numeric",
    month: "long",
    hour: "2-digit",
    minute: "2-digit",
  })
}

async function fetchStatus() {
  loading.value = true
  errored.value = false
  try {
    const { data } = await apiClient.get<any>(`/v1/public/reservations/${code.value}`)
    reservation.value = data?.data ?? null
  } catch {
    errored.value = true
    reservation.value = null
  } finally {
    loading.value = false
  }
}

onMounted(fetchStatus)
</script>

<template>
  <div class="min-h-svh bg-background text-foreground antialiased">
    <header class="border-b border-border/50">
      <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
        <RouterLink :to="{ name: 'CourtsCatalog' }" class="flex items-center gap-2">
          <Goal class="size-5 text-primary" />
          <span class="font-semibold">Fullbolito</span>
        </RouterLink>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-10">
      <RouterLink :to="{ name: 'CourtsCatalog' }" class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="size-4" /> Volver al catálogo
      </RouterLink>

      <h1 class="text-2xl font-bold tracking-tight">Estado de tu reserva</h1>
      <p class="mt-1 text-sm text-muted-foreground">Código: <span class="font-mono font-semibold">{{ code }}</span></p>

      <div v-if="loading" class="mt-12 flex items-center justify-center py-12">
        <Loader2 class="size-6 animate-spin text-muted-foreground" />
      </div>

      <div
        v-else-if="errored || !reservation"
        class="mt-8 rounded-2xl border border-dashed border-border bg-card p-10 text-center"
      >
        <XCircle class="mx-auto size-10 text-muted-foreground" />
        <p class="mt-4 text-sm font-medium">No encontramos esa reserva.</p>
        <p class="mt-1 text-xs text-muted-foreground">Verifica el código e intenta de nuevo.</p>
      </div>

      <div v-else class="mt-8 space-y-6">
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <Badge :class="[
              'gap-1.5 text-sm',
              statusInfo.color === 'emerald' && 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/10',
              statusInfo.color === 'amber' && 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/10',
              statusInfo.color === 'blue' && 'bg-blue-500/10 text-blue-600 hover:bg-blue-500/10',
              statusInfo.color === 'red' && 'bg-red-500/10 text-red-600 hover:bg-red-500/10',
              statusInfo.color === 'gray' && 'bg-muted text-muted-foreground hover:bg-muted',
            ]">
              <component :is="statusInfo.icon" class="size-3.5" />
              {{ statusInfo.label }}
            </Badge>
            <span class="text-xl font-bold">S/ {{ Number(reservation.total).toFixed(2) }}</span>
          </div>

          <div class="mt-4 space-y-2 text-sm">
            <div class="flex items-baseline gap-2">
              <span class="text-muted-foreground">Cancha:</span>
              <span class="font-medium">{{ reservation.court?.name ?? "—" }}</span>
            </div>
            <div class="flex items-baseline gap-2">
              <span class="text-muted-foreground">Horario:</span>
              <span class="font-medium capitalize">{{ fmtDateTime(reservation.start_at) }}</span>
            </div>
            <div v-if="reservation.customer_name" class="flex items-baseline gap-2">
              <span class="text-muted-foreground">Cliente:</span>
              <span class="font-medium">{{ reservation.customer_name }}</span>
            </div>
          </div>

          <div
            v-if="reservation.status === 'held' && reservation.held_until"
            class="mt-5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400"
          >
            Esta reserva está en espera. Para confirmarla coordina el pago con el complejo
            antes de las <span class="font-medium">{{ fmtDateTime(reservation.held_until) }}</span>.
          </div>

          <div
            v-else-if="reservation.status === 'cancelled'"
            class="mt-5 rounded-lg border border-red-500/30 bg-red-500/5 p-3 text-sm text-red-700 dark:text-red-400"
          >
            Esta reserva fue cancelada. Si fue un error, contacta al complejo o vuelve a reservar.
          </div>
        </div>

        <Button as-child variant="outline" class="w-full sm:w-auto">
          <RouterLink :to="{ name: 'CourtsCatalog' }">
            Hacer una nueva reserva
          </RouterLink>
        </Button>
      </div>
    </main>
  </div>
</template>
