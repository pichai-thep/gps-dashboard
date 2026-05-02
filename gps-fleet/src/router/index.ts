import { createRouter, createWebHistory } from 'vue-router'

import LoginView from '@/views/LoginView.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import TrackingView from '@/views/TrackingView.vue'
import HistoryView from '@/views/HistoryView.vue'
import NotificationsView from '@/views/NotificationsView.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: {
                guestOnly: true,
            },
        },
        {
            path: '/',
            component: DashboardLayout,
            meta: {
                requiresAuth: true,
            },
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: DashboardView,
                },
                {
                    path: 'tracking',
                    name: 'tracking',
                    component: TrackingView,
                },
                {
                    path: 'history',
                    name: 'history',
                    component: HistoryView,
                },
                {
                    path: 'notifications',
                    name: 'notifications',
                    component: NotificationsView,
                },
            ],
        },
    ],
})

router.beforeEach((to) => {
    const token = localStorage.getItem('gps_fleet_token')

    if (to.meta.requiresAuth && !token) {
        return {
            path: '/login',
            query: {
                redirect: to.fullPath,
            },
        }
    }

    if (to.meta.guestOnly && token) {
        return {
            path: '/tracking',
        }
    }

    return true
})

export default router