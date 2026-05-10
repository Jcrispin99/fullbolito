<script setup lang="ts">
import { onMounted } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useSiteConfigStore } from '@tenant/stores/siteConfig'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const siteConfig = useSiteConfigStore()
const editorMode = useEditorModeStore()

onMounted(async () => { if (!siteConfig.site) await siteConfig.fetchSite() })

function updateColor(key: string, value: string) {
  if (!siteConfig.site?.theme) return
  siteConfig.updateTheme({ colors: { ...siteConfig.site.theme.colors, [key]: value } })
}
</script>

<template>
  <BuilderDrawer title="Tema Visual" @close="editorMode.closeDrawer()">
    <div v-if="siteConfig.isLoading" class="text-muted-foreground text-sm">Cargando...</div>
    <div v-else-if="siteConfig.site?.theme" class="space-y-6">
      <!-- Colors -->
      <section>
        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Colores</h4>
        <div class="space-y-2">
          <div v-for="(value, key) in siteConfig.site.theme.colors" :key="key" class="flex items-center gap-3">
            <input :value="value" type="color" class="w-8 h-8 rounded border cursor-pointer flex-shrink-0" @change="updateColor(key as string, ($event.target as HTMLInputElement).value)" />
            <div class="min-w-0">
              <div class="text-xs font-medium capitalize">{{ (key as string).replace('_', ' ') }}</div>
              <div class="text-xs text-muted-foreground">{{ value }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Typography -->
      <section>
        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Tipografia</h4>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium mb-1">Fuente titulos</label>
            <input :value="siteConfig.site.theme.typography.heading_font" type="text" class="w-full px-2 py-1.5 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, heading_font: ($event.target as HTMLInputElement).value } })" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Fuente cuerpo</label>
            <input :value="siteConfig.site.theme.typography.body_font" type="text" class="w-full px-2 py-1.5 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, body_font: ($event.target as HTMLInputElement).value } })" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Tamano base (px)</label>
            <input :value="siteConfig.site.theme.typography.base_size" type="number" min="12" max="24" class="w-full px-2 py-1.5 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ typography: { ...siteConfig.site!.theme!.typography, base_size: Number(($event.target as HTMLInputElement).value) } })" />
          </div>
        </div>
      </section>

      <!-- Borders -->
      <section>
        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Bordes</h4>
        <div>
          <label class="block text-xs font-medium mb-1">Border radius</label>
          <select :value="siteConfig.site.theme.borders.radius" class="w-full px-2 py-1.5 border rounded text-sm bg-background" @change="siteConfig.updateTheme({ borders: { ...siteConfig.site!.theme!.borders, radius: ($event.target as HTMLSelectElement).value as any } })">
            <option value="none">Ninguno</option>
            <option value="sm">Pequeno</option>
            <option value="md">Medio</option>
            <option value="lg">Grande</option>
            <option value="full">Completo</option>
          </select>
        </div>
      </section>
    </div>
  </BuilderDrawer>
</template>
