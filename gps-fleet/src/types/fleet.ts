export type VehicleStatus =
    | 'running'
    | 'idle'
    | 'acc_on'
    | 'parking'
    | 'no_gps'
    | 'offline'

export type Vehicle = {
    id?: string | number
    sequen_no?: number
    imei?: string
    vehicle_id: string
    plate_no: string
    acc_state: boolean
    status: VehicleStatus
    lat: number | null
    lng: number | null
    speed: number
    location?: string
    heading?: number | null
    gps_time?: string | null
    received_time?: string | null
    fuel_left?: number | string | null
    icon?: string
    dlt_synch: boolean
    driver_name?: string | null
    driver_phone?: string | null
    track1?: string
    track3?: string
    address?: string
}

