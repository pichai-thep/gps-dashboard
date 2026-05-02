<template>
  <div class="tracking-page">
    <!-- LEFT: VEHICLE LIST -->
    <aside class="vehicle-panel">
      <div class="panel-header">

        <Message v-if="error" severity="error" class="m-3">
          {{ error }}
        </Message>

        <div v-if="loading" class="loading-text">
          Loading tracking...
        </div>

        <h2>Current Tracking</h2>
        <p>{{ vehicles.length }} vehicles</p>
      </div>

      <div class="control-bar">
        <Dropdown
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="All status"
            showClear
        >
          <template #option="slotProps">
            <div class="status-option">
              <span :class="['status-dot', slotProps.option.value]"></span>
              {{ slotProps.option.label }}
            </div>
          </template>
        </Dropdown>

        <InputText
            v-model="search"
            placeholder="Search by plate/imei"
        />
      </div>

      <DataTable
          :value="filteredVehicles"
          scrollable
          scrollHeight="calc(100vh - 280px)"
          class="vehicle-table"
          selectionMode="single"
          @row-click="onRowClick"
      >
        <!-- index -->
        <Column header="#" style="width: 50px">
          <template #body="slotProps">
            {{ slotProps.index + 1 }}
          </template>
        </Column>

        <!-- status -->
        <Column header="ST" style="width: 70px">
          <template #body="slotProps">
            <span :class="['status-dot', slotProps.data.status]"></span>
          </template>
        </Column>

        <!-- plate -->
        <Column field="plate_no" header="Plate" sortable />

        <!-- time -->
        <Column header="Time" sortable>
          <template #body="slotProps">
            {{ formatGpsTime(slotProps.data.gps_time) }}
          </template>
        </Column>
      </DataTable>

    </aside>

    <!-- RIGHT: MAP -->
    <section class="map-area">
      <FleetMap
          :vehicles="vehicles"
          :focus-vehicle-id="selectedVehicleId"
          @vehicle-click="selectVehicle"
      />
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Tag from 'primevue/tag'
import FleetMap from '../components/FleetMap.vue'
import type { Vehicle, VehicleStatus } from '../types/fleet.ts'
import { getCurrentTracking } from '../services/tracking.js'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'

const selectedVehicleId = ref<string | null>(null)
const vehicles = ref<Vehicle[]>([])
const search = ref('')
const statusFilter = ref<string | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const filteredVehicles = computed(() => {
  return vehicles.value.filter((vehicle) => {
    const keyword = search.value.trim().toLowerCase()

    const matchSearch =
        !keyword ||
        vehicle.plate_no?.toLowerCase().includes(keyword) ||
        String(vehicle.vehicle_id || '').toLowerCase().includes(keyword)

    const matchStatus =
        !statusFilter.value || vehicle.status === statusFilter.value

    return matchSearch && matchStatus
  })
})

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'Parking', value: 'parking' },
  { label: 'Offline', value: 'offline' },
  { label: 'No GPS', value: 'no_gps' },
]

let pollingTimer: number | null = null

async function loadVehicles() {
  console.log('LOAD VEHICLES CALLED')

  try {
    loading.value = true
    error.value = null
    vehicles.value = await getCurrentTracking()
    console.log('VEHICLES LOADED', vehicles.value)
  } catch (e) {
    console.error('LOAD VEHICLES ERROR', e)
    error.value = 'Cannot load tracking data'
    vehicles.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  console.log('TRACKING ON MOUNTED')

  await loadVehicles()

  pollingTimer = window.setInterval(() => {
    loadVehicles()
  }, 30_000)
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    window.clearInterval(pollingTimer)
  }
})

function onRowClick(event: any) {
  const vehicle = event.data
  selectVehicle(vehicle)
}

function getVehicleKey(vehicle: Vehicle): string {
  return String(
      vehicle.vehicle_id ||
      vehicle.id ||
      vehicle.plate_no
  )
}
function selectVehicle(vehicle: Vehicle) {
  selectedVehicleId.value = getVehicleKey(vehicle)
}

function statusSeverity(status: VehicleStatus) {
  return {
    running: 'success',
    idle: 'warn',
    parking: 'secondary',
    offline: 'danger',
    no_gps: 'contrast',
  }[status] || 'secondary'
}
function formatGpsTime(value?: string | null): string {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('th-TH', {
    dateStyle: 'short',
    timeStyle: 'medium',
    hour12: false,
  }).format(date)
}

function formatFuel(value?: number | string | null): string {
  if (value === null || value === undefined || value === '') return '-'

  return `${value}%`
}

</script>

<style scoped>
body {
  background: #020617; /* 👈 เข้มขึ้น */
}

.tracking-page {
  flex: 1;              /* 👈 เปลี่ยนจาก height calc */
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 16px;
  min-height: 0;        /* 👈 สำคัญ */
}

.vehicle-panel {
  background: #111827;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;

  box-shadow: 0 10px 30px rgba(0,0,0,0.4); /* 👈 สำคัญ */
}

.panel-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: #fff;
}

.panel-header p {
  color: #9ca3af;
}

.vehicle-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.panel-header {
  padding: 18px;
  border-bottom: 1px solid var(--p-surface-700);
}

.panel-header h2 {
  margin: 0;
  font-size: 20px;
}

.panel-header p {
  margin: 4px 0 0;
  color: var(--p-text-muted-color);
}

.vehicle-item {
  width: 100%;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--p-text-color);
  text-align: left;
  cursor: pointer;
}

.vehicle-item:hover,
.vehicle-item.active {
  background: var(--p-surface-800);
  border-color: var(--p-surface-600);
}

.vehicle-item strong,
.vehicle-item span {
  display: block;
}

.vehicle-item span {
  margin-top: 4px;
  font-size: 13px;
  color: var(--p-text-muted-color);
}

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;        /* 👈 เพิ่ม */
}

@media (max-width: 960px) {
  .tracking-page {
    grid-template-columns: 1fr;
  }
}

.loading-text {
  padding: 12px 18px;
  color: var(--p-text-muted-color);
  font-size: 14px;
}

.vehicle-stats {
  display: flex;
  gap: 10px;
  margin-top: 6px;
  font-size: 12px;
  color: var(--p-text-muted-color);
}

.vehicle-stats span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.gps-time {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  opacity: 0.8;
}

.fuel-bar {
  height: 4px;
  background: var(--p-surface-700);
  border-radius: 4px;
  margin-top: 4px;
  overflow: hidden;
}

.fuel-fill {
  height: 100%;
  background: #22c55e;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.status-dot.running {
  background: #22c55e;
}

.status-dot.idle {
  background: #f59e0b;
}

.status-dot.parking {
  background: #64748b;
}

.status-dot.offline {
  background: #ef4444;
}

.status-dot.no_gps {
  background: #e5e7eb;
}

.control-bar {
  display: flex;
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid var(--p-surface-700);
}

.status-option {
  display: flex;
  align-items: center;
  gap: 8px;
}

.vehicle-panel {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  border: 1px solid var(--p-surface-700);
  background: var(--p-surface-900);
  overflow: hidden;
}

.control-bar {
  flex-shrink: 0;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

</style>