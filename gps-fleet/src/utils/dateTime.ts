const notificationDateFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Asia/Bangkok',
  year: '2-digit',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hourCycle: 'h23',
})

export function formatNotificationDateTime(value: string | null | undefined): string {
  if (!value) return '-'

  // Notification timestamps without an offset are UTC; the feed also uses YY-MM-DD.
  let normalized = value.trim().replace(/^(\d{2})-(\d{2})-(\d{2})(?=[ T])/, '20$1-$2-$3')
    .replace(' ', 'T')
  if (!/(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalized)) normalized += 'Z'

  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return value

  const parts = Object.fromEntries(
    notificationDateFormatter.formatToParts(date).map(({ type, value }) => [type, value]),
  )
  return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`
}

export function formatDateTime(
  value: string | null | undefined,
  locale: 'th' | 'en',
): string {
  if (!value) return '-'

  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'))

  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(locale === 'th' ? 'th-TH' : 'en-GB', {
    dateStyle: 'short',
    timeStyle: 'medium',
  }).format(date)
}
