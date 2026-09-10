<script setup lang="ts">
import { onMounted } from 'vue'
import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import { useSiteConfigStore } from '@tenant/stores/siteConfig'

const siteConfig = useSiteConfigStore()

onMounted(async () => {
  await siteConfig.fetchSite()
})

async function updateColor(key: string, value: string) {
  if (!siteConfig.site?.theme) return
  await siteConfig.updateTheme({ colors: { ...siteConfig.site.theme.colors, [key]: value } })
}
</script>

<template>
  <DashboardLayout>
    <div class="max-w-3xl mx-auto p-6">
      <h1 class="text-2xl font-bold mb-6">Tema visual</h1>
      <div v-if="siteConfig.isLoading" class="text-muted-foreground">Cargando...</div>
      <div v-else-if="siteConfig.site?.theme" class="space-y-8">
        <section>
          <h2 class="text-lg font-semibold mb-4">Colores</h2>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="(value, key) in siteConfig.site.theme.colors" :key="key" class="flex items-center gap-3">
              <input :value="value" type="color" class="w-10 h-10 rounded border cursor-pointer" @change="updateColor(key as string, ($event.target as HTMLInputElement).value)" />
              <div>
                <div class="text-sm font-medium capitalize">{{ (key as string).replace('_', ' ') }}</div>
                <div class="text-xs text-muted-foreground">{{ value }}</div>
              </div>
            </div>
          </div>
        </section>
        <section>
          <h2 class="text-lg font-semibold mb-4">Tipografía</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Fuente títulos</label>
              <input :value="siteConfig.site.theme.typography.heading_font" type="text" class="w-full px-3 py-2 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, heading_font: ($event.target as HTMLInputElement).value } })" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Fuente cuerpo</label>
              <input :value="siteConfig.site.theme.typography.body_font" type="text" class="w-full px-3 py-2 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, body_font: ($event.target as HTMLInputElement).value } })" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Tamaño base (px)</label>
              <input :value="siteConfig.site.theme.typography.base_size" type="number" min="12" max="24" class="w-full px-3 py-2 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, base_size: Number(($event.target as HTMLInputElement).value) } })" />
            </div>
          </div>
        </section>
        <section>
          <h2 class="text-lg font-semibold mb-4">Bordes</h2>
          <div>
            <label class="block text-sm font-medium mb-1">Redondeo de bordes</label>
            <select :value="siteConfig.site.theme.borders.radius" class="w-full max-w-xs px-3 py-2 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ borders: { ...siteConfig.site!.theme!.borders, radius: ($event.target as HTMLSelectElement).value as any } })">
              <option value="none">Ninguno</option>
              <option value="sm">Pequeño</option>
              <option value="md">Medio</option>
              <option value="lg">Grande</option>
              <option value="full">Completo</option>
            </select>
          </div>
        </section>
      </div>
    </div>
  </DashboardLayout>
</template>
