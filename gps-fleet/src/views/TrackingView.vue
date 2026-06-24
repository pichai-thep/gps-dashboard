<template>
  <div class="tracking-page">
    <aside class="vehicle-panel">
      <div class="panel-header">
        <Message v-show="error" severity="error" class="tracking-error">
          {{ error }}
        </Message>

        <div class="loading-text" :class="{ active: loading }">
          Loading...
        </div>

        <div class="header-title-row">
          <div>
            <h2>Current Tracking</h2>

            <p class="subtitle">
              <span>{{ mapVehicles.length }}</span>
              / {{ totalRecords }} vehicles on map
            </p>
          </div>

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
      </div>

      <div class="filter-toggle">
        <Button
            :label="showFilters ? 'Hide Filters' : 'Show Filters'"
            :icon="showFilters ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
            text
            size="small"
            @click="showFilters = !showFilters"
        />
      </div>

      <div v-show="showFilters" class="tracking-filters">
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
          class="vehicle-table"
          selectionMode="single"
          tableStyle="width: 100%; table-layout: fixed;"
          @row-click="onRowClick"
          @page="onPage"
      >
        <Column header="#" style="width: 30px">
          <template #body="slotProps">
            <div class="row-no">
              {{ (page - 1) * perPage + slotProps.index + 1 }}
            </div>
          </template>
        </Column>

        <Column header="Status" style="width: 120px">
          <template #body="slotProps">
            <div class="status-box">
              <div class="status-row">
                <i
                    :class="[
                    getStatusIcon(slotProps.data.status),
                    'status-icon',
                    slotProps.data.status,
                  ]"
                    :style="getStatusIconStyle(slotProps.data.status, slotProps.data.heading)"
                    v-tooltip="slotProps.data.status"
                />

                <span :class="['status-text', slotProps.data.status]">
                  {{ slotProps.data.status }}
                </span>
              </div>

              <div class="speed-text">
                {{ slotProps.data.speed ?? 0 }} km/h
              </div>

              <div class="fuel-badge" v-if="slotProps.data.fuel_left!=null">
                <i class="pi pi-car"></i>
                Fuel: {{ formatFuel(slotProps.data.fuel_left) }}
              </div>

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
            </div>
          </template>
        </Column>

        <Column field="plate_no" header="Vehicle">
          <template #body="slotProps">
            <div class="vehicle-box">
              <div class="plate-text">
                {{ slotProps.data.plate_no }}
              </div>

              <div
                  class="address-text"
                  :title="slotProps.data.address"
              >
                {{ slotProps.data.address ?? '-' }}
              </div>

              <div v-if="slotProps.data.track3"
                  class="address-text"
                  :title="slotProps.data.track3"
              >
                {{ slotProps.data.track3 ?? '-' }}
              </div>

            </div>
          </template>
        </Column>

        <Column header="GPS / Temp" style="width: 130px">
          <template #body="slotProps">
            <div class="gps-box">
              <div class="gps-line">
                <i class="pi pi-clock"></i>
                <span>{{ formatGpsTimeCompact(slotProps.data.gps_time) }}</span>
              </div>

              <div
                  class="gps-line satellite-line"
                  :class="{
                      low: Number(slotProps.data.num_sats ?? 0) < 4
                    }">
                <i class="pi pi-wave-pulse"></i>
                <span>Sat: {{ slotProps.data.num_sats ?? '-' }}</span>
              </div>

              <div
                  class="temp-line"
                  v-if="
                      slotProps.data.temperature !== null &&
                      slotProps.data.temperature !== undefined &&
                      slotProps.data.temperature !== ''
                    "
                                >
                                  <i class="pi pi-thermometer"></i>

                                  <span>
                      {{ slotProps.data.temperature }} °C
                    </span>
              </div>

            </div>
          </template>
        </Column>

        <Column header="" style="width: 42px">
          <template #body="slotProps">
            <div class="eye-box">
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
} from '../services/tracking'
import { useAuthStore } from '../stores/auth'
import type { Vehicle, VehicleStatus } from '../types/fleet'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

type StatusCount = {
  run: number
  idle: number
  park: number
  no_gps: number
  offline: number
}

type DriverStatus = 'ok' | 'missing' | 'no_license' | 'hide'

const showFilters = ref(false)
const auth = useAuthStore()

const defaultStatusCount: StatusCount = {
  run: 0,
  idle: 0,
  park: 0,
  no_gps: 0,
  offline: 0,
}

const refreshOptions = [
  { label: '10s', value: 10_000 },
  { label: '30s', value: 30_000 },
  { label: '1m', value: 60_000 },
]

const statusOptions = [
  { label: 'Run', value: 'run' },
  { label: 'Idle', value: 'idle' },
  { label: 'Park', value: 'park' },
  { label: 'No GPS', value: 'no_gps' },
  { label: 'Offline', value: 'offline' },
]

const statusSummaryItems = [
  { label: 'Run', value: 'run' },
  { label: 'Idle', value: 'idle' },
  { label: 'Park', value: 'park' },
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
const noDriverCardTotal = ref(0)
const hiddenVehicleKeys = ref<Set<string>>(new Set())

let pollingTimer: number | null = null
let searchTimer: number | null = null
let isLoading = false

const mapVehicles = computed(() => {
  return vehicles.value.filter((vehicle) => {
    return !hiddenVehicleKeys.value.has(getVehicleKey(vehicle))
  })
})

const noDriverCardCount = computed(() => noDriverCardTotal.value)

onMounted(async () => {
  statusFilter.value = getStatusFromQuery()
  noDriverCardFilter.value = getNoDriverCardFromQuery()
})

watch(
    () => route.query.status,
    () => {
      const nextStatus = getStatusFromQuery()

      if (statusFilter.value === nextStatus) return

      statusFilter.value = nextStatus
      resetListState()
      loadVehicles()
    }
)

watch(
    () => route.query.no_driver_card,
    () => {
      const nextNoDriverCard = getNoDriverCardFromQuery()

      if (noDriverCardFilter.value === nextNoDriverCard) return

      noDriverCardFilter.value = nextNoDriverCard
      resetListState()
      loadVehicles()
    }
)

function getStatusFromQuery(): VehicleStatus | null {
  const status = route.query.status

  if (typeof status !== 'string') return null

  const allowStatuses = statusOptions.map((item) => item.value)

  return allowStatuses.includes(status as VehicleStatus)
      ? status as VehicleStatus
      : null
}

function getNoDriverCardFromQuery(): boolean {
  const value = route.query.no_driver_card

  return Array.isArray(value)
      ? value.includes('1')
      : value === '1'
}

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
    noDriverCardTotal.value = response.meta.no_driver_card_count ?? 0
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'โหลดข้อมูลไม่ได้'
    vehicles.value = []
    totalRecords.value = 0
    statusCount.value = { ...defaultStatusCount }
    noDriverCardTotal.value = 0
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

  router.replace({
    query: {
      ...route.query,
      status: status || undefined,
    },
  })

  loadVehicles()
}

function loadByStatus(status: VehicleStatus) {
  setStatusAndLoad(statusFilter.value === status ? null : status)
}

function toggleNoDriverCardFilter() {
  noDriverCardFilter.value = !noDriverCardFilter.value
  resetListState()

  router.replace({
    query: {
      ...route.query,
      no_driver_card: noDriverCardFilter.value ? 1 : undefined,
    },
  })

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
      Number(vehicle.speed ?? 0) > 5 &&
      !String(vehicle.track3 ?? '').trim()
  )
}

function getStatusIcon(status: VehicleStatus) {
  return {
    run: 'pi pi-arrow-circle-up',
    idle: 'pi pi-arrow-circle-up',
    park: 'pi pi-stop-circle',
    no_gps: 'pi pi-exclamation-circle',
    offline: 'pi pi-exclamation-triangle',
  }[status] || 'pi pi-circle'
}

function getStatusIconStyle(status: VehicleStatus, heading?: number | null) {
  const shouldRotate = status === 'run' || status === 'idle'

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

  if (dltSynch !== 1 || speed <= 5) {
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
  display: grid;
  grid-template-columns: minmax(420px, 32vw) minmax(0, 1fr);
  gap: 16px;
  width: 100%;
  height: calc(100vh - 118px);
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  background: #020617;
}

.vehicle-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border-radius: 20px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: #0f172a;
}

/* header */
.panel-header {
  position: relative;
  flex-shrink: 0;
  padding: 18px 22px 10px;
  background: #0f172a;
}

.header-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.panel-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 22px;
  font-weight: 950;
  letter-spacing: -0.04em;
  line-height: 1.05;
}

.subtitle {
  margin: 16px 0 0;
  color: #94a3b8;
  font-size: 13px;
  font-weight: 800;
}

.subtitle span {
  color: #22c55e;
}

.loading-text {
  position: absolute;
  top: 8px;
  right: 22px;
  opacity: 0;
  pointer-events: none;
  color: #60a5fa;
  font-size: 11px;
  font-weight: 800;
}

.loading-text.active {
  opacity: 1;
}

/* refresh */
.refresh-field {
  display: flex;
  align-items: center;
  gap: 7px;
  width: 96px;
  height: 40px;
  padding: 0 10px;
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: #111827;
}

.refresh-field > .pi {
  color: #94a3b8;
  font-size: 14px;
}

.refresh-field :deep(.p-dropdown),
.refresh-field :deep(.p-select) {
  flex: 1;
  min-width: 0;
  height: 38px;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
}

.refresh-field :deep(.p-dropdown-label),
.refresh-field :deep(.p-select-label) {
  padding: 0 !important;
  color: #f8fafc !important;
  font-size: 14px !important;
  font-weight: 900;
  line-height: 38px;
}

.refresh-field :deep(.p-dropdown-trigger),
.refresh-field :deep(.p-select-dropdown) {
  width: 18px;
  color: #94a3b8 !important;
}

/* filter */
.filter-toggle {
  flex-shrink: 0;
  padding: 6px 22px 14px;
  background: #0f172a;
}

.filter-toggle :deep(.p-button) {
  padding: 0;
  color: #34d399 !important;
  font-size: 14px;
  font-weight: 900;
}

.filter-toggle :deep(.p-button:hover) {
  background: transparent !important;
}

.tracking-filters {
  flex-shrink: 0;
  padding: 0 12px 12px;
  background: #0f172a;
}

.summary-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 0 8px 10px;
}

.summary-item {
  height: 24px;
  padding: 0 7px;
  border: 0;
  border-radius: 999px;
  color: #ffffff;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}

.summary-item.run,
.summary-item.running {
  background: #16a34a;
}

.summary-item.idle {
  background: #eab308;
}

.summary-item.park,
.summary-item.parking {
  background: #ef4444;
}

.summary-item.no_gps {
  background: #2563eb;
}

.summary-item.offline {
  background: #64748b;
}

.summary-item.no-driver-card {
  background: #b91c1c;
}

.summary-item.active {
  outline: 2px solid rgba(255, 255, 255, 0.85);
  outline-offset: 2px;
}

.control-bar {
  display: grid;
  grid-template-columns: 1.4fr 1.6fr 90px;
  gap: 5px;
  padding: 7px;
  border-radius: 14px;
  background: #020617;
  border: 1px solid rgba(148, 163, 184, 0.14);
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
  gap: 7px;
  min-width: 0;
  height: 34px;
  padding: 0 9px;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: #0f172a;
}

.filter-field > .pi {
  color: #94a3b8;
  font-size: 13px;
}

.filter-field :deep(.p-dropdown),
.filter-field :deep(.p-select),
.filter-field :deep(.p-inputtext) {
  flex: 1;
  width: 100%;
  min-width: 0;
  height: 32px;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  color: #e5e7eb !important;
  font-size: 12px !important;
}

.filter-field :deep(.p-dropdown-label),
.filter-field :deep(.p-select-label),
.filter-field :deep(.p-inputtext) {
  padding: 0 !important;
  color: #e5e7eb !important;
  font-size: 12px !important;
  font-weight: 800;
  line-height: 32px;
}

/* datatable */
.vehicle-table {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #0f172a !important;
}

.vehicle-table :deep(.p-datatable),
.vehicle-table :deep(.p-datatable-wrapper),
.vehicle-table :deep(.p-datatable-table-container) {
  flex: 1;
  min-height: 0;
  background: #0f172a !important;
  overflow-x: hidden !important;
  overflow-y: auto !important;
}

.vehicle-table :deep(.p-datatable-table) {
  width: 100% !important;
  table-layout: fixed !important;
  background: #0f172a !important;
}

.vehicle-table :deep(.p-datatable-thead > tr > th) {
  height: 42px;
  padding: 7px 10px !important;
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-top: 1px solid rgba(148, 163, 184, 0.16) !important;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2) !important;
  font-size: 14px !important;
  font-weight: 900 !important;
  line-height: 1.12;
}

.vehicle-table :deep(.p-datatable-tbody > tr) {
  background: #0f172a !important;
}

.vehicle-table :deep(.p-datatable-tbody > tr:hover) {
  background: #182235 !important;
}

.vehicle-table :deep(.p-datatable-tbody > tr > td) {
  height: 72px;
  padding: 8px 10px !important;
  background: transparent !important;
  color: #e5e7eb !important;
  border-bottom: 1px solid rgba(148, 163, 184, 0.14) !important;
  vertical-align: middle !important;
  overflow: hidden;
}

.vehicle-table :deep(.selected-vehicle-row) {
  background: #1e293b !important;
  box-shadow: inset 3px 0 0 #22c55e;
}

/* cells */
.row-no {
  text-align: center;
  color: #f8fafc;
  font-size: 12px;
  font-weight: 900;
}

.status-box {
  min-height: 66px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  min-width: 0;
  padding-left: 8px;
  border-left: 1px solid rgba(148, 163, 184, 0.14);
}

.status-row {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
}

.status-icon {
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 22px;
}

.status-icon.run,
.status-icon.running,
.status-icon.moving {
  color: #22c55e;
}

.status-icon.idle {
  color: #facc15;
}

.status-icon.park,
.status-icon.parking {
  color: #ef4444;
}

.status-icon.no_gps {
  color: #3b82f6;
}

.status-icon.offline {
  color: #9ca3af;
}

.status-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 18px;
  font-weight: 500;
  line-height: 1;
  text-transform: lowercase;
}

.status-text.run,
.status-text.running,
.status-text.moving {
  color: #22c55e;
}

.status-text.idle {
  color: #facc15;
}

.status-text.park {
  color: #ef4444;
}

.status-text.no_gps {
  color: #3b82f6;
}

.status-text.offline {
  color: #9ca3af;
}

.speed-text {
  color: #cbd5e1;
  font-size: 14px;
  font-weight: 500;
  line-height: 2;
  white-space: nowrap;
}

.fuel-badge {
  width: fit-content;
  max-width: 100%;
  height: 24px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 8px;
  border-radius: 8px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: #1e293b;
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.fuel-badge .pi {
  color: #94a3b8;
  font-size: 12px;
}

.vehicle-box {
  min-height: 66px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  padding-left: 12px;
  border-left: 1px solid rgba(148, 163, 184, 0.14);
}

.plate-text {
  max-width: 100%;
  color: #f8fafc;
  font-size: 16px;
  font-weight: 900;
  line-height: 1.15;
  white-space: normal;
  word-break: normal;
  overflow-wrap: anywhere;
}

.address-text {
  max-width: 100%;
  color: #cbd5e1;
  font-size: 12px;
  font-weight: 400;
  line-height: 1.35;
  white-space: normal;
  word-break: normal;
  overflow-wrap: anywhere;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.gps-box {
  min-height: 66px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 7px;
  padding-left: 12px;
  border-left: 1px solid rgba(148, 163, 184, 0.14);
}

.gps-line,
.temp-line {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #e5e7eb;
  font-size: 13px;
  font-weight:600;
  white-space: nowrap;
}

.gps-line .pi,
.temp-line .pi {
  color: #60a5fa;
  font-size: 16px;
}

.eye-box {
  height: 66px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-left: 1px solid rgba(148, 163, 184, 0.14);
}

.eye-box :deep(.p-button) {
  width: 28px;
  height: 28px;
  padding: 0;
  color: #22c55e !important;
}

.eye-box :deep(.p-button:hover) {
  background: rgba(34, 197, 94, 0.12) !important;
}

.eye-box :deep(.p-button .pi) {
  font-size: 16px;
}

.satellite-line .pi {
  color: #22c55e;
}
.satellite-line.low {
  color: #ef4444;
  font-weight: 800;
}

.satellite-line.low .pi {
  color: #ef4444;
}

/* paginator */
.vehicle-table :deep(.p-paginator) {
  flex-shrink: 0;
  min-height: 48px;
  display: flex !important;
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-top: 1px solid rgba(148, 163, 184, 0.18) !important;
}

.vehicle-table :deep(.p-paginator .p-paginator-page),
.vehicle-table :deep(.p-paginator .p-paginator-next),
.vehicle-table :deep(.p-paginator .p-paginator-prev),
.vehicle-table :deep(.p-paginator .p-paginator-first),
.vehicle-table :deep(.p-paginator .p-paginator-last) {
  color: #94a3b8 !important;
  border-radius: 10px;
}

.vehicle-table :deep(.p-paginator .p-paginator-page.p-highlight),
.vehicle-table :deep(.p-paginator .p-paginator-page.p-paginator-page-selected) {
  background: #16a34a !important;
  color: #ffffff !important;
  border-color: #22c55e !important;
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

/* map */
.map-area {
  display: flex;
  height: 100%;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border-radius: 20px;
}

@media (max-width: 1280px) {
  .tracking-page {
    grid-template-columns: minmax(500px, 42vw) minmax(0, 1fr);
  }

  .panel-header h2 {
    font-size: 24px;
  }

  .vehicle-table :deep(.p-datatable-tbody > tr > td) {
    height: 78px;
  }

}


</style>
