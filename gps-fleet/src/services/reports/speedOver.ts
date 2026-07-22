import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getSpeedOverReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/speed-over', { params })).data
}
