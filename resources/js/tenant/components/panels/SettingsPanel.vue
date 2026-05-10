<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useSiteConfigStore } from '@tenant/stores/siteConfig'
import { useCustomDomainStore } from '@tenant/stores/customDomain'
import { useRedirectStore } from '@tenant/stores/redirect'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const siteConfig = useSiteConfigStore()
const domainStore = useCustomDomainStore()
const redirectStore = useRedirectStore()
const editorMode = useEditorModeStore()

const activeTab = ref<'general' | 'domain' | 'redirects'>('general')
const newDomain = ref('')
const newFrom = ref('')
const newTo = ref('')

const site = computed(() => siteConfig.site)

onMounted(async () => {
  if (!siteConfig.site) await siteConfig.fetchSite()
  await redirectStore.fetchRedirects()
})

async function setDomain() {
  if (!newDomain.value) return
  await domainStore.setDomain(newDomain.value)
  await siteConfig.fetchSite()
  newDomain.value = ''
}

async function verifyDomain() {
  await domainStore.verifyDomain()
  await siteConfig.fetchSite()
}

async function removeDomain() {
  await domainStore.removeDomain()
  await siteConfig.fetchSite()
}

async function createRedirect() {
  if (!newFrom.value || !newTo.value) return
  await redirectStore.createRedirect({ from_slug: newFrom.value, to_slug: newTo.value })
  newFrom.value = ''
  newTo.value = ''
}

const statusColors: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  verifying: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
  active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
  failed: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
}
</script>

<template>
  <BuilderDrawer title="Configuracion del Sitio" width="w-96" @close="editorMode.closeDrawer()">
    <!-- Tabs -->
    <div class="flex border-b -mx-4 px-4 mb-4">
      <button
        v-for="tab in (['general', 'domain', 'redirects'] as const)"
        :key="tab"
        class="px-3 py-2 text-xs font-medium border-b-2 transition-colors"
        :class="activeTab === tab ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
        @click="activeTab = tab"
      >
        {{ tab === 'general' ? 'General' : tab === 'domain' ? 'Dominio' : 'Redirecciones' }}
      </button>
    </div>

    <!-- General -->
    <div v-if="activeTab === 'general' && site" class="space-y-4">
      <div>
        <label class="block text-xs font-medium mb-1">Nombre del sitio</label>
        <input
          :value="site.name"
          type="text"
          class="w-full px-2 py-1.5 border rounded text-sm bg-background"
          @change="siteConfig.updateSite({ name: ($event.target as HTMLInputElement).value })"
        />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Google Analytics ID</label>
        <input
          :value="site.settings?.google_analytics || ''"
          type="text"
          class="w-full px-2 py-1.5 border rounded text-sm bg-background"
          placeholder="G-XXXXXXXXXX"
          @change="siteConfig.updateSite({ settings: { ...site.settings, google_analytics: ($event.target as HTMLInputElement).value || null } as any })"
        />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">CSS personalizado</label>
        <textarea
          :value="site.settings?.custom_css || ''"
          rows="4"
          class="w-full px-2 py-1.5 border rounded text-sm bg-background font-mono"
          placeholder="body { ... }"
          @change="siteConfig.updateSite({ settings: { ...site.settings, custom_css: ($event.target as HTMLTextAreaElement).value || null } as any })"
        />
      </div>
    </div>

    <!-- Domain -->
    <div v-if="activeTab === 'domain'" class="space-y-4">
      <div v-if="!site?.custom_domain">
        <p class="text-sm text-muted-foreground mb-3">Conecta tu propio dominio</p>
        <div class="flex gap-2">
          <input v-model="newDomain" type="text" class="flex-1 px-2 py-1.5 border rounded text-sm bg-background" placeholder="www.mitienda.com" />
          <button class="px-3 py-1.5 bg-primary text-primary-foreground rounded text-sm hover:bg-primary/90" :disabled="!newDomain || domainStore.isLoading" @click="setDomain">
            {{ domainStore.isLoading ? '...' : 'Conectar' }}
          </button>
        </div>
      </div>

      <div v-else class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-medium text-sm">{{ site.custom_domain }}</div>
            <span v-if="site.domain_status" class="text-xs px-2 py-0.5 rounded-full" :class="statusColors[site.domain_status]">
              {{ site.domain_status }}
            </span>
          </div>
          <button class="text-xs text-destructive hover:text-destructive/80" @click="removeDomain">Eliminar</button>
        </div>

        <div v-if="site.domain_status !== 'active'" class="bg-muted/50 rounded p-3 space-y-2">
          <p class="text-xs text-muted-foreground">Agrega un registro TXT:</p>
          <div class="font-mono text-xs space-y-1">
            <div>Host: _verification.{{ site.custom_domain }}</div>
            <div>Valor: {{ domainStore.verificationResult?.verification_token || '...' }}</div>
          </div>
          <button class="w-full px-3 py-1.5 border rounded text-sm hover:bg-muted" :disabled="domainStore.isLoading" @click="verifyDomain">
            {{ domainStore.isLoading ? 'Verificando...' : 'Verificar DNS' }}
          </button>
        </div>

        <div v-else class="bg-green-50 dark:bg-green-900/20 rounded p-3">
          <p class="text-xs text-green-700 dark:text-green-300">Dominio verificado y activo</p>
        </div>
      </div>
    </div>

    <!-- Redirects -->
    <div v-if="activeTab === 'redirects'" class="space-y-4">
      <div class="flex gap-2">
        <input v-model="newFrom" type="text" class="flex-1 px-2 py-1.5 border rounded text-sm bg-background" placeholder="/viejo" />
        <span class="self-center text-muted-foreground text-xs">&rarr;</span>
        <input v-model="newTo" type="text" class="flex-1 px-2 py-1.5 border rounded text-sm bg-background" placeholder="/nuevo" />
        <button class="px-3 py-1.5 bg-primary text-primary-foreground rounded text-sm hover:bg-primary/90" :disabled="!newFrom || !newTo" @click="createRedirect">+</button>
      </div>

      <div v-if="!redirectStore.redirects.length" class="text-center py-4 text-muted-foreground text-xs">Sin redirecciones</div>
      <div v-else class="space-y-1.5">
        <div v-for="r in redirectStore.redirects" :key="r.id" class="flex items-center justify-between p-2 border rounded text-xs">
          <div class="flex items-center gap-1.5 min-w-0">
            <span class="font-mono text-muted-foreground truncate">/{{ r.from_slug }}</span>
            <span class="text-muted-foreground">&rarr;</span>
            <span class="font-mono truncate">/{{ r.to_slug }}</span>
            <span class="px-1 bg-muted rounded flex-shrink-0">{{ r.type }}</span>
          </div>
          <button class="text-destructive hover:text-destructive/80 flex-shrink-0 ml-2" @click="redirectStore.deleteRedirect(r.id)">&#x2715;</button>
        </div>
      </div>
    </div>
  </BuilderDrawer>
</template>
