export function reportFractionDigits(field: string, reportKey = '') {
  const normalizedField = field.toLowerCase()

  if (reportKey === 'monthly-distance' && (normalizedField === 'total_km' || /^d\d+$/.test(normalizedField))) {
    return 0
  }
  if (reportKey === 'monthly-income' && (normalizedField === 'total_fare' || /^d\d+$/.test(normalizedField))) {
    return 2
  }
  if (/(?:^|_)(?:lat|latitude|lon|lng|longitude)(?:_|$)/.test(normalizedField)) return 8
  if (/(?:^|_)(?:amount|fare|price|cost|income|revenue)(?:_|$)/.test(normalizedField)) return 2
  if (/(?:^|_)(?:distance|distance_m|distance_km|total_km|km)(?:_|$)/.test(normalizedField)) return 1
  if (/(?:^|_)(?:speed|max_speed|speed_limited)(?:_|$)/.test(normalizedField)) return 0
  if (/(?:^|_)(?:satellite|satellites|sat_count|satellite_count)(?:_|$)/.test(normalizedField)) return 0

  return undefined
}

export function formatReportNumber(
  value: unknown,
  field: string,
  reportKey = '',
  fallbackFractionDigits?: number,
) {
  const number = Number(value)
  if (!Number.isFinite(number)) return String(value ?? '')

  const fractionDigits = reportFractionDigits(field, reportKey) ?? fallbackFractionDigits
  if (fractionDigits === undefined) return number.toLocaleString()

  return number.toLocaleString(undefined, {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  })
}

export function formatReportInteger(value: unknown) {
  return formatReportNumber(value ?? 0, 'count', '', 0)
}

export function formatDistanceKmFromMeters(value: unknown, withUnit = true) {
  const formatted = formatReportNumber(Number(value || 0) / 1000, 'distance_km')
  return withUnit ? `${formatted} km` : formatted
}
