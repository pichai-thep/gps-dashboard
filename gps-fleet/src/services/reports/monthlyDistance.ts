import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getMonthlyDistance(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/monthly-distance', { params })).data
}
