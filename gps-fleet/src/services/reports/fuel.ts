import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getFuelReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/fuel', { params })).data
}
