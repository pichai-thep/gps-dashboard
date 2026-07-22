import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getSpeedOverSummary(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/speed-over-summary', { params })).data
}
