<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'

import {
  getRecentNotifications,
  getNotificationUnreadCount,
  markNotificationsRead,
  type NotificationItem,
} from '@/services/notification'

const router = useRouter()
const toast = useToast()

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
const latestId = ref(0)
const initialized = ref(false)

let timer: number | undefined

async function loadNotifications() {
  try {
    const items = await getRecentNotifications()
    const count = await getNotificationUnreadCount()

    const newestId = Number(items[0]?.id ?? 0)
    const oldLatestId = latestId.value

    notifications.value = items
    unreadCount.value = count

    if (initialized.value && newestId > oldLatestId) {
      const newItems = items.filter((item) => Number(item.id) > oldLatestId)

      newItems
          .slice()
          .reverse()
          .forEach((item) => {
            toast.add({
              severity: 'warn',
              summary: item.msg_type,
              detail: item.message,
              life: 5000,
            })
          })
    }

    if (newestId > latestId.value) {
      latestId.value = newestId
    }

    initialized.value = true
  } catch (err) {
    console.error('loadNotifications error', err)
  }
}

async function goNotifications() {
  await markNotificationsRead()
  unreadCount.value = 0
  router.push('/notifications')
}

onMounted(async () => {
  await loadNotifications()

  timer = window.setInterval(() => {
    loadNotifications()
  }, 5000)
})

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
  }
})
</script>

<template>
  <button
      class="sidebar-bell-btn"
      @click="goNotifications"
  >
    <i class="pi pi-bell"></i>
    <span
        v-if="unreadCount > 0"
        class="sidebar-badge"
    >
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </span>
  </button>
</template>

<style scoped>
.sidebar-bell-btn {
  position: relative;

  width: 55px;
  height: 60px;

  border: none;
  border-radius: 22px;

  background: #252b3a;
  color: #e5e7eb;

  font-size: 28px;
  cursor: pointer;

  display: flex;
  align-items: center;
  justify-content: center;

  transition: all 0.2s ease;
}

.sidebar-bell-btn:hover {
  background: #374151;
}

.sidebar-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  min-width: 16px;
  height: 16px;
  font-size: 9px;
  line-height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: #ef4444;
  color: #ffffff;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;

  box-shadow: 0 0 0 2px #252b3a;

  z-index: 10;
}

</style>