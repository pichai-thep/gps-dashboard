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
    dlt_synch?: number | null
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

export type EngineCutCommand = 'engine-cut' | 'engine-cut-cancel'

export type EngineCutPayload = {
    imei: string
    pwd: string
}

export type EngineCutResponse = {
    code: number
    message: string
    ref_id?: string | number | null
}

const engineCutEndpoints: Record<EngineCutCommand, string> = {
    'engine-cut': '/engine-cut',
    'engine-cut-cancel': '/engine-cut-cancel',
}

export async function sendEngineCutCommand(
    command: EngineCutCommand,
    payload: EngineCutPayload,
): Promise<EngineCutResponse> {
    const res = await api.post<EngineCutResponse>(
        engineCutEndpoints[command],
        payload,
    )

    return res.data
}

export type StatusCount = {
    run: number
    idle: number
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
        dlt_synch_count?: number
    }
}
