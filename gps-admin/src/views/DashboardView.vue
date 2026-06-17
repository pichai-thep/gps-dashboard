<template>
  <div class="dashboard">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon users">
          <i class="pi pi-users"></i>
        </div>
        <div class="stat-body">
          <div class="stat-label">Total Users</div>
          <div class="stat-value">{{ stats.users ?? '—' }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon customers">
          <i class="pi pi-building"></i>
        </div>
        <div class="stat-body">
          <div class="stat-label">Total Customers</div>
          <div class="stat-value">{{ stats.customers ?? '—' }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon trackers">
          <i class="pi pi-wifi"></i>
        </div>
        <div class="stat-body">
          <div class="stat-label">Total Trackers</div>
          <div class="stat-value">{{ stats.trackers ?? '—' }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon active">
          <i class="pi pi-check-circle"></i>
        </div>
        <div class="stat-body">
          <div class="stat-label">Active Trackers</div>
          <div class="stat-value">{{ stats.activeTrackers ?? '—' }}</div>
        </div>
      </div>
    </div>

    <div class="quick-links">
      <h2>Quick Access</h2>
      <div class="link-grid">
        <button class="link-card" @click="router.push('/users')">
          <i class="pi pi-users"></i>
          <span>Manage Users</span>
        </button>
        <button class="link-card" @click="router.push('/customers')">
          <i class="pi pi-building"></i>
          <span>Manage Customers</span>
        </button>
        <button class="link-card" @click="router.push('/trackers')">
          <i class="pi pi-wifi"></i>
          <span>Manage Trackers</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

const stats = ref<{
  users?: number
  customers?: number
  trackers?: number
  activeTrackers?: number
}>({})

onMounted(async () => {
  try {
    const res = await api.get('/admin/stats')
    stats.value = res.data
  } catch {
    // stats not available
  }
})
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  font-size: 20px;
  flex-shrink: 0;
}

.stat-icon.users { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
.stat-icon.customers { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.stat-icon.trackers { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.stat-icon.active { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }

.stat-label {
  font-size: 12px;
  color: #64748b;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #f1f5f9;
}

.quick-links h2 {
  margin: 0 0 16px;
  font-size: 15px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.link-grid {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.link-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  color: #e2e8f0;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.link-card:hover {
  background: #1e293b;
  border-color: #f59e0b;
  color: #fbbf24;
}

.link-card i {
  font-size: 18px;
}
</style>
