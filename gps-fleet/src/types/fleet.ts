export type VehicleStatus =
    | 'running'
    | 'idle'
    | 'parking'
    | 'offline'
    | 'no_gps'

export type Vehicle = {
    id?: string | number
    vehicle_id: string
    plate_no: string
    status: VehicleStatus
    lat: number | null
    lng: number | null
    speed: number
    location?: string
    heading?: number | null
    gps_time?: string | null
    last_seen_at?: string | null
    fuel?: number | string | null
    icon?: string
}

