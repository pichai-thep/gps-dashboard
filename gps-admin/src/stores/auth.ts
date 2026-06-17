import { defineStore } from 'pinia'
import api from '../services/api'

export type AdminUser = {
    id: number | string
    name?: string
    username?: string
    email?: string | null
    role?: string
    roles?: string[]
}

type LoginResponse = {
    token: string
    user: AdminUser
}

type MeResponse = {
    user: AdminUser
}

const TOKEN_KEY = 'gps_admin_token'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as AdminUser | null,
        token: localStorage.getItem(TOKEN_KEY) as string | null,
        isReady: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),

        userName: (state) =>
            state.user?.name || state.user?.username || 'Admin',

        userInitial: (state) => {
            const name = state.user?.name || state.user?.username || 'A'
            return name.charAt(0).toUpperCase()
        },

        roleName: (state) =>
            state.user?.roles?.join(', ') || state.user?.role || 'admin',
    },

    actions: {
        async init() {
            if (!this.token) {
                this.isReady = true
                return
            }

            try {
                await this.fetchMe()
            } catch {
                this.clearAuth()
            } finally {
                this.isReady = true
            }
        },

        async login(username: string, password: string) {
            const res = await api.post<LoginResponse>('/auth/login', {
                username,
                password,
            })

            this.token = res.data.token
            this.user = res.data.user

            localStorage.setItem(TOKEN_KEY, res.data.token)

            await this.fetchMe()

            this.isReady = true
        },

        async fetchMe() {
            const res = await api.get<MeResponse>('/auth/me')
            this.user = res.data.user
        },

        async logout() {
            try {
                await api.post('/auth/logout')
            } finally {
                this.clearAuth()
            }
        },

        clearAuth() {
            this.user = null
            this.token = null
            this.isReady = false
            localStorage.removeItem(TOKEN_KEY)
        },
    },
})
