import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getStatusDetail(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/status-detail', { params })).data
}
