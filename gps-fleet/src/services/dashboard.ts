// src/services/dashboard.ts
import api from '../services/api'

export interface DashboardSummary {
    total: number
    running: number
    idle: number
    parking: number
    offline: number
    no_gps: number
    updated_at: string
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
    const res = await api.get('/dashboard/summary')
    return res.data.data
}