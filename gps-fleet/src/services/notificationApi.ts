import api from '../plugins/axios'

export type NotificationItem = {
    id: number
    imei: string
    tracker_model?: string
    msg_type: string
    message: string
    gps_time?: string
    lat?: number
    lon?: number
    created_at: string
}

export async function getRecentNotifications(): Promise<NotificationItem[]> {
    const res = await api.get('/api/notifications/recent')

    return res.data?.data ?? []
}

export async function getNotificationUnreadCount(): Promise<number> {
    const res = await api.get('/api/notifications/unread-count')

    return res.data?.count ?? 0
}

export async function markNotificationsRead(): Promise<void> {
    await api.post('/api/notifications/mark-read')
}