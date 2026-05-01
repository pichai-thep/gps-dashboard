import { defineStore } from 'pinia'
import api from '../services/api'

export type AuthUser = {
    id: number | string
    name?: string
    username?: string
    email?: string
    role?: string
}

type LoginResponse = {
    token: string
    user: AuthUser
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as AuthUser | null,
        token: localStorage.getItem('gps_fleet_token') as string | null,
    }),

    actions: {
        async login(username: string, password: string) {
            const res = await api.post<LoginResponse>('/auth/login', {
                username,
                password,
            })

            this.token = res.data.token
            this.user = res.data.user

            localStorage.setItem('gps_fleet_token', res.data.token)
        },

        async fetchMe() {
            const res = await api.get<AuthUser>('/auth/me')
            this.user = res.data
        },

        async logout() {
            try {
                await api.post('/auth/logout')
            } finally {
                this.user = null
                this.token = null
                localStorage.removeItem('gps_fleet_token')
            }
        },
    },
})