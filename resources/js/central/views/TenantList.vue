<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { storeToRefs } from "pinia"
import { useRouter } from "vue-router"
import DashboardLayout from "@/central/layouts/DashboardLayout.vue"
import ModuleHeader from "@/central/components/ModuleHeader.vue"
import { useAuthStore } from "@/central/stores/auth"
import { useTenantStore } from "@/central/stores/tenant"
import { Checkbox } from "@/components/ui/checkbox"
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const { tenants, meta, isLoading } = storeToRefs(tenantStore)

const perPage = ref(15)
const search = ref("")
const selectedTenants = ref<string[]>([])

type ColumnKey = "id" | "business" | "domains" | "owner" | "created"

const COLUMN_STORAGE_KEY = "tenants_table_columns"

const columnOptions: { key: ColumnKey; label: string }[] = [
  { key: "id", label: "ID" },
  { key: "business", label: "Negocio" },
  { key: "domains", label: "Dominios" },
  { key: "owner", label: "Propietario" },
  { key: "created", label: "Creado" },
]

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
  id: true,
  business: true,
  domains: true,
  owner: true,
  created: true,
}

const columnVisibility = ref<Record<ColumnKey, boolean>>({
  ...defaultColumnVisibility,
})

const visibleColumnCount = computed(
  () => Object.values(columnVisibility.value).filter(Boolean).length,
)
const tableColspan = computed(() => 1 + visibleColumnCount.value)

const allSelected = computed(() => {
  return (
    tenants.value.length > 0 &&
    selectedTenants.value.length === tenants.value.length
  )
})

const toggleSelectAll = (checked: boolean) => {
  if (checked) {
    selectedTenants.value = tenants.value.map((t: any) => t.id)
  } else {
    selectedTenants.value = []
  }
}

const toggleSelectRow = (id: string, checked: boolean) => {
  if (checked) {
    selectedTenants.value.push(id)
  } else {
    selectedTenants.value = selectedTenants.value.filter((tid) => tid !== id)
  }
}

const filteredTenants = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return tenants.value

  return tenants.value.filter((t: any) => {
    const id = String(t.id || "").toLowerCase()
    const business = String(t?.data?.business_name || "").toLowerCase()
    const domains = Array.isArray(t.domains)
      ? t.domains.map((d: any) => d.domain).join(" ").toLowerCase()
      : ""
    return id.includes(q) || business.includes(q) || domains.includes(q)
  })
})

const loadTenants = (page = 1) => {
  selectedTenants.value = []
  tenantStore.fetchTenants(page, Number(perPage.value))
}

onMounted(() => {
  loadTenants()
})

const handleSearch = (value: string) => {
  search.value = value
}

const handlePerPageChange = (newPerPage: number | string) => {
  if (newPerPage === "total") {
    perPage.value = meta.value.total || 9999
    loadTenants(1)
  } else {
    perPage.value = Number(newPerPage)
    loadTenants(1)
  }
}

const handlePageChange = (page: number) => {
  loadTenants(page)
}

const navigateToCreate = () => {
  router.push("/tenants/create")
}

const navigateToEdit = (id: string) => {
  router.push(`/tenants/${id}/edit`)
}

const formatCreated = (iso: string | null | undefined) => {
  if (!iso) return "-"
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return "-"
  return d.toLocaleString("es-PE", { dateStyle: "medium" })
}

const canCreate = computed(() =>
  (authStore.user?.roles ?? []).includes("superadmin"),
)
</script>

<template>
  <DashboardLayout :breadcrumbs="[{ label: 'Negocios' }]">
    <div class="space-y-6">
      <ModuleHeader
        title="Negocios"
        :items-count="filteredTenants.length"
        :total-items="meta.total"
        :per-page="meta.per_page"
        :current-page="meta.current_page"
        :loading="isLoading"
        :can-create="canCreate"
        :search="search"
        :selected-items="selectedTenants"
        @create="navigateToCreate"
        @update:per-page="handlePerPageChange"
        @update:search="handleSearch"
        @page-change="handlePageChange"
      />

      <div class="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-[50px]">
                <Checkbox
                  :checked="allSelected"
                  @update:checked="toggleSelectAll"
                />
              </TableHead>
              <TableHead v-if="columnVisibility.id">ID</TableHead>
              <TableHead v-if="columnVisibility.business">Negocio</TableHead>
              <TableHead v-if="columnVisibility.domains">Dominios</TableHead>
              <TableHead v-if="columnVisibility.owner">Propietario</TableHead>
              <TableHead v-if="columnVisibility.created">Creado</TableHead>
              <TableColumnSettingsHead
                v-model="columnVisibility"
                :columns="columnOptions"
                :storage-key="COLUMN_STORAGE_KEY"
              />
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="isLoading">
              <TableCell :colspan="tableColspan" class="text-center py-8"
                >Cargando...</TableCell
              >
            </TableRow>
            <TableRow v-else-if="filteredTenants.length === 0">
              <TableCell
                :colspan="tableColspan"
                class="text-center py-8 text-muted-foreground"
                >No se encontraron negocios.</TableCell
              >
            </TableRow>
            <TableRow
              v-for="tenant in filteredTenants"
              :key="tenant.id"
              class="cursor-pointer hover:bg-muted/50"
              @click="navigateToEdit(tenant.id)"
            >
              <TableCell @click.stop>
                <Checkbox
                  :checked="selectedTenants.includes(tenant.id)"
                  @update:checked="
                    (checked) => toggleSelectRow(tenant.id, checked)
                  "
                />
              </TableCell>
              <TableCell v-if="columnVisibility.id" class="font-medium">
                {{ tenant.id }}
              </TableCell>
              <TableCell v-if="columnVisibility.business">
                {{ tenant?.data?.business_name || "-" }}
              </TableCell>
              <TableCell v-if="columnVisibility.domains" class="min-w-0">
                <div class="flex flex-col gap-1">
                  <span
                    v-for="d in (tenant.domains || []).slice(0, 2)"
                    :key="d.id"
                    class="truncate"
                  >
                    {{ d.domain }}
                  </span>
                  <span
                    v-if="(tenant.domains || []).length > 2"
                    class="text-xs text-muted-foreground"
                  >
                    +{{ (tenant.domains || []).length - 2 }} más
                  </span>
                </div>
              </TableCell>
              <TableCell v-if="columnVisibility.owner">
                {{ tenant.user_id ?? "-" }}
              </TableCell>
              <TableCell v-if="columnVisibility.created">
                {{ formatCreated(tenant.created_at) }}
              </TableCell>
              <TableCell />
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </DashboardLayout>
</template>
