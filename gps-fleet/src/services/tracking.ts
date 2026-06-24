import api from '@/services/api'
import type { Vehicle } from '@/types/fleet'

export type VehicleGroup = {
    id: number | string
    name: string
}

export type TrackingMeta = {
    page: number
    per_page: number
    total: number
    last_page: number
    has_next_page: boolean
    sort_by: string
    sort_dir: 'asc' | 'desc'
    status_counts: number
}

export type TrackingParams = {
    page: number
    per_page: number
    group_id?: number | string
    status?: string | null
    search?: string
    sort_by?: string
    sort_dir?: 'asc' | 'desc'
    no_driver_card: number | null
}

export async function getVehicleGroups(
    customerId: number | string
): Promise<VehicleGroup[]> {
    const res = await api.get<{ groups: VehicleGroup[] }>('/tracking/groups', {
        params: {
            customer_id: customerId,
        },
    })

    return res.data.groups || []
}

export async function getCurrentTracking(
    params: TrackingParams
): Promise<TrackingResponse> {
    const res = await api.get<TrackingResponse>('/tracking/current', {
        params,
    })

    return res.data
}

export type StatusCount = {
    run: number
    idle: number
    acc_on: number
    park: number
    no_gps: number
    offline: number
}

export type TrackingResponse = {
    vehicles: Vehicle[]

    meta: {
        total: number
        status_counts?: StatusCount | null
        no_driver_card_count?: number
    }
}
