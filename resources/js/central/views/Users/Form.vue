<script setup lang="ts">
import { ref, watch } from "vue"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import type { User } from "@/types/models"

const props = defineProps<{
  mode: "create" | "edit"
  initialData?: Partial<User>
  isLoading?: boolean
  errors?: Record<string, string>
}>()

const emit = defineEmits<{
  (e: "submit", data: any): void
}>()

const form = ref({
  name: "",
  email: "",
  role: "user" as "user" | "superadmin",
  password: "",
  password_confirmation: "",
})

watch(
  () => props.initialData,
  (newData) => {
    if (!newData) return
    const currentRoles = (newData.roles as string[] | undefined) ?? []
    form.value = {
      name: String(newData.name || ""),
      email: String(newData.email || ""),
      role: currentRoles.includes("superadmin") ? "superadmin" : "user",
      password: "",
      password_confirmation: "",
    }
  },
  { immediate: true },
)

const submit = () => {
  const payload: any = {
    name: form.value.name,
    email: form.value.email,
    roles: [form.value.role],
  }

  if (props.mode === "create" || form.value.password) {
    payload.password = form.value.password
    payload.password_confirmation = form.value.password_confirmation
  }

  emit("submit", payload)
}

defineExpose({ submit })
</script>

<template>
  <Card class="w-full relative overflow-hidden">
    <CardContent class="pt-6">
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              v-model="form.name"
              placeholder="e.g. Central Admin"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.name" class="text-sm text-destructive">
              {{ errors.name }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="user@example.com"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.email" class="text-sm text-destructive">
              {{ errors.email }}
            </p>
          </div>
        </div>

        <div class="space-y-2">
          <Label htmlFor="role">Role</Label>
          <Input id="role" v-model="form.role" :disabled="isLoading" />
          <p v-if="errors?.role" class="text-sm text-destructive">
            {{ errors.role }}
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              :disabled="isLoading"
              :required="mode === 'create'"
            />
            <p v-if="errors?.password" class="text-sm text-destructive">
              {{ errors.password }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="password_confirmation">Confirm Password</Label>
            <Input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              :disabled="isLoading"
              :required="mode === 'create'"
            />
            <p
              v-if="errors?.password_confirmation"
              class="text-sm text-destructive"
            >
              {{ errors.password_confirmation }}
            </p>
          </div>
        </div>
      </form>
    </CardContent>
  </Card>
</template>

