import api from '@/services/api'
import type { Vehicle } from '@/types/fleet'

export type VehicleGroup = {
    id: number | string
    name: string
}

// export async function getVehicleGroups(): Promise<VehicleGroup[]> {
//     const res = await api.get<{ groups: VehicleGroup[] }>('/tracking/groups')
//     return res.data.groups || []
// }

export async function getVehicleGroups(customerId: number | string): Promise<VehicleGroup[]> {
    const res = await api.get<{ groups: VehicleGroup[] }>('/tracking/groups', {
        params: {
            customer_id: customerId,
        },
    })

    return res.data.groups || []
}

export async function getCurrentTracking(
    groupId: number | string = -1
): Promise<Vehicle[]> {
    const res = await api.get<{ vehicles: Vehicle[] }>('/tracking/current', {
        params: {
            group_id: groupId,
        },
    })

    return res.data.vehicles || []
}