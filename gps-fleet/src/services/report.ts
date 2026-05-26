import api from '@/services/api'

export interface ReportGroupOption {
    group_id: number
    group_name: string
}

export interface ReportVehicleOption {
    imei: string
    label: string
    group_id?: number
}

export async function getReportGroups() {
    const res = await api.get('/reports/options/groups')
    return res.data.data as ReportGroupOption[]
}

export async function getReportVehicles(params?: {
    group_ids?: number[]
}) {
    const res = await api.get('/reports/options/vehicles', {
        params,
    })

    return res.data.data as ReportVehicleOption[]
}

export interface DailySummaryRow {
    imei: string
    data_date: string
    run_time_s: number
    idle_time_s: number
    park_time_s: number
    distance_m: number
    updated_at: string
}

export interface DailySummaryResponse {
    success: boolean
    summary: {
        total_rows: number
        total_vehicle: number
        run_time_s: number
        idle_time_s: number
        park_time_s: number
        distance_m: number
    }
    data: DailySummaryRow[]
    pagination: {
        page: number
        per_page: number
        total: number
    }
}

export async function getDailySummary(params: {
    date_from: string
    date_to: string
    group_ids?: number[]
    imeis?: string[]
    page?: number
    per_page?: number
}) {
    const res = await api.get<DailySummaryResponse>('/reports/daily-summary', {
        params,
    })

    return res.data
}


export interface StatusSummaryRow {
    id: number
    imei: string
    data_date: string
    gps_status: string
    start_time: string
    end_time: string
    duration_s: number
    updated_at: string
}

export interface StationSummaryRow {
    id: number
    imei: string
    data_date: string
    station_id: number
    station_name: string | null
    start_time: string
    end_time: string
    duration_s: number
    updated_at: string
}

export async function getStatusSummary(params: {
    date_from: string
    date_to: string
    imei?: string
    status?: string
    page?: number
    per_page?: number
}) {
    const res = await api.get('/reports/status-summary', { params })
    return res.data
}

export async function getStationSummary(params: {
    date_from: string
    date_to: string
    imei?: string
    station_id?: string
    page?: number
    per_page?: number
}) {
    const res = await api.get('/reports/station-summary', { params })
    return res.data
}

