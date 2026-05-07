import api from './api'
import { useAuthStore } from '@/stores/auth'

export interface VehicleItem {
    vehicle_id: number | string
    plate_no: string
    imei: string
    group_id?: number | string
}

export async function getVehiclesByGroup(groupId: number | string) {
    const response = await api.get('/tracking/current', {
        params: {
            group_id: groupId,
            limit: 1000,
        },
    })

    return response.data
}

export async function getVehicles(groupId: string | number | null = null) {
    const auth = useAuthStore()

    const customerId =
        auth.customer?.id ??
        auth.user?.customer_id ??
        auth.config?.customer_id ??
        localStorage.getItem('gps_fleet_customer_id')

    const response = await api.get('/tracking/current', {
        params: {
            customer_id: customerId,
            group_id: groupId || -1,
            limit: 1000,
            per_page: 1000,
        },
    })

    return response.data
}