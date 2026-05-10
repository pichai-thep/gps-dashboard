<template>
  <div class="tracking-page">
    <aside class="vehicle-panel">
      <div class="panel-header">
        <Message v-show="error" severity="error" class="tracking-error">
          {{ error }}
        </Message>

        <div class="loading-text" :class="{ active: loading }">
          Loading tracking...
        </div>

        <h2>Current Tracking</h2>

        <div class="panel-subtitle">
          <p>{{ mapVehicles.length }} / {{ totalRecords }} vehicles on map</p>

          <div class="refresh-field">
            <i class="pi pi-refresh"></i>

            <Dropdown
                v-model="refreshInterval"
                :options="refreshOptions"
                optionLabel="label"
                optionValue="value"
            />
          </div>
        </div>

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
            no-license {{ noDriverCardCount }}
          </button>
        </div>
      </div>

      <div class="control-bar">
        <div class="filter-field group-filter">
          <i class="pi pi-users"></i>

          <Dropdown
              v-model="selectedGroupId"
              :options="groupOptions"
              optionLabel="name"
              optionValue="id"
              placeholder="Group"
          />
        </div>

        <div class="filter-field search-input">
          <i class="pi pi-search"></i>

          <InputText
              v-model="search"
              placeholder="Search vehicle..."
          />
        </div>

        <div class="filter-field status-filter">
          <i class="pi pi-flag"></i>

          <Dropdown
              :modelValue="statusFilter"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Status"
              showClear
              @update:modelValue="setStatusAndLoad"
          />
        </div>

        <div class="filter-field sort-filter">
          <i class="pi pi-sort-alpha-down"></i>

          <Dropdown
              v-model="sortBy"
              :options="sortOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Sort by"
          />
        </div>

        <div class="filter-field sort-dir-filter">
          <i class="pi pi-sort-alt"></i>

          <Dropdown
              v-model="sortDir"
              :options="sortDirOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="ASC"
          />
        </div>
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
          scrollHeight="calc(150vh - 450px)"
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
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'

import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'

import TrackingMap from '@/components/maps/TrackingMap.vue'
import {
  getCurrentTracking,
  getVehicleGroups,
  type VehicleGroup,
} from '@/services/tracking'
import { useAuthStore } from '@/stores/auth'
import type { Vehicle, VehicleStatus } from '@/types/fleet'
import {getRecentNotifications} from "@/services/notificationApi";

type StatusCount = Record<VehicleStatus, number>
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
  { label: '10s', value: 10_000 },
  { label: '30s', value: 30_000 },
  { label: '1m', value: 60_000 },
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
const noDriverCardFilter = ref(false)

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

onMounted(async () => {
  // const items = await getRecentNotifications()

  // console.log(items)
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

function toggleNoDriverCardFilter() {
  noDriverCardFilter.value = !noDriverCardFilter.value
  resetListState()
  loadVehicles()
}

function stopPolling() {
  if (!pollingTimer) return

  window.clearInterval(pollingTimer)
  pollingTimer = null
}

function startPolling() {
  stopPolling()

  pollingTimer = window.setInterval(() => {
    loadVehicles()
  }, refreshInterval.value)
}

function handleVisibilityChange() {
  if (document.hidden) {
    stopPolling()
    return
  }

  loadVehicles()
  startPolling()
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

function isNoDriverCard(vehicle: any): boolean {
  return (
      Number(vehicle.dltSynch ?? vehicle.dlt_synch ?? 0) === 1 &&
      Number(vehicle.speed ?? 0) > 0 &&
      !String(vehicle.track3 ?? '').trim()
  )
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
    transform:
        shouldRotate && heading != null
            ? `rotate(${Number(heading)}deg)`
            : undefined,
  }
}

function getDriverStatus(vehicle: any): DriverStatus {
  const dltSynch = Number(vehicle.dltSynch ?? vehicle.dlt_synch ?? 0)
  const speed = Number(vehicle.speed ?? 0)
  const track3 = String(vehicle.track3 ?? '').trim()

  if (dltSynch !== 1 || speed <= 0) {
    return 'hide'
  }

  return track3 ? 'ok' : 'missing'
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
  if (value === null || value === undefined || value === '') {
    return '-'
  }

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
  if (!document.hidden) {
    startPolling()
  }
})

onMounted(async () => {
  if (!auth.customer) {
    await auth.fetchMe()
  }

  await loadGroups()
  await loadVehicles()

  startPolling()

  document.addEventListener(
      'visibilitychange',
      handleVisibilityChange
  )
})

onBeforeUnmount(() => {
  stopPolling()

  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }

  document.removeEventListener(
      'visibilitychange',
      handleVisibilityChange
  )
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
  display: flex;
  flex-direction: column;
  height: 100%;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border-radius: 18px;
  background: #111827;
}

.panel-header {
  position: relative;
  flex-shrink: 0;
  padding: 10px 10px 10px;
  background: #111827;
}

.loading-text {
  position: absolute;
  top: 10px;
  right: 16px;
  opacity: 0;
  pointer-events: none;
  color: #93c5fd;
  font-size: 11px;
  font-weight: 700;
  transition: opacity 0.15s ease;
}

.loading-text.active {
  opacity: 1;
}

.panel-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.panel-subtitle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2px;
  margin-top: 2px;
}

.panel-subtitle p {
  margin: 0;
  color: #9ca3af;
  font-size: 13px;
  font-weight: 500;
}

.refresh-field {
  display: flex;
  align-items: center;
  gap: 6px;

  width: 82px;
  height: 34px;
  padding: 0 8px;

  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.25);

  background: #0f172a;
}

.refresh-field > .pi {
  color: #94a3b8;
  font-size: 13px;
}

.refresh-field :deep(.p-dropdown),
.refresh-field :deep(.p-select) {
  flex: 1;
  min-width: 0;
  height: 32px;

  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
}

.refresh-field :deep(.p-dropdown-label),
.refresh-field :deep(.p-select-label) {
  padding: 0 !important;

  color: #e5e7eb !important;

  font-size: 13px !important;
  font-weight: 700;
  line-height: 32px;
}

.refresh-field :deep(.p-dropdown-trigger),
.refresh-field :deep(.p-select-dropdown) {
  width: 20px;
  color: #94a3b8 !important;
}

.summary-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.summary-item {
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 6px;
  border: 0;
  border-radius: 999px;
  color: #ffffff;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
  cursor: pointer;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.summary-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
}

.summary-item.active {
  outline: 2px solid rgba(255, 255, 255, 0.85);
  outline-offset: 2px;
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

.summary-item.offline,
.summary-item.no-driver-card {
  background: #ef4444;
}

.control-bar {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: 1.7fr 1.4fr 100px;
  gap: 5px;
  padding: 5px;
  background: #020617;
  border-top: 1px solid rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.group-filter {
  grid-column: 1 / 2;
}

.search-input {
  grid-column: 2 / 4;
}

.status-filter {
  grid-column: 1 / 2;
}

.sort-filter {
  grid-column: 2 / 3;
}

.sort-dir-filter {
  grid-column: 3 / 4;
}

.filter-field {
  display: flex;
  align-items: center;
  gap: 8px;

  min-width: 0;
  height: 38px;
  padding: 0 10px;

  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.25);

  background: #0f172a;
}

.filter-field > .pi {
  flex: 0 0 auto;
  color: #94a3b8;
  font-size: 14px;
}

.filter-field :deep(.p-dropdown),
.filter-field :deep(.p-select),
.filter-field :deep(.p-inputtext) {
  flex: 1;
  width: 100%;
  min-width: 0;
  height: 36px;
  font-size: 12px !important;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;

  color: #e5e7eb !important;
}

.filter-field :deep(.p-dropdown-label),
.filter-field :deep(.p-select-label),
.filter-field :deep(.p-inputtext) {
  padding: 0 !important;

  color: #e5e7eb !important;

  font-size: 12px !important;
  font-weight: 700;
  line-height: 36px;
}

.filter-field :deep(.p-placeholder) {
  color: #64748b !important;
}

.filter-field :deep(.p-dropdown-trigger),
.filter-field :deep(.p-select-dropdown) {
  width: 24px;
  color: #94a3b8 !important;
}

.filter-field:focus-within {
  border-color: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
}

.tracking-error {
  margin-bottom: 8px;
}

.vehicle-table {
  flex: 1;
  min-height: 0;
  overflow: hidden;
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

:deep(.p-datatable-thead > tr > th) {
  padding: 12px;
  background: #ffffff !important;
  color: #334155 !important;
  font-size: 15px;
  font-weight: 800;
  border-bottom: 1px solid #e2e8f0 !important;
}

:deep(.p-datatable-tbody > tr > td) {
  padding: 12px;
  color: #334155;
  border-bottom: 1px solid #e2e8f0 !important;
}

:deep(.selected-vehicle-row) {
  background: #ecfdf5 !important;
}

:deep(.selected-vehicle-row td) {
  border-top: 1px solid #22c55e !important;
  border-bottom: 1px solid #22c55e !important;
}

.status-icon {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  filter: saturate(1.35) contrast(1.2) drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
  transition: transform 0.15s ease;
}

.status-icon.running {
  color: #22c55e;
}

.status-icon.idle {
  color: #eab308;
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

.plate-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.plate-cell strong {
  display: block;
  max-width: 100%;
  color: #334155;
  font-size: 16px;
  font-weight: 800;
  line-height: 1.15;
  word-break: break-word;
}

.time-cell {
  width: auto;
  max-width: 100%;
  overflow: hidden;
  color: #64748b;
  font-size: 12px;
  line-height: 1.3;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.time-cell-gps {
  color: #334155;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.eye-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.eye-cell :deep(.p-button) {
  width: 28px;
  height: 28px;
  padding: 0;
  color: #22c55e;
}

.eye-cell :deep(.p-button:hover) {
  background: rgba(34, 197, 94, 0.12);
}

.eye-cell :deep(.p-button .pi) {
  font-size: 16px;
}

:deep(.p-datatable-tbody > tr > td:last-child) {
  padding-right: 8px;
}

:deep(.p-datatable-thead > tr > th) {
  background: #111827 !important;
  color: #cbd5e1 !important;
}
:deep(.p-datatable-tbody > tr > td) {
  padding: 14px 12px;
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

.map-area {
  display: flex;
  min-width: 0;
  height: 100%;
}
</style>