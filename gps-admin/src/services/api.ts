import axios from 'axios'

const TOKEN_KEY = 'gps_admin_token'

let isRedirecting = false

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY)

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

api.interceptors.response.use(
    (res) => res,
    (err) => {
        const status = err.response?.status
        const url = err.config?.url || ''

        if (status === 401 && !isRedirecting && !url.includes('/auth/login')) {
            isRedirecting = true

            localStorage.removeItem(TOKEN_KEY)

            window.location.href = '/login'
        }

        return Promise.reject(err)
    }
)

export default api
