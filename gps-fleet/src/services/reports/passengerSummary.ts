import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getPassengerSummary(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/passenger-summary', { params })).data
}
