export interface ReportNavigationItem {
  key: string
  path: string
  title: { th: string; en: string }
  section: 'summary' | 'general'
  features?: Array<'summaryReport' | 'fare'>
}

export const reportNavigation: ReportNavigationItem[] = [
  { key: 'monthly-distance', path: '/reports/monthly-distance', title: { th: 'ระยะทางรายเดือน', en: 'Monthly Distance' }, section: 'summary', features: ['summaryReport'] },
  { key: 'monthly-income', path: '/reports/monthly-income', title: { th: 'รายได้รายเดือน', en: 'Monthly Income' }, section: 'summary', features: ['summaryReport', 'fare'] },
  { key: 'speed-over-summary', path: '/reports/speed-over-summary', title: { th: 'รายงานสรุปความเร็วเกินกำหนด', en: 'Speed Over Summary' }, section: 'summary', features: ['summaryReport'] },
  { key: 'drive4h-summary', path: '/reports/drive4h-summary', title: { th: 'รายงานสรุปการขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Summary' }, section: 'summary', features: ['summaryReport'] },
  { key: 'passenger-summary', path: '/reports/passenger-summary', title: { th: 'รายงานสรุปจำนวนผู้โดยสาร', en: 'Passenger Summary' }, section: 'summary', features: ['summaryReport'] },
  { key: 'status-detail', path: '/reports/status-detail', title: { th: 'รายงานรายละเอียดสถานะรถ', en: 'Vehicle Status Detail Report' }, section: 'general' },
  { key: 'speed-over', path: '/reports/speed-over', title: { th: 'รายงานความเร็วเกินกำหนด', en: 'Speed Over Report' }, section: 'general' },
  { key: 'speed', path: '/reports/speed', title: { th: 'รายงานความเร็ว', en: 'Speed Report' }, section: 'general' },
  { key: 'event', path: '/reports/events', title: { th: 'รายงานเหตุการณ์สำคัญ', en: 'Important Event Report' }, section: 'general' },
  { key: 'fuel', path: '/reports/fuel', title: { th: 'รายงานน้ำมัน/เชื้อเพลิง', en: 'Fuel Report' }, section: 'general' },
  { key: 'temperature', path: '/reports/temperature', title: { th: 'รายงานอุณหภูมิ', en: 'Temperature Report' }, section: 'general' },
  { key: 'swipe', path: '/reports/swipe', title: { th: 'รายงานการรูดบัตร', en: 'Card Swipe Report' }, section: 'general' },
  { key: 'drive4h', path: '/reports/drive4h', title: { th: 'รายงานขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Report' }, section: 'general' },
  { key: 'passenger', path: '/reports/passenger', title: { th: 'รายงานผู้โดยสาร', en: 'Passenger Report' }, section: 'general' },
  { key: 'forbidden-inside', path: '/reports/forbidden-inside', title: { th: 'รายงานการเข้าพื้นที่ห้ามเข้า', en: 'Forbidden Area Entry Report' }, section: 'general' },
]
