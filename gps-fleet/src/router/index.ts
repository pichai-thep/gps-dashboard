import {
    createRouter,
    createWebHistory,
} from 'vue-router'

import { useAuthStore } from '../stores/auth'

import LoginView from '@/views/LoginView.vue'

import DashboardLayout from '@/layouts/DashboardLayout.vue'

import DashboardView from '@/views/DashboardView.vue'

import TrackingView from '@/views/TrackingView.vue'

import HistoryView from '@/views/HistoryView.vue'

import NotificationsView from '@/views/NotificationsView.vue'
import StationManagementView from '@/views/StationManagementView.vue'
import VehicleManagementView from "@/views/VehicleManagementView.vue";



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
                    component:
                    DashboardView,
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
                    path: '/vehicles',
                    name: 'vehicles',
                    component: VehicleManagementView,
                    meta: {
                        requiresAuth: true,
                        title: 'Vehicle Management',
                    },
                },
                {
                    path: '/stations',
                    name: 'stations',
                    component: StationManagementView,
                    meta: { requiresAuth: true },
                },
                {
                    path: '/pois',
                    name: 'pois',
                    component: () => import('@/views/PoiManagementView.vue'),

                    meta: { requiresAuth: true },
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

/**
 * --------------------------------------------------
 * ROUTE GUARD
 * --------------------------------------------------
 */
router.beforeEach(async (to) => {
    const auth = useAuthStore()

    /**
     * restore auth
     * ตอน refresh page
     */
    if (
        auth.token &&
        !auth.isReady
    ) {
        await auth.init()
    }

    /**
     * auth required
     */
    if (
        to.meta.requiresAuth &&
        !auth.token
    ) {
        return {
            path: '/login',

            query: {
                redirect: to.fullPath,
            },
        }
    }

    /**
     * already login
     */
    if (
        to.meta.guestOnly &&
        auth.token
    ) {
        return {
            path: '/tracking',
        }
    }

    return true
})

export default router