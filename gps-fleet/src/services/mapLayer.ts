import api from '@/services/api'

export async function getCustomerPois() {
    const res = await api.get('/map-layers/pois')
    return res.data.data || []
}

export async function getCustomerStations() {
    const res = await api.get('/map-layers/stations')
    return res.data.data || []
}

export async function getCustomerForbiddenZones() {
    const res = await api.get('/map-layers/forbidden-zones')
    return res.data.data || []
}