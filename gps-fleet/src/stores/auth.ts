import { defineStore } from 'pinia'
import api from '../services/api'

/**
 * --------------------------------------------------
 * TYPES
 * --------------------------------------------------
 */
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

    customer_id?: number | string
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
    stationInOutSummaryReport?: boolean

    canbus?: boolean
    engineCut?: boolean
    fuel?: boolean
    battery?: boolean
    passenger?: boolean

    geocoding?: boolean
    attendance?: boolean
    fare?: boolean
    temperature?: boolean
    input1?: boolean
    input2?: boolean
}

export type AppConfig = {
    fuelUnit?: string | null

    mapApi?: string | null
    mapApi_key?: string | null

    showInfoWindow?: boolean

    customer_id?: number | string
}

/**
 * --------------------------------------------------
 * API RESPONSE
 * --------------------------------------------------
 */
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

/**
 * --------------------------------------------------
 * STORAGE KEYS
 * --------------------------------------------------
 */
const TOKEN_KEY = 'gps_fleet_token'

const CUSTOMER_ID_KEY =
    'gps_fleet_customer_id'

/**
 * --------------------------------------------------
 * STORE
 * --------------------------------------------------
 */
export const useAuthStore = defineStore(
    'auth',
    {
        /**
         * --------------------------------------------------
         * STATE
         * --------------------------------------------------
         */
        state: () => ({
            user: null as AuthUser | null,

            customer:
                null as Customer | null,

            customers:
                [] as Customer[],

            features:
                {} as FeatureFlags,

            config:
                {} as AppConfig,

            token:
                localStorage.getItem(
                    TOKEN_KEY
                ) as string | null,

            isReady: false,
        }),

        /**
         * --------------------------------------------------
         * GETTERS
         * --------------------------------------------------
         */
        getters: {
            isAuthenticated: (
                state
            ) => Boolean(state.token),

            customerName: (state) =>
                state.customer?.name || '',

            customerId: (state) => {
                return (
                    state.customer?.id ??
                    state.user?.customer_id ??
                    state.config
                        ?.customer_id ??
                    localStorage.getItem(
                        CUSTOMER_ID_KEY
                    ) ??
                    null
                )
            },

            hasFeature: (state) => {
                return (
                    feature:
                        keyof FeatureFlags
                ) => {
                    return Boolean(
                        state.features[
                            feature
                            ]
                    )
                }
            },
        },

        /**
         * --------------------------------------------------
         * ACTIONS
         * --------------------------------------------------
         */
        actions: {
            /**
             * INIT AUTH
             * ใช้ตอน refresh page
             */
            async init() {
                if (!this.token) {
                    this.isReady = true
                    return
                }

                try {
                    await this.fetchMe()
                } catch (error) {
                    console.error(
                        'auth init error',
                        error
                    )

                    this.clearAuth()
                } finally {
                    this.isReady = true
                }
            },

            /**
             * LOGIN
             */
            async login(
                username: string,
                password: string
            ) {
                const res =
                    await api.post<LoginResponse>(
                        '/auth/login',
                        {
                            username,
                            password,
                        }
                    )

                this.token =
                    res.data.token

                this.user =
                    res.data.user

                localStorage.setItem(
                    TOKEN_KEY,
                    res.data.token
                )

                /**
                 * fallback customer_id
                 */
                if (
                    res.data.user
                        ?.customer_id
                ) {
                    localStorage.setItem(
                        CUSTOMER_ID_KEY,
                        String(
                            res.data.user
                                .customer_id
                        )
                    )
                }

                await this.fetchMe()

                this.isReady = true
            },

            /**
             * FETCH PROFILE
             */
            async fetchMe() {
                const res =
                    await api.get<MeResponse>(
                        '/auth/me'
                    )

                this.user =
                    res.data.user

                this.customer =
                    res.data.customer

                this.customers =
                    res.data.customers ||
                    []

                this.features =
                    res.data.features ||
                    {}

                this.config =
                    res.data.config ||
                    {}

                /**
                 * save customer_id
                 */
                const customerId =
                    res.data.customer?.id ??
                    res.data.user
                        ?.customer_id ??
                    res.data.config
                        ?.customer_id

                if (customerId) {
                    localStorage.setItem(
                        CUSTOMER_ID_KEY,
                        String(customerId)
                    )
                }
            },

            /**
             * LOGOUT
             */
            async logout() {
                try {
                    await api.post(
                        '/auth/logout'
                    )
                } finally {
                    this.clearAuth()
                }
            },

            /**
             * CLEAR AUTH
             */
            clearAuth() {
                this.user = null

                this.customer = null

                this.customers = []

                this.features = {}

                this.config = {}

                this.token = null

                this.isReady = false

                localStorage.removeItem(
                    TOKEN_KEY
                )

                localStorage.removeItem(
                    CUSTOMER_ID_KEY
                )
            },
        },
    }
)
