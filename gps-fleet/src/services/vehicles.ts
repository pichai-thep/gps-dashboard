import { getReportVehicles as fetchVehicleOptions } from './report'

export interface VehicleItem {
    vehicle_id: number | string
    plate_no: string
    imei: string
    group_id?: number | string
}

export const getReportVehicles = fetchVehicleOptions

export async function getVehiclesByGroup(groupId: number | string) {
    return getVehicles(groupId)
}

export async function getVehicles(groupId: string | number | null = null) {
    // The shared options endpoint returns every permitted vehicle without
    // Tracking's 100-row pagination cap or the cost of fetching live telemetry.
    const id = Number(groupId)
    const options = await fetchVehicleOptions({
        group_ids: id > 0 ? [id] : undefined,
    })

    return {
        vehicles: options.map((vehicle) => ({
            ...vehicle,
            vehicle_id: vehicle.imei,
        })),
    }
}
