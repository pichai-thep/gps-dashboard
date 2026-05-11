<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {getRecentNotifications, type NotificationItem,} from '@/services/notification'

const notifications = ref<NotificationItem[]>([])
const loading = ref(false)

let timer: number | undefined

async function loadNotifications() {
  try {
    loading.value = true
    notifications.value = await getRecentNotifications()
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadNotifications()

  timer = window.setInterval(() => {
    loadNotifications()
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
        <p>รายการแจ้งเตือนล่าสุดจาก Redis</p>
      </div>

      <button @click="loadNotifications">
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

<!--    <div class="notification-list">-->
<!--      <article-->
<!--          v-for="item in notifications"-->
<!--          :key="item.id"-->
<!--          class="notification-card"-->
<!--      >-->
<!--      </article>-->
<!--    </div>-->

    <div
        v-if="notifications.length > 0"
        class="notification-list"
    >
      <article
          v-for="item in notifications"
          :key="item.id"
          class="notification-card"
      >
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