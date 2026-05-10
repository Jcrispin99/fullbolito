<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@tenant/stores/auth'
import { usePublicSiteStore } from '@tenant/stores/publicSite'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import AdminToolbar from '@tenant/components/AdminToolbar.vue'

const props = defineProps<{
  currentPageId?: number
}>()

const router = useRouter()
const authStore = useAuthStore()
const publicSite = usePublicSiteStore()
const pageStore = useBuilderPageStore()
const catalogStore = useBlockCatalogStore()
const editorMode = useEditorModeStore()

const isAuth = computed(() => authStore.isAuthenticated)

const headerNav = computed(() => {
  return publicSite.siteData?.navs?.find(n => n.location === 'header')
})

const siteName = computed(() => publicSite.siteData?.name || '')

onMounted(async () => {
  if (!publicSite.siteData) {
    await publicSite.fetchSite()
  }
  if (isAuth.value) {
    await Promise.all([
      pageStore.fetchPages(1, 50),
      catalogStore.fetchBlockTypes(),
    ])
  }
})

function navigateTo(item: { type: string; target: string; open_new_tab: boolean }) {
  if (item.open_new_tab) {
    window.open(item.type === 'page' ? `/${item.target}` : item.target, '_blank')
    return
  }
  if (item.type === 'page') {
    const page = pageStore.pages.find(p => p.slug === item.target)
    if (page?.is_homepage) {
      router.push('/')
    } else {
      router.push(`/${item.target}`)
    }
  } else if (item.type === 'anchor') {
    const el = document.querySelector(item.target)
    if (el) el.scrollIntoView({ behavior: 'smooth' })
  } else {
    window.location.href = item.target
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <!-- Admin Toolbar (solo auth) -->
    <AdminToolbar
      v-if="isAuth"
      :current-page-id="currentPageId"
    />

    <!-- Site Header -->
    <header
      v-if="headerNav"
      class="border-b"
      :class="{ 'sticky z-50': headerNav.config.sticky }"
      :style="{
        top: isAuth ? '44px' : '0',
        backgroundColor: headerNav.config.bg_color,
        color: headerNav.config.text_color,
      }"
    >
      <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <router-link
          :to="headerNav.config.logo.href"
          class="font-bold text-xl"
        >
          <template v-if="headerNav.config.logo.type === 'text'">
            {{ headerNav.config.logo.text || siteName }}
          </template>
          <img
            v-else-if="headerNav.config.logo.image_url"
            :src="headerNav.config.logo.image_url"
            :alt="siteName"
            class="h-8"
          />
        </router-link>

        <nav class="hidden md:flex items-center gap-6">
          <template v-for="item in headerNav.items" :key="item.id">
            <button
              class="text-sm font-medium hover:opacity-75 transition-opacity"
              @click="navigateTo(item)"
            >
              {{ item.label }}
            </button>
          </template>
          <router-link
            v-if="!isAuth"
            to="/login"
            class="text-sm font-medium px-4 py-2 rounded-md border hover:bg-black/5 transition-colors"
          >
            Ingresar
          </router-link>
        </nav>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1" :class="{ 'pb-12': editorMode.isEditing }">
      <slot />
    </main>

    <!-- Footer -->
    <footer
      v-if="!editorMode.isEditing"
      class="border-t py-8 text-center text-sm text-muted-foreground"
    >
      <p>&copy; {{ new Date().getFullYear() }} {{ siteName }}. Todos los derechos reservados.</p>
    </footer>
  </div>
</template>
