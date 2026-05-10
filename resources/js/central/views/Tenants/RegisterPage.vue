<script setup lang="ts">
import { computed, ref } from "vue"
import { useRouter } from "vue-router"
import DashboardLayout from "@/central/layouts/DashboardLayout.vue"
import PageHeader from "@/components/PageHeader.vue";
import RegisterForm from "./RegisterForm.vue"
import { Button } from "@/components/ui/button"
import { ArrowLeft, Save } from "lucide-vue-next"
import { useTenantStore } from "@/central/stores/tenant"

const router = useRouter()
const tenantStore = useTenantStore()

const isLoading = ref(false)
const errors = ref<Record<string, string>>({})
const registerForm = ref<InstanceType<typeof RegisterForm> | null>(null)

const breadcrumbs = computed(() => [
  { label: "Tenants", href: "/tenants" },
  { label: "Create Tenant" },
])

const handleCancel = () => {
  router.push("/tenants")
}

const handleSave = () => {
  registerForm.value?.submit()
}

const handleSubmit = async (formData: any) => {
  isLoading.value = true
  errors.value = {}

  try {
    await tenantStore.registerTenant(formData)
    router.push("/tenants")
  } catch (err: any) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors
    } else {
      console.error("Error registering tenant:", err)
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <PageHeader title="Create Tenant">
      <template #leading>
        <Button
          variant="outline"
          size="icon"
          class="h-9 w-9"
          aria-label="Back"
          @click="handleCancel"
        >
          <ArrowLeft class="h-4 w-4" />
        </Button>
      </template>

      <template #trailing>
        <Button size="sm" class="h-9" :disabled="isLoading" @click="handleSave">
          <Save class="mr-2 h-4 w-4" />
          {{ isLoading ? "Creating..." : "Create Tenant" }}
        </Button>
      </template>
    </PageHeader>

    <div class="pt-0">
      <div class="grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-9">
          <RegisterForm
            ref="registerForm"
            :is-loading="isLoading"
            :errors="errors"
            @submit="handleSubmit"
          />
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

