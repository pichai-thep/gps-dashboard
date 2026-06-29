import { computed, ref } from 'vue'

import en from './locales/en'
import th from './locales/th'

export type Locale = 'th' | 'en'

const LOCALE_KEY = 'gps_fleet_locale'
const defaultLocale: Locale = 'th'

const storedLocale = localStorage.getItem(LOCALE_KEY)

export const locale = ref<Locale>(
    storedLocale === 'en' || storedLocale === 'th'
        ? storedLocale
        : defaultLocale
)

const messages = {
    en,
    th,
} as const

export type MessageKey = keyof typeof messages.en

export function setLocale(nextLocale: Locale) {
    locale.value = nextLocale
    localStorage.setItem(LOCALE_KEY, nextLocale)
    document.documentElement.lang = nextLocale
}

document.documentElement.lang = locale.value

export function useI18n() {
    const isThai = computed(() => locale.value === 'th')

    function t(key: MessageKey, params?: Record<string, string | number>): string {
        let text = messages[locale.value][key] ?? messages.en[key] ?? key

        if (!params) return text

        for (const [paramKey, paramValue] of Object.entries(params)) {
            text = text.split(`{${paramKey}}`).join(String(paramValue))
        }

        return text
    }

    function toggleLocale() {
        setLocale(locale.value === 'th' ? 'en' : 'th')
    }

    return {
        isThai,
        locale,
        setLocale,
        t,
        toggleLocale,
    }
}
