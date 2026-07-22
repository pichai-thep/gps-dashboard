import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getForbiddenInsideReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/forbidden-inside', { params })).data
}
