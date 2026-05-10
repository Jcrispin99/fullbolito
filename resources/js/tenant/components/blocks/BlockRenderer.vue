<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import type { SiteSection, SiteTheme } from '@/types/builder'
import { useBlockLayout } from './useBlockLayout'

const props = defineProps<{
  section: SiteSection
  theme?: SiteTheme | null
  editing?: boolean
}>()

const emit = defineEmits<{
  updateField: [{ key: string; value: any }]
}>()

const blockComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
  hero_banner: defineAsyncComponent(() => import('./HeroBanner.vue')),
  text_block: defineAsyncComponent(() => import('./TextBlock.vue')),
  text_image: defineAsyncComponent(() => import('./TextImage.vue')),
  image_text: defineAsyncComponent(() => import('./ImageText.vue')),
  heading_block: defineAsyncComponent(() => import('./HeadingBlock.vue')),
  image_block: defineAsyncComponent(() => import('./ImageBlock.vue')),
  image_gallery: defineAsyncComponent(() => import('./ImageGallery.vue')),
  features_grid: defineAsyncComponent(() => import('./FeaturesGrid.vue')),
  stats_block: defineAsyncComponent(() => import('./StatsBlock.vue')),
  cta_banner: defineAsyncComponent(() => import('./CtaBanner.vue')),
  faq_accordion: defineAsyncComponent(() => import('./FaqAccordion.vue')),
  contact_form: defineAsyncComponent(() => import('./ContactForm.vue')),
  spacer: defineAsyncComponent(() => import('./SpacerBlock.vue')),
  divider: defineAsyncComponent(() => import('./DividerBlock.vue')),
}

const component = computed(() => blockComponents[props.section.block_type_key])
const { sectionStyle, widthClass, hasOverlay, overlayColor } = useBlockLayout(props.section.layout)
</script>

<template>
  <section
    v-if="section.is_visible !== false"
    class="relative w-full"
    :style="sectionStyle"
    :id="section.layout.anchor_id || undefined"
  >
    <!-- Background overlay -->
    <div
      v-if="hasOverlay"
      class="absolute inset-0 z-0"
      :style="{ backgroundColor: overlayColor || 'rgba(0,0,0,0.4)' }"
    />

    <!-- Content wrapper -->
    <div class="relative z-10" :class="widthClass">
      <component
        v-if="component"
        :is="component"
        :content="section.content"
        :style-overrides="section.style_overrides || {}"
        :layout="section.layout"
        :theme="theme"
        :editing="editing"
        @update-field="(payload: { key: string; value: any }) => emit('updateField', payload)"
      />

      <!-- Fallback for unknown block types -->
      <div v-else class="py-8 px-6 text-center text-muted-foreground">
        <p class="text-sm">Bloque: {{ section.block_type_key }}</p>
      </div>
    </div>
  </section>
</template>
