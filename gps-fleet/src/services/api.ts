import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

let isRedirectingToLogin = false

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('gps_fleet_token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
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

            localStorage.removeItem('gps_fleet_token')

            window.location.href = '/login'
        }

        return Promise.reject(err)
    }
)




api.interceptors.request.use((config) => {

    const auth = useAuthStore()

    if (auth.token) {
        config.headers.Authorization =
            `Bearer ${auth.token}`
    }

    const customerId =
        auth.user?.customer_id

    if (customerId) {

        config.params = {
            ...(config.params || {}),
            customer_id: customerId,
        }

    }

    return config
})

export default api