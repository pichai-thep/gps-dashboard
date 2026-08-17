// import api from '../plugins/axios'
import {useAuthStore} from "../stores/auth";
import api from "../services/api";

export type NotificationItem = {
    id: number
    imei: string
    plate: string
    msg_type: string
    message: string
    gps_time?: string
    created_at: string
}

export type NotificationSnapshot = {
    items: NotificationItem[]
    unreadCount: number
}

export async function getNotificationSnapshot(): Promise<NotificationSnapshot> {
    const res = await api.get('/notifications/recent')

    return {
        items: res.data?.data ?? [],
        unreadCount: Number(res.data?.unread_count ?? 0),
    }
}

export async function getRecentNotifications(): Promise<NotificationItem[]> {

    const res = await api.get('/notifications/recent')

    // console.log(`Notification Service res1:${JSON.stringify(res)}`);
    // console.log(`Notification Service res2:${JSON.stringify(res.data)}`);
    // console.log(`Notification Service res3:${JSON.stringify(res.data.data)}`);
    return res.data?.data ?? []
}

export async function getNotificationUnreadCount(): Promise<number> {
    const res = await api.get('/notifications/unread-count')
    // console.log(`getNotificationUnreadCount Service res:${JSON.stringify(res.data)}`);
    return res.data?.count ?? 0
}

export async function markNotificationsRead(): Promise<void> {
    await api.post('/notifications/mark-read')
}
