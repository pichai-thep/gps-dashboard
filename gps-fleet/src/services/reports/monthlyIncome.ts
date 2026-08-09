import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getMonthlyIncome(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/monthly-income', { params })).data
}
