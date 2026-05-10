import { computed } from 'vue'
import type { SectionLayout } from '@/types/builder'

const spacingMap: Record<string, string> = {
  none: '0', xs: '0.5rem', sm: '1rem', md: '2rem', lg: '4rem', xl: '6rem', '2xl': '8rem',
}

export function useBlockLayout(layout: SectionLayout) {
  const paddingTop = computed(() => spacingMap[layout.padding_top] || spacingMap[layout.padding_y || 'none'] || '0')
  const paddingBottom = computed(() => spacingMap[layout.padding_bottom] || spacingMap[layout.padding_y || 'none'] || '0')
  const paddingX = computed(() => spacingMap[layout.padding_x || 'none'] || '0')
  const marginTop = computed(() => spacingMap[layout.margin_top || 'none'] || '0')
  const marginBottom = computed(() => spacingMap[layout.margin_bottom || 'none'] || '0')

  const sectionStyle = computed(() => {
    const s: Record<string, string> = {
      paddingTop: paddingTop.value,
      paddingBottom: paddingBottom.value,
      paddingLeft: paddingX.value,
      paddingRight: paddingX.value,
      marginTop: marginTop.value,
      marginBottom: marginBottom.value,
    }

    if (layout.bg_type === 'color' && layout.bg_value) {
      s.backgroundColor = layout.bg_value
    }
    if (layout.bg_type === 'image' && layout.bg_value) {
      s.backgroundImage = `url(${layout.bg_value})`
      s.backgroundSize = layout.bg_size || 'cover'
      s.backgroundPosition = layout.bg_position || 'center'
      s.backgroundRepeat = 'no-repeat'
    }
    if (layout.bg_type === 'gradient' && layout.bg_value) {
      s.backgroundImage = layout.bg_value
    }

    return s
  })

  const widthClass = computed(() => {
    if (layout.width === 'contained') return 'max-w-7xl mx-auto'
    if (layout.width === 'narrow') return 'max-w-3xl mx-auto'
    return ''
  })

  const textAlignClass = computed(() => {
    const align = layout.text_align || 'left'
    if (align === 'center') return 'text-center'
    if (align === 'right') return 'text-right'
    return 'text-left'
  })

  const hasOverlay = computed(() => layout.bg_overlay && layout.bg_type !== 'none' && layout.bg_type !== 'color')

  return { sectionStyle, widthClass, textAlignClass, hasOverlay, overlayColor: layout.bg_overlay }
}
