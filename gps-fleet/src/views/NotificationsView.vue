<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {getRecentNotifications, type NotificationItem,} from '../services/notification'

const notifications = ref<NotificationItem[]>([])
const loading = ref(false)
const auto_loading = ref(false)
const error = ref('')

let timer: number | undefined
let isLoadingNotifications = false

const notificationTypeMeta: Record<string, { icon: string; tone: string }> = {
  engine_on_alert: { icon: 'pi pi-key', tone: 'success' },
  engine_off_alert: { icon: 'pi pi-stop-circle', tone: 'neutral' },
  power_on_alert: { icon: 'pi pi-bolt', tone: 'success' },
  power_off_alert: { icon: 'pi pi-power-off', tone: 'neutral' },

  speed_over_device_alert: { icon: 'pi pi-gauge', tone: 'danger' },
  speed_over_cloud_alert: { icon: 'pi pi-gauge', tone: 'danger' },

  hash_accelerate_alert: { icon: 'pi pi-arrow-up', tone: 'danger' },
  hash_break_alert: { icon: 'pi pi-arrow-down', tone: 'danger' },
  drive4h_alert: { icon: 'pi pi-clock', tone: 'warning' },

  station_in_alert: { icon: 'pi pi-sign-in', tone: 'warning' },
  station_out_alert: { icon: 'pi pi-sign-out', tone: 'warning' },

  gps_antenna_connect_alert: { icon: 'pi pi-wifi', tone: 'warning' },
  abnormal_gps_alert: { icon: 'pi pi-compass', tone: 'danger' },
  abnormal_fuel_alert: { icon: 'pi pi-sliders-h', tone: 'danger' },
  input2_on_alert: { icon: 'pi pi-bolt', tone: 'warning' },
}

function getNotificationTypeMeta(type?: string) {
  const normalizedType = (type || '').trim().toLowerCase().replace(/-/g, '_')

  return notificationTypeMeta[normalizedType] || {
    icon: 'pi pi-bell',
    tone: 'default',
  }
}

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
        <div
            class="icon"
            :class="`icon-${getNotificationTypeMeta(item.msg_type).tone}`"
        >
          <i :class="getNotificationTypeMeta(item.msg_type).icon"></i>
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
  min-height: 100%;
  padding: 24px;
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
  font-size: 30px;
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
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr));
  gap: 14px;
  width: 100%;
}

.notification-card {
  display: flex;
  gap: 12px;
  padding: 14px;
  border: 0px solid #1f2937;
  border-radius: 14px;
  background: #2d3748;
  width: 100%;
  min-width: 0;
}

.icon {
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  border-radius: 12px;
  background: #1f2937;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #f87171;
  font-size: 18px;
}

.icon-success {
  background: rgba(34, 197, 94, 0.14);
  color: #4ade80;
}

.icon-neutral {
  background: rgba(148, 163, 184, 0.14);
  color: #cbd5e1;
}

.icon-danger {
  background: rgba(248, 113, 113, 0.14);
  color: #f87171;
}

.icon-warning {
  background: rgba(251, 191, 36, 0.14);
  color: #fbbf24;
}

.icon-default {
  background: rgba(96, 165, 250, 0.14);
  color: #60a5fa;
}

.content {
  flex: 1;
  min-width: 0;
}

.row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.row h3 {
  margin: 0;
  font-size: 15px;
  overflow-wrap: anywhere;
}

.row span {
  color: #9ca3af;
  font-size: 12px;
  white-space: nowrap;
}

.content p {
  margin: 6px 0;
  color: #f9fafb;
  font-size: 14px;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  color: #9ca3af;
  font-size: 12px;
}

@media (max-width: 560px) {
  .notification-page {
    padding: 10px;
  }

  .page-header {
    align-items: center;
    margin-bottom: 14px;
  }

  .page-header h1 {
    font-size: 22px;
  }

  .page-header p {
    font-size: 13px;
  }

  .page-header button {
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 12px;
  }

  .notification-list {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .notification-card {
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
  }

  .icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
    font-size: 15px;
  }

  .row {
    align-items: flex-start;
    flex-direction: column;
    gap: 3px;
  }
}

.empty,
.loading {
  color: #9ca3af;
}
</style>
