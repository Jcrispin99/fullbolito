<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { useRoute, RouterLink } from "vue-router"
import { apiClient } from "@tenant/lib/api"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import {
  MapPin,
  Clock,
  Users,
  Goal,
  ArrowRight,
  ArrowLeft,
  Loader2,
  CheckCircle2,
  Frown,
  MessageCircle,
} from "lucide-vue-next"

interface CompanyInfo {
  id: number
  name: string | null
  address: string | null
  phone: string | null
}

interface Court {
  id: number
  name: string
  slug: string
  sport: string
  surface: string | null
  capacity: number | null
  slot_duration_minutes: number
  description: string | null
  base_price: string | number | null
  company?: CompanyInfo
}

interface Slot {
  start: string // "HH:MM"
  end: string
  start_at: string // ISO
  end_at: string
  price: string
  available: boolean
  status: "available" | "held" | "reserved" | "past"
}

interface Reservation {
  code: string
  start_at: string
  end_at: string
  total: string | number
  status: string
  held_until: string | null
  message: string
}

const route = useRoute()

const slug = computed(() => String(route.params.slug ?? ""))
const initialDate = computed(() => String(route.query.date ?? ""))
const initialTime = computed(() => String(route.query.time ?? ""))

const court = ref<Court | null>(null)
const otherCourts = ref<Court[]>([])

// Inicio de la semana visible (lunes).
const weekStart = ref<Date>(startOfWeek(new Date()))

// Map fecha 'YYYY-MM-DD' → slots[]
const slotsByDate = ref<Record<string, Slot[]>>({})
const selectedSlot = ref<Slot | null>(null)

const form = ref({ name: "", phone: "", email: "", notes: "" })
const reservation = ref<Reservation | null>(null)

const loadingCourt = ref(false)
const loadingWeek = ref(false)
const submitting = ref(false)
const courtError = ref<string | null>(null)
const submitError = ref<string | null>(null)

// === Helpers de fecha ===
function startOfWeek(d: Date): Date {
  const x = new Date(d)
  x.setHours(0, 0, 0, 0)
  // Lunes = 1 ... Domingo = 0 → ajustamos para que Lunes sea el primer día.
  const day = x.getDay()
  const diff = day === 0 ? -6 : 1 - day
  x.setDate(x.getDate() + diff)
  return x
}

function isoDate(d: Date): string {
  return d.toISOString().slice(0, 10)
}

function dayLabel(d: Date): { dow: string; day: string; month: string } {
  return {
    dow: d.toLocaleDateString("es-PE", { weekday: "short" }),
    day: d.getDate().toString().padStart(2, "0"),
    month: d.toLocaleDateString("es-PE", { month: "short" }),
  }
}

function isToday(d: Date): boolean {
  const t = new Date()
  return d.getFullYear() === t.getFullYear()
    && d.getMonth() === t.getMonth()
    && d.getDate() === t.getDate()
  }

function isPastDate(d: Date): boolean {
  const t = new Date()
  t.setHours(0, 0, 0, 0)
  return d < t
}

const weekDays = computed<Date[]>(() => {
  return Array.from({ length: 7 }).map((_, i) => {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    return d
  })
})

const weekLabel = computed(() => {
  const first = weekDays.value[0]
  const last = weekDays.value[6]
  const sameMonth = first.getMonth() === last.getMonth()
  if (sameMonth) {
    return `${first.getDate()} - ${last.getDate()} de ${last.toLocaleDateString("es-PE", { month: "long", year: "numeric" })}`
  }
  return `${first.toLocaleDateString("es-PE", { day: "numeric", month: "short" })} - ${last.toLocaleDateString("es-PE", { day: "numeric", month: "short", year: "numeric" })}`
})

// Horas únicas presentes en los slots de toda la semana.
const hourRows = computed<string[]>(() => {
  const set = new Set<string>()
  for (const date of weekDays.value) {
    const day = slotsByDate.value[isoDate(date)] ?? []
    for (const s of day) set.add(s.start)
  }
  return Array.from(set).sort()
})

function getSlot(date: Date, hour: string): Slot | null {
  const day = slotsByDate.value[isoDate(date)] ?? []
  return day.find((s) => s.start === hour) ?? null
}

// === API ===
async function fetchCourt() {
  loadingCourt.value = true
  courtError.value = null
  try {
    const { data } = await apiClient.get<any>(`/v1/public/courts/by-slug/${slug.value}`)
    court.value = data?.data ?? null
  } catch (e: any) {
    courtError.value = e?.response?.data?.message ?? "No pudimos cargar la cancha."
    court.value = null
  } finally {
    loadingCourt.value = false
  }
}

async function fetchOtherCourts() {
  try {
    const { data } = await apiClient.get<any>("/v1/public/courts")
    const list = (data?.data?.courts ?? []) as Court[]
    otherCourts.value = list.filter((c) => c.id !== court.value?.id).slice(0, 6)
  } catch {
    otherCourts.value = []
  }
}

async function fetchWeekAvailability() {
  if (!court.value) return
  loadingWeek.value = true
  try {
    const results = await Promise.all(
      weekDays.value.map((d) =>
        apiClient
          .get<any>(`/v1/public/courts/${court.value!.id}/availability`, {
            params: { date: isoDate(d) },
          })
          .then((r) => ({ date: isoDate(d), slots: (r.data?.data?.slots ?? []) as Slot[] }))
          .catch(() => ({ date: isoDate(d), slots: [] as Slot[] })),
      ),
    )
    const map: Record<string, Slot[]> = {}
    for (const r of results) map[r.date] = r.slots
    slotsByDate.value = map

    // Pre-selección por query string (solo la primera vez después de cargar court).
    if (initialDate.value && initialTime.value && !selectedSlot.value) {
      const candidates = map[initialDate.value] ?? []
      const match = candidates.find((s) => s.start === initialTime.value && s.available)
      if (match) selectedSlot.value = match
    }
  } finally {
    loadingWeek.value = false
  }
}

function selectSlot(slot: Slot | null) {
  if (!slot || !slot.available) return
  selectedSlot.value = slot
  reservation.value = null
  submitError.value = null
  requestAnimationFrame(() => {
    document
      .getElementById("reservation-form")
      ?.scrollIntoView({ behavior: "smooth", block: "start" })
  })
}

async function submitReservation() {
  if (!court.value || !selectedSlot.value) return
  submitting.value = true
  submitError.value = null
  try {
    const { data } = await apiClient.post<any>(
      `/v1/public/courts/${court.value.id}/reservations`,
      {
        start_at: selectedSlot.value.start_at,
        customer_name: form.value.name,
        customer_phone: form.value.phone,
        customer_email: form.value.email || undefined,
        notes: form.value.notes || undefined,
      },
    )
    reservation.value = data?.data ?? null
    fetchWeekAvailability()
  } catch (e: any) {
    submitError.value =
      e?.response?.data?.message ??
      "No pudimos confirmar tu reserva. Intenta de nuevo."
  } finally {
    submitting.value = false
  }
}

function shiftWeek(delta: number) {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() + delta * 7)
  weekStart.value = d
}

function goToToday() {
  weekStart.value = startOfWeek(new Date())
}

function priceFmt(p: string | number | null) {
  if (p === null || p === undefined) return "Consultar"
  return `S/ ${Number(p).toFixed(2)}`
}

function whatsappLink(phone: string | null | undefined, msg?: string) {
  if (!phone) return null
  const clean = phone.replace(/\D/g, "")
  const text = msg ? `?text=${encodeURIComponent(msg)}` : ""
  return `https://wa.me/${clean}${text}`
}

watch(weekStart, fetchWeekAvailability)

watch(slug, async () => {
  reservation.value = null
  selectedSlot.value = null
  form.value = { name: "", phone: "", email: "", notes: "" }
  await fetchCourt()
  if (court.value) {
    await fetchOtherCourts()
    await fetchWeekAvailability()
  }
})

onMounted(async () => {
  // Si vino ?date= en URL y es una fecha futura, posicionamos la semana ahí.
  if (initialDate.value) {
    const target = new Date(initialDate.value + "T00:00:00")
    if (!isNaN(target.getTime())) {
      weekStart.value = startOfWeek(target)
    }
  }
  await fetchCourt()
  if (court.value) {
    await fetchOtherCourts()
    await fetchWeekAvailability()
  }
})
</script>

<template>
  <div class="min-h-svh bg-background text-foreground antialiased">
    <header class="border-b border-border/50 bg-background/80 backdrop-blur-md">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <RouterLink :to="{ name: 'CourtsCatalog' }" class="flex items-center gap-2">
          <Goal class="size-5 text-primary" />
          <span class="font-semibold">{{ court?.company?.name ?? "Reservas" }}</span>
        </RouterLink>
        <a
          v-if="court?.company?.phone"
          :href="whatsappLink(court.company.phone, `Hola, quiero más información sobre ${court.name}`) ?? '#'"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
          <MessageCircle class="size-4" />
          {{ court.company.phone }}
        </a>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-10">
      <RouterLink
        :to="{ name: 'CourtsCatalog' }"
        class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft class="size-4" /> Volver al catálogo
      </RouterLink>

      <div v-if="loadingCourt" class="flex items-center justify-center py-24">
        <Loader2 class="size-6 animate-spin text-muted-foreground" />
      </div>

      <div
        v-else-if="courtError || !court"
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-20 text-center"
      >
        <Frown class="size-10 text-muted-foreground" />
        <p class="mt-4 text-sm font-medium">{{ courtError ?? "Cancha no encontrada" }}</p>
      </div>

      <template v-else>
        <!-- Court header -->
        <section class="rounded-2xl border border-border bg-card p-6 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <Badge class="gap-1">
                  <Goal class="size-3" />
                  {{ court.sport.charAt(0).toUpperCase() + court.sport.slice(1) }}
                </Badge>
                <Badge v-if="court.surface" variant="secondary">{{ court.surface }}</Badge>
              </div>
              <h1 class="mt-3 text-2xl font-bold tracking-tight">{{ court.name }}</h1>
              <p v-if="court.company?.address" class="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
                <MapPin class="size-4" /> {{ court.company.address }}
              </p>
              <div class="mt-3 flex flex-wrap gap-4 text-sm text-muted-foreground">
                <span v-if="court.capacity" class="inline-flex items-center gap-1.5">
                  <Users class="size-4" /> {{ court.capacity }} jugadores
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <Clock class="size-4" /> Turnos de {{ court.slot_duration_minutes }} min
                </span>
              </div>
              <p v-if="court.description" class="mt-4 max-w-2xl text-sm text-muted-foreground">
                {{ court.description }}
              </p>
            </div>
            <div class="text-right">
              <div class="text-xs uppercase tracking-wider text-muted-foreground">Desde</div>
              <div class="text-3xl font-bold">{{ priceFmt(court.base_price) }}</div>
            </div>
          </div>
        </section>

        <!-- Calendar -->
        <section class="mt-8 rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 p-4">
            <div>
              <h2 class="text-lg font-semibold">Disponibilidad</h2>
              <p class="text-xs text-muted-foreground capitalize">{{ weekLabel }}</p>
            </div>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="icon" @click="shiftWeek(-1)">
                <ArrowLeft class="size-4" />
              </Button>
              <Button variant="outline" size="sm" @click="goToToday">Hoy</Button>
              <Button variant="outline" size="icon" @click="shiftWeek(1)">
                <ArrowRight class="size-4" />
              </Button>
            </div>
          </div>

          <div v-if="loadingWeek" class="flex items-center justify-center py-16">
            <Loader2 class="size-5 animate-spin text-muted-foreground" />
          </div>

          <div v-else-if="!hourRows.length" class="p-10 text-center text-sm text-muted-foreground">
            La cancha no opera ningún día de esta semana.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[680px] border-collapse text-sm">
              <thead>
                <tr class="border-b border-border/60 bg-muted/30">
                  <th class="w-16 px-2 py-3 text-left text-xs font-medium text-muted-foreground">Hora</th>
                  <th
                    v-for="d in weekDays"
                    :key="isoDate(d)"
                    :class="[
                      'px-2 py-3 text-center text-xs font-medium',
                      isToday(d) ? 'text-primary' : 'text-muted-foreground',
                      isPastDate(d) && !isToday(d) && 'opacity-50',
                    ]"
                  >
                    <div class="capitalize">{{ dayLabel(d).dow }}</div>
                    <div :class="['mt-0.5 text-base font-bold', isToday(d) ? 'text-primary' : 'text-foreground']">
                      {{ dayLabel(d).day }}
                    </div>
                    <div class="text-[10px] capitalize">{{ dayLabel(d).month }}</div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="hour in hourRows"
                  :key="hour"
                  class="border-b border-border/40 last:border-b-0"
                >
                  <td class="bg-muted/20 px-2 py-2 align-top text-xs font-medium text-muted-foreground">
                    {{ hour }}
                  </td>
                  <td
                    v-for="d in weekDays"
                    :key="isoDate(d) + '-' + hour"
                    class="p-1"
                  >
                    <template v-if="getSlot(d, hour)">
                      <button
                        type="button"
                        :disabled="!getSlot(d, hour)!.available"
                        :class="[
                          'block w-full rounded-md px-2 py-2 text-left transition',
                          getSlot(d, hour)!.available
                            ? 'border border-border bg-background hover:border-primary hover:bg-primary/5 cursor-pointer'
                            : 'border border-transparent bg-muted/40 opacity-60 cursor-not-allowed',
                          selectedSlot?.start_at === getSlot(d, hour)!.start_at
                            && 'border-primary bg-primary/15 ring-1 ring-primary',
                        ]"
                        @click="selectSlot(getSlot(d, hour))"
                      >
                        <div class="text-[11px] font-semibold">S/ {{ getSlot(d, hour)!.price }}</div>
                        <div class="mt-0.5 text-[10px] text-muted-foreground">
                          {{ getSlot(d, hour)!.start }}–{{ getSlot(d, hour)!.end }}
                        </div>
                        <div
                          v-if="!getSlot(d, hour)!.available"
                          class="mt-0.5 text-[9px] uppercase tracking-wider text-muted-foreground"
                        >
                          {{ getSlot(d, hour)!.status === "past" ? "Pasado"
                            : getSlot(d, hour)!.status === "held" ? "Espera" : "Ocupado" }}
                        </div>
                      </button>
                    </template>
                    <template v-else>
                      <div class="block h-[58px] rounded-md bg-muted/10"></div>
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Leyenda -->
          <div class="flex flex-wrap items-center gap-4 border-t border-border/60 bg-muted/10 px-4 py-2 text-[11px] text-muted-foreground">
            <span class="inline-flex items-center gap-1.5">
              <span class="size-2.5 rounded-sm border border-border bg-background"></span> Disponible
            </span>
            <span class="inline-flex items-center gap-1.5">
              <span class="size-2.5 rounded-sm bg-muted/60"></span> Ocupado / pasado
            </span>
            <span class="inline-flex items-center gap-1.5">
              <span class="size-2.5 rounded-sm bg-primary/40 ring-1 ring-primary"></span> Seleccionado
            </span>
          </div>
        </section>

        <!-- Reservation form / success -->
        <section
          v-if="selectedSlot || reservation"
          id="reservation-form"
          class="mt-8 rounded-2xl border border-border bg-card p-6 shadow-sm"
        >
          <template v-if="!reservation">
            <h2 class="text-lg font-semibold">Tus datos</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Horario seleccionado:
              <span class="font-medium text-foreground">
                {{ new Date(selectedSlot!.start_at).toLocaleDateString("es-PE", { weekday: "long", day: "numeric", month: "short" }) }}
                · {{ selectedSlot!.start }} – {{ selectedSlot!.end }} · S/ {{ selectedSlot!.price }}
              </span>
            </p>

            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitReservation">
              <div>
                <Label for="r-name" class="text-xs">Nombre completo *</Label>
                <Input id="r-name" v-model="form.name" required maxlength="255" class="mt-1" />
              </div>
              <div>
                <Label for="r-phone" class="text-xs">Teléfono *</Label>
                <Input id="r-phone" v-model="form.phone" required maxlength="32" placeholder="+51 9XX XXX XXX" class="mt-1" />
              </div>
              <div>
                <Label for="r-email" class="text-xs">Email (opcional)</Label>
                <Input id="r-email" v-model="form.email" type="email" maxlength="255" class="mt-1" />
              </div>
              <div>
                <Label for="r-notes" class="text-xs">Notas (opcional)</Label>
                <Input id="r-notes" v-model="form.notes" maxlength="500" placeholder="Ej. somos 10 personas" class="mt-1" />
              </div>

              <div
                v-if="submitError"
                class="md:col-span-2 rounded-lg border border-destructive bg-destructive/10 px-3 py-2 text-sm text-destructive"
              >
                {{ submitError }}
              </div>

              <div class="md:col-span-2 flex justify-end">
                <Button type="submit" :disabled="submitting" class="gap-2">
                  <Loader2 v-if="submitting" class="size-4 animate-spin" />
                  Confirmar reserva
                </Button>
              </div>
            </form>
          </template>

          <template v-else>
            <div class="flex items-center gap-3 text-emerald-600">
              <CheckCircle2 class="size-6" />
              <h2 class="text-lg font-semibold">¡Reserva en espera!</h2>
            </div>

            <div class="mt-4 rounded-lg bg-muted/50 p-4">
              <p class="text-xs uppercase tracking-wider text-muted-foreground">Código</p>
              <p class="text-2xl font-mono font-bold">{{ reservation.code }}</p>
              <p class="mt-3 text-sm">
                Total: <span class="font-semibold">S/ {{ Number(reservation.total).toFixed(2) }}</span>
              </p>
              <p class="mt-1 text-xs text-muted-foreground">{{ reservation.message }}</p>
            </div>

            <div class="mt-5 space-y-2 text-sm">
              <p class="font-medium">Próximos pasos:</p>
              <ol class="ml-4 list-decimal space-y-1 text-muted-foreground">
                <li>Coordina el pago con el complejo (Yape, Plin, efectivo)</li>
                <li>Una vez recibido el pago, el complejo confirma tu reserva</li>
                <li>Llega 10 minutos antes con tu código <span class="font-mono">{{ reservation.code }}</span></li>
              </ol>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
              <Button v-if="court.company?.phone" as-child class="gap-2">
                <a
                  :href="whatsappLink(court.company.phone, `Hola, acabo de hacer una reserva (${reservation.code}) por S/ ${Number(reservation.total).toFixed(2)}. ¿Cómo coordinamos el pago?`) ?? '#'"
                  target="_blank"
                  rel="noopener"
                >
                  <MessageCircle class="size-4" />
                  Coordinar por WhatsApp
                </a>
              </Button>
              <Button as-child variant="outline" class="gap-2">
                <RouterLink :to="{ name: 'ReservationStatus', params: { code: reservation.code } }">
                  Ver estado de mi reserva
                  <ArrowRight class="size-4" />
                </RouterLink>
              </Button>
            </div>
          </template>
        </section>

        <!-- Other courts -->
        <section v-if="otherCourts.length" class="mt-12">
          <h2 class="text-lg font-semibold">Otras canchas en este complejo</h2>
          <div class="mt-4 grid gap-4 md:grid-cols-3">
            <RouterLink
              v-for="oc in otherCourts"
              :key="oc.id"
              :to="{ name: 'CourtDetail', params: { slug: oc.slug } }"
              class="group rounded-xl border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:shadow-md"
            >
              <Badge class="gap-1">
                <Goal class="size-3" />
                {{ oc.sport }}
              </Badge>
              <h3 class="mt-2 text-base font-semibold">{{ oc.name }}</h3>
              <p class="mt-1 text-xs text-muted-foreground">Desde {{ priceFmt(oc.base_price) }}</p>
            </RouterLink>
          </div>
        </section>
      </template>
    </main>

    <footer class="mt-12 border-t border-border/50">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6 text-xs text-muted-foreground">
        <span>{{ court?.company?.name ?? "Reservas" }}</span>
        <span>Con tecnología de Fullbolito</span>
      </div>
    </footer>
  </div>
</template>
