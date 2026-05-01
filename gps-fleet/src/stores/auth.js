import { defineStore } from 'pinia'
import api from '../services/api'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('gps_fleet_token'),
    }),

    actions: {
        async login(username, password) {
            const res = await api.post('/auth/login', { username, password })

            this.token = res.data.token
            this.user = res.data.user

            localStorage.setItem('gps_fleet_token', this.token)
        },

        async fetchMe() {
            const res = await api.get('/auth/me')
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