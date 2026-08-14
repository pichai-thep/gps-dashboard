import type { Locale } from '@/i18n'

const BANGKOK_OFFSET = '+07:00'
const DATE_TIME_WITHOUT_ZONE = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)$/

function parseDateTime(value: string): number {
    const trimmedValue = value.trim()
    const localDateTime = trimmedValue.match(DATE_TIME_WITHOUT_ZONE)

    if (localDateTime) {
        return Date.parse(`${localDateTime[1]}T${localDateTime[2]}${BANGKOK_OFFSET}`)
    }

    return Date.parse(trimmedValue)
}

export function formatRelativeTime(
    value: string | null | undefined,
    locale: Locale,
    justNowLabel: string,
    now = Date.now(),
): string {
    if (!value) return '-'

    const timestamp = parseDateTime(value)

    if (!Number.isFinite(timestamp)) return value

    const elapsedSeconds = Math.max(0, Math.floor((now - timestamp) / 1000))

    if (elapsedSeconds < 60) return justNowLabel

    const formatter = new Intl.RelativeTimeFormat(locale, {
        numeric: 'always',
    })

    if (elapsedSeconds < 60 * 60) {
        return formatter.format(-Math.floor(elapsedSeconds / 60), 'minute')
    }

    if (elapsedSeconds < 60 * 60 * 24) {
        return formatter.format(-Math.floor(elapsedSeconds / (60 * 60)), 'hour')
    }

    return formatter.format(-Math.floor(elapsedSeconds / (60 * 60 * 24)), 'day')
}
