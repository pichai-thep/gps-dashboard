import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import LoginView from '@/views/LoginView.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DashboardView from '@/views/DashboardView.vue'

const router = createRouter({
    history: createWebHistory(),

    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { guestOnly: true },
        },

        {
            path: '/',
            component: AdminLayout,
            meta: { requiresAuth: true },

            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: DashboardView,
                    meta: { requiresAuth: true, title: 'Dashboard' },
                },

                // User Management
                {
                    path: 'users',
                    name: 'users',
                    component: () => import('@/views/users/UserManagementView.vue'),
                    meta: { requiresAuth: true, title: 'User Management' },
                },

                // Customer Management
                {
                    path: 'customers',
                    name: 'customers',
                    component: () => import('@/views/customers/CustomerManagementView.vue'),
                    meta: { requiresAuth: true, title: 'Customer Management' },
                },

                // Tracker Management
                {
                    path: 'trackers',
                    name: 'trackers',
                    component: () => import('@/views/trackers/TrackerManagementView.vue'),
                    meta: { requiresAuth: true, title: 'Tracker Management' },
                },
            ],
        },
    ],
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()

    if (auth.token && !auth.isReady) {
        await auth.init()
    }

    if (to.meta.requiresAuth && !auth.token) {
        return { path: '/login', query: { redirect: to.fullPath } }
    }

    if (to.meta.guestOnly && auth.token) {
        return { path: '/' }
    }

    return true
})

export default router
