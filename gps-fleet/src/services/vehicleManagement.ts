import api from '@/services/api'

export interface VehicleListItem {
    imei: string
    plate_no?: string
    sequen_no?: number | null
    group_names?: string | null
}

export interface VehicleDetail {
    imei: string
    plate_no?: string
    sequen_no?: number | null

    driver_id?: string | null
    driver_name?: string | null
    driver_phone?: string | null

    speed_limited?: number | null
    icon_path?: string | null

    fuel_min_vol?: number | null
    fuel_max_vol?: number | null
    input_fuel_reverse?: boolean | number | null

    fuel_kmpl?: number | null
    fuel_lph?: number | null
    fuel_tank_size?: number | null
    fuel_price?: number | null

    fuel_mont?: boolean | number | null
    remark?: string | null

    current_mileage?: number | null

    ur_rate_type: 'A' | 'B' | null
    ur_rate_satsun: boolean | number | null
    ur_rate_work_hour: number | null

    export_to_active: boolean | number |null
}

export interface VehicleGroup {
    customer_group_id: number
    customer_group_name: string
}

export async function getVehicles(params: {
    keyword?: string
    group_id?: number | null
}) {
    const res = await api.get('/vehicles', { params })
    return res.data.data as VehicleListItem[]
}

export async function getVehicle(imei: string) {
    const res = await api.get(`/vehicles/${imei}`)
    return res.data.data as VehicleDetail
}

// export async function updateVehicle(imei: string, payload: Partial<VehicleDetail>) {
//     return api.put(`/vehicles/${imei}`, payload)
// }
export async function updateVehicle(imei: string, payload: Partial<VehicleDetail>,) {
    const response = await api.put(
        `/vehicles/${imei}`,
        payload,
    )

    return response.data
}

export async function updateMileage(imei: string, current_mileage: number) {
    return api.put(`/vehicles/${imei}/mileage`, {
        current_mileage,
    })
}

export async function updateUrRate(imei: string, ur_rate_type: 'A' | 'B') {
    return api.put(`/vehicles/${imei}/ur-rate`, {
        ur_rate_type,
    })
}

export async function getVehicleGroups() {
    const res = await api.get('/vehicle-groups')
    return res.data.data as VehicleGroup[]
}

export async function createVehicleGroup(customer_group_name: string) {
    return api.post('/vehicle-groups', {
        customer_group_name,
    })
}

export async function deleteVehicleGroup(id: number) {
    return api.delete(`/vehicle-groups/${id}`)
}

export async function moveVehiclesToGroup(imeis: string[], customer_group_id: number) {
    return api.post('/vehicle-groups/move', {
        imeis,
        customer_group_id,
    })
}

export async function removeVehiclesFromGroup(groupId: number, imeis: string[],) {
    await api.post('/vehicle-groups/remove-vehicles', {
        group_id: groupId,
        imeis,
    })
}