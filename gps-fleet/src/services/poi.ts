import api from './api'

export interface Poi {
    poi_id: number
    poi_name: string
    icon?: string | null
    lat: number | null
    lng: number | null
}

export interface PoiPayload {
    poi_name: string
    icon?: string | null
    lat: number | null
    lng: number | null
}

export async function getPois() {
    const res = await api.get('/pois')
    return res.data.data as Poi[]
}

export async function createPoi(payload: PoiPayload) {
    const res = await api.post('/pois', payload)
    return res.data
}

export async function updatePoi(id: number, payload: PoiPayload) {
    const res = await api.put(`/pois/${id}`, payload)
    return res.data
}

export async function deletePoi(id: number) {
    const res = await api.delete(`/pois/${id}`)
    return res.data
}