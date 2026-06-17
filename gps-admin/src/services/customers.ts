import api from './api'

export type Customer = {
    id: number | string
    name: string
    email?: string | null
    phone?: string | null
    address?: string | null
    map_api?: string | null
    map_api_key?: string | null
    is_active?: boolean
    user_count?: number
    tracker_count?: number
    created_at?: string
    updated_at?: string
}

export type CustomerForm = {
    name: string
    email?: string | null
    phone?: string | null
    address?: string | null
    map_api?: string | null
    map_api_key?: string | null
    is_active?: boolean
}

type ListResponse = {
    data: Customer[]
    total?: number
    per_page?: number
    current_page?: number
    last_page?: number
}

export const customersService = {
    list(params?: Record<string, unknown>) {
        return api.get<ListResponse>('/admin/customers', { params })
    },

    get(id: number | string) {
        return api.get<{ data: Customer }>(`/admin/customers/${id}`)
    },

    create(payload: CustomerForm) {
        return api.post<{ data: Customer }>('/admin/customers', payload)
    },

    update(id: number | string, payload: Partial<CustomerForm>) {
        return api.put<{ data: Customer }>(`/admin/customers/${id}`, payload)
    },

    delete(id: number | string) {
        return api.delete(`/admin/customers/${id}`)
    },
}
