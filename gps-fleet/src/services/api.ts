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

api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401 && !isRedirectingToLogin) {
            isRedirectingToLogin = true

            localStorage.removeItem('gps_fleet_token')

            if (router.currentRoute.value.path !== '/login') {
                router.push('/login')
            }
        }

        return Promise.reject(err)
    }
)

export default api