import api from './api'
import { useAuthStore } from '@/stores/auth'

export interface GroupItem {
    group_id: number | string
    group_name: string
}

export async function getGroups() {
    const auth = useAuthStore()

    const customerId =
        auth.customer?.id ??
        auth.user?.customer_id ??
        auth.config?.customer_id

    const response = await api.get('/tracking/groups', {
        params: {
            customer_id: customerId,
        },
    })

    return response.data
}