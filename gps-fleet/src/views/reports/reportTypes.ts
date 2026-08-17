export interface ReportColumn {
  field: string
  label: string
  aliases?: string[]
  type?: 'text' | 'number' | 'datetime' | 'location'
}

export interface ReportCriterion {
  key: string
  label: string | { th: string; en: string }
  type?: 'select' | 'number'
  options?: Array<{ label: string; value: string | number }>
  defaultValue: string | number | null
  min?: number
  maxFractionDigits?: number
  suffix?: string
}

export interface ReportDefinition {
  key: string
  title: { th: string; en: string }
  subtitle: { th: string; en: string }
  maxRangeDays: number
  monthly?: boolean
  enableTimeStart?: boolean
  enableTimeEnd?: boolean
  timeStartRequired?: boolean
  timeEndRequired?: boolean
  enableExportCsv?: boolean
  exportFormat?: 'csv' | 'excel'
  enablePdf?: boolean
  vehicleRequired?: boolean
  graph?: boolean | 'fuel' | 'speed' | 'temperature'
  serverPagination?: boolean
  dailyDistanceLimitCriterionKey?: string
  criteria?: ReportCriterion[]
  columns: ReportColumn[]
}

export interface StoredProcedureReportParams {
  date_from: string
  date_to: string
  time_from?: string
  time_to?: string
  group_id?: number | null
  imei?: string | null
  criteria?: Record<string, string | number | null>
  offset?: number
  size?: number
  sensor_no?: number
}

export interface StoredProcedureReportResponse {
  success: boolean
  report: string
  data: Record<string, unknown>[]
  summary?: Record<string, unknown>
  config?: {
    sensor_a?: TemperatureSensorConfig
    sensor_b?: TemperatureSensorConfig
  }
  pagination?: {
    current_page: number
    per_page: number
    offset: number
    total_rows: number
    total_pages: number
  }
  meta: { total_rows: number; max_range_days: number; offset?: number; size?: number }
}

export interface TemperatureSensorConfig {
  min: number | null
  max: number | null
  average: number | null
}

export type ReportLoader = (
  params: StoredProcedureReportParams,
) => Promise<StoredProcedureReportResponse>

export type ReportChartLoader = ReportLoader
