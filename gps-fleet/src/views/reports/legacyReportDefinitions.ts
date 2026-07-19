export type LegacyReportKey =
  | 'speed-over-summary'
  | 'drive4h-summary'
  | 'passenger-summary'
  | 'speed-over'
  | 'event'
  | 'fuel'
  | 'swipe'
  | 'drive4h'
  | 'passenger'
  | 'forbidden-inside'

export interface LegacyColumn {
  field: string
  label: string
  aliases?: string[]
  type?: 'text' | 'number' | 'datetime' | 'location'
}

export interface LegacyCriterion {
  key: string
  label: string
  options: Array<{ label: string; value: string | number }>
  defaultValue: string | number
}

export interface LegacyReportDefinition {
  key: LegacyReportKey
  path: string
  title: { th: string; en: string }
  subtitle: { th: string; en: string }
  maxRangeDays: number
  enableTime?: boolean
  requireVehicle?: boolean
  graph?: boolean
  criteria?: LegacyCriterion[]
  columns: LegacyColumn[]
}

const eventTypes = [
  ['gps_disconnect', 'GPS antenna disconnect'],
  ['sos', 'SOS'],
  ['over_speed_system', 'Speed over (web)'],
  ['over_speed_device', 'Speed over (device)'],
  ['gps_signal_lost', 'GPS signal lost'],
  ['gps_antenna_disconnect', 'GPS antenna disconnect'],
  ['ext_power_disconnect', 'External power disconnect'],
  ['harsh_break', 'Harsh brake'],
  ['harsh_accelerate', 'Harsh accelerate'],
  ['Tamper', 'Tamper'],
  ['cover_removed', 'Cover removed'],
  ['Low internal battery', 'Low internal battery'],
  ['Powered off due to low battery', 'Powered off due to low battery'],
  ['Vibration', 'Vibration'],
  ['DRIVE_330', 'Drive reach 3:30 hr'],
  ['DRIVE_345', 'Drive reach 3:45 hr'],
  ['DRIVE_340', 'Drive reach 4:00 hr'],
].map(([value, label]) => ({ value, label }))

export const legacyReports: LegacyReportDefinition[] = [
  {
    key: 'speed-over-summary',
    path: '/reports/speed-over-summary',
    title: { th: 'รายงานสรุปความเร็วเกินกำหนด', en: 'Speed Over Summary' },
    subtitle: { th: 'สรุปเหตุการณ์ความเร็วเกินแยกตามรถ', en: 'Speed violation summary by vehicle' },
    maxRangeDays: 31,
    criteria: [{
      key: 'over_type',
      label: 'Over type',
      defaultValue: '',
      options: [
        { label: 'ทั้งหมด / All', value: '' },
        { label: 'System', value: 'system' },
        { label: 'Device', value: 'device' },
      ],
    }],
    columns: [
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'max_speed_date', label: 'Max speed date' },
      { field: 'max_speed_time', label: 'Max speed time' },
      { field: 'speed_limited', label: 'Speed limited', aliases: ['Speed_limited'], type: 'number' },
      { field: 'max_speed', label: 'Max speed', aliases: ['Max speed'], type: 'number' },
      { field: 'address', label: 'Address', aliases: ['Address'] },
      { field: 'total_events', label: 'Total events', type: 'number' },
      { field: 'total_days', label: 'Total days', type: 'number' },
    ],
  },
  {
    key: 'drive4h-summary',
    path: '/reports/drive4h-summary',
    title: { th: 'รายงานสรุปการขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Summary' },
    subtitle: { th: 'สรุปจำนวนและระยะเวลาการขับต่อเนื่องเกินกำหนด', en: 'Continuous driving violation summary' },
    maxRangeDays: 31,
    columns: [
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'count4h', label: 'Count 4h', type: 'number' },
      { field: 'max_duration_mm', label: 'Max duration (min)', type: 'number' },
    ],
  },
  {
    key: 'passenger-summary',
    path: '/reports/passenger-summary',
    title: { th: 'รายงานสรุปจำนวนผู้โดยสาร', en: 'Passenger Summary' },
    subtitle: { th: 'สรุปจำนวนผู้โดยสารขึ้น ลง และลืมลง', en: 'Passenger check-in and check-out summary' },
    maxRangeDays: 31,
    columns: [
      { field: 'checkin_date', label: 'Date' },
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'checkin_people', label: 'Check-in', type: 'number' },
      { field: 'checkout_people', label: 'Check-out', type: 'number' },
      { field: 'forget_checkout_people', label: 'Forgot check-out', type: 'number' },
      { field: 'runtime_hhmm', label: 'Runtime' },
    ],
  },
  {
    key: 'speed-over',
    path: '/reports/speed-over',
    title: { th: 'รายงานความเร็วเกินกำหนด', en: 'Speed Over Report' },
    subtitle: { th: 'รายละเอียดช่วงเวลาที่รถใช้ความเร็วเกินกำหนด', en: 'Detailed speed violation events' },
    maxRangeDays: 7,
    enableTime: true,
    criteria: [{
      key: 'over_type',
      label: 'Over type',
      defaultValue: '',
      options: [
        { label: 'ทั้งหมด / All', value: '' },
        { label: 'System', value: 'system' },
        { label: 'Device', value: 'device' },
      ],
    }],
    columns: [
      { field: 'imei', label: 'IMEI', aliases: ['IMEI'] },
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'over_type', label: 'Over type', aliases: ['Over-type'] },
      { field: 'event_time', label: 'Event time', aliases: ['event-time'], type: 'datetime' },
      { field: 'end_time', label: 'End time', aliases: ['end-time'], type: 'datetime' },
      { field: 'duration', label: 'Duration', aliases: ['Duration'] },
      { field: 'speed_limited', label: 'Speed limited', aliases: ['Speed limited'], type: 'number' },
      { field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number' },
      { field: 'lat_lon', label: 'Location', aliases: ['Lat/lon'], type: 'location' },
    ],
  },
  {
    key: 'event',
    path: '/reports/events',
    title: { th: 'รายงานเหตุการณ์สำคัญ', en: 'Important Event Report' },
    subtitle: { th: 'เหตุการณ์แจ้งเตือนสำคัญจากรถ', en: 'Important vehicle alert events' },
    maxRangeDays: 7,
    criteria: [{
      key: 'event_type',
      label: 'Event type',
      defaultValue: '',
      options: [{ label: 'ทั้งหมด / All', value: '' }, ...eventTypes],
    }],
    columns: [
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'event_type', label: 'Event type', aliases: ['Event type'] },
      { field: 'event_time', label: 'Event time', aliases: ['event time'], type: 'datetime' },
      { field: 'driver_id', label: 'Driver ID', aliases: ['Driver id'] },
      { field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number' },
      { field: 'address', label: 'Address / Map', aliases: ['Address'], type: 'location' },
    ],
  },
  {
    key: 'fuel',
    path: '/reports/fuel',
    title: { th: 'รายงานน้ำมัน/เชื้อเพลิง', en: 'Fuel Report' },
    subtitle: { th: 'สถานะรถ ความเร็ว และระดับเชื้อเพลิงตามเวลา', en: 'Vehicle status, speed and fuel timeline' },
    maxRangeDays: 3,
    enableTime: true,
    requireVehicle: true,
    graph: true,
    columns: [
      { field: 'data_date', label: 'Date/time', type: 'datetime' },
      { field: 'vehicle_status', label: 'Vehicle status', aliases: ['state', 'status'] },
      { field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number' },
      { field: 'fuel', label: 'Fuel', aliases: ['Fuel', 'fuel_left'], type: 'number' },
    ],
  },
  {
    key: 'swipe',
    path: '/reports/swipe',
    title: { th: 'รายงานการรูดบัตร', en: 'Card Swipe Report' },
    subtitle: { th: 'รายละเอียดข้อมูลการรูดบัตรผู้ขับขี่', en: 'Driver card swipe details' },
    maxRangeDays: 7,
    enableTime: true,
    criteria: [{
      key: 'swipe_type',
      label: 'Swipe type',
      defaultValue: '',
      options: [
        { label: 'ทั้งหมด / All', value: '' },
        { label: 'Check in', value: 'in' },
        { label: 'Check out', value: 'out' },
      ],
    }],
    columns: [
      { field: 'imei', label: 'IMEI', aliases: ['IMEI'] },
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'swipe_time', label: 'Swipe time', aliases: ['Swipe_time'], type: 'datetime' },
      { field: 'swipe_type', label: 'Swipe type' },
      { field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number' },
      { field: 'swipe_data', label: 'Swipe data' },
    ],
  },
  {
    key: 'drive4h',
    path: '/reports/drive4h',
    title: { th: 'รายงานขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Report' },
    subtitle: { th: 'รายละเอียดการขับรถต่อเนื่องเกินกำหนด', en: 'Continuous driving violation details' },
    maxRangeDays: 31,
    columns: [
      { field: 'imei', label: 'IMEI', aliases: ['IMEI'] },
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'driver_id', label: 'Driver ID' },
      { field: 'start_time', label: 'Start time', aliases: ['Start_time'], type: 'datetime' },
      { field: 'alert1_time', label: 'Alert 1', aliases: ['Alert1_time'], type: 'datetime' },
      { field: 'alert2_time', label: 'Alert 2', aliases: ['Alert2_time'], type: 'datetime' },
      { field: 'alert3_time', label: 'Alert 3', aliases: ['Alert3_time'], type: 'datetime' },
      { field: 'end_time', label: 'End time', type: 'datetime' },
      { field: 'duration_mm', label: 'Duration (min)', type: 'number' },
    ],
  },
  {
    key: 'passenger',
    path: '/reports/passenger',
    title: { th: 'รายงานผู้โดยสาร', en: 'Passenger Report' },
    subtitle: { th: 'รายละเอียดการขึ้นและลงของผู้โดยสาร', en: 'Passenger check-in and check-out details' },
    maxRangeDays: 31,
    columns: [
      { field: 'checkin_date', label: 'Date' },
      { field: 'plate_no', label: 'Plate no' },
      { field: 'checkin_time', label: 'Check-in time', type: 'datetime' },
      { field: 'checkout_time', label: 'Check-out time', type: 'datetime' },
      { field: 'uid', label: 'UID' },
      { field: 'full_name', label: 'Full name', aliases: ['Full_name'] },
      { field: 'custom1', label: 'Custom 1' },
      { field: 'custom2', label: 'Custom 2' },
      { field: 'custom3', label: 'Custom 3', aliases: ['Custom3'] },
      { field: 'checkin_location', label: 'Check-in location', aliases: ['Checkin_location'], type: 'location' },
      { field: 'checkout_location', label: 'Check-out location', aliases: ['Checkout_location'], type: 'location' },
      { field: 'duration_hhmm', label: 'Duration', aliases: ['Duration_hhmm'] },
    ],
  },
  {
    key: 'forbidden-inside',
    path: '/reports/forbidden-inside',
    title: { th: 'รายงานการเข้าพื้นที่ห้ามเข้า', en: 'Forbidden Area Entry Report' },
    subtitle: { th: 'รายละเอียดรถที่ตรวจพบภายในพื้นที่ห้ามเข้า', en: 'Vehicles detected inside forbidden areas' },
    maxRangeDays: 31,
    columns: [
      { field: 'imei', label: 'IMEI', aliases: ['IMEI'] },
      { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
      { field: 'zone_name', label: 'Zone name' },
      { field: 'gps_time', label: 'GPS time', aliases: ['Gps_time'], type: 'datetime' },
      { field: 'lat', label: 'Latitude', aliases: ['Lat'] },
      { field: 'lon', label: 'Longitude' },
      { field: 'address', label: 'Address / Map', aliases: ['Address'], type: 'location' },
    ],
  },
]

export const legacyReportMap = Object.fromEntries(
  legacyReports.map((report) => [report.key, report])
) as Record<LegacyReportKey, LegacyReportDefinition>
