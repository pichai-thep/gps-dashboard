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
import { legacyReports } from '@/views/reports/legacyReportDefinitions'



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
                    meta: {
                        requiresAuth: true,
                        title: 'Fleet Dashboard',
                    },
                },

                {
                    path: 'tracking',
                    name: 'tracking',
                    component: TrackingView,
                    meta: {
                        requiresAuth: true,
                        title: 'Current Tracking',
                    },
                },
                {
                    path: 'history',
                    name: 'history',
                    component: HistoryView,
                    meta: {
                        requiresAuth: true,
                        title: 'History Query',
                    },
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
                    meta: {
                        requiresAuth: true,
                        title: 'Stations',
                    },
                },
                {
                    path: '/pois',
                    name: 'pois',
                    component: () => import('@/views/PoiManagementView.vue'),

                    meta: {
                        requiresAuth: true,
                        title: 'Pois',
                    },
                },
                {
                    path: '/forbidden-zones',
                    name: 'forbidden-zones',
                    component: () => import('@/views/ForbiddenZoneManagementView.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Forbidden Zone',
                    },
                },

                {
                    path: 'notifications',
                    name: 'notifications',
                    component: NotificationsView,
                },

                {
                    path: '/reports/daily-summary',
                    name: 'DailySummary',
                    component: () => import('@/views/reports/DailySummaryView.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Daily Summary Report',
                    },
                },

                {
                    path: '/reports/status-summary',
                    name: 'StatusSummary',
                    component: () => import('@/views/reports/StatusSummaryView.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Status Timeline Report',
                    },
                },
                {
                    path: '/reports/station-summary',
                    name: 'StationSummary',
                    component: () => import('@/views/reports/StationSummaryView.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Station Visit Report',
                    },
                },
                ...legacyReports.map((report) => ({
                    path: report.path,
                    name: `LegacyReport-${report.key}`,
                    component: () => import('@/views/reports/LegacyReportView.vue'),
                    meta: {
                        requiresAuth: true,
                        title: report.title.en,
                        reportKey: report.key,
                    },
                })),
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
