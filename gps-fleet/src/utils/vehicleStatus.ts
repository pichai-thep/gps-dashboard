import type { VehicleStatus } from '@/types/fleet'

export type VehicleStatusLocale = 'th' | 'en'

export type VehicleStatusMeta = {
  label: Record<VehicleStatusLocale, string>
  color: string
  textColor: string
  softColor: string
  borderColor: string
  icon: string
  rotate?: boolean
}

export const vehicleStatusMap: Record<VehicleStatus, VehicleStatusMeta> = {
  run: {
    label: { th: 'วิ่ง', en: 'RUN' },
    color: '#16a34a',
    textColor: '#86efac',
    softColor: 'rgb(34 197 94 / 16%)',
    borderColor: 'rgb(34 197 94 / 35%)',
    icon: 'pi pi-arrow-circle-up',
    rotate: true,
  },
  idle: {
    label: { th: 'ติดเครื่อง', en: 'IDLE' },
    color: '#eab308',
    textColor: '#fde047',
    softColor: 'rgb(234 179 8 / 16%)',
    borderColor: 'rgb(234 179 8 / 35%)',
    icon: 'pi pi-arrow-circle-up',
    rotate: true,
  },
  park: {
    label: { th: 'จอด', en: 'PARK' },
    color: '#ef4444',
    textColor: '#fca5a5',
    softColor: 'rgb(239 68 68 / 16%)',
    borderColor: 'rgb(239 68 68 / 35%)',
    icon: 'pi pi-stop-circle',
  },
  no_gps: {
    label: { th: 'รับดาวเทียมไม่ได้', en: 'NO_GPS' },
    color: '#2563eb',
    textColor: '#93c5fd',
    softColor: 'rgb(37 99 235 / 16%)',
    borderColor: 'rgb(37 99 235 / 35%)',
    icon: 'pi pi-exclamation-circle',
  },
  offline: {
    label: { th: 'ออฟไลน์', en: 'OFFLINE' },
    color: '#64748b',
    textColor: '#cbd5e1',
    softColor: 'rgb(100 116 139 / 16%)',
    borderColor: 'rgb(100 116 139 / 35%)',
    icon: 'pi pi-exclamation-triangle',
  },
}

const statusAliases: Record<string, VehicleStatus> = {
  run: 'run',
  running: 'run',
  moving: 'run',
  idle: 'idle',
  start: 'idle',
  acc_on: 'idle',
  park: 'park',
  parking: 'park',
  stop: 'park',
  stopped: 'park',
  no_gps: 'no_gps',
  nogps: 'no_gps',
  'no gps': 'no_gps',
  offline: 'offline',
}

export function normalizeVehicleStatus(value: unknown): VehicleStatus | null {
  return statusAliases[String(value ?? '').trim().toLowerCase()] ?? null
}

export function vehicleStatusLabel(value: unknown, locale: string): string {
  const status = normalizeVehicleStatus(value)
  if (!status) return String(value ?? '').trim() || '-'
  return vehicleStatusMap[status].label[locale.toLowerCase().startsWith('th') ? 'th' : 'en']
}

export function vehicleStatusBadgeStyle(value: unknown): Record<string, string> {
  const status = normalizeVehicleStatus(value)
  if (!status) {
    return {
      color: '#cbd5e1',
      backgroundColor: 'rgb(148 163 184 / 12%)',
      borderColor: 'rgb(148 163 184 / 25%)',
    }
  }

  const meta = vehicleStatusMap[status]
  return {
    color: meta.textColor,
    backgroundColor: meta.softColor,
    borderColor: meta.borderColor,
  }
}
