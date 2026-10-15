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
