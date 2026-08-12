import api from '@/services/api'
import type { StoredProcedureReportParams, StoredProcedureReportResponse } from '@/views/reports/reportTypes'

export async function getTemperatureReport(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/temperature', { params })).data
}

export async function getTemperatureChart(params: StoredProcedureReportParams) {
  return (await api.get<StoredProcedureReportResponse>('/reports/temperature/chart', { params })).data
}
