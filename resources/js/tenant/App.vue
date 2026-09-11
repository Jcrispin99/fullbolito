<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@tenant/stores/auth'
import NavigationLoader from '@/components/NavigationLoader.vue'

const authStore = useAuthStore()

onMounted(async () => {
  await authStore.initAuth()
})
</script>

<template>
  <router-view />
  <NavigationLoader />
</template>
