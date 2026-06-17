import api from './api'

export type Tracker = {
    id: number | string
    imei: string
    serial?: string | null
    name?: string | null
    model?: string | null
    sim_number?: string | null
    customer_id?: number | string
    customer_name?: string | null
    vehicle_id?: number | string
    vehicle_name?: string | null
    is_active?: boolean
    last_seen_at?: string | null
    created_at?: string
    updated_at?: string
}

export type TrackerForm = {
    imei: string
    serial?: string | null
    name?: string | null
    model?: string | null
    sim_number?: string | null
    customer_id?: number | string
    is_active?: boolean
}

type ListResponse = {
    data: Tracker[]
    total?: number
    per_page?: number
    current_page?: number
    last_page?: number
}

export const trackersService = {
    list(params?: Record<string, unknown>) {
        return api.get<ListResponse>('/admin/trackers', { params })
    },

    get(id: number | string) {
        return api.get<{ data: Tracker }>(`/admin/trackers/${id}`)
    },

    create(payload: TrackerForm) {
        return api.post<{ data: Tracker }>('/admin/trackers', payload)
    },

    update(id: number | string, payload: Partial<TrackerForm>) {
        return api.put<{ data: Tracker }>(`/admin/trackers/${id}`, payload)
    },

    delete(id: number | string) {
        return api.delete(`/admin/trackers/${id}`)
    },
}
