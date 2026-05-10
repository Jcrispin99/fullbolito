<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'
import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import { useAppsStore, type AppItem, type PlanOption } from '@tenant/stores/apps'
import { useAuthStore } from '@tenant/stores/auth'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  CheckCircle2,
  AlertTriangle,
  Loader2,
  Sparkles,
  Heart,
  Globe,
  ArrowLeftRight,
  Crown,
  Package,
} from 'lucide-vue-next'

const route = useRoute()
const store = useAppsStore()
const authStore = useAuthStore()

onMounted(() => {
  if (!store.catalog) store.fetchCatalog()
})

const formatPrice = (n: number) => `$${n.toFixed(2)}`

// ─── Banner: feature missing (router redirect with ?missing=key) ─────────────
const missingFeature = computed(() => (route.query.missing as string | undefined) ?? null)
const missingApp = computed(() => {
  if (!missingFeature.value || !store.catalog) return null
  return store.catalog.apps.find((a) => a.key === missingFeature.value) ?? null
})

// ─── Derived state ───────────────────────────────────────────────────────────
const currentPlanSlug = computed(() => store.catalog?.current_plan?.slug ?? null)
const hasStripe = computed(() => store.catalog?.has_stripe_subscription === true)
const addons = computed<AppItem[]>(() => store.catalog?.apps.filter((a) => a.is_addon) ?? [])

const monthlyTotal = computed(() => {
  if (!store.catalog?.current_plan) return 0
  return store.catalog.current_plan.price + store.catalog.addon_total
})

const daysUntilRenewal = computed(() => {
  const ends = store.catalog?.current_plan?.ends_at
  if (!ends) return null
  const diff = new Date(ends).getTime() - Date.now()
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})

// ─── Visual mappings ─────────────────────────────────────────────────────────
const ADDON_ICONS: Record<string, any> = {
  loyalty: Heart,
  builder: Globe,
  transfers: ArrowLeftRight,
}

const ADDON_COLOR: Record<string, string> = {
  loyalty: 'text-pink-600 bg-pink-50',
  builder: 'text-blue-600 bg-blue-50',
  transfers: 'text-cyan-600 bg-cyan-50',
}

const RECOMMENDED_PLAN_SLUG = 'pro-mensual'

// ─── Add-on toggle ───────────────────────────────────────────────────────────
async function toggleAddon(app: AppItem): Promise<void> {
  try {
    if (app.is_active_addon) {
      await store.detachAddon(app.key)
      toast.success(`"${app.label}" desactivado`)
    } else {
      await store.attachAddon(app.key)
      toast.success(`"${app.label}" activado`)
    }
    await authStore.fetchUser()
  } catch {
    toast.error(store.error ?? 'No se pudo actualizar el addon')
  }
}

// ─── Plan switch ─────────────────────────────────────────────────────────────
const planToConfirm = ref<PlanOption | null>(null)

const addonsLostByConfirmedPlan = computed<string[]>(() => {
  if (!planToConfirm.value || !store.catalog) return []
  const newPlanKeys = new Set(planToConfirm.value.modules.map((m) => m.key))
  return store.catalog.apps
    .filter((a) => a.is_active_addon && newPlanKeys.has(a.key))
    .map((a) => a.label)
})

function requestPlanSwitch(plan: PlanOption): void {
  planToConfirm.value = plan
}

async function confirmPlanSwitch(): Promise<void> {
  const plan = planToConfirm.value
  if (!plan) return
  try {
    await store.switchPlan(plan.slug)
    toast.success(`Plan actualizado a "${plan.name}"`)
    await authStore.fetchUser()
  } catch {
    toast.error(store.error ?? 'No se pudo cambiar el plan')
  } finally {
    planToConfirm.value = null
  }
}
</script>

<template>
  <DashboardLayout :breadcrumbs="[{ label: 'Mi Plan' }]">
    <div class="max-w-6xl mx-auto w-full space-y-10 pb-12">
      <!-- ─── Header ─────────────────────────────────────────────────────── -->
      <header class="space-y-2">
        <div class="flex items-center gap-2">
          <Sparkles class="w-6 h-6 text-fuchsia-500" />
          <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
            Tu plan y tus apps
          </h1>
        </div>
        <p class="text-muted-foreground">
          Personaliza tu suscripción: cambia de plan, activa funcionalidades extra.
        </p>
      </header>

      <!-- ─── Missing feature alert ──────────────────────────────────────── -->
      <div
        v-if="missingApp"
        class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900"
      >
        <AlertTriangle class="w-5 h-5 mt-0.5 flex-shrink-0" />
        <div class="space-y-1 text-sm">
          <p class="font-medium">
            El módulo "{{ missingApp.label }}" no está activo en tu cuenta.
          </p>
          <p>
            Actívalo abajo como add-on
            (<strong>{{ formatPrice(missingApp.addon_price) }}/mes</strong>)
            o cambia a un plan que lo incluya.
          </p>
        </div>
      </div>

      <!-- ─── Loading / error ────────────────────────────────────────────── -->
      <div v-if="store.isLoading && !store.catalog" class="text-center py-16 text-muted-foreground">
        <Loader2 class="w-6 h-6 animate-spin mx-auto mb-3" />
        Cargando tu plan…
      </div>

      <div v-else-if="store.error && !store.catalog" class="text-center py-16 text-destructive">
        {{ store.error }}
      </div>

      <template v-else-if="store.catalog">
        <!-- ─── Hero: plan actual ──────────────────────────────────────── -->
        <section
          v-if="store.catalog.current_plan"
          class="rounded-2xl border bg-gradient-to-br from-fuchsia-50 via-violet-50/50 to-background p-6 md:p-8"
        >
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
            <div class="space-y-2">
              <p class="text-xs uppercase tracking-widest text-muted-foreground font-medium">
                Plan actual
              </p>
              <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-2xl md:text-3xl font-bold">
                  {{ store.catalog.current_plan.name }}
                </h2>
                <Badge variant="outline" class="capitalize">
                  {{ store.catalog.current_plan.status }}
                </Badge>
              </div>
              <p class="text-muted-foreground text-sm">
                {{ formatPrice(store.catalog.current_plan.price) }}
                cada {{ store.catalog.current_plan.duration_days }} días
                <span v-if="daysUntilRenewal !== null">
                  · Renueva en
                  <strong class="text-foreground">{{ daysUntilRenewal }} días</strong>
                </span>
              </p>
            </div>

            <div class="text-right md:min-w-[200px] space-y-1">
              <p class="text-xs uppercase tracking-widest text-muted-foreground font-medium">
                Total mensual
              </p>
              <p class="text-3xl md:text-4xl font-bold tabular-nums">
                {{ formatPrice(monthlyTotal) }}
              </p>
              <p
                v-if="store.catalog.addon_total > 0"
                class="text-xs text-muted-foreground"
              >
                {{ formatPrice(store.catalog.current_plan.price) }} plan
                + {{ formatPrice(store.catalog.addon_total) }} add-ons
              </p>
            </div>
          </div>
        </section>

        <!-- ─── Planes ─────────────────────────────────────────────────── -->
        <section v-if="store.catalog.plans.length" class="space-y-4">
          <div class="space-y-1">
            <h2 class="text-xl font-bold">Elige tu plan</h2>
            <p class="text-sm text-muted-foreground">
              Cambia cuando lo necesites. Los add-ons activos se mantienen.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card
              v-for="plan in store.catalog.plans"
              :key="plan.id"
              :class="[
                'relative flex flex-col transition-all',
                currentPlanSlug === plan.slug
                  ? 'border-fuchsia-500 ring-2 ring-fuchsia-500/20 shadow-md'
                  : plan.slug === RECOMMENDED_PLAN_SLUG
                  ? 'border-primary/40 shadow-sm'
                  : 'hover:border-foreground/20',
              ]"
            >
              <!-- Recommended badge -->
              <div
                v-if="plan.slug === RECOMMENDED_PLAN_SLUG && currentPlanSlug !== plan.slug"
                class="absolute -top-3 left-1/2 -translate-x-1/2"
              >
                <Badge class="bg-primary text-primary-foreground gap-1 px-2.5 py-0.5">
                  <Crown class="w-3 h-3" />
                  Recomendado
                </Badge>
              </div>

              <CardHeader class="pb-4">
                <div class="flex items-center justify-between gap-2">
                  <CardTitle class="text-lg">{{ plan.name }}</CardTitle>
                  <Badge
                    v-if="currentPlanSlug === plan.slug"
                    class="bg-fuchsia-500 text-white"
                  >
                    Actual
                  </Badge>
                </div>
                <div class="pt-2">
                  <span class="text-3xl font-bold tabular-nums">
                    {{ formatPrice(plan.price) }}
                  </span>
                  <span class="text-sm text-muted-foreground ml-1">
                    /{{ plan.duration_days === 365 ? 'año' : `${plan.duration_days}d` }}
                  </span>
                </div>
              </CardHeader>

              <CardContent class="flex-1 flex flex-col gap-4">
                <ul class="space-y-2 text-sm flex-1">
                  <li
                    v-for="m in plan.modules"
                    :key="m.key"
                    class="flex items-start gap-2"
                  >
                    <CheckCircle2 class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" />
                    <span>{{ m.label }}</span>
                  </li>
                  <li
                    v-if="plan.modules.length === 0"
                    class="text-muted-foreground italic"
                  >
                    Sin módulos asignados
                  </li>
                </ul>

                <Button
                  v-if="currentPlanSlug !== plan.slug"
                  :variant="plan.slug === RECOMMENDED_PLAN_SLUG ? 'default' : 'outline'"
                  class="w-full"
                  :disabled="store.switchingPlan === plan.slug"
                  @click="requestPlanSwitch(plan)"
                >
                  <Loader2
                    v-if="store.switchingPlan === plan.slug"
                    class="w-4 h-4 mr-2 animate-spin"
                  />
                  Cambiar a este plan
                </Button>
                <Button v-else variant="ghost" class="w-full" disabled>
                  Tu plan actual
                </Button>
              </CardContent>
            </Card>
          </div>
        </section>

        <!-- ─── Add-ons ────────────────────────────────────────────────── -->
        <section v-if="addons.length" class="space-y-4">
          <div class="space-y-1">
            <h2 class="text-xl font-bold flex items-center gap-2">
              <Package class="w-5 h-5 text-muted-foreground" />
              Funcionalidades extra
            </h2>
            <p class="text-sm text-muted-foreground">
              Activa solo lo que necesitas, sin cambiar de plan. Cobro prorrateado.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card
              v-for="addon in addons"
              :key="addon.key"
              :class="[
                'flex flex-col transition-all',
                addon.is_active_addon
                  ? 'border-primary/60 ring-1 ring-primary/20 shadow-sm'
                  : addon.included_in_plan
                  ? 'opacity-75'
                  : 'hover:border-foreground/20',
              ]"
            >
              <CardHeader class="pb-3">
                <div class="flex items-start gap-3">
                  <div
                    :class="[
                      'w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0',
                      ADDON_COLOR[addon.key] ?? 'text-muted-foreground bg-muted',
                    ]"
                  >
                    <component
                      :is="ADDON_ICONS[addon.key] ?? Package"
                      class="w-6 h-6"
                    />
                  </div>
                  <div class="flex-1 min-w-0 space-y-1">
                    <CardTitle class="text-base leading-tight">
                      {{ addon.label }}
                    </CardTitle>
                    <CardDescription v-if="addon.description" class="line-clamp-2">
                      {{ addon.description }}
                    </CardDescription>
                  </div>
                </div>
              </CardHeader>

              <CardContent class="flex-1 flex flex-col justify-end gap-3">
                <div class="flex items-baseline justify-between">
                  <div>
                    <span class="text-2xl font-bold tabular-nums">
                      {{ formatPrice(addon.addon_price) }}
                    </span>
                    <span class="text-xs text-muted-foreground ml-1">/mes</span>
                  </div>
                  <Badge
                    v-if="addon.included_in_plan"
                    variant="secondary"
                    class="text-xs"
                  >
                    Incluido en tu plan
                  </Badge>
                  <Badge
                    v-else-if="addon.is_active_addon"
                    class="bg-green-600 text-white text-xs"
                  >
                    Activo
                  </Badge>
                </div>

                <Button
                  v-if="addon.can_toggle"
                  :variant="addon.is_active_addon ? 'outline' : 'default'"
                  class="w-full"
                  :disabled="store.togglingKey === addon.key"
                  @click="toggleAddon(addon)"
                >
                  <Loader2
                    v-if="store.togglingKey === addon.key"
                    class="w-4 h-4 mr-2 animate-spin"
                  />
                  {{ addon.is_active_addon ? 'Desactivar' : 'Activar add-on' }}
                </Button>
              </CardContent>
            </Card>
          </div>
        </section>
      </template>
    </div>

    <!-- ─── Confirm plan switch dialog ───────────────────────────────── -->
    <!--
      NOTE: We use a regular <Button> instead of <AlertDialogAction> for the
      confirm action because AlertDialogAction auto-closes the dialog before
      the @click handler runs, which resets planToConfirm to null via
      @update:open and breaks the async flow. confirmPlanSwitch handles
      closing on success/error via planToConfirm.value = null in finally.
    -->
    <AlertDialog
      :open="planToConfirm !== null"
      @update:open="(v: boolean) => { if (!v && !store.switchingPlan) planToConfirm = null }"
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            ¿Cambiar a "{{ planToConfirm?.name }}"?
          </AlertDialogTitle>
          <AlertDialogDescription class="space-y-2">
            <span class="block">
              Tu nuevo plan costará
              <strong>{{ planToConfirm ? formatPrice(planToConfirm.price) : '' }}</strong>
              cada {{ planToConfirm?.duration_days }} días y la fecha de renovación
              se reiniciará desde hoy.
            </span>
            <span v-if="hasStripe" class="block">
              Tu suscripción de Stripe se actualizará al nuevo precio
              <strong>con prorrateo</strong>: la diferencia del periodo actual
              se acreditará o cobrará en la próxima factura.
            </span>
            <span v-if="addonsLostByConfirmedPlan.length" class="block">
              Los siguientes add-ons quedarán incluidos en el nuevo plan y se
              dejarán de cobrar como add-on:
              <strong>{{ addonsLostByConfirmedPlan.join(', ') }}</strong>.
            </span>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <Button
            :disabled="!!store.switchingPlan"
            @click="confirmPlanSwitch"
          >
            <Loader2 v-if="store.switchingPlan" class="w-4 h-4 mr-2 animate-spin" />
            Confirmar cambio
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </DashboardLayout>
</template>
