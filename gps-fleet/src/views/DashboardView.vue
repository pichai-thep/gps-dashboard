<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Card from 'primevue/card'
import api from "@/services/api";

type DashboardSummary = {
  running: number
  idle: number
  acc_on: number
  parking: number
  no_gps: number
  offline: number
  in_station: number
  out_station: number
  driving_without_card: number
  card_inserted: number
}

const summary = ref<DashboardSummary>({
  running: 0,
  idle: 0,
  acc_on: 0,
  parking: 0,
  no_gps: 0,
  offline: 0,
  in_station: 0,
  out_station: 0,
  driving_without_card: 0,
  card_inserted: 0,
})

const loading = ref(false)
const lastUpdated = ref('')

let timer: number | undefined

const vehicleTotal = computed(() => {
  return (
      summary.value.running +
      summary.value.idle +
      summary.value.acc_on +
      summary.value.parking +
      summary.value.no_gps +
      summary.value.offline
  )
})

function percent(value: number) {
  if (!vehicleTotal.value) return '0%'
  return `${((value / vehicleTotal.value) * 100).toFixed(1)}%`
}

function formatDateTime() {
  return new Date().toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

async function loadDashboard() {
  loading.value = true

  try {

    const res = await api.get('/dashboard/summary')
    summary.value = res.data.data

    // summary.value = {
    //   running: 15,
    //   idle: 8,
    //   acc_on: 4,
    //   parking: 62,
    //   no_gps: 3,
    //   offline: 7,
    //   in_station: 31,
    //   out_station: 60,
    //   driving_without_card: 2,
    //   card_inserted: 18,
    // }

    lastUpdated.value = formatDateTime()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboard()
  timer = window.setInterval(loadDashboard, 30000)
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
})
</script>

<template>
  <div class="dashboard-page">
    <div class="page-header">
      <div>
        <h1>Dashboard</h1>
        <p>Fleet overview and vehicle summary</p>
      </div>

      <button class="refresh-button" type="button" @click="loadDashboard">
        <i class="pi pi-refresh" :class="{ spinning: loading }"></i>
      </button>
    </div>

    <section class="dashboard-section">
      <h2>Vehicle Status</h2>

      <div class="status-grid">
        <Card class="summary-card status-card running">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-play"></i>
              </div>

              <div class="card-info">
                <div class="label">Running</div>
                <div class="value">{{ summary.running }}</div>
                <div class="percent">{{ percent(summary.running) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card idle">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-pause"></i>
              </div>

              <div class="card-info">
                <div class="label">Idle</div>
                <div class="value">{{ summary.idle }}</div>
                <div class="percent">{{ percent(summary.idle) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card acc-on">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-power-off"></i>
              </div>

              <div class="card-info">
                <div class="label">Acc-on</div>
                <div class="value">{{ summary.acc_on }}</div>
                <div class="percent">{{ percent(summary.acc_on) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card parking">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-stop"></i>
              </div>

              <div class="card-info">
                <div class="label">Parking</div>
                <div class="value">{{ summary.parking }}</div>
                <div class="percent">{{ percent(summary.parking) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card no-gps">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-map-marker"></i>
              </div>

              <div class="card-info">
                <div class="label">No GPS</div>
                <div class="value">{{ summary.no_gps }}</div>
                <div class="percent">{{ percent(summary.no_gps) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card offline">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-times"></i>
              </div>

              <div class="card-info">
                <div class="label">Offline</div>
                <div class="value">{{ summary.offline }}</div>
                <div class="percent">{{ percent(summary.offline) }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <section class="dashboard-section">
      <h2>Station</h2>

      <div class="wide-grid">
        <Card class="summary-card wide-card in-station">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-home"></i>
              </div>

              <div>
                <div class="label">In Station</div>
                <div class="wide-value">{{ summary.in_station }}</div>
                <div class="percent">{{ percent(summary.in_station) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card wide-card out-station">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-send"></i>
              </div>

              <div>
                <div class="label">Out Station</div>
                <div class="wide-value">{{ summary.out_station }}</div>
                <div class="percent">{{ percent(summary.out_station) }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <section class="dashboard-section">
      <h2>Driver Card</h2>

      <div class="wide-grid">
        <Card class="summary-card wide-card no-card">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-id-card"></i>
              </div>

              <div>
                <div class="label">Driving Without Card</div>
                <div class="wide-value">{{ summary.driving_without_card }}</div>
                <div class="percent">{{ percent(summary.driving_without_card) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card wide-card card-inserted">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-user"></i>
              </div>

              <div>
                <div class="label">Card Inserted</div>
                <div class="wide-value">{{ summary.card_inserted }}</div>
                <div class="percent">{{ percent(summary.card_inserted) }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <div class="dashboard-footer">
      <span>Last updated: {{ lastUpdated || '-' }}</span>
      <span>
        <i class="pi pi-sync"></i>
        Auto refresh every 30 seconds
      </span>
    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
  width: 100%;
  min-height: calc(100vh - 78px);
  padding: 1.4rem 2rem 1.2rem;
  color: #f8fafc;
  background:
      radial-gradient(circle at top right, rgba(30, 64, 175, 0.16), transparent 34%),
      #020617;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(150px, 1fr));
  gap: 1rem;
  width: 100%;
}

.wide-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(320px, 1fr));
  gap: 1rem;
  width: 100%;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

.page-header h1 {
  margin: 0;
  font-size: 2rem;
  line-height: 1.1;
  font-weight: 800;
  color: #f8fafc;
}

.page-header p {
  margin: 0.45rem 0 0;
  font-size: 1rem;
  color: #94a3b8;
}

.refresh-button {
  width: 42px;
  height: 42px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 12px;
  color: #e5e7eb;
  background: rgba(15, 23, 42, 0.78);
  cursor: pointer;
  transition:
      background 0.15s ease,
      transform 0.15s ease,
      border-color 0.15s ease;
}

.refresh-button:hover {
  transform: translateY(-1px);
  border-color: rgba(148, 163, 184, 0.5);
  background: rgba(30, 41, 59, 0.9);
}

.spinning {
  animation: spin 0.8s linear infinite;
}

.dashboard-section {
  margin-bottom: 1.75rem;
}

.dashboard-section h2 {
  margin: 0 0 0.8rem;
  font-size: 1.15rem;
  font-weight: 800;
  color: #f8fafc;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(135px, 1fr));
  gap: 1rem;
}

.wide-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(260px, 1fr));
  gap: 1rem;
}

.summary-card {
  position: relative;
  overflow: hidden;
  border: 0;
  border-radius: 18px;
  color: #fff;
  cursor: pointer;
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
  transition:
      transform 0.16s ease,
      box-shadow 0.16s ease,
      filter 0.16s ease;
}

.summary-card:hover {
  transform: translateY(-3px);
  filter: brightness(1.04);
  box-shadow: 0 18px 36px rgba(0, 0, 0, 0.32);
}

.summary-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
      radial-gradient(circle at 30% 10%, rgba(255, 255, 255, 0.18), transparent 34%),
      linear-gradient(135deg, rgba(255, 255, 255, 0.13), transparent 55%);
  pointer-events: none;
}

.summary-card :deep(.p-card-body),
.summary-card :deep(.p-card-content) {
  padding: 0;
}

.status-card-inner {
  position: relative;
  z-index: 1;
  min-height: 120px;
  padding: 1.2rem 1.15rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.icon-circle {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border: 3px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
}

.icon-circle i {
  font-size: 1.2rem;
  font-weight: 800;
}

.card-info {
  min-width: 0;
}

.label {
  font-size: 0.95rem;
  line-height: 1.15;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
}

.value {
  margin-top: 0.25rem;
  font-size: 2.15rem;
  line-height: 0.95;
  font-weight: 900;
  letter-spacing: -0.04em;
}

.percent {
  margin-top: 1rem;
  font-size: 0.95rem;
  line-height: 1;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.92);
}

.wide-card-inner {
  position: relative;
  z-index: 1;
  min-height: 126px;
  padding: 1.35rem 1.6rem;
  display: flex;
  align-items: center;
  gap: 1.4rem;
}

.large-icon-circle {
  width: 74px;
  height: 74px;
  flex: 0 0 74px;
  display: grid;
  place-items: center;
  border: 3px solid rgba(255, 255, 255, 0.22);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
}

.large-icon-circle i {
  font-size: 2rem;
}

.wide-value {
  margin-top: 0.4rem;
  font-size: 2.5rem;
  line-height: 0.95;
  font-weight: 900;
  letter-spacing: -0.05em;
}

.dashboard-footer {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-top: 0.75rem;
  color: #64748b;
  font-size: 0.9rem;
}

.dashboard-footer span {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

/* colors */
.running {
  background: linear-gradient(135deg, #16a34a, #15803d);
}

.idle {
  background: linear-gradient(135deg, #facc15, #ca8a04);
}

.acc-on {
  background: linear-gradient(135deg, #fb923c, #ea580c);
}

.parking {
  background: linear-gradient(135deg, #9ca3af, #6b7280);
}

.no-gps {
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.offline {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
}

.in-station {
  background: linear-gradient(135deg, #fb923c, #f97316);
}

.out-station {
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
}

.no-card {
  background: linear-gradient(135deg, #b91c1c, #991b1b);
}

.card-inserted {
  background: linear-gradient(135deg, #16a34a, #15803d);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1100px) {
  .status-grid {
    grid-template-columns: repeat(3, minmax(180px, 1fr));
  }
}

@media (max-width: 900px) {
  .dashboard-page {
    padding: 1rem;
  }

  .status-grid,
  .wide-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .status-card-inner {
    min-height: 105px;
  }

  .wide-card-inner {
    min-height: 110px;
    padding: 1rem;
  }

  .large-icon-circle {
    width: 58px;
    height: 58px;
    flex-basis: 58px;
  }

  .large-icon-circle i {
    font-size: 1.55rem;
  }
}

@media (max-width: 560px) {
  .page-header {
    gap: 1rem;
  }

  .page-header h1 {
    font-size: 1.6rem;
  }

  .status-grid,
  .wide-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.6rem;
  }
}
</style>