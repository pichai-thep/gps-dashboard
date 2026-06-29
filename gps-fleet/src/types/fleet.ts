export type VehicleStatus =
    | 'run'
    | 'idle'
    | 'park'
    | 'no_gps'
    | 'offline'

export type Vehicle = {
    id?: string | number
    sequen_no?: number
    imei?: string
    vehicle_id: string
    plate_no: string
    acc_state: boolean
    input1?: string | number | boolean | null
    input2?: string | number | boolean | null
    status: VehicleStatus
    lat: number | null
    lng: number | null
    speed: number
    location?: string
    heading?: number | null
    gps_time?: string | null
    received_time?: string | null

    icon?: string
    dlt_synch: boolean
    driver_name?: string | null
    driver_phone?: string | null

    track1?: string
    track3?: string

    fuel_left?: number | string | null
    temperature?: string

    address?: string
    num_sats?: number | string | null
}
