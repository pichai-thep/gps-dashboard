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

        <Column header="Status" style="width: 60px">
          <template #body="slotProps">
            <i
                :class="[
                'pi',
                getStatusIcon(slotProps.data.status),
                'status-icon',
                slotProps.data.status,
              ]"
            ></i>
            {{slotProps.data.status}}
          </template>
        </Column>

        <Column header="" style="width: 50px">
          <template #body="slotProps">
            <template v-if="getDriverCardStatus(slotProps.data) !== 'hide'">

              <i
                  :class="[
                    'pi',
                    getDriverCardStatus(slotProps.data) === 'ok'
                      ? 'pi-id-card driver-ok'
                      : 'pi-id-card driver-missing'
                  ]"
                  v-tooltip="
                getDriverCardStatus(slotProps.data) === 'ok'
                  ? 'รูดบัตรแล้ว'
                  : 'ยังไม่รูดบัตร'
              "
              ></i>
            </template>
          </template>
        </Column>

        <Column field="plate_no" header="Plate">
          <template #body="slotProps">
            <div class="plate-cell">
              <strong>{{ slotProps.data.plate_no }}</strong>
              <span class="time-cell">
                Speed: {{ slotProps.data.speed ?? 0 }} km/h
                <br>Fuel: {{ formatFuel(slotProps.data.fuel_left) }}
              </span>
            </div>
          </template>
        </Column>

        <Column header="GPSTime" style="width: 110px">
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'

import FleetMap from '@/components/FleetMap.vue'
import { useAuthStore } from '@/stores/auth'
import {
  getCurrentTracking,
  getVehicleGroups,
  type VehicleGroup,
} from '@/services/tracking'
import type { Vehicle, VehicleStatus } from '@/types/fleet'

const auth = useAuthStore()

const vehicles = ref<Vehicle[]>([])
const selectedVehicleId = ref<string | null>(null)

const selectedGroupId = ref<number | string>(-1)
const statusFilter = ref<string | null>(null)
const refreshInterval = ref(30_000)
const search = ref('')

const page = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)

const sortBy = ref('plate_no')
const sortDir = ref<'asc' | 'desc'>('asc')

const loading = ref(false)
const error = ref<string | null>(null)
const hiddenVehicleKeys = ref<Set<string>>(new Set())

let pollingTimer: number | null = null
let searchTimer: number | null = null
let isLoading = false

const refreshOptions = [
  { label: '10 sec', value: 10_000 },
  { label: '30 sec', value: 30_000 },
  { label: '1 min', value: 60_000 },
]

const groupOptions = ref<VehicleGroup[]>([
  { id: -1, name: 'All Group' },
])

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Start', value: 'start' },
  { label: 'ACC on', value: 'acc_on' },
  { label: 'Parking', value: 'parking' },
  { label: 'No GPS', value: 'no_gps' },
  { label: 'Offline', value: 'offline' },
]

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

const mapVehicles = computed(() => {
  return vehicles.value.filter((vehicle) => {
    return !hiddenVehicleKeys.value.has(getVehicleKey(vehicle))
  })
})

async function loadVehicles() {
  if (isLoading) return

  try {
    isLoading = true
    loading.value = true
    error.value = null

    const response = await getCurrentTracking({
      page: page.value,
      per_page: perPage.value,
      group_id: selectedGroupId.value,
      status: statusFilter.value,
      search: search.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    })

    vehicles.value = response.vehicles
    totalRecords.value = response.meta.total
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'โหลดข้อมูลไม่ได้'
    vehicles.value = []
    totalRecords.value = 0
  } finally {
    isLoading = false
    loading.value = false
  }
}

async function loadGroups() {
  try {
    const customerId = auth.customer?.id

    if (!customerId) {
      groupOptions.value = [{ id: -1, name: 'All Group' }]
      return
    }

    const groups = await getVehicleGroups(customerId)
    groupOptions.value = [{ id: -1, name: 'All Group' }, ...groups]
  } catch (e) {
    console.error('LOAD GROUPS ERROR', e)
    groupOptions.value = [{ id: -1, name: 'All Group' }]
  }
}

function startPolling() {
  if (pollingTimer) {
    window.clearInterval(pollingTimer)
  }

  pollingTimer = window.setInterval(() => {
    if (!document.hidden) {
      loadVehicles()
    }
  }, refreshInterval.value)
}

function onPage(event: any) {
  page.value = event.page + 1
  perPage.value = event.rows
  loadVehicles()
}

function onRowClick(event: { data: Vehicle }) {
  selectVehicle(event.data)
}

function selectVehicle(vehicle: Vehicle) {
  selectedVehicleId.value = getVehicleKey(vehicle)
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

  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }

  hiddenVehicleKeys.value = next
}

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

function getStatusIcon(status: VehicleStatus) {
  return {
    running: 'pi-play-circle',
    start: 'pi-pause-circle',   // เหลือง
    acc_on: 'pi-key',          // ส้ม
    parking: 'pi-stop-circle',
    offline: 'pi-times-circle',
    no_gps: 'pi-exclamation-circle',
  }[status] || 'pi-circle'
}

function getDriverCardStatus(vehicle: any): 'ok' | 'missing' | 'hide' {
  const dlt_synch = Number(vehicle.dlt_synch ?? 0)
  const track3 = String(vehicle.track3 ?? '').trim()

  if (dlt_synch === 0) return 'hide'

  if (dlt_synch === 1 && track3.length > 0) return 'ok'

  return 'missing'
}

watch(
    [selectedGroupId, statusFilter, sortBy, sortDir],
    () => {
      page.value = 1
      selectedVehicleId.value = null
      loadVehicles()
    }
)

watch(search, () => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }

  searchTimer = window.setTimeout(() => {
    page.value = 1
    selectedVehicleId.value = null
    loadVehicles()
  }, 300)
})

watch(refreshInterval, () => {
  startPolling()
})

onMounted(async () => {
  if (!auth.customer) {
    await auth.fetchMe()
  }

  await loadGroups()
  await loadVehicles()
  startPolling()
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    window.clearInterval(pollingTimer)
  }

  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }
})


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
  background: #111827;
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

.control-bar {
  flex-shrink: 0;
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

.time-cell {
  width: 90px;
  color: #334155;
  font-size: 12px;
  line-height: 1.25;
}

.status-icon {
  font-size: 18px;
  filter: drop-shadow(0 0 2px rgba(0, 0, 0, 0.3));
}

.status-icon.running { color: #22c55e; }   /* เขียว */
.status-icon.start { color: #facc15; }     /* เหลือง */
.status-icon.acc_on { color: #f97316; }    /* ส้ม 🔥 */
.status-icon.parking { color: #64748b; }   /* เทา */
.status-icon.no_gps { color: #3b82f6; }    /* น้ำเงิน */
.status-icon.offline { color: #ef4444; }   /* แดง */

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;
}

.driver-ok {
  color: #22c55e; /* เขียว */
}

.driver-missing {
  color: #ef4444; /* แดง */
}

:deep(.p-datatable-table-container) {
  overflow-y: auto !important;
}
</style>