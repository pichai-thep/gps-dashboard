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

        <div class="summary-row">
          <button
              v-for="item in statusSummaryItems"
              :key="item.value"
              type="button"
              class="summary-item"
              :class="[item.value, { active: statusFilter === item.value }]"
              @click="loadByStatus(item.value)"
          >
            {{ item.label }} {{ statusCount[item.value] }}
          </button>

          <button
              type="button"
              class="summary-item no-driver-card"
              :class="{ active: noDriverCardFilter }"
              @click="toggleNoDriverCardFilter"
          >
            ไม่ได้รูดบัตร {{ noDriverCardCount }}
          </button>
        </div>

      </div>

      <div class="control-bar">
        <Dropdown
            v-model="selectedGroupId"
            :options="groupOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Group"
        />

        <Dropdown
            :modelValue="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Status"
            showClear
            @update:modelValue="setStatusAndLoad"
        />

        <Dropdown
            v-model="refreshInterval"
            :options="refreshOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Interval"
        />

        <InputText
            v-model="search"
            placeholder="search"
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
          :rowClass="getRowClass"
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
          tableStyle="width: 100%; table-layout: fixed;"
          @row-click="onRowClick"
          @page="onPage"
      >
        <Column header="#" style="width: 36px">
          <template #body="slotProps">
            {{ (page - 1) * perPage + slotProps.index + 1 }}
          </template>
        </Column>

        <Column header="Status" style="width: 105px">
          <template #body="slotProps">
            <i
                :class="[
                getStatusIcon(slotProps.data.status),
                'status-icon',
                slotProps.data.status,
              ]"
                :style="getStatusIconStyle(slotProps.data.status, slotProps.data.heading)"
                v-tooltip="slotProps.data.status"
            />

            {{ slotProps.data.status }}

            <template v-if="getDriverStatus(slotProps.data) !== 'hide'">
              <i
                  :class="[
                  'pi',
                  getDriverIcon(getDriverStatus(slotProps.data)),
                  getDriverClass(getDriverStatus(slotProps.data)),
                ]"
                  v-tooltip="getDriverTooltip(getDriverStatus(slotProps.data))"
              />
            </template>
          </template>
        </Column>

        <Column field="plate_no" header="Plate">
          <template #body="slotProps">
            <div class="plate-cell">
              <strong>{{ slotProps.data.plate_no }}</strong>

              <span class="time-cell">
                Speed: {{ slotProps.data.speed ?? 0 }} km/h
                <br />
                Fuel: {{ formatFuel(slotProps.data.fuel_left) }}
              </span>
            </div>
          </template>
        </Column>

        <Column header="GPSTime" style="width: 90px">
          <template #body="slotProps">
            <div class="time-cell-gps">
              {{ formatGpsTimeCompact(slotProps.data.gps_time) }}
            </div>
          </template>
        </Column>

<!--        <Column header="" style="width: 56px; min-width: 56px; text-align: center">-->
<!--          <template #body="slotProps">-->
<!--            <div class="eye-cell">-->
<!--              <Button-->
<!--                  text-->
<!--                  rounded-->
<!--                  size="small"-->
<!--                  :icon="isVehicleVisible(slotProps.data) ? 'pi pi-eye' : 'pi pi-eye-slash'"-->
<!--                  @click.stop="toggleVehicleVisible(slotProps.data)"-->
<!--              />-->
<!--            </div>-->
<!--          </template>-->
<!--        </Column>-->

        <Column header="" style="width: 44px">
          <template #body="slotProps">
            <div class="eye-cell">
              <Button
                  text
                  rounded
                  size="small"
                  :icon="isVehicleVisible(slotProps.data) ? 'pi pi-eye' : 'pi pi-eye-slash'"
                  @click.stop="toggleVehicleVisible(slotProps.data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </aside>

    <section class="map-area">
      <TrackingMap
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

import TrackingMap from '@/components/maps/TrackingMap.vue'
import { getCurrentTracking, getVehicleGroups, type VehicleGroup } from '@/services/tracking'
import { useAuthStore } from '@/stores/auth'
import type { Vehicle, VehicleStatus } from '@/types/fleet'

type StatusCount = Record<VehicleStatus, number>
const noDriverCardFilter = ref(false)
type DriverStatus = 'ok' | 'missing' | 'no_license' | 'hide'

const auth = useAuthStore()

const defaultStatusCount: StatusCount = {
  running: 0,
  idle: 0,
  acc_on: 0,
  parking: 0,
  no_gps: 0,
  offline: 0,
}

const refreshOptions = [
  { label: '10 sec', value: 10_000 },
  { label: '30 sec', value: 30_000 },
  { label: '1 min', value: 60_000 },
]

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'ACC:on', value: 'acc_on' },
  { label: 'Parking', value: 'parking' },
  { label: 'No GPS', value: 'no_gps' },
  { label: 'Offline', value: 'offline' },
]

const statusSummaryItems = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'ACC:on', value: 'acc_on' },
  { label: 'Parking', value: 'parking' },
  { label: 'No GPS', value: 'no_gps' },
  { label: 'Offline', value: 'offline' },
] as const

const driverSummaryItems = [
  { label: 'OK', value: 'ok' },
  { label: 'No License', value: 'no_license' },
  { label: 'Missing', value: 'missing' },
] as const

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

const vehicles = ref<Vehicle[]>([])
const groupOptions = ref<VehicleGroup[]>([{ id: -1, name: 'All Group' }])

const selectedVehicleId = ref<string | null>(null)
const selectedGroupId = ref<number | string>(-1)
const statusFilter = ref<VehicleStatus | null>(null)
const driverFilter = ref<DriverStatus | null>(null)

const refreshInterval = ref(30_000)
const search = ref('')

const page = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)

const sortBy = ref('plate_no')
const sortDir = ref<'asc' | 'desc'>('asc')

const loading = ref(false)
const error = ref<string | null>(null)

const statusCount = ref<StatusCount>({ ...defaultStatusCount })
const hiddenVehicleKeys = ref<Set<string>>(new Set())

let pollingTimer: number | null = null
let searchTimer: number | null = null
let isLoading = false

const mapVehicles = computed(() => {
  return vehicles.value.filter((vehicle) => {
    return !hiddenVehicleKeys.value.has(getVehicleKey(vehicle))
  })
})

const noDriverCardCount = computed(() => {
  return vehicles.value.filter(isNoDriverCard).length
})

const driverCount = computed(() => {
  const counts = {
    ok: 0,
    no_license: 0,
    missing: 0,
  }

  for (const vehicle of vehicles.value) {
    const status = getDriverStatus(vehicle)

    if (status === 'ok' || status === 'no_license' || status === 'missing') {
      counts[status]++
    }
  }

  return counts
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
      no_driver_card: noDriverCardFilter.value ? 1 : null,
      search: search.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    })

    vehicles.value = response.vehicles
    totalRecords.value = response.meta.total
    statusCount.value = response.meta.status_counts ?? { ...defaultStatusCount }
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'โหลดข้อมูลไม่ได้'
    vehicles.value = []
    totalRecords.value = 0
    statusCount.value = { ...defaultStatusCount }
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

function resetListState() {
  page.value = 1
  selectedVehicleId.value = null
}

function setStatusAndLoad(status: VehicleStatus | null) {
  statusFilter.value = status
  resetListState()
  loadVehicles()
}

function loadByStatus(status: VehicleStatus) {
  setStatusAndLoad(statusFilter.value === status ? null : status)
}

function loadByDriver(status: DriverStatus) {
  driverFilter.value = driverFilter.value === status ? null : status
  resetListState()
  loadVehicles()
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

function isNoDriverCard(vehicle: any): boolean {
  return (
      Number(vehicle.dlt_synch ?? 0) === 1 &&
      Number(vehicle.speed ?? 0) > 0 &&
      getDriverStatus(vehicle) === 'missing'
  )
}

function toggleNoDriverCardFilter() {
  noDriverCardFilter.value = !noDriverCardFilter.value
  resetListState()
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

    if (selectedVehicleId.value === key) {
      selectedVehicleId.value = null
    }
  }

  hiddenVehicleKeys.value = next
}

function getRowClass(vehicle: Vehicle) {
  return {
    'selected-vehicle-row': selectedVehicleId.value === getVehicleKey(vehicle),
  }
}

function getStatusIcon(status: VehicleStatus) {
  return {
    running: 'pi pi-arrow-circle-up',
    idle: 'pi pi-arrow-circle-up',
    acc_on: 'pi pi-key',
    parking: 'pi pi-stop-circle',
    no_gps: 'pi pi-exclamation-circle',
    offline: 'pi pi-exclamation-triangle',
  }[status] || 'pi pi-circle'
}

function getStatusIconStyle(status: VehicleStatus, heading?: number | null) {
  const shouldRotate = status === 'running' || status === 'idle'

  return {
    transform: shouldRotate && heading != null
        ? `rotate(${Number(heading)}deg)`
        : undefined,
  }
}

function getDriverStatus(vehicle: any): DriverStatus {
  return vehicle.driver_status ?? 'hide'
}

function getDriverIcon(status: DriverStatus): string {
  return {
    ok: 'pi-id-card',
    missing: 'pi-id-card',
    no_license: 'pi-exclamation-circle',
    hide: '',
  }[status]
}

function getDriverClass(status: DriverStatus): string {
  return {
    ok: 'driver-ok',
    missing: 'driver-missing',
    no_license: 'driver-no-license',
    hide: '',
  }[status]
}

function getDriverTooltip(status: DriverStatus): string {
  return {
    ok: 'รูดบัตรแล้ว',
    missing: 'ยังไม่รูดบัตร',
    no_license: 'ไม่มีใบขับขี่',
    hide: '',
  }[status]
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

watch([selectedGroupId, sortBy, sortDir], () => {
  resetListState()
  loadVehicles()
})

watch(search, () => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }

  searchTimer = window.setTimeout(() => {
    resetListState()
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

.panel-header {
  padding: 16px 22px 14px;
  overflow: visible;
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
  grid-template-columns: 1fr 140px 100px;
  gap: 10px;
  padding: 12px;
  background: #0b1220;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.search-input,
.sort-filter,
.sort-dir-filter {
  width: 100%;
}

.search-input {
  grid-column: 1;
}

.sort-filter {
  grid-column: 2;
}

.sort-dir-filter {
  grid-column: 3;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.plate-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.plate-cell strong {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
}

.plate-cell small {
  color: #64748b;
  font-size: 9px;
}

.time-cell {
  font-size: 12px;
  color: #64748b;
  line-height: 1.25;

  display: -webkit-box;
  -webkit-line-clamp: 2;   /* 👈 จำกัด 2 บรรทัด */
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.time-cell-gps {
  font-size: 12px;
  color: #334155;
  white-space: nowrap;
}

.status-summary,
.driver-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}


.eye-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.eye-cell :deep(.p-button) {
  width: 30px;
  height: 30px;
  padding: 0;
}

.eye-cell :deep(.p-button .pi) {
  font-size: 16px;
}

.summary-item {
  border: 0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
}

.summary-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
}

.summary-item.active {
  outline: 3px solid rgba(255, 255, 255, 0.85);
  outline-offset: 2px;
}

.summary-item:not(.active) {
  opacity: 0.9;
}

.summary-item.running {
  background: #16a34a;
}

.summary-item.idle {
  background: #eab308;
}

.summary-item.acc_on {
  background: #f97316;
}

.summary-item.parking {
  background: #6b7280;
}

.summary-item.no_gps {
  background: #2563eb;
}

.summary-item.offline {
  background: #ef4444;
}

.summary-item.ok {
  background: #22c55e;
}

.summary-item.no_license {
  background: #f59e0b;
}

.summary-item.missing {
  background: #ef4444;
}

.status-icon {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 500;
  line-height: 1;
  filter: saturate(1.35) contrast(1.2) drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
  transition: transform 0.15s ease;
}

.status-icon.running {
  color: #22c55e;
}

.status-icon.idle {
  color: #aa9000;
}

.status-icon.acc_on {
  color: #f97316;
}

.status-icon.parking {
  color: #64748b;
}

.status-icon.no_gps {
  color: #3b82f6;
}

.status-icon.offline {
  color: #ef4444;
}

.summary-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
  align-items: center;
}

.summary-item.no-driver-card {
  background: #ef4444;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.plate-cell {
  min-width: 0;
}

.plate-cell strong {
  word-break: break-word;
  line-height: 1.15;
}

:deep(.p-datatable-table-container) {
  width: 100% !important;
  max-width: 100% !important;
  overflow-x: hidden !important;
  overflow-y: auto !important;
}

:deep(.p-datatable-table) {
  width: 100% !important;
  table-layout: fixed !important;
}

:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr > td) {
  overflow: visible;
}

.time-cell {
  width: auto;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.plate-cell {
  min-width: 0;
}

.plate-cell strong {
  display: block;
  max-width: 100%;
  word-break: break-word;
  line-height: 1.15;
}

.time-cell {
  width: auto;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:deep(.p-datatable-tbody > tr > td:last-child) {
  padding-right: 8px;
}

:deep(.p-button .pi) {
  font-size: 16px;
}

:deep(.selected-vehicle-row) {
  background: #ecfdf5 !important;
}

:deep(.selected-vehicle-row td) {
  border-top: 1px solid #22c55e !important;
  border-bottom: 1px solid #22c55e !important;
}

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;
}

.driver-ok {
  color: #22c55e;
}

.driver-missing {
  color: #ef4444;
}

.driver-no-license {
  color: #f59e0b;
}

</style>