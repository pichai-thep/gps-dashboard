import api from '@/services/api'

export interface ReportGroupOption {
    group_id: number
    group_name: string
}

export interface ReportVehicleOption {
    imei: string
    plate_no: string
    group_id?: number
}

export interface ReportStationOption {
    station_id: number
    station_name: string
}

export async function getReportStations() {
    const res = await api.get('/reports/options/stations')
    return res.data.data as ReportStationOption[]
}

export interface ReportPagination {
    current_page: number
    per_page: number
    total_rows: number
    total_pages: number
    offset?: number
}

export async function getReportGroups() {
    const res = await api.get('/reports/options/groups')
    return res.data.data as ReportGroupOption[]
}

export async function getReportVehicles(params?: { group_ids?: number[] }) {
    const res = await api.get('/reports/options/vehicles', { params })
    return res.data.data as ReportVehicleOption[]
}

export interface DailySummaryRow {
    imei: string
    plate_no: string
    data_date: string
    run_time_s: number
    idle_time_s: number
    park_time_s: number
    distance_m: number
    ur_formula?: string
    ur_rate?: number | null
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
        ur_rate_avg?: number
    }
    pagination: ReportPagination
    data: DailySummaryRow[]
}

export async function getDailySummary(params: {
    date_from: string
    date_to: string
    group_ids?: number[]
    imeis?: string[]
    page?: number
    per_page?: number
    export?: boolean
}) {
    const res = await api.get<DailySummaryResponse>('/reports/daily-summary', {
        params,
    })

    return res.data
}

export interface StatusSummaryRow {
    id: number
    imei: string
    plate_no: string
    data_date: string
    gps_status: string
    start_time: string
    end_time: string
    duration_s: number
    updated_at: string
}

export interface StatusSummaryResponse {
    success: boolean
    summary: {
        total_rows: number
        total_vehicle: number
        duration_s: number
    }
    pagination: ReportPagination
    data: StatusSummaryRow[]
}

export async function getStatusSummary(params: {
    date_from: string
    date_to: string
    group_ids?: number[]
    imeis?: string[]
    status?: string
    page?: number
    per_page?: number
    export?: boolean
}) {
    const res = await api.get<StatusSummaryResponse>('/reports/status-summary', {
        params,
    })

    return res.data
}

export interface StationSummaryRow {
    id: number
    imei: string
    plate_no: string
    data_date: string
    station_id: number
    start_time: string
    end_time: string
    duration_s: number
    updated_at: string
}

export interface StationSummaryResponse {
    success: boolean
    summary: {
        total_rows: number
        total_vehicle: number
        total_station: number
        duration_s: number
    }
    pagination: ReportPagination
    data: StationSummaryRow[]
}

export async function getStationSummary(params: {
    date_from: string
    date_to: string
    station_id?: number | null
    group_ids?: number[]
    imeis?: string[]
    page?: number
    per_page?: number
    export?: boolean
}) {
    const res = await api.get<StationSummaryResponse>('/reports/station-summary', {
        params,
    })

    return res.data
}