import api from '../plugins/axios'
import {useAuthStore} from "../stores/auth";

export type NotificationItem = {
    id: number
    imei: string
    plate: string
    msg_type: string
    message: string
    gps_time?: string
    created_at: string
}

export async function getRecentNotifications(): Promise<NotificationItem[]> {

    const res = await api.get('/api/notifications/recent')

    // console.log(`Notification Service res1:${JSON.stringify(res)}`);
    // console.log(`Notification Service res2:${JSON.stringify(res.data)}`);
    // console.log(`Notification Service res3:${JSON.stringify(res.data.data)}`);
    return res.data?.data ?? []
}

export async function getNotificationUnreadCount(): Promise<number> {
    const res = await api.get('/api/notifications/unread-count')
    // console.log(`getNotificationUnreadCount Service res:${JSON.stringify(res.data)}`);
    return res.data?.count ?? 0
}

export async function markNotificationsRead(): Promise<void> {
    await api.post('/api/notifications/mark-read')
}