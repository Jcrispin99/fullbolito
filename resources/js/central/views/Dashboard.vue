<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { useRouter } from "vue-router"
import DashboardLayout from "@/central/layouts/DashboardLayout.vue"
import { apiClient } from "@central/lib/api"
import { useAuthStore } from "@central/stores/auth"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Building2,
  CheckCircle2,
  Clock,
  TrendingUp,
  Plus,
  ArrowUpRight,
  Loader2,
} from "lucide-vue-next"

interface Tenant {
  id: string
  business_name?: string | null
  owner_email?: string | null
  created_at?: string | null
}

interface SubscriptionsResponse {
  data: Array<unknown>
  summary?: {
    active_count: number
    trial_count: number
    monthly_recurring_revenue: number
  }
}

const router = useRouter()
const authStore = useAuthStore()
const isSuperAdmin = computed(() =>
  (authStore.user?.roles ?? []).includes("superadmin"),
)

const loading = ref(true)
const tenants = ref<Tenant[]>([])
const totalTenants = ref(0)
const summary = ref<SubscriptionsResponse["summary"] | null>(null)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return "Buenos días"
  if (hour < 19) return "Buenas tardes"
  return "Buenas noches"
})

const userFirstName = computed(() => {
  const name = authStore.user?.name ?? ""
  return name.split(" ")[0] || "👋"
})

async function load() {
  loading.value = true
  try {
    const tenantsEndpoint = isSuperAdmin.value ? "/v1/tenants" : "/v1/my-tenants"
    const requests: Promise<unknown>[] = [
      apiClient.get<{ data: Tenant[]; meta?: { total: number } }>(tenantsEndpoint),
    ]
    if (isSuperAdmin.value) {
      requests.push(
        apiClient.get<{ data: SubscriptionsResponse }>("/v1/subscriptions").catch(() => null),
      )
    }
    const [tenantsRes, subsRes] = (await Promise.all(requests)) as [any, any]
    const list = (tenantsRes?.data?.data ?? []) as Tenant[]
    tenants.value = list.slice(0, 5)
    totalTenants.value = tenantsRes?.data?.meta?.total ?? list.length
    summary.value = subsRes?.data?.data?.summary ?? null
  } catch {
    tenants.value = []
  } finally {
    loading.value = false
  }
}

const formatCurrency = (n: number | undefined | null) => {
  const v = Number(n ?? 0)
  return `S/ ${v.toFixed(2)}`
}

const formatDate = (iso: string | null | undefined) => {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString("es-PE", { day: "2-digit", month: "short", year: "numeric" })
}

onMounted(load)
</script>

<template>
  <DashboardLayout
    :title="`${greeting}, ${userFirstName}`"
    :description="isSuperAdmin ? 'Vista global de la plataforma.' : 'Resumen de tus workspaces.'"
  >
    <template #actions>
      <Button v-if="isSuperAdmin" variant="outline" @click="router.push({ name: 'Plans' })">
        Ver planes
      </Button>
      <Button @click="router.push({ name: 'TenantsCreate' })">
        <Plus class="size-4" />
        Crear tenant
      </Button>
    </template>

    <div v-if="loading" class="grid place-items-center py-24 text-muted-foreground">
      <Loader2 class="size-6 animate-spin" />
    </div>

    <template v-else>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card class="relative overflow-hidden">
          <div class="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-primary/10 blur-2xl"></div>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">
              {{ isSuperAdmin ? 'Tenants totales' : 'Mis tenants' }}
            </CardTitle>
            <div class="grid size-8 place-items-center rounded-md bg-primary/10 text-primary">
              <Building2 class="size-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold tracking-tight">{{ totalTenants }}</div>
            <CardDescription class="mt-1">
              {{ isSuperAdmin ? 'Workspaces provisionados' : 'Workspaces que controlás' }}
            </CardDescription>
          </CardContent>
        </Card>

        <Card v-if="isSuperAdmin" class="relative overflow-hidden">
          <div class="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-emerald-500/10 blur-2xl"></div>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Activas</CardTitle>
            <div class="grid size-8 place-items-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 class="size-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold tracking-tight">{{ summary?.active_count ?? 0 }}</div>
            <CardDescription class="mt-1">Suscripciones pagas</CardDescription>
          </CardContent>
        </Card>

        <Card v-if="isSuperAdmin" class="relative overflow-hidden">
          <div class="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-blue-500/10 blur-2xl"></div>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">En trial</CardTitle>
            <div class="grid size-8 place-items-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">
              <Clock class="size-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold tracking-tight">{{ summary?.trial_count ?? 0 }}</div>
            <CardDescription class="mt-1">Aún no convirtieron</CardDescription>
          </CardContent>
        </Card>

        <Card v-if="isSuperAdmin" class="relative overflow-hidden">
          <div class="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-secondary/20 blur-2xl"></div>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">MRR</CardTitle>
            <div class="grid size-8 place-items-center rounded-md bg-secondary/15 text-secondary-foreground">
              <TrendingUp class="size-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold tracking-tight">
              {{ formatCurrency(summary?.monthly_recurring_revenue) }}
            </div>
            <CardDescription class="mt-1">Ingresos mensuales recurrentes</CardDescription>
          </CardContent>
        </Card>
      </div>

      <Card class="overflow-hidden">
        <CardHeader class="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle>{{ isSuperAdmin ? 'Tenants recientes' : 'Mis tenants' }}</CardTitle>
            <CardDescription>Los últimos {{ tenants.length }} workspaces.</CardDescription>
          </div>
          <Button variant="ghost" size="sm" @click="router.push({ name: 'TenantList' })">
            Ver todos
            <ArrowUpRight class="size-4" />
          </Button>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="!tenants.length" class="px-6 py-12 text-center text-sm text-muted-foreground">
            Todavía no tenés tenants. Creá el primero para empezar.
          </div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-y border-border/60 bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th class="px-6 py-2.5 font-medium">Negocio</th>
                <th class="px-6 py-2.5 font-medium">Email</th>
                <th class="px-6 py-2.5 font-medium">Creado</th>
                <th class="px-6 py-2.5 font-medium">ID</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in tenants"
                :key="t.id"
                class="border-b border-border/40 transition hover:bg-muted/30 last:border-0"
              >
                <td class="px-6 py-3 font-medium">{{ t.business_name ?? '—' }}</td>
                <td class="px-6 py-3 text-muted-foreground">{{ t.owner_email ?? '—' }}</td>
                <td class="px-6 py-3 text-muted-foreground">{{ formatDate(t.created_at) }}</td>
                <td class="px-6 py-3">
                  <Badge variant="outline" class="font-mono text-[10px]">{{ t.id }}</Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </template>
  </DashboardLayout>
</template>
