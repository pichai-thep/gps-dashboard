<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import {
  getRecentNotifications,
  type NotificationItem,
} from '@/services/notificationApi'
import {useToast} from "primevue";

const notifications = ref<NotificationItem[]>([])
const loading = ref(false)
const toast = useToast()

const latestId = ref<number>(0)

let timer: number | undefined

async function loadNotifications() {
  try {
    loading.value = true

    const items = await getRecentNotifications()

    const oldLatestId = latestId.value
    const newestId = Number(items[0]?.id ?? 0)

    notifications.value = items

    if (oldLatestId > 0 && newestId > oldLatestId) {
      const newestItem = items[0]

      toast.add({
        severity: 'warn',
        summary: newestItem.msg_type,
        detail: newestItem.message,
        life: 5000,
      })
    }

    if (newestId > latestId.value) {
      latestId.value = newestId
    }

    console.log({
      oldLatestId,
      newestId,
      latestId: latestId.value,
      count: items.length,
    })
  } catch (err) {
    console.error('loadNotifications error', err)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  toast.add({
    severity: 'success',
    summary: 'Toast Test',
    detail: 'PrimeVue Toast พร้อมใช้งาน',
    life: 3000,
  })

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

<!--  <div class="notification-bell">-->
    <button class="menu-item">
      <i class="pi pi-bell"></i>
      <span
          v-if="notifications.length"
          class="sidebar-badge"
      >
        {{ notifications.length }}
      </span>
    </button>
<!--  </div>-->
</template>

<style scoped>
.notification-bell {
  position: relative;
}

.bell-btn {
  position: relative;
  border: none;
  background: transparent;
  font-size: 24px;
  cursor: pointer;
}

.badge {
  position: absolute;
  top: -6px;
  right: -8px;
  background: red;
  color: white;
  border-radius: 999px;
  min-width: 18px;
  height: 18px;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}

.dropdown {
  margin-top: 12px;
  width: 320px;
  border: 1px solid #ddd;
  border-radius: 8px;
  background: white;
  overflow: hidden;
}

.notify-item {
  padding: 10px;
  border-bottom: 1px solid #eee;
}

.notify-item:last-child {
  border-bottom: none;
}

.title {
  font-weight: 600;
}

.message {
  margin-top: 4px;
  font-size: 14px;
}

.time {
  margin-top: 6px;
  font-size: 12px;
  color: #888;
}

.empty {
  padding: 16px;
  text-align: center;
  color: #999;
}

.notification-bell {
  position: relative;
  width: 100%;
  height: 100%;
}

.sidebar-bell-btn {
  position: relative;
  width: 72px;
  height: 72px;
  border: none;
  border-radius: 20px;
  background: #252b3a;
  color: #e5e7eb;
  font-size: 28px;
  cursor: pointer;
}

.sidebar-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 999px;
  background: #ef4444;
  color: white;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

</style>