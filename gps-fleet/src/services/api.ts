import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const auth = useAuthStore()
    const token = auth.token || localStorage.getItem('gps_fleet_token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    const customerId =
        auth.customer?.id ??
        auth.user?.customer_id ??
        auth.config?.customer_id ??
        localStorage.getItem('gps_fleet_customer_id')

    const url = String(config.url || '')

    if (customerId && !url.startsWith('/auth/')) {
        config.params = {
            customer_id: customerId,
            ...(config.params || {}),
        }
    }

    return config
})

let isRedirecting = false

api.interceptors.response.use(
    (res) => res,
    (err) => {
        const status = err.response?.status
        const url = err.config?.url || ''

        if (status === 401 && !isRedirecting && !url.includes('/auth/login')) {
            isRedirecting = true

            useAuthStore().clearAuth()

            const redirect = window.location.pathname + window.location.search
            const loginUrl =
                redirect && redirect !== '/'
                    ? `/login?redirect=${encodeURIComponent(redirect)}`
                    : '/login'

            window.location.href = loginUrl
        }

        return Promise.reject(err)
    }
)

export default api
