import api from './api'

export interface HistoryTrackingParams {
    imei: string
    start_date: string
    end_date: string
    start_time: string
    end_time: string
    page?: number
    per_page?: number
}

export interface HistoryResponse {
    success: boolean
    summary: any
    pagination: {
        total_rows: number
        page: number
        per_page: number
        total_pages: number
        offset_rows: number
    }
    data: HistoryPoint[]
}

export interface HistoryPoint {
    gpsdata_id?: number
    imei?: string
    gps_time?: string
    server_time?: string
    lat?: number
    lng?: number
    latitude?: number
    longitude?: number
    speed?: number
    course?: number
    heading?: number
    status?: string | number
    acc_state?: boolean | number
    state?: string | number
    gps_status?: string
    num_sats?: number
    fuel_left?: number
    temperature?: string
    fuel_per?: number
    address?: string
    track3?: string
}

export async function getHistoryTracking(params: HistoryTrackingParams): Promise<HistoryResponse> {
    const response = await api.get('/tracking/history', { params })
    return response.data
}

export async function exportHistoryTracking(
    params: HistoryTrackingParams
) {
    const response = await api.get(
        '/tracking/history/export',
        {
            params,
            responseType: 'blob',
        }
    )

    return response.data
}