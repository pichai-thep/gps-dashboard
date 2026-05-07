import api from './api'

export interface HistoryTrackingParams {
    imei: string
    start_date: string
    end_date: string
    start_time: string
    end_time: string
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

    fuel?: number
    fuel_per?: number
}

export async function getHistoryTracking(
    params: HistoryTrackingParams
) {
    const response = await api.get(
        '/tracking/history',
        {
            params,
        }
    )

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