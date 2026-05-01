import api from './api'
import type { Vehicle } from '../types/fleet'

type CurrentTrackingResponse = {
    data?: Vehicle[]
    vehicles?: Vehicle[]
}

export async function getCurrentTracking(): Promise<Vehicle[]> {
    console.log('CALL API /tracking/current')

    const res = await api.get<CurrentTrackingResponse>('/tracking/current')

    console.log('TRACKING API RESPONSE', res.data)

    if (Array.isArray(res.data.data)) {
        return res.data.data
    }

    if (Array.isArray(res.data.vehicles)) {
        return res.data.vehicles
    }

    return []
}