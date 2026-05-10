<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import { useSiteNavStore } from '@tenant/stores/siteNav'

const navStore = useSiteNavStore()
const newLabel = ref('')
const newTarget = ref('')
const newType = ref<'page' | 'url' | 'anchor'>('page')

const headerNav = computed(() => navStore.getNav('header'))

onMounted(async () => { await navStore.fetchNavs() })

async function addItem() {
  if (!newLabel.value || !newTarget.value) return
  await navStore.addNavItem('header', { label: newLabel.value, type: newType.value, target: newTarget.value })
  newLabel.value = ''
  newTarget.value = ''
}
</script>

<template>
  <DashboardLayout>
    <div class="max-w-3xl mx-auto p-6">
      <h1 class="text-2xl font-bold mb-6">Navegación</h1>
      <div v-if="navStore.isLoading" class="text-muted-foreground">Cargando...</div>
      <div v-else-if="headerNav" class="space-y-8">
        <section class="border rounded-lg overflow-hidden">
          <div class="px-6 h-14 flex items-center justify-between" :style="{ backgroundColor: headerNav.config.bg_color, color: headerNav.config.text_color }">
            <span class="font-bold">{{ headerNav.config.logo.text || 'Logo' }}</span>
            <div class="flex items-center gap-4">
              <span v-for="item in headerNav.items" :key="item.id" class="text-sm">{{ item.label }}</span>
            </div>
          </div>
        </section>
        <section>
          <h2 class="text-lg font-semibold mb-4">Items del menú</h2>
          <div class="space-y-2 mb-4">
            <div v-for="item in headerNav.items" :key="item.id" class="flex items-center justify-between p-3 border rounded-lg">
              <div class="flex items-center gap-3">
                <span class="text-muted-foreground">≡</span>
                <span class="font-medium text-sm">{{ item.label }}</span>
                <span class="text-xs text-muted-foreground">{{ item.type }} → {{ item.target }}</span>
              </div>
              <button class="text-xs text-destructive hover:text-destructive/80" @click="navStore.deleteNavItem('header', item.id)">Eliminar</button>
            </div>
          </div>
          <div class="flex items-end gap-2">
            <div class="flex-1">
              <label class="block text-xs font-medium mb-1">Label</label>
              <input v-model="newLabel" type="text" class="w-full px-3 py-2 border rounded text-sm bg-background" placeholder="Ej: Menú" />
            </div>
            <div class="w-24">
              <label class="block text-xs font-medium mb-1">Tipo</label>
              <select v-model="newType" class="w-full px-2 py-2 border rounded text-sm bg-background">
                <option value="page">Página</option>
                <option value="url">URL</option>
                <option value="anchor">Anchor</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="block text-xs font-medium mb-1">Target</label>
              <input v-model="newTarget" type="text" class="w-full px-3 py-2 border rounded text-sm bg-background" placeholder="Ej: menu" />
            </div>
            <button class="px-4 py-2 bg-primary text-primary-foreground rounded text-sm hover:bg-primary/90" :disabled="!newLabel || !newTarget" @click="addItem">Agregar</button>
          </div>
        </section>
      </div>
    </div>
  </DashboardLayout>
</template>
