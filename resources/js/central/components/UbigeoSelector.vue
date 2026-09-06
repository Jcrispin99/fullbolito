<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { apiClient } from "@central/lib/api"

interface UbigeoOption {
  id: string
  name: string
}

const props = withDefaults(
  defineProps<{
    modelValue: string
    labelDept?: string
    labelProv?: string
    labelDist?: string
    /** Cuando true, oculta los labels de cada select. */
    hideLabels?: boolean
  }>(),
  {
    labelDept: "Departamento",
    labelProv: "Provincia",
    labelDist: "Distrito",
    hideLabels: false,
  },
)

const emit = defineEmits<{
  (e: "update:modelValue", value: string): void
}>()

const departments = ref<UbigeoOption[]>([])
const provinces = ref<UbigeoOption[]>([])
const districts = ref<UbigeoOption[]>([])

const deptId = ref<string>("")
const provId = ref<string>("")
const distId = ref<string>("")

const loadingDept = ref(false)
const loadingProv = ref(false)
const loadingDist = ref(false)

// Bandera para suprimir emisiones durante hidratación inicial.
let hydrating = false

// El ubigeo activo es el código MÁS específico seleccionado.
const currentUbigeo = computed(() => {
  if (distId.value) return distId.value
  if (provId.value) return provId.value
  if (deptId.value) return deptId.value
  return ""
})

async function loadDepartments() {
  loadingDept.value = true
  try {
    const { data } = await apiClient.get<any>("/v1/marketplace/ubigeo/departments")
    const list: UbigeoOption[] = data?.data ?? []
    departments.value = list
  } finally {
    loadingDept.value = false
  }
}

async function loadProvinces(depId: string) {
  if (!depId) {
    provinces.value = []
    return
  }
  loadingProv.value = true
  try {
    const { data } = await apiClient.get<any>(
      `/v1/marketplace/ubigeo/departments/${depId}/provinces`,
    )
    provinces.value = (data?.data ?? []) as UbigeoOption[]
  } finally {
    loadingProv.value = false
  }
}

async function loadDistricts(prId: string) {
  if (!prId) {
    districts.value = []
    return
  }
  loadingDist.value = true
  try {
    const { data } = await apiClient.get<any>(
      `/v1/marketplace/ubigeo/provinces/${prId}/districts`,
    )
    districts.value = (data?.data ?? []) as UbigeoOption[]
  } finally {
    loadingDist.value = false
  }
}

function onDeptChange() {
  provId.value = ""
  distId.value = ""
  provinces.value = []
  districts.value = []
  if (deptId.value) loadProvinces(deptId.value)
  if (!hydrating) emit("update:modelValue", currentUbigeo.value)
}

function onProvChange() {
  distId.value = ""
  districts.value = []
  if (provId.value) loadDistricts(provId.value)
  if (!hydrating) emit("update:modelValue", currentUbigeo.value)
}

function onDistChange() {
  if (!hydrating) emit("update:modelValue", currentUbigeo.value)
}

function clearAll() {
  deptId.value = ""
  provId.value = ""
  distId.value = ""
  provinces.value = []
  districts.value = []
  emit("update:modelValue", "")
}

// Hidrata el selector desde el modelValue (URL query / restore).
// El código ubigeo es jerárquico: 2 = depto, 4 = provincia, 6 = distrito.
async function hydrateFromValue(value: string) {
  hydrating = true
  try {
    if (departments.value.length === 0) await loadDepartments()

    if (!value) {
      deptId.value = ""
      provId.value = ""
      distId.value = ""
      provinces.value = []
      districts.value = []
      return
    }

    deptId.value = value.slice(0, 2)

    if (value.length >= 4) {
      await loadProvinces(deptId.value)
      provId.value = value.slice(0, 4)
    } else {
      provId.value = ""
      provinces.value = []
    }

    if (value.length === 6) {
      await loadDistricts(provId.value)
      distId.value = value
    } else {
      distId.value = ""
      districts.value = []
    }
  } finally {
    hydrating = false
  }
}

// En mount: SIEMPRE cargar departments (aunque modelValue venga vacío), e
// hidratar el cascade si vino un ubigeo en la URL/parent.
onMounted(() => {
  hydrateFromValue(props.modelValue || "")
})

// Watch externo para cambios POSTERIORES (ej. clearFilters del padre).
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal === currentUbigeo.value) return
    hydrateFromValue(newVal)
  },
)

defineExpose({ clearAll })
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-3">
    <div>
      <label v-if="!hideLabels" class="text-xs">
        {{ labelDept }}
      </label>
      <select
        v-model="deptId"
        :disabled="loadingDept"
        class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
        @change="onDeptChange"
      >
        <option value="">{{ loadingDept ? "Cargando…" : "Todos" }}</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">
          {{ d.name }}
        </option>
      </select>
    </div>

    <div>
      <label v-if="!hideLabels" class="text-xs">
        {{ labelProv }}
      </label>
      <select
        v-model="provId"
        :disabled="!deptId || loadingProv"
        class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
        @change="onProvChange"
      >
        <option value="">
          {{ !deptId ? "—" : loadingProv ? "Cargando…" : "Todas" }}
        </option>
        <option v-for="p in provinces" :key="p.id" :value="p.id">
          {{ p.name }}
        </option>
      </select>
    </div>

    <div>
      <label v-if="!hideLabels" class="text-xs">
        {{ labelDist }}
      </label>
      <select
        v-model="distId"
        :disabled="!provId || loadingDist"
        class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
        @change="onDistChange"
      >
        <option value="">
          {{ !provId ? "—" : loadingDist ? "Cargando…" : "Todos" }}
        </option>
        <option v-for="d in districts" :key="d.id" :value="d.id">
          {{ d.name }}
        </option>
      </select>
    </div>
  </div>
</template>
