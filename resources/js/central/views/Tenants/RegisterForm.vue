<script setup lang="ts">
import { ref } from "vue"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

const props = defineProps<{
  isLoading?: boolean
  errors?: Record<string, string>
}>()

const emit = defineEmits<{
  (e: "submit", data: any): void
}>()

const form = ref({
  first_name: "",
  last_name: "",
  email: "",
  business_name: "",
  phone: "",
  password: "",
  password_confirmation: "",
})

const submit = () => {
  emit("submit", { ...form.value })
}

defineExpose({ submit })
</script>

<template>
  <Card class="w-full relative overflow-hidden">
    <CardContent class="pt-6">
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="first_name">First Name</Label>
            <Input
              id="first_name"
              v-model="form.first_name"
              placeholder="John"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.first_name" class="text-sm text-destructive">
              {{ errors.first_name }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="last_name">Last Name</Label>
            <Input
              id="last_name"
              v-model="form.last_name"
              placeholder="Doe"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.last_name" class="text-sm text-destructive">
              {{ errors.last_name }}
            </p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
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

          <div class="space-y-2">
            <Label htmlFor="phone">Phone</Label>
            <Input
              id="phone"
              v-model="form.phone"
              placeholder="+1234567890"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.phone" class="text-sm text-destructive">
              {{ errors.phone }}
            </p>
          </div>
        </div>

        <div class="space-y-2">
          <Label htmlFor="business_name">Business Name</Label>
          <Input
            id="business_name"
            v-model="form.business_name"
            placeholder="Acme Inc"
            :disabled="isLoading"
            required
          />
          <p v-if="errors?.business_name" class="text-sm text-destructive">
            {{ errors.business_name }}
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
              required
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
              required
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

