import api from './api'

export type User = {
    id: number | string
    name: string
    username: string
    email?: string | null
    role?: string
    roles?: string[]
    customer_id?: number | string
    customer_name?: string
    is_active?: boolean
    created_at?: string
    updated_at?: string
}

export type UserForm = {
    name: string
    username: string
    email?: string | null
    password?: string
    role?: string
    customer_id?: number | string
    is_active?: boolean
}

type ListResponse = {
    data: User[]
    total?: number
    per_page?: number
    current_page?: number
    last_page?: number
}

export const usersService = {
    list(params?: Record<string, unknown>) {
        return api.get<ListResponse>('/admin/users', { params })
    },

    get(id: number | string) {
        return api.get<{ data: User }>(`/admin/users/${id}`)
    },

    create(payload: UserForm) {
        return api.post<{ data: User }>('/admin/users', payload)
    },

    update(id: number | string, payload: Partial<UserForm>) {
        return api.put<{ data: User }>(`/admin/users/${id}`, payload)
    },

    delete(id: number | string) {
        return api.delete(`/admin/users/${id}`)
    },

    resetPassword(id: number | string, password: string) {
        return api.post(`/admin/users/${id}/reset-password`, { password })
    },
}
