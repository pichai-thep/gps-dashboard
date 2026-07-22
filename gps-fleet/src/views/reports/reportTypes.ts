export interface ReportColumn {
  field: string
  label: string
  aliases?: string[]
  type?: 'text' | 'number' | 'datetime' | 'location'
}

export interface ReportCriterion {
  key: string
  label: string
  options: Array<{ label: string; value: string | number }>
  defaultValue: string | number
}

export interface ReportDefinition {
  key: string
  title: { th: string; en: string }
  subtitle: { th: string; en: string }
  maxRangeDays: number
  enableTimeStart?: boolean
  enableTimeEnd?: boolean
  timeStartRequired?: boolean
  timeEndRequired?: boolean
  enableExportCsv?: boolean
  enablePdf?: boolean
  vehicleRequired?: boolean
  graph?: boolean
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
  criteria?: Record<string, string | number>
}

export interface StoredProcedureReportResponse {
  success: boolean
  report: string
  data: Record<string, unknown>[]
  meta: { total_rows: number; max_range_days: number }
}

export type ReportLoader = (
  params: StoredProcedureReportParams,
) => Promise<StoredProcedureReportResponse>
