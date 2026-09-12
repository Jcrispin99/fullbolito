import { createI18n } from 'vue-i18n'
import es from './locales/es'
import en from './locales/en'

export type SupportedLocale = 'es' | 'en'

const STORAGE_KEY = 'tenant-locale'

function getInitialLocale(): SupportedLocale {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored === 'es' || stored === 'en') return stored
    return 'es'
}

const i18n = createI18n({
    legacy: false,
    locale: getInitialLocale(),
    fallbackLocale: 'es',
    messages: { es, en },
})

export function setLocale(locale: SupportedLocale) {
    i18n.global.locale.value = locale
    localStorage.setItem(STORAGE_KEY, locale)
}

export default i18n
