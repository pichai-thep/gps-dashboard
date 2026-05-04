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
        <p>{{ mapVehicles.length }} / {{ totalRecords }} vehicles on map</p>

      </div>

      <!-- 🔥 FILTER BAR -->
      <div class="control-bar">
        <Dropdown
            v-model="refreshInterval"
            :options="refreshOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Interval"
        />

        <Dropdown
            v-model="selectedGroupId"
            :options="groupOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="All Group"
        />

        <Dropdown
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Status"
            showClear
        />

        <InputText
            v-model="search"
            placeholder="Search plate/imei"
            class="search-input"
        />

        <Dropdown
            v-model="sortBy"
            :options="sortOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sort by"
            class="sort-filter"
        />

        <Dropdown
            v-model="sortDir"
            :options="sortDirOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Order"
            class="sort-dir-filter"
        />
      </div>

      <!-- 🔥 TABLE -->
      <DataTable
          :value="vehicles"
          lazy
          paginator
          :rows="perPage"
          :totalRecords="totalRecords"
          :first="(page - 1) * perPage"
          :rowsPerPageOptions="[10, 20, 50, 100]"
          scrollable
          scrollHeight="calc(100vh - 390px)"
          class="vehicle-table"
          selectionMode="single"
          @row-click="onRowClick"
          @page="onPage"
      >
        <Column header="#" style="width: 44px">
          <template #body="slotProps">
            {{ (page - 1) * perPage + slotProps.index + 1 }}
          </template>
        </Column>

        <Column header="ST" style="width: 60px">
          <template #body="slotProps">
            <i
                :class="[
          'pi',
          getStatusIcon(slotProps.data.status),
          'status-icon',
          slotProps.data.status
        ]"
            ></i>
          </template>
        </Column>

        <Column field="plate_no" header="Plate">
          <template #body="slotProps">
            <div class="plate-cell">
              <strong>{{ slotProps.data.plate_no }}</strong>
              <small>
                Speed: {{ slotProps.data.speed ?? 0 }} km/h
                · Fuel: {{ formatFuel(slotProps.data.fuel_left) }}
              </small>
            </div>
          </template>
        </Column>

        <Column header="Time" style="width: 110px">
          <template #body="slotProps">
            <div class="time-cell">
              {{ formatGpsTimeCompact(slotProps.data.gps_time) }}
            </div>
          </template>
        </Column>

        <Column header="" style="width: 44px">
          <template #body="slotProps">
            <Button
                text
                rounded
                size="small"
                :icon="isVehicleVisible(slotProps.data) ? 'pi pi-eye' : 'pi pi-eye-slash'"
                @click.stop="toggleVehicleVisible(slotProps.data)"
            />
          </template>
        </Column>
      </DataTable>
    </aside>

    <!-- 🔥 MAP -->
    <section class="map-area">
      <FleetMap
          :vehicles="mapVehicles"
          :focus-vehicle-id="selectedVehicleId"
          @vehicle-click="selectVehicle"
      />
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import FleetMap from '@/components/FleetMap.vue'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

import {
  getCurrentTracking,
  getVehicleGroups,
  type VehicleGroup,
} from '@/services/tracking'

import type { Vehicle, VehicleStatus } from '@/types/fleet'

/* ================= STATE ================= */

const vehicles = ref<Vehicle[]>([])
const selectedVehicleId = ref<string | null>(null)

const selectedGroupId = ref<number | string>(-1)
const statusFilter = ref<string | null>(null)
const refreshInterval = ref(30000)
const search = ref('')

const loading = ref(false)
const error = ref<string | null>(null)

/* 👁 hidden */
const hiddenVehicleKeys = ref<Set<string>>(new Set())

// const mapVehicles = computed(() => {
//   return filteredVehicles.value.filter(
//       (v) => !hiddenVehicleKeys.value.has(getVehicleKey(v))
//   )
// })

const mapVehicles = computed(() => {
  return vehicles.value.filter(
      (v) => !hiddenVehicleKeys.value.has(getVehicleKey(v))
  )
})

/* ================= OPTIONS ================= */

const refreshOptions = [
  { label: '10 sec', value: 10000 },
  { label: '30 sec', value: 30000 },
  { label: '1 min', value: 60000 },
]

const groupOptions = ref<VehicleGroup[]>([
  { id: -1, name: 'All Group' },
])

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'Parking', value: 'parking' },
  { label: 'Offline', value: 'offline' },
  { label: 'No GPS', value: 'no_gps' },
]

const page = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)
const sortBy = ref('plate_no')
const sortDir = ref<'asc' | 'desc'>('asc')

const sortOptions = [
  { label: 'Plate', value: 'plate_no' },
  { label: 'Time', value: 'gps_time' },
  { label: 'Speed', value: 'speed' },
  { label: 'Fuel', value: 'fuel_left' },
  { label: 'Status', value: 'status' },
]

const sortDirOptions = [
  { label: 'ASC', value: 'asc' },
  { label: 'DESC', value: 'desc' },
]
/* ================= COMPUTED ================= */

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
const visibleVehicles = computed(() => {
  return filteredVehicles.value.filter(
      (v) => !hiddenVehicleKeys.value.has(getVehicleKey(v))
  )
})

/* ================= FUNCTIONS ================= */


function formatGpsTimeCompact(value?: string | null): string {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('th-TH', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

function formatFuel(value?: number | string | null): string {
  if (value === null || value === undefined || value === '') return '-'
  return `${value}%`
}

function getVehicleKey(vehicle: Vehicle): string {
  return String(vehicle.vehicle_id || vehicle.id || vehicle.plate_no)
}

function isVehicleVisible(vehicle: Vehicle): boolean {
  return !hiddenVehicleKeys.value.has(getVehicleKey(vehicle))
}

function toggleVehicleVisible(vehicle: Vehicle) {
  const key = getVehicleKey(vehicle)
  const next = new Set(hiddenVehicleKeys.value)

  next.has(key) ? next.delete(key) : next.add(key)
  hiddenVehicleKeys.value = next
}

function selectVehicle(vehicle: Vehicle) {
  selectedVehicleId.value = getVehicleKey(vehicle)
}

function onRowClick(event: { data: Vehicle }) {
  selectVehicle(event.data)
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

/* ================= API ================= */

let pollingTimer: number | null = null
let isLoading = false

async function loadVehicles() {
  if (isLoading) return

  try {
    isLoading = true
    loading.value = true
    error.value = null

    const res = await getCurrentTracking({
      page: page.value,
      per_page: perPage.value,
      group_id: selectedGroupId.value,
      status: statusFilter.value,
      search: search.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    })

    vehicles.value = res.vehicles
    totalRecords.value = res.meta.total
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'โหลดข้อมูลไม่ได้'
    vehicles.value = []
  } finally {
    isLoading = false
    loading.value = false
  }
}

async function loadGroups() {
  try {
    const customerId = auth.customer?.id

    console.log('CUSTOMER ID', customerId)

    if (!customerId) return

    const groups = await getVehicleGroups(customerId)

    console.log('GROUPS', groups)

    groupOptions.value = [{ id: -1, name: 'All Group' }, ...groups]
  } catch (e) {
    console.error('LOAD GROUPS ERROR', e)
  }
}

function onPage(event: any) {
  page.value = event.page + 1
  perPage.value = event.rows
  loadVehicles()
}

/* ================= POLLING ================= */

function startPolling() {
  if (pollingTimer) clearInterval(pollingTimer)

  pollingTimer = window.setInterval(() => {
    if (!document.hidden) loadVehicles()
  }, refreshInterval.value)
}

/* ================= WATCH ================= */

watch(
    [selectedGroupId, statusFilter, sortBy, sortDir],
    () => {
      page.value = 1
      loadVehicles()
    }
)

watch(refreshInterval, () => {
  startPolling()
})

let searchTimer: number | null = null

watch(search, () => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }

  searchTimer = window.setTimeout(() => {
    page.value = 1
    loadVehicles()
  }, 300)
})

/* ================= LIFECYCLE ================= */

onMounted(async () => {
  if (!auth.customer) {
    await auth.fetchMe()
  }

  await loadGroups()
  await loadVehicles()
  startPolling()
})

onBeforeUnmount(() => {
  if (pollingTimer) clearInterval(pollingTimer)
})
</script>

<style scoped>
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
  background: #111827;
}

.control-bar {
  display: grid;
  grid-template-columns: 100px 1fr 120px;
  gap: 10px;
  padding: 12px;
  background: #0b1220;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.search-input,
.sort-filter,
.sort-dir-filter {
  min-width: 0;
  width: 100%;
}

.search-input {
  grid-column: 1 / -1;
}

.sort-filter {
  grid-column: 1 / 3;
}

.sort-dir-filter {
  grid-column: 3 / 4;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.plate-cell {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.plate-cell small {
  color: #64748b;
  font-size: 9px;
}

.status-icon {
  font-size: 18px;
}

/* 🔥 สีตาม requirement */

.status-icon.running {
  color: #22c55e; /* เขียว */
}

.status-icon.idle {
  color: #f59e0b; /* เหลือง */
}

.status-icon.parking {
  color: #64748b; /* เทา */
}

.status-icon.offline {
  color: #ef4444; /* แดง */
}

.status-icon.no_gps {
  color: #3b82f6; /* น้ำเงิน */
}

.status-icon {
  font-size: 18px;
  filter: drop-shadow(0 0 2px rgba(0,0,0,0.3));
}

.time-cell {
  width: 90px;
  color: #334155;
  font-size: 12px;
  line-height: 1.25;
}

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;
}

.control-bar {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: 100px 1fr 120px;
  gap: 10px;
  padding: 12px;
  background: #0b1220;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.search-input {
  grid-column: 1 / -1;
  width: 100%;
  min-width: 0;
}

:deep(.p-datatable-table-container) {
  overflow-y: auto !important;
}
</style>