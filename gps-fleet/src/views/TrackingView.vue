<template>
  <div class="tracking-page">
    <aside class="vehicle-panel">
      <div class="panel-header">
        <Message v-if="error" severity="error" class="m-3">
          {{ error }}
        </Message>

        <div v-if="loading" class="loading-text">
          Loading tracking...
        </div>

        <h2>Current Tracking</h2>
        <p>{{ filteredVehicles.length }} / {{ vehicles.length }} vehicles</p>
      </div>

      <div class="control-bar">
        <Dropdown
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="All status"
            showClear
            class="status-filter"
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
            placeholder="Search plate/imei"
            class="search-input"
        />
      </div>

      <DataTable
          :value="filteredVehicles"
          scrollable
          scrollHeight="calc(100vh - 310px)"
          class="vehicle-table"
          selectionMode="single"
          @row-click="onRowClick"
      >
        <Column header="#" style="width: 44px">
          <template #body="slotProps">
            <span class="row-index">{{ slotProps.index + 1 }}</span>
          </template>
        </Column>

        <Column header="ST" style="width: 60px">
          <template #body="slotProps">
            {{ slotProps.data.status }}
            <i
                :class="['pi', getStatusIcon(slotProps.data.status), 'status-icon']"
            ></i>
          </template>
        </Column>

        <Column field="plate_no" header="Plate" sortable>
          <template #body="slotProps">
            <div class="plate-cell">
              <strong>{{ slotProps.data.plate_no }}</strong>
              <small>{{ slotProps.data.speed ?? 0 }} km/h</small>
            </div>
          </template>
        </Column>

        <Column header="Time" sortable style="width: 92px">
          <template #body="slotProps">
            <div class="time-cell">
              {{ formatGpsTimeCompact(slotProps.data.gps_time) }}
            </div>
          </template>
        </Column>
      </DataTable>
    </aside>

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
import FleetMap from '@/components/FleetMap.vue'
import type {Vehicle, VehicleStatus} from '@/types/fleet'
import { getCurrentTracking } from '@/services/tracking'
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

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'Parking', value: 'parking' },
  { label: 'Offline', value: 'offline' },
  { label: 'No GPS', value: 'no_gps' },
]

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

let pollingTimer: number | null = null

async function loadVehicles() {
  try {
    loading.value = true
    error.value = null
    vehicles.value = await getCurrentTracking()
  } catch (e) {
    console.error('LOAD VEHICLES ERROR', e)
    error.value = 'Cannot load tracking data'
    vehicles.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
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

function onRowClick(event: { data: Vehicle }) {
  selectVehicle(event.data)
}

function getVehicleKey(vehicle: Vehicle): string {
  return String(vehicle.vehicle_id || vehicle.id || vehicle.plate_no)
}

function selectVehicle(vehicle: Vehicle) {
  selectedVehicleId.value = getVehicleKey(vehicle)
}

function formatGpsTimeCompact(value?: string | null): string {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('th-TH', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

function getStatusIcon(status: VehicleStatus) {
  return {
    running: 'pi-play-circle',
    idle: 'pi-pause-circle',
    parking: 'pi-stop-circle',
    offline: 'pi-times-circle',
    no_gps: 'pi-exclamation-circle',
  }[status] || 'pi-circle'
}

</script>

<style scoped>
.tracking-page {
  flex: 1;
  display: grid;
  grid-template-columns: 420px minmax(0, 1fr);
  gap: 16px;
  min-width: 0;
  min-height: 0;
}

.vehicle-panel {
  height: 100%;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: #111827;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.panel-header {
  flex-shrink: 0;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.panel-header h2 {
  margin: 0;
  color: #fff;
  font-size: 22px;
  font-weight: 800;
}

.panel-header p {
  margin: 6px 0 0;
  color: #9ca3af;
  font-size: 14px;
}

.loading-text {
  margin-bottom: 10px;
  color: #9ca3af;
  font-size: 13px;
}

.control-bar {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr);
  gap: 10px;
  padding: 12px;
  background: #0b1220;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.status-filter,
.search-input {
  min-width: 0;
  width: 100%;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.row-index {
  color: #334155;
  font-weight: 600;
}

.status-dot {
  width: 10px;
  height: 10px;
  display: inline-block;
  border-radius: 999px;
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

.status-option {
  display: flex;
  align-items: center;
  gap: 8px;
}

.plate-cell {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.plate-cell strong {
  max-width: 140px;
  overflow: hidden;
  color: #1f2937;
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.plate-cell small {
  color: #64748b;
  font-size: 11px;
}

.time-cell {
  width: 80px;
  color: #334155;
  font-size: 12px;
  line-height: 1.25;
  white-space: normal;
}

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;
}

:deep(.p-datatable) {
  height: 100%;
  font-size: 13px;
}

:deep(.p-datatable-table-container) {
  flex: 1;
  min-height: 0;
}

:deep(.p-datatable-thead > tr > th) {
  padding: 10px 12px;
  color: #334155;
  font-size: 13px;
  font-weight: 800;
  background: #fff;
}

:deep(.p-datatable-tbody > tr) {
  cursor: pointer;
}

:deep(.p-datatable-tbody > tr > td) {
  padding: 10px 12px;
  vertical-align: middle;
}

:deep(.p-datatable-tbody > tr:hover) {
  background: #f1f5f9;
}

@media (max-width: 1100px) {
  .tracking-page {
    grid-template-columns: 360px minmax(0, 1fr);
  }

  .control-bar {
    grid-template-columns: 1fr;
  }
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.status-icon {
  font-size: 16px;
}

/* color แยกตาม status */
.pi-play-circle {
  color: #22c55e;
}

.pi-pause-circle {
  color: #f59e0b;
}

.pi-stop-circle {
  color: #64748b;
}

.pi-times-circle {
  color: #ef4444;
}

.pi-exclamation-circle {
  color: #8b5cf6;
}

:deep(.p-datatable-table-container) {
  overflow-y: auto !important;
}

@media (max-width: 960px) {
  .tracking-page {
    grid-template-columns: 1fr;
  }
}
</style>