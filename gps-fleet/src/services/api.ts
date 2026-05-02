import axios from 'axios'
import router from '@/router'

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

        // ❌ อย่าจับ login
        if (status === 401 && !isRedirecting && !url.includes('/auth/login')) {
            isRedirecting = true

            localStorage.removeItem('gps_fleet_token')

            window.location.href = '/login'
        }

        return Promise.reject(err)
    }
)

export default api