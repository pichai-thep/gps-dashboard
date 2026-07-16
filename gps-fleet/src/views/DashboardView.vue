<template>
  <div class="dashboard-page">
    <div class="page-header">
      <div>
        <h1>{{ t('dashboard') }}</h1>
        <p>{{ t('dashboardSubtitle') }}</p>
      </div>

      <button class="refresh-button" type="button" @click="loadDashboard">
        <i class="pi pi-refresh" :class="{ spinning: loading }"></i>
      </button>
    </div>

    <section class="dashboard-section">
      <h2>{{ t('vehicleStatus') }}</h2>

      <div class="status-grid">
        <Card class="summary-card status-card total" @click="goToTracking()">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-car"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('totalVehicles') }}</div>
                <div class="value">{{ vehicleTotal }}</div>
                <div class="percent">100%</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card running" @click="goToTracking('run')">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-play"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('run') }}</div>
                <div class="value">{{ summary.run }}</div>
                <div class="percent">{{ percent(summary.run) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card idle" @click="goToTracking('idle')">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-pause"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('idle') }}</div>
                <div class="value">{{ summary.idle }}</div>
                <div class="percent">{{ percent(summary.idle) }}</div>
              </div>
            </div>
          </template>
        </Card>

<!--        <Card class="summary-card status-card acc-on" @click="goToTracking('acc_on')">-->
<!--          <template #content>-->
<!--            <div class="status-card-inner">-->
<!--              <div class="icon-circle">-->
<!--                <i class="pi pi-power-off"></i>-->
<!--              </div>-->

<!--              <div class="card-info">-->
<!--                <div class="label">Acc-on</div>-->
<!--                <div class="value">{{ summary.acc_on }}</div>-->
<!--                <div class="percent">{{ percent(summary.acc_on) }}</div>-->
<!--              </div>-->
<!--            </div>-->
<!--          </template>-->
<!--        </Card>-->

        <Card class="summary-card status-card parking" @click="goToTracking('park')">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-stop"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('park') }}</div>
                <div class="value">{{ summary.park }}</div>
                <div class="percent">{{ percent(summary.park) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card no-gps" @click="goToTracking('no_gps')">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-map-marker"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('noGps') }}</div>
                <div class="value">{{ summary.no_gps }}</div>
                <div class="percent">{{ percent(summary.no_gps) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card status-card offline" @click="goToTracking('offline')">
          <template #content>
            <div class="status-card-inner">
              <div class="icon-circle">
                <i class="pi pi-times"></i>
              </div>

              <div class="card-info">
                <div class="label">{{ t('offline') }}</div>
                <div class="value">{{ summary.offline }}</div>
                <div class="percent">{{ percent(summary.offline) }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>

    <section class="dashboard-section">
      <h2>{{ t('dltDriverCard') }}</h2>
<!--      <h3>{{ t('dltConnected') }} : {{ summary.dlt_synch_total }}</h3>-->
      <div class="wide-grid">
        <Card
            class="summary-card wide-card dlt-synched"
            @click="goToTracking(undefined, { dlt_synch: 1 })"
        >
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-link"></i>
              </div>

              <div>
                <div class="label">{{ t('dltConnected') }}</div>
                <div class="wide-value">{{ summary.dlt_synch_total }}</div>
                <div class="percent">{{ percent(summary.dlt_synch_total) }}</div>
              </div>
            </div>
          </template>
        </Card>

        <Card
            class="summary-card wide-card no-card"
            @click="goToTracking(undefined, { no_driver_card: 1 })"
        >
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-id-card"></i>
              </div>

              <div>
                <div class="label">{{ t('dltDrivingWithoutCard') }}</div>
                <div class="wide-value">{{ summary.driving_without_card }}</div>
                <div class="percent">{{ dltNoCardPercent }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <div class="dlt-list-panel">
        <div class="dlt-list-header">
          <div>
            <h3>{{ t('dltVehicleList') }}</h3>
            <p>{{ t('dltVehicleListSubtitle') }}</p>
          </div>

          <div class="dlt-list-actions">
            <button
                v-if="isDltListExpanded"
                class="text-button"
                type="button"
                @click="goToTracking('run', { dlt_synch: 1 })"
            >
              {{ t('viewAll') }}
            </button>

            <button
                class="collapse-button"
                type="button"
                :aria-expanded="isDltListExpanded"
                aria-controls="dlt-card-vehicle-list"
                @click="isDltListExpanded = !isDltListExpanded"
            >
              <span>{{ t(isDltListExpanded ? 'hideCardDetails' : 'showCardDetails') }}</span>
              <i
                  class="pi pi-chevron-down"
                  :class="{ 'is-expanded': isDltListExpanded }"
                  aria-hidden="true"
              ></i>
            </button>
          </div>
        </div>

        <div v-show="isDltListExpanded" id="dlt-card-vehicle-list">
          <div v-if="runningDriverCardVehicles.length" class="dlt-table-wrap">
            <table class="dlt-table">
              <thead>
              <tr>
                <th>{{ t('vehicle') }}</th>
                <th>{{ t('licenseName') }}</th>
                <th>{{ t('licenseNo') }}</th>
                <th>{{ t('status') }}</th>
              </tr>
              </thead>
              <tbody>
              <tr
                  v-for="(vehicle, index) in runningDriverCardVehicles"
                  :key="vehicle.imei || vehicle.plate_no || index"
                  @click="goToTracking('run', { dlt_synch: 1 })"
              >
                <td>
                  <div class="vehicle-name">{{ vehicle.plate_no || '-' }}</div>
                  <div v-if="vehicle.driver_name" class="vehicle-subtitle">
                    {{ vehicle.driver_name }}
                  </div>
                </td>
                <td>{{ vehicle.license_name || '-' }}</td>
                <td>{{ vehicle.license_no || '-' }}</td>
                <td>
                  <span class="status-pill" :class="vehicle.status">
                    {{ formatStatus(vehicle.status) }}
                  </span>
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="empty-state">
            {{ t('noDltVehicles') }}
          </div>
        </div>
      </div>
    </section>

    <section class="dashboard-section">
      <h2>{{ t('station') }}</h2>

      <div class="wide-grid">
        <Card class="summary-card wide-card readonly-card in-station">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-home"></i>
              </div>

              <div>
                <div class="label">{{ t('inStation') }}</div>
                <div class="wide-value">{{ summary.in_station }}</div>
<!--                <div class="percent">{{ percent(summary.in_station) }}</div>-->
              </div>
            </div>
          </template>
        </Card>

        <Card class="summary-card wide-card readonly-card out-station">
          <template #content>
            <div class="wide-card-inner">
              <div class="large-icon-circle">
                <i class="pi pi-send"></i>
              </div>

              <div>
                <div class="label">{{ t('outStation') }}</div>
                <div class="wide-value">{{ summary.out_station }}</div>
<!--                <div class="percent">{{ percent(summary.out_station) }}</div>-->
              </div>
            </div>
          </template>
        </Card>
      </div>
    </section>



    <div class="dashboard-footer">
      <span>{{ t('lastUpdated') }}: {{ lastUpdated || '-' }}</span>
      <span>
        <i class="pi pi-sync"></i>
        {{ t('autoRefresh30') }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Card from 'primevue/card'
import api from "@/services/api";
import {useRouter} from "vue-router";
import { useI18n } from '@/i18n'

type DashboardSummary = {
  total: number
  run: number
  idle: number
  acc_on: number
  park: number
  no_gps: number
  offline: number
  in_station: number
  out_station: number
  driving_without_card: number
  card_inserted: number
  dlt_synch_total: number
  driver_card_vehicles: DriverCardVehicle[]
}

type DriverCardVehicle = {
  imei?: string | null
  plate_no?: string | null
  driver_name?: string | null
  license_name?: string | null
  license_no?: string | null
  speed?: number
  status?: string | null
}

const summary = ref<DashboardSummary>({
  total: 0,
  run: 0,
  idle: 0,
  acc_on: 0,
  park: 0,
  no_gps: 0,
  offline: 0,
  in_station: 0,
  out_station: 0,
  driving_without_card: 0,
  card_inserted: 0,
  dlt_synch_total: 0,
  driver_card_vehicles: [],
})

const loading = ref(false)
const lastUpdated = ref('')
const isDltListExpanded = ref(false)
const router = useRouter();
const { locale, t } = useI18n()

let timer: number | undefined

const vehicleTotal = computed(() => {
  return summary.value.total || (
    summary.value.run +
    summary.value.idle +
    summary.value.acc_on +
    summary.value.park +
    summary.value.no_gps +
    summary.value.offline
  )
})

const dltNoCardPercent = computed(() => {
  if (!summary.value.dlt_synch_total) return '0%'

  return `${((summary.value.driving_without_card / summary.value.dlt_synch_total) * 100).toFixed(1)}%`
})

const runningDriverCardVehicles = computed(() => {
  return summary.value.driver_card_vehicles.filter((vehicle) => vehicle.status === 'run')
})

function goToTracking(status?: string, extraQuery: Record<string, string | number> = {}) {
  router.push({
    name: 'tracking',
    query: {
      ...(status ? { status } : {}),
      ...extraQuery,
    },
  })
}

function percent(value: number) {
  if (!vehicleTotal.value) return '0%'
  return `${((value / vehicleTotal.value) * 100).toFixed(1)}%`
}

function formatStatus(status?: string | null) {
  return {
    run: t('run'),
    idle: t('idle'),
    acc_on: 'Acc-on',
    park: t('park'),
    no_gps: t('noGps'),
    offline: t('offline'),
  }[String(status ?? '')] || '-'
}

function formatDateTime() {
  return new Date().toLocaleString(locale.value === 'th' ? 'th-TH' : 'en-US', {
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
    summary.value = normalizeSummary(res.data.data)

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

function normalizeSummary(data: Partial<DashboardSummary> & {
  running?: number
  parking?: number
  dlt_synched_vehicles?: DriverCardVehicle[]
} = {}): DashboardSummary {
  return {
    total: Number(data.total ?? 0),
    run: Number(data.run ?? data.running ?? 0),
    idle: Number(data.idle ?? 0),
    acc_on: Number(data.acc_on ?? 0),
    park: Number(data.park ?? data.parking ?? 0),
    no_gps: Number(data.no_gps ?? 0),
    offline: Number(data.offline ?? 0),
    in_station: Number(data.in_station ?? 0),
    out_station: Number(data.out_station ?? 0),
    driving_without_card: Number(data.driving_without_card ?? 0),
    card_inserted: Number(data.card_inserted ?? 0),
    dlt_synch_total: Number(data.dlt_synch_total ?? 0),
    driver_card_vehicles: Array.isArray(data.driver_card_vehicles)
        ? data.driver_card_vehicles
        : Array.isArray(data.dlt_synched_vehicles)
            ? data.dlt_synched_vehicles
        : [],
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

.readonly-card {
  cursor: default;
}

.readonly-card:hover {
  transform: none;
  filter: none;
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
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

.dlt-list-panel {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.72);
}

.dlt-list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.dlt-list-header h3 {
  margin: 0;
  color: #f8fafc;
  font-size: 1rem;
  font-weight: 800;
}

.dlt-list-header p {
  margin: 0.25rem 0 0;
  color: #94a3b8;
  font-size: 0.88rem;
}

.dlt-list-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.text-button {
  min-height: 34px;
  padding: 0 0.85rem;
  border: 1px solid rgba(45, 212, 191, 0.35);
  border-radius: 8px;
  color: #ccfbf1;
  background: rgba(15, 118, 110, 0.24);
  font-weight: 800;
  cursor: pointer;
}

.text-button:hover {
  background: rgba(15, 118, 110, 0.36);
}

.collapse-button {
  min-height: 34px;
  padding: 0 0.85rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 8px;
  color: #e2e8f0;
  background: rgba(30, 41, 59, 0.72);
  font-weight: 800;
  cursor: pointer;
  transition:
      background 0.15s ease,
      border-color 0.15s ease;
}

.collapse-button:hover {
  border-color: rgba(148, 163, 184, 0.48);
  background: rgba(51, 65, 85, 0.82);
}

.collapse-button i {
  font-size: 0.75rem;
  transition: transform 0.2s ease;
}

.collapse-button i.is-expanded {
  transform: rotate(180deg);
}

.dlt-table-wrap {
  margin-top: 0.9rem;
  max-height: 320px;
  overflow: auto;
}

.dlt-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.dlt-table th,
.dlt-table td {
  padding: 0.75rem 0.65rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  text-align: left;
  vertical-align: middle;
}

.dlt-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  color: #94a3b8;
  background: #0f172a;
  font-size: 0.78rem;
  font-weight: 900;
  text-transform: uppercase;
}

.dlt-table td {
  color: #e5e7eb;
  font-size: 0.92rem;
  overflow-wrap: anywhere;
}

.dlt-table tbody tr {
  cursor: pointer;
}

.dlt-table tbody tr:hover {
  background: rgba(45, 212, 191, 0.08);
}

.vehicle-name {
  color: #f8fafc;
  font-weight: 900;
}

.vehicle-subtitle {
  margin-top: 0.2rem;
  color: #94a3b8;
  font-size: 0.8rem;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 0.55rem;
  border-radius: 999px;
  color: #f8fafc;
  background: rgba(148, 163, 184, 0.28);
  font-size: 0.78rem;
  font-weight: 900;
}

.status-pill.run {
  background: rgba(22, 163, 74, 0.72);
}

.status-pill.idle,
.status-pill.acc_on {
  background: rgba(202, 138, 4, 0.72);
}

.status-pill.park {
  background: rgba(185, 28, 28, 0.72);
}

.status-pill.no_gps {
  background: rgba(29, 78, 216, 0.72);
}

.status-pill.offline {
  background: rgba(107, 114, 128, 0.72);
}

.empty-state {
  margin-top: 0.9rem;
  padding: 1.25rem;
  border: 1px dashed rgba(148, 163, 184, 0.24);
  border-radius: 8px;
  color: #94a3b8;
  text-align: center;
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

.total {
  background: linear-gradient(135deg, #475569, #1e293b);
}

.idle {
  background: linear-gradient(135deg, #facc15, #ca8a04);
}

.acc-on {
  background: linear-gradient(135deg, #fb923c, #ea580c);
}

.parking {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
}

.no-gps {
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.offline {
  background: linear-gradient(135deg, #9ca3af, #6b7280);
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

.dlt-synched {
  background: linear-gradient(135deg, #0f766e, #115e59);
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

  .dlt-list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .dlt-list-actions {
    width: 100%;
  }

  .dlt-list-actions > button {
    flex: 1;
  }
}
</style>
