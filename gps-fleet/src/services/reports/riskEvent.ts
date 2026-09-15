import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getRiskEventReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/risk-events', { params })).data
}
