import api from './api'

export type StationType = 'circle' | 'polygon'

export interface Station {
    station_id: number
    station_name: string
    station_type: StationType
    lat?: number | null
    lng?: number | null
    radius?: number | null
    polygon_wkt?: string | null
    created_at?: string | null
    modified_at?: string | null
}

export interface StationPayload {
    station_name: string
    station_type: StationType
    lat?: number | null
    lng?: number | null
    radius?: number | null
    polygon?: Array<{
        lat: number
        lng: number
    }>
}

export async function getStations() {
    const res = await api.get('/stations')
    return res.data.data as Station[]
}

export async function createStation(payload: StationPayload) {
    const res = await api.post('/stations', payload)
    return res.data
}

export async function updateStation(id: number, payload: StationPayload) {
    const res = await api.put(`/stations/${id}`, payload)
    return res.data
}

export async function deleteStation(id: number) {
    const res = await api.delete(`/stations/${id}`)
    return res.data
}
