import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getEventReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/events', { params })).data
}
