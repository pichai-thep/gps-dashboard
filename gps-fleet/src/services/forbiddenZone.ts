import api from '@/services/api'

export interface ForbiddenZone {
    id: number
    zone_name: string
    polygon_wkt?: string | null
    customer_id?: number
}

export interface ForbiddenZonePayload {
    zone_name: string
    polygon: Array<{
        lat: number
        lng: number
    }>
}

export async function getForbiddenZones(): Promise<ForbiddenZone[]> {
    const res = await api.get('/forbidden-zones')
    return res.data.data || []
}

export async function createForbiddenZone(payload: ForbiddenZonePayload) {
    const res = await api.post('/forbidden-zones', payload)
    return res.data
}

export async function updateForbiddenZone(id: number, payload: ForbiddenZonePayload) {
    const res = await api.put(`/forbidden-zones/${id}`, payload)
    return res.data
}

export async function deleteForbiddenZone(id: number) {
    const res = await api.delete(`/forbidden-zones/${id}`)
    return res.data
}