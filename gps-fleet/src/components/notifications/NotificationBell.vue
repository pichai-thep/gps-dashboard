<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'

import {
  getNotificationSnapshot,
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
let isLoading = false
let pollingDelay = 15_000

async function loadNotifications() {
  if (isLoading) return

  try {
    isLoading = true
    const snapshot = await getNotificationSnapshot()
    const items = snapshot.items
    const count = snapshot.unreadCount

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
              detail: item.plate +' ' + item.message,
              life: 5000,
            })
          })
    }

    if (newestId > latestId.value) {
      latestId.value = newestId
    }

    initialized.value = true
    pollingDelay = 15_000
  } catch (err: any) {
    if (err?.response?.status === 429) {
      pollingDelay = Math.max(
        60_000,
        Number(err?.response?.headers?.['retry-after'] ?? 0) * 1000,
      )
    }
    console.error('loadNotifications error', err)
  } finally {
    isLoading = false
  }
}

function scheduleNotifications() {
  if (timer) window.clearTimeout(timer)

  timer = window.setTimeout(async () => {
    await loadNotifications()
    scheduleNotifications()
  }, pollingDelay)
}

async function goNotifications() {
  await markNotificationsRead()
  unreadCount.value = 0
  router.push('/notifications')
}

onMounted(async () => {
  await loadNotifications()
  scheduleNotifications()
})

onUnmounted(() => {
  if (timer) {
    clearTimeout(timer)
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
