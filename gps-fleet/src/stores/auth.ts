import { defineStore } from 'pinia'
import api from '@/services/api'

export type AuthUser = {
    id: number | string
    gps_user_id?: number | string
    name?: string
    username?: string
    email?: string | null
    server_name?: string
    gps_connection?: string
    db_host?: string
    db_port?: string
    role?: string
    roles?: string[]
}

export type Customer = {
    id: number | string
    name: string
    map_api?: string | null
}

export type FeatureFlags = {
    station?: boolean
    poi?: boolean
    zone?: boolean
    overSpeedReport?: boolean
    summaryReport?: boolean
    canbus?: boolean
    engineCut?: boolean
    fuel?: boolean
    battery?: boolean
    passenger?: boolean
    geocoding?: boolean
    attendance?: boolean
    fare?: boolean
    temperature?: boolean
}

export type AppConfig = {
    fuelUnit?: string | null
    mapApi?: string | null
    mapApi_key?: string | null
    showInfoWindow?: boolean
}

type LoginResponse = {
    token: string
    user: AuthUser
}

type MeResponse = {
    user: AuthUser
    customer: Customer
    customers?: Customer[]
    features: FeatureFlags
    config?: AppConfig
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as AuthUser | null,
        customer: null as Customer | null,
        customers: [] as Customer[],
        features: {} as FeatureFlags,
        config: {} as AppConfig,
        token: localStorage.getItem('gps_fleet_token') as string | null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        customerName: (state) => state.customer?.name || '',
        hasFeature: (state) => {
            return (feature: keyof FeatureFlags) => Boolean(state.features[feature])
        },
    },

    actions: {
        async login(username: string, password: string) {
            const res = await api.post<LoginResponse>('/auth/login', {
                username,
                password,
            })

            this.token = res.data.token
            this.user = res.data.user

            localStorage.setItem('gps_fleet_token', res.data.token)

            await this.fetchMe()
        },

        async fetchMe() {
            const res = await api.get<MeResponse>('/auth/me')

            this.user = res.data.user
            this.customer = res.data.customer
            this.customers = res.data.customers || []
            this.features = res.data.features || {}
            this.config = res.data.config || {}
        },

        async logout() {
            try {
                await api.post('/auth/logout')
            } finally {
                this.user = null
                this.customer = null
                this.customers = []
                this.features = {}
                this.config = {}
                this.token = null
                localStorage.removeItem('gps_fleet_token')
            }
        },
    },
})