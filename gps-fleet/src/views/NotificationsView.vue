<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {getRecentNotifications, type NotificationItem,} from '../services/notification'

const notifications = ref<NotificationItem[]>([])
const loading = ref(false)
const auto_loading = ref(false)
const error = ref('')

let timer: number | undefined
let isLoadingNotifications = false

async function loadNotifications() {
  if (isLoadingNotifications) return

  try {
    isLoadingNotifications = true
    loading.value = true
    error.value = ''
    notifications.value = await getRecentNotifications()
  } catch (err) {
    error.value = 'โหลดรายการแจ้งเตือนไม่สำเร็จ'
  } finally {
    loading.value = false
    isLoadingNotifications = false
  }
}
async function autoLoadNotifications() {
  if (isLoadingNotifications) return

  try {
    isLoadingNotifications = true
    auto_loading.value = true
    error.value = ''
    notifications.value = await getRecentNotifications()
  } catch (err) {
    error.value = 'โหลดรายการแจ้งเตือนไม่สำเร็จ'
  } finally {
    auto_loading.value = false
    isLoadingNotifications = false
  }
}

onMounted(async () => {
  await loadNotifications()

  timer = window.setInterval(() => {
    void autoLoadNotifications()
  }, 5000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <section class="notification-page">
    <div class="page-header">
      <div>
        <h1>Notifications</h1>
        <p>รายการแจ้งเตือนล่าสุด</p>
      </div>

      <button @click="loadNotifications">
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && notifications.length === 0" class="loading">
      Loading notifications...
    </div>

    <div v-else-if="error" class="empty">
      {{ error }}
    </div>

    <div v-else-if="notifications.length === 0" class="empty">
      ไม่มีรายการแจ้งเตือน
    </div>

    <div v-else class="notification-list">
      <article
          v-for="item in notifications"
          :key="item.id"
          class="notification-card"
      >
        <div class="icon">
          <i class="pi pi-bell"></i>
        </div>

        <div class="content">
          <div class="row">
            <h3>{{ item.msg_type || 'Notification' }}</h3>
            <span>{{ item.gps_time || item.created_at || '-' }}</span>
          </div>

          <p>{{ item.message || '-' }}</p>

          <div class="meta">
            <span>IMEI: {{ item.imei || '-' }}</span>
            <span>Plate: {{ item.plate || '-' }}</span>
          </div>

        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.notification-page {
  width: 100%;
  min-height: 100vh;
  padding: 32px;
  color: #e5e7eb;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.page-header h1 {
  margin: 0;
  font-size: 36px;
  font-weight: 800;
}

.page-header p {
  margin: 6px 0 0;
  color: #9ca3af;
}

.page-header button {
  border: 1px solid #334155;
  background: #111827;
  color: #e5e7eb;
  border-radius: 14px;
  padding: 10px 16px;
  cursor: pointer;
}

.notification-list {
  display: grid;

  grid-template-columns: repeat(
    auto-fill,
    minmax(420px, 1fr)
  );

  gap: 20px;

  width: 100%;
}

.notification-card {
  display: flex;
  gap: 16px;
  padding: 18px;
  border: 0px solid #1f2937;
  border-radius: 20px;
  background: #2d3748;
  width: 100%;
}

.icon {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  background: #1f2937;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #f87171;
  font-size: 22px;
}

.content {
  flex: 1;
}

.row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.row h3 {
  margin: 0;
  font-size: 18px;
}

.row span {
  color: #9ca3af;
  font-size: 13px;
}

.content p {
  margin: 8px 0;
  color: #f9fafb;
  font-size: 16px;
}

.meta {
  display: flex;
  gap: 18px;
  color: #9ca3af;
  font-size: 13px;
}

.empty,
.loading {
  color: #9ca3af;
}
</style>
