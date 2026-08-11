export function isReportDurationField(field: string) {
  return /(?:^|_)(?:duration|runtime|run_time|idle_time|park_time)(?:_|$)/.test(field.toLowerCase())
}

export function formatReportDuration(value: unknown, field = 'duration') {
  if (value === null || value === undefined || value === '') return '-'

  const text = String(value).trim()
  const colonParts = text.split(':')
  if (colonParts.length >= 2 && colonParts.every((part) => /^\d+$/.test(part))) {
    const hours = Number(colonParts[0])
    const minutes = Number(colonParts[1])
    if (Number.isFinite(hours) && Number.isFinite(minutes)) {
      return toHoursMinutes((hours * 60) + minutes)
    }
  }

  const number = Number(value)
  if (!Number.isFinite(number)) return text

  const normalizedField = field.toLowerCase()
  const totalMinutes = /(?:^|_)s$/.test(normalizedField)
    ? Math.floor(number / 60)
    : Math.floor(number)

  return toHoursMinutes(totalMinutes)
}

function toHoursMinutes(totalMinutes: number) {
  const safeMinutes = Math.max(0, totalMinutes)
  const hours = Math.floor(safeMinutes / 60)
  const minutes = safeMinutes % 60
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
}
