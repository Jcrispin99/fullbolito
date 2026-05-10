<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useSiteNavStore } from '@tenant/stores/siteNav'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const navStore = useSiteNavStore()
const editorMode = useEditorModeStore()
const newLabel = ref('')
const newTarget = ref('')
const newType = ref<'page' | 'url' | 'anchor'>('page')

const headerNav = computed(() => navStore.getNav('header'))

onMounted(async () => { if (!navStore.navs.length) await navStore.fetchNavs() })

async function addItem() {
  if (!newLabel.value || !newTarget.value) return
  await navStore.addNavItem('header', { label: newLabel.value, type: newType.value, target: newTarget.value })
  newLabel.value = ''
  newTarget.value = ''
}
</script>

<template>
  <BuilderDrawer title="Navegacion" @close="editorMode.closeDrawer()">
    <div v-if="navStore.isLoading" class="text-muted-foreground text-sm">Cargando...</div>
    <div v-else-if="headerNav" class="space-y-6">
      <!-- Preview -->
      <section class="border rounded-lg overflow-hidden">
        <div class="px-4 h-10 flex items-center justify-between text-xs" :style="{ backgroundColor: headerNav.config.bg_color, color: headerNav.config.text_color }">
          <span class="font-bold">{{ headerNav.config.logo.text || 'Logo' }}</span>
          <div class="flex items-center gap-3">
            <span v-for="item in headerNav.items" :key="item.id">{{ item.label }}</span>
          </div>
        </div>
      </section>

      <!-- Items -->
      <section>
        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Items del menu</h4>
        <div class="space-y-1.5 mb-4">
          <div v-for="item in headerNav.items" :key="item.id" class="flex items-center justify-between p-2 border rounded text-xs">
            <div class="flex items-center gap-2">
              <span class="text-muted-foreground">&#x2261;</span>
              <span class="font-medium">{{ item.label }}</span>
              <span class="text-muted-foreground">{{ item.type }} &rarr; {{ item.target }}</span>
            </div>
            <button class="text-destructive hover:text-destructive/80" @click="navStore.deleteNavItem('header', item.id)">&#x2715;</button>
          </div>
        </div>

        <!-- Add item -->
        <div class="space-y-2">
          <input v-model="newLabel" type="text" class="w-full px-2 py-1.5 border rounded text-sm bg-background" placeholder="Label" />
          <div class="flex gap-2">
            <select v-model="newType" class="flex-1 px-2 py-1.5 border rounded text-sm bg-background">
              <option value="page">Pagina</option>
              <option value="url">URL</option>
              <option value="anchor">Anchor</option>
            </select>
            <input v-model="newTarget" type="text" class="flex-1 px-2 py-1.5 border rounded text-sm bg-background" placeholder="Target" />
          </div>
          <button class="w-full px-3 py-1.5 bg-primary text-primary-foreground rounded text-sm hover:bg-primary/90" :disabled="!newLabel || !newTarget" @click="addItem">Agregar item</button>
        </div>
      </section>
    </div>
  </BuilderDrawer>
</template>
