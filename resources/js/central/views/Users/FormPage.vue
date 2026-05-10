<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { useRoute, useRouter } from "vue-router"
import DashboardLayout from "@/central/layouts/DashboardLayout.vue"
import PageHeader from "@/components/PageHeader.vue";
import UserForm from "./Form.vue"
import { Button } from "@/components/ui/button"
import { ArrowLeft, Save, Settings2, Trash2 } from "lucide-vue-next"
import ConfirmDialog from "@/components/ConfirmDialog.vue"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useUserStore } from "@/central/stores/user"
import type { User } from "@/types/models"

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const mode = computed(() => (route.params.id ? "edit" : "create"))
const userId = computed(() => (route.params.id ? String(route.params.id) : null))

const isLoading = ref(false)
const errors = ref<Record<string, string>>({})
const currentUser = ref<Partial<User>>({})
const userForm = ref<InstanceType<typeof UserForm> | null>(null)
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null)

const canManageUser = computed(() => mode.value === "edit" && !!userId.value)

onMounted(async () => {
  if (mode.value === "edit" && userId.value) {
    isLoading.value = true
    try {
      const u = await userStore.fetchUser(userId.value)
      if (u) currentUser.value = u
    } catch (error) {
      console.error("Error fetching user:", error)
      router.push("/users")
    } finally {
      isLoading.value = false
    }
  }
})

const handleSubmit = async (formData: any) => {
  isLoading.value = true
  errors.value = {}

  try {
    if (mode.value === "edit" && userId.value) {
      await userStore.updateUser(userId.value, formData)
    } else {
      await userStore.createUser(formData)
    }
    router.push("/users")
  } catch (err: any) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors
    } else {
      console.error("Error saving user:", err)
    }
  } finally {
    isLoading.value = false
  }
}

const handleCancel = () => {
  router.push("/users")
}

const pageTitle = computed(() => (mode.value === "edit" ? "Edit User" : "Create User"))

const handleSave = () => {
  userForm.value?.submit()
}

const handleDelete = () => {
  if (!userId.value) return
  const id = userId.value
  confirmDialog.value?.show(
    "Delete user",
    "Are you sure you want to delete this user? This action cannot be undone.",
    async () => {
      isLoading.value = true
      try {
        await userStore.deleteUser(id)
        router.push("/users")
      } finally {
        isLoading.value = false
      }
    },
  )
}

const breadcrumbs = computed(() => [
  { label: "Users", href: "/users" },
  { label: mode.value === "edit" ? "Edit User" : "Create User" },
])
</script>

<template>
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <PageHeader :title="pageTitle">
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
          {{ isLoading ? "Saving..." : mode === "edit" ? "Update User" : "Create User" }}
        </Button>
        <DropdownMenu v-if="canManageUser">
          <DropdownMenuTrigger as-child>
            <Button
              variant="outline"
              size="icon"
              class="h-9 w-9"
              aria-label="User settings"
            >
              <Settings2 class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-[200px]">
            <DropdownMenuLabel>User</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="text-destructive" @click="handleDelete">
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
    </PageHeader>

    <div class="pt-0">
      <div class="grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-9">
          <UserForm
            ref="userForm"
            :mode="mode"
            :initial-data="currentUser"
            :is-loading="isLoading"
            :errors="errors"
            @submit="handleSubmit"
          />
        </div>
      </div>
    </div>
  </DashboardLayout>
  <ConfirmDialog ref="confirmDialog" />
</template>

