import { createRouter, createWebHistory } from 'vue-router'

import LoginView from '@/views/LoginView.vue'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import DashboardView from '../views/DashboardView.vue'
import TrackingView from '../views/TrackingView.vue'
import HistoryView from '../views/HistoryView.vue'
import NotificationsView from '../views/NotificationsView.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: LoginView },
        {
            path: '/',
            component: DashboardLayout,
            children: [
                { path: '', component: DashboardView },
                { path: 'tracking', component: TrackingView },
                { path: 'history', component: HistoryView },
                { path: 'notifications', component: NotificationsView },
            ],
        },
    ],
})

export default router