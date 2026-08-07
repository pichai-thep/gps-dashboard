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
                    path: '/reports/summary',
                    name: 'SummaryReportHub',
                    component: () => import('@/views/reports/ReportHubView.vue'),
                    meta: { requiresAuth: true, title: 'Summary Reports', reportSection: 'summary' },
                },
                {
                    path: '/reports/general',
                    name: 'GeneralReportHub',
                    component: () => import('@/views/reports/ReportHubView.vue'),
                    meta: { requiresAuth: true, title: 'General Reports', reportSection: 'general' },
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
                { path: '/reports/speed-over-summary', name: 'SpeedOverSummary', component: () => import('@/views/reports/SpeedOverSummaryView.vue'), meta: { requiresAuth: true, title: 'Speed Over Summary' } },
                { path: '/reports/drive4h-summary', name: 'Drive4hSummary', component: () => import('@/views/reports/Drive4hSummaryView.vue'), meta: { requiresAuth: true, title: 'Drive Over 4 Hours Summary' } },
                { path: '/reports/passenger-summary', name: 'PassengerSummary', component: () => import('@/views/reports/PassengerSummaryView.vue'), meta: { requiresAuth: true, title: 'Passenger Summary' } },
                { path: '/reports/status-detail', name: 'StatusDetail', component: () => import('@/views/reports/StatusDetailView.vue'), meta: { requiresAuth: true, title: 'Vehicle Status Detail Report' } },
                { path: '/reports/speed-over', name: 'SpeedOver', component: () => import('@/views/reports/SpeedOverView.vue'), meta: { requiresAuth: true, title: 'Speed Over Report' } },
                { path: '/reports/speed', name: 'SpeedReport', component: () => import('@/views/reports/SpeedReportView.vue'), meta: { requiresAuth: true, title: 'Speed Report' } },
                { path: '/reports/events', name: 'EventReport', component: () => import('@/views/reports/EventReportView.vue'), meta: { requiresAuth: true, title: 'Important Event Report' } },
                { path: '/reports/fuel', name: 'FuelReport', component: () => import('@/views/reports/FuelReportView.vue'), meta: { requiresAuth: true, title: 'Fuel Report' } },
                { path: '/reports/swipe', name: 'SwipeReport', component: () => import('@/views/reports/SwipeReportView.vue'), meta: { requiresAuth: true, title: 'Card Swipe Report' } },
                { path: '/reports/drive4h', name: 'Drive4hReport', component: () => import('@/views/reports/Drive4hView.vue'), meta: { requiresAuth: true, title: 'Drive Over 4 Hours Report' } },
                { path: '/reports/passenger', name: 'PassengerReport', component: () => import('@/views/reports/PassengerReportView.vue'), meta: { requiresAuth: true, title: 'Passenger Report' } },
                { path: '/reports/forbidden-inside', name: 'ForbiddenInside', component: () => import('@/views/reports/ForbiddenInsideView.vue'), meta: { requiresAuth: true, title: 'Forbidden Area Entry Report' } },
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
