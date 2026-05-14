<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import Card from 'primevue/card'

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

let timer: number | undefined

async function loadDashboard() {
  // ช่วงแรก mock ไว้ก่อน
  summary.value = {
    running: 15,
    idle: 8,
    acc_on: 4,
    parking: 62,
    no_gps: 3,
    offline: 7,
    in_station: 31,
    out_station: 60,
    driving_without_card: 2,
    card_inserted: 18,
  }

  // ถ้าทำ API แล้วค่อยเปลี่ยนเป็นแบบนี้
  // const res = await api.get('/dashboard')
  // summary.value = res.data.data.summary
}

onMounted(() => {
  loadDashboard()
  timer = window.setInterval(loadDashboard, 30000)
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
})
</script>

```vue
<template>
  <div class="dashboard-page">
    <div class="page-header">
      <div>
        <h1>Dashboard</h1>
        <p>Fleet overview and vehicle summary</p>
      </div>
    </div>

    <!-- Vehicle Status -->
    <section class="dashboard-section">
      <div class="section-title">
        <h2>Vehicle Status</h2>
      </div>

      <div class="summary-grid status-grid">
        <Card class="summary-card running">
          <template #content>
            <div class="card-content">
              <i class="pi pi-play-circle"></i>

              <div class="card-text">
                <div class="label">Running</div>
                <div class="value">{{ summary.running }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card idle">
          <template #content>
            <div class="card-content">
              <i class="pi pi-pause-circle"></i>

              <div class="card-text">
                <div class="label">Idle</div>
                <div class="value">{{ summary.idle }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card acc-on">
          <template #content>
            <div class="card-content">
              <i class="pi pi-power-off"></i>

              <div class="card-text">
                <div class="label">Acc-on</div>
                <div class="value">{{ summary.acc_on }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card parking">
          <template #content>
            <div class="card-content">
              <i class="pi pi-stop-circle"></i>

              <div class="card-text">
                <div class="label">Parking</div>
                <div class="value">{{ summary.parking }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card no-gps">
          <template #content>
            <div class="card-content">
              <i class="pi pi-map-marker"></i>

              <div class="card-text">
                <div class="label">No GPS</div>
                <div class="value">{{ summary.no_gps }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card offline">
          <template #content>
            <div class="card-content">
              <i class="pi pi-times-circle"></i>

              <div class="card-text">
                <div class="label">Offline</div>
                <div class="value">{{ summary.offline }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <!-- Station -->
    <section class="dashboard-section">
      <div class="section-title">
        <h2>Station</h2>
      </div>

      <div class="summary-grid station-grid">
        <Card class="summary-card in-station">
          <template #content>
            <div class="card-content">
              <i class="pi pi-home"></i>

              <div class="card-text">
                <div class="label">In Station</div>
                <div class="value">{{ summary.in_station }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card out-station">
          <template #content>
            <div class="card-content">
              <i class="pi pi-send"></i>

              <div class="card-text">
                <div class="label">Out Station</div>
                <div class="value">{{ summary.out_station }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <!-- Driver Card -->
    <section class="dashboard-section">
      <div class="section-title">
        <h2>Driver Card</h2>
      </div>

      <div class="summary-grid driver-grid">
        <Card class="summary-card no-card">
          <template #content>
            <div class="card-content">
              <i class="pi pi-id-card"></i>

              <div class="card-text">
                <div class="label">Driving Without Card</div>
                <div class="value">
                  {{ summary.driving_without_card }}
                </div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card card-inserted">
          <template #content>
            <div class="card-content">
              <i class="pi pi-user"></i>

              <div class="card-text">
                <div class="label">Card Inserted</div>
                <div class="value">{{ summary.card_inserted }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>
  </div>
</template>
```


<style scoped>
.dashboard-page {
  padding: 1rem 1.5rem;
  color: #e5e7eb;
}

.page-header {
  margin-bottom: 1rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 700;
  color: #f8fafc !important;
}

.page-header p {
  margin: 0.25rem 0 0;
  color: #94a3b8 !important;
  font-size: 0.9rem;
}


.dashboard-section {
  margin-bottom: 1.1rem;
}

.section-title {
  margin-bottom: 0.7rem;
}

.section-title h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc !important;
}

.summary-grid {
  display: grid;
  gap: 0.85rem;
}

.status-grid {
  grid-template-columns: repeat(6, minmax(140px, 1fr));
}

.station-grid,
.driver-grid {
  grid-template-columns: repeat(2, minmax(260px, 1fr));
  max-width: 860px;
}

.summary-card {
  border-radius: 18px;
  overflow: hidden;
  border: none;
  color: #fff;
  cursor: pointer;
  transition:
      transform 0.15s ease,
      box-shadow 0.15s ease,
      opacity 0.15s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
  opacity: 0.96;
}

.summary-card :deep(.p-card-body) {
  padding: 0;
}

.summary-card :deep(.p-card-content) {
  padding: 0.85rem 1rem;
}

.card-content {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-height: 70px;
}

.status-grid .card-content {
  min-height: 58px;
}

.card-content i {
  font-size: 1.7rem;
  opacity: 0.95;
}

.label {
  font-size: 0.82rem;
  font-weight: 600;
  opacity: 0.96;
}

.value {
  margin-top: 0.15rem;
  font-size: 1.55rem;
  line-height: 1;
  font-weight: 800;
}

.status-grid .value {
  font-size: 1.45rem;
}

/* STATUS COLORS */

.running {
  background: #16a34a;
}

.idle {
  background: #eab308;
}

.acc-on {
  background: #f97316;
}

.parking {
  background: #6b7280;
}

.no-gps {
  background: #2563eb;
}

.offline {
  background: #dc2626;
}

/* STATION */

.in-station {
  background: #f97316;
}

.out-station {
  background: #0284c7;
}

/* DRIVER CARD */

.no-card {
  background: #991b1b;
}

.card-inserted {
  background: #15803d;
}

/* RESPONSIVE */

@media (max-width: 1400px) {
  .status-grid {
    grid-template-columns: repeat(3, minmax(160px, 1fr));
  }
}

@media (max-width: 768px) {
  .dashboard-page {
    padding: 0.85rem;
  }

  .status-grid,
  .station-grid,
  .driver-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .summary-card :deep(.p-card-content) {
    padding: 0.8rem;
  }

  .card-content {
    min-height: 60px;
  }

  .card-content i {
    font-size: 1.45rem;
  }

  .label {
    font-size: 0.76rem;
  }

  .value {
    font-size: 1.3rem;
  }
}

.dashboard-page :deep(h1),
.dashboard-page :deep(h2),
.dashboard-page :deep(p) {
  color: #f8fafc !important;
}

.dashboard-page :deep(p) {
  color: #94a3b8 !important;
}
</style>