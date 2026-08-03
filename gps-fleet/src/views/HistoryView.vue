<template>
  <div class="history-page">
    <div class="left-panel">

      <div class="filter-card">
        <div class="card-header">
          <div>
            <h2>{{ t('historyTracking') }}</h2>
            <p>{{ t('historySubtitle') }}</p>
          </div>
          <div class="filter-toggle">
            <Button
                :label="showFilters ? t('hideFilters') : t('showFilters')"
                :icon="showFilters ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                text
                size="small"
                @click="showFilters = !showFilters"
            />
          </div>
        </div>



        <div v-show="showFilters" class="tracking-filters">
          <div class="field-grid">
            <div class="form-group">
              <label>{{ t('group') }}</label>

              <Select
                  v-model="selectedGroup"
                  :options="groups"
                  optionLabel="group_name"
                  optionValue="group_id"
                  :placeholder="t('allGroup')"
                  fluid
                  filter
                  showClear
                  :disabled="pageLoading"
              />

            </div>
            <div class="form-group">
              <label>{{ t('vehicle') }}</label>

              <AutoComplete
                  v-model="selectedVehicleOption"
                  :suggestions="filteredVehicles"
                  optionLabel="plate_no"
                  dropdown
                  dropdownMode="blank"
                  forceSelection
                  fluid
                  :disabled="pageLoading"
                  @complete="searchVehicle"
                  @dropdown-click="onVehicleDropdown"
                  @focus="onVehicleDropdown"
                  @item-select="onVehicleSelect"
              />

            </div>
          </div>
          <div class="date-grid">
            <div class="form-group">
              <label>{{ t('startDateTime') }}</label>
              <DatePicker id="datepicker-24h" v-model="datetime1"
                          :hide-on-date-time-select="true"
                          date-format="dd/mm/yy" showTime hourFormat="24" fluid
              />
            </div>

            <div class="form-group">
              <label>{{ t('endDateTime') }}</label>
              <DatePicker id="datepicker-24h" v-model="datetime2" :hide-on-date-time-select="true" date-format="dd/mm/yy" showTime hourFormat="24" fluid />
            </div>
          </div>


          <div v-if="errorMessage" class="error-message">
            {{ errorMessage }}
          </div>

          <div class="button-row">
            <button
                type="button"
                class="primary-button"
                :disabled="loading || pageLoading"
                @click="searchHistory"
            >
              {{ loading ? t('loading') : t('loadHistory') }}
            </button>

            <button
                type="button"
                class="secondary-button"
                :disabled="!rows.length"
                @click="exportXls"
            >
              {{ t('saveXls') }}
            </button>
          </div>
        </div>

      </div>

      <div class="table-card">
<!--        <div class="table-header">-->
<!--          <div>-->
<!--            <h3>Data List</h3>-->
<!--            <p>{{ rows.length }} records</p>-->
<!--          </div>-->
<!--        </div>-->

        <div v-if="summary" class="summary-grid">
          <div class="summary-card running">
            <span>{{ t('runTime') }}</span>
            <strong>{{ summary.run_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card idle">
            <span>{{ t('idleTime') }}</span>
            <strong>{{ summary.idle_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card parking">
            <span>{{ t('parkTime') }}</span>
            <strong>{{ summary.park_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card distance">
            <span>{{ t('distance') }}</span>
            <strong>{{ summary.distance_km ?? 0 }} km</strong>
          </div>
        </div>

        <div class="table-scroll">
          <DataTable
              :value="rows"
              scrollable
              scrollHeight="flex"
              dataKey="gpsdata_id"
              selectionMode="single"
              v-model:selection="selectedHistoryRow"
              class="history-datatable"
              paginator
              lazy
              :rows="perPage"
              :first="firstRow"
              :totalRecords="totalRows"
              :rowsPerPageOptions="[100, 300, 500, 1000, 5000]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
              currentPageReportTemplate="{first} - {last} / {totalRecords}"
              @page="onPageChange"
              @row-click="onHistoryRowClick"
          >
            <Column header="#" style="width: 52px">
              <template #body="{ index }">
                {{ index + 1 }}
              </template>
            </Column>

            <Column :header="t('history')">
              <template #body="{ data, index }">
                <div class="history-cell">
                  <div class="mobile-history-index">
                    #{{ firstRow + index + 1 }}
                  </div>

                  <div class="main-line">
                    <span
                        class="status-pill"
                        :class="getStatusClass(data.state, data.speed, data.gps_status)"
                    >
                      <span
                          class="status-arrow"
                          :style="{ transform: `rotate(${Number(data.heading ?? 0) - 90}deg)` }"
                      >
                        ➤
                      </span>
                        {{ getStatusLabel(data.state, data.speed, data.gps_status) }}
                    </span>

                    <div class="gps-time">
                      {{ data.gps_time ?? '-' }}
                    </div>

                    <div
                        class="mini-chip speed-chip"
                        :class="{ danger: isSpeedOverLimit(data) }"
                    >
                      <span>SP</span>
                      <strong>{{ data.speed ?? 0 }}</strong>
                    </div>

                    <div class="mini-chip direction-chip">
                      <span>DIR</span>
                      <strong>{{ Number(data.heading ?? 0).toFixed(0) }}°</strong>
                    </div>

                    <div
                        class="mini-chip satellite-chip"
                        :class="{ danger: Number(data.num_sats ?? 0) < 4 }"
                    >
                      <span>SAT</span>
                      <strong>{{ data.num_sats ?? 0 }}</strong>
                    </div>

                  </div>

                  <div
                      v-if="
                          data.track3 ||
                          data.fuel_left !== null ||
                          data.temperature !== null ||
                          showInputColumn
                        "
                      class="optional-line"
                  >
                    <div v-if="showInputColumn" class="history-input-line">
                      <span
                          v-if="showInput1"
                          class="history-input-state"
                          :class="getInputClass(data.input1)"
                          :title="`IN1: ${getInputText(data.input1)}`"
                      >
                        IN1:
                        <i class="history-input-dot"></i>
                      </span>

                      <span
                          v-if="showInput2"
                          class="history-input-state"
                          :class="getInputClass(data.input2)"
                          :title="`IN2: ${getInputText(data.input2)}`"
                      >
                        IN2:
                        <i class="history-input-dot"></i>
                      </span>
                    </div>

                    <div v-if="data.track3" class="track3-text">
                      <i class="pi pi-id-card" />
                      {{ data.track3 }}
                    </div>

                    <div
                        v-if="data.fuel_left !== null && data.fuel_left !== undefined"
                        class="sensor-chip fuel"
                    >
                      <span>{{ t('fuel') }}</span>
                      <strong>{{ data.fuel_left }}</strong>
                    </div>

                    <div
                        v-if="data.temperature !== null && data.temperature !== undefined"
                        class="sensor-chip temp"
                    >
                      <span>{{ t('temp') }}</span>
                      <strong>{{ data.temperature }}</strong>
                    </div>

                  </div>

                  <div v-if="data.address" class="address-text">
                    {{ data.address }}
                  </div>
                </div>
              </template>
            </Column>
          </DataTable>
        </div>

      </div>
    </div>

    <div class="map-panel">
      <HistoryMap
          :history-points="rows"
          :focus-history-index="selectedHistoryIndex"
          :focus-location="selectedMapLocation"
      >
        <template #map-top-controls>
          <div class="station-zoom-control">
            <Select
                v-model="selectedMapLocation"
                :options="mapLocations"
                optionLabel="name"
                dataKey="key"
                :placeholder="t('selectMapLocation')"
                :loading="mapLocationLoading"
                appendTo="self"
                filter
                showClear
                @change="onMapLocationChange"
            >
              <template #value="{ value, placeholder }">
                <div v-if="value" class="map-location-option">
                  <i :class="getMapLocationIcon(value.type)"></i>
                  <span>{{ value.name }}</span>
                  <small>{{ getMapLocationTypeLabel(value.type) }}</small>
                </div>
                <span v-else>{{ placeholder }}</span>
              </template>

              <template #option="{ option }">
                <div class="map-location-option">
                  <i :class="getMapLocationIcon(option.type)"></i>
                  <span>{{ option.name }}</span>
                  <small>{{ getMapLocationTypeLabel(option.type) }}</small>
                </div>
              </template>
            </Select>
          </div>
        </template>
      </HistoryMap>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

import HistoryMap from '@/components/maps/HistoryMap.vue'

import {
  getHistoryTracking,
  exportHistoryTracking,
  type HistoryPoint,
} from '@/services/history'

import { getGroups } from '@/services/groups'
import { getVehicles } from '@/services/vehicles'
import {
  getMapLocations,
  type MapLocationOption,
  type MapLocationType,
} from '@/services/mapLocations'
import Button from "primevue/button";
import {DatePicker} from "primevue";
import AutoComplete from 'primevue/autocomplete'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useI18n } from '@/i18n'

type GroupItem = {
  group_id: number | string
  group_name: string
}

type VehicleItem = {
  vehicle_id: number | string
  plate_no: string
  imei: string
  group_id?: number | string | null
  group_name?: string | null
}

const auth = useAuthStore()
const { t } = useI18n()

const customerId = computed(() => {
  return (
      auth.customer?.id ??
      auth.user?.customer_id ??
      auth.config?.customer_id ??
      localStorage.getItem('gps_fleet_customer_id') ??
      localStorage.getItem('customer_id') ??
      null
  )
})

const pageLoading = ref(false)
const loading = ref(false)
const mapLocationLoading = ref(false)
const errorMessage = ref('')
const initializedCustomerId = ref<number | string | null>(null)

const groups = ref<GroupItem[]>([])
const allVehicles = ref<VehicleItem[]>([])
const vehicles = ref<VehicleItem[]>([])
const mapLocations = ref<MapLocationOption[]>([])

const selectedGroup = ref<number | string | null>(null)
const selectedVehicle = ref<number | string | null>(null)
const selectedMapLocation = ref<MapLocationOption | null>(null)
const selectedHistoryIndex = ref<number | null>(null)
const selectedHistoryRow = ref<HistoryPoint | null>(null)
const rows = ref<HistoryPoint[]>([])

const currentPage = ref(1)
const perPage = ref(5000)
const totalRows = ref(0)
const totalPages = ref(0)

const summary = ref<any>({})

const today = new Date()
const yyyy = today.getFullYear()
const mm = String(today.getMonth() + 1).padStart(2, '0')
const dd = String(today.getDate()).padStart(2, '0')
const todayText = `${yyyy}-${mm}-${dd}`

const startDate = ref(todayText)
const endDate = ref(todayText)
const startTime = ref('00:00')
const endTime = ref('23:59')

const d1 = new Date()
const d2 = new Date()
d1.setHours(0, 0, 0, 0)
d2.setHours(23, 59, 59, 0)
const datetime1 = ref<Date | null>(d1)
const datetime2 = ref<Date | null>(d2)

const showFilters = ref(true)
const selectedVehicleOption = ref<VehicleItem | null>(null)
const filteredVehicles = ref<VehicleItem[]>([])

const firstRow = computed(() => {
  return (currentPage.value - 1) * perPage.value
})

const showInput1 = computed(() => Boolean(auth.features?.input1))
const showInput2 = computed(() => Boolean(auth.features?.input2))
const showInputColumn = computed(() => showInput1.value || showInput2.value)

async function onPageChange(event: any) {
  currentPage.value = Math.floor(event.first / event.rows) + 1
  perPage.value = event.rows

  await loadHistory()
}
function onVehicleDropdown() {
  filteredVehicles.value = [...vehicles.value]
}
async function searchHistory() {
  currentPage.value = 1
  selectedHistoryIndex.value = null
  selectedMapLocation.value = null
  await loadHistory()
}

function onMapLocationChange() {
  if (selectedMapLocation.value) {
    selectedHistoryIndex.value = null
    selectedHistoryRow.value = null
  }
}

function getMapLocationIcon(type: MapLocationType) {
  return {
    poi: 'pi pi-map-marker location-icon poi',
    station: 'pi pi-building-columns location-icon station',
    'forbidden-zone': 'pi pi-ban location-icon forbidden-zone',
  }[type]
}

function getMapLocationTypeLabel(type: MapLocationType) {
  return {
    poi: t('poisLayer'),
    station: t('stationsLayer'),
    'forbidden-zone': t('forbiddenZonesLayer'),
  }[type]
}

function searchVehicle(event: any) {
  const keyword = normalizeText(event?.query ?? '')

  if (!keyword) {
    filteredVehicles.value = [...vehicles.value]
    return
  }

  filteredVehicles.value = vehicles.value.filter((vehicle) => {
    return (
        normalizeText(vehicle.plate_no).includes(keyword) ||
        normalizeText(vehicle.imei).includes(keyword)
    )
  })
}

function onVehicleSelect(event: any) {
  selectedVehicle.value = event.value?.vehicle_id ?? null
}

function onHistoryRowClick(event: any) {
  const index = rows.value.findIndex((item) => item === event.data)
  if (index >= 0) {
    selectHistoryRow(index)
  }
}

function isSpeedOverLimit(point: HistoryPoint): boolean {
  const speed = Number(point.speed ?? 0)
  const speedLimit = Number(point.speed_limited ?? 0)

  return speedLimit > 0 && speed > speedLimit
}

function normalizeStatus(
    state: any,
    speed: any,
    gpsStatus: any,
): string {

  const stateValue = Number(state ?? 0)
  const speedValue = Number(speed ?? 0)
  const gpsValue = String(gpsStatus ?? '').toUpperCase()

  // no gps
  if (gpsValue === 'V') {
    return 'no_gps'
  }

  // running
  if (stateValue === 1 && speedValue > 0) {
    return 'run'
  }

  // start
  if (stateValue === 1 && speedValue <= 0) {
    return 'idle'
  }

  // parking
  if (stateValue === 0) {
    return 'park'
  }

  return 'park'
}

function getStatusLabel(
    state: any,
    speed: any,
    gpsStatus: any,
): string {

  return {
    run: t('run'),
    idle: t('idle'),
    park: t('park'),
    no_gps: t('noGps'),
  }[
      normalizeStatus(state, speed, gpsStatus)
      ] ?? '-'
}

function formatDate(d: Date) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function getStatusClass(
    state: any,
    speed: any,
    gpsStatus: any,
): string {

  return `status-${normalizeStatus(
      state,
      speed,
      gpsStatus,
  )}`
}

watch(
    customerId,
    async (id) => {
      if (!id) return

      if (String(initializedCustomerId.value) === String(id)) {
        return
      }

      initializedCustomerId.value = id
      await initPage()
    },
    { immediate: true }
)

async function initPage() {
  errorMessage.value = ''
  pageLoading.value = true

  try {
    await Promise.all([
      loadGroups(),
      loadMapLocations(),
    ])
    await loadVehicles(-1)
  } finally {
    pageLoading.value = false
  }
}

async function loadMapLocations() {
  try {
    mapLocationLoading.value = true
    mapLocations.value = await getMapLocations()
  } catch (error) {
    console.error('LOAD MAP LOCATIONS ERROR', error)
    mapLocations.value = []
  } finally {
    mapLocationLoading.value = false
  }
}

async function loadGroups() {
  try {
    const response = await getGroups()
    const list = normalizeList<any>(response, ['data', 'groups', 'result'])

    groups.value = list
        .map((item) => ({
          group_id:
              item.group_id ??
              item.customer_group_id ??
              item.customerGroupId ??
              item.customer_group ??
              item.id ??
              item.value,

          group_name:
              item.group_name ??
              item.customer_group_name ??
              item.customerGroupName ??
              item.name ??
              item.label ??
              item.text ??
              '-',
        }))
        .filter((item) => item.group_id !== undefined && item.group_id !== null)
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        t('errorGroupsLoadFailed')
  }
}

async function loadVehicles(groupId: string | number | null = -1) {
  try {
    const response = await getVehicles(groupId)

    const list = normalizeList<any>(
        response,
        ['data', 'vehicles', 'result']
    )

    allVehicles.value = list.map((item) => ({
      vehicle_id:
          item.vehicle_id ??
          item.id ??
          item.car_id ??
          item.imei,

      plate_no:
          item.plate_no ??
          item.v_plate_no ??
          item.plate ??
          item.name ??
          item.imei ??
          '-',

      imei:
          item.imei ??
          item.device_imei ??
          item.vehicle_imei ??
          '',
    }))

    vehicles.value = allVehicles.value
    // เพิ่มบรรทัดนี้
    filteredVehicles.value = vehicles.value
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        t('errorVehiclesLoadFailed')
  }
}


function normalizeText(value: any): string {
  return String(value ?? '')
      .trim()
      .toLowerCase()
}
async function loadHistory() {
  if (!validateRange()) return

  const vehicle = findSelectedVehicle()

  if (!vehicle?.imei) {
    errorMessage.value = t('errorImeiMissing')
    return
  }

  loading.value = true
  rows.value = []
  selectedHistoryIndex.value = null
  selectedHistoryRow.value = null

  const hh1 = String(datetime1.value?.getHours()).padStart(2, '0')
  const mm1 = String(datetime1.value?.getMinutes()).padStart(2, '0')
  const hh2 = String(datetime2.value?.getHours()).padStart(2, '0')
  const mm2 = String(datetime2.value?.getMinutes()).padStart(2, '0')
  const hhmm1 = `${hh1}:${mm1}`
  const hhmm2 = `${hh2}:${mm2}`
  try {
    const response = await getHistoryTracking({
      imei: vehicle.imei,
      start_date: formatDate(datetime1.value!),
      end_date: formatDate(datetime2.value!),
      start_time: hhmm1,
      end_time: hhmm2,
      page: currentPage.value,
      per_page: perPage.value,
    })

    const list = response?.data ?? []
    summary.value = response?.summary ?? {}
    totalRows.value = response?.pagination?.total_rows ?? 0
    totalPages.value = response?.pagination?.total_pages ?? 0

    rows.value = list.map(normalizeHistoryPoint)
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        t('errorHistoryLoadFailed')
  } finally {
    loading.value = false
  }
}

async function goToPage(page: number) {

  if (page < 1) return
  if (page > totalPages.value) return

  currentPage.value = page

  await loadHistory()
}

function normalizeHistoryPoint(item: any): HistoryPoint {
  const gpsTime =
      item.gps_time ??
      item.data_date ??
      item.gpsTime ??
      item.gpstime ??
      item.gps_datetime ??
      item.datetime ??
      item.server_time ??
      item.time ??
      null

  return {
    ...item,
    gps_time: gpsTime,

    lat: item.lat ?? item.latitude ?? item.y,
    lng: item.lng ?? item.lon ?? item.longitude ?? item.x,

    speed: item.speed ?? item.vspeed ?? item.gps_speed ?? 0,
    speed_limited:
        item.speed_limited ??
        item.speedLimited ??
        item.speed_limit ??
        null,
    heading: item.heading ?? item.course ?? item.direction ?? item.angle ?? 0,
    status: item.status ?? item.status_name ?? item.vehicle_status ?? item.gps_status ?? '-',

    state:
        item.state ??
        item.acc_state ??
        item.acc ??
        item.engine_state ??
        item.status ??
        undefined,

    gps_status: String(
        item.gps_status ??
        item.gpsStatus ??
        item.valid ??
        item.valid_status ??
        ''
    ),

    address: item.address ?? '',

    fuel_left:
        item.fuel_left ??
        item.fuelLeft ??
        item.fuel ??
        null,

    temperature:
        item.temperature ??
        item.temp ??
        null,

    num_sats:
        item.num_sats ??
        item.sat_num ??
        item.satNum ??
        item.satellite ??
        item.sat ??
        null,

    track1: item.track1 ?? null,
    track3: item.track3 ?? null,
    input1: item.input1 ?? null,
    input2: item.input2 ?? null,

    di_input:
        item.input2 ??
        null,
  }

}

function selectHistoryRow(index: number) {
  selectedMapLocation.value = null
  selectedHistoryIndex.value = index
  selectedHistoryRow.value = rows.value[index] ?? null
}

async function exportXls() {
  const vehicle = findSelectedVehicle()

  if (!vehicle?.imei || !datetime1.value || !datetime2.value) {
    errorMessage.value = t('errorSelectVehicleAndRange')
    return
  }

  const hh1 = String(datetime1.value.getHours()).padStart(2, '0')
  const mm1 = String(datetime1.value.getMinutes()).padStart(2, '0')
  const hh2 = String(datetime2.value.getHours()).padStart(2, '0')
  const mm2 = String(datetime2.value.getMinutes()).padStart(2, '0')

  const blob = await exportHistoryTracking({
    imei: vehicle.imei,
    start_date: formatDate(datetime1.value),
    end_date: formatDate(datetime2.value),
    start_time: `${hh1}:${mm1}`,
    end_time: `${hh2}:${mm2}`,
    page: 1,
    per_page: 50000,
  })

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = `history-${vehicle.plate_no}-${formatDate(datetime1.value)}-${formatDate(datetime2.value)}.csv`
  link.click()

  URL.revokeObjectURL(url)
}

function validateRange() {
  errorMessage.value = ''

  if (!customerId.value) {
    errorMessage.value = t('errorCustomerMissing')
    return false
  }

  if (!selectedVehicle.value) {
    errorMessage.value = t('errorSelectVehicle')
    return false
  }

  if (!datetime1.value || !datetime2.value) {
    errorMessage.value = t('errorSelectVehicleAndRange')
    return false
  }

  const start = datetime1.value
  const end = datetime2.value

  if (start >= end) {
    errorMessage.value = t('errorEndBeforeStart')
    return false
  }

  const diffDays = (end.getTime() - start.getTime()) / 86400000

  if (diffDays > 31) {
    errorMessage.value = t('errorDateRangeTooLong')
    return false
  }

  return true
}

function findSelectedVehicle() {
  if (selectedVehicleOption.value?.imei) {
    return selectedVehicleOption.value
  }

  return vehicles.value.find((item) => {
    return String(item.vehicle_id) === String(selectedVehicle.value)
  })
}

function normalizeList<T>(response: any, keys: string[] = []): T[] {
  if (Array.isArray(response)) return response

  for (const key of keys) {
    if (Array.isArray(response?.[key])) {
      return response[key]
    }

    if (Array.isArray(response?.data?.[key])) {
      return response.data[key]
    }
  }

  if (Array.isArray(response?.data)) {
    return response.data
  }

  return []
}

function isInputOn(value?: string | number | boolean | null): boolean {
  if (value === true) return true

  const normalized = String(value ?? '').trim().toLowerCase()

  return ['1', 'true', 'on'].includes(normalized)
}

function getInputText(value?: string | number | boolean | null): string {
  if (value === null || value === undefined || value === '') {
    return 'unknown'
  }

  return isInputOn(value) ? 'on' : 'off'
}

function getInputClass(value?: string | number | boolean | null) {
  return {
    on: isInputOn(value),
    off: value !== null && value !== undefined && value !== '' && !isInputOn(value),
    empty: value === null || value === undefined || value === '',
  }
}

watch(selectedGroup, async (groupId) => {

  selectedVehicle.value = null
  selectedVehicleOption.value = null
  filteredVehicles.value = []

  selectedHistoryIndex.value = null
  rows.value = []
  errorMessage.value = ''

  await loadVehicles(groupId || -1)
})

watch(datetime1, (newValue) => {
  if (!newValue) return

  const endDate = new Date(newValue)
  endDate.setHours(23, 59, 59, 999)

  datetime2.value = endDate
})

</script>

<style scoped>
.history-page {
  display: flex;
  gap: 16px;
  width: 100%;
  height: calc(100vh - 80px);
  min-height: 560px;
  overflow: hidden;
}

.left-panel {
  width: 520px;
  min-width: 520px;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.map-panel {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 100%;
  background: #020617;
  border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 18px;
  overflow: hidden;
}

.station-zoom-control {
  display: flex;
  align-items: center;
  gap: 8px;
  width: min(320px, 100%);
  height: 40px;
  padding: 0 10px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.94);
  color: #ffffff;
  box-shadow: 0 8px 24px rgba(2, 6, 23, 0.28);
  backdrop-filter: blur(12px);
}

.station-zoom-control :deep(.p-select) {
  flex: 1;
  min-width: 0;
  height: 38px;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
}

.station-zoom-control :deep(.p-select-label) {
  padding-block: 8px !important;
  padding-left: 0 !important;
  color: #f8fafc !important;
  font-size: 13px;
  font-weight: 800;
}

.station-zoom-control :deep(.p-placeholder) {
  color: #cbd5e1 !important;
}

.map-location-option {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  min-width: 0;
}

.map-location-option > span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.map-location-option > small {
  margin-left: auto;
  color: #94a3b8;
  font-size: 10px;
  white-space: nowrap;
}

.location-icon {
  flex-shrink: 0;
  font-size: 15px;
}

.location-icon.poi {
  color: #22c55e;
}

.location-icon.station {
  color: #60a5fa;
}

.location-icon.forbidden-zone {
  color: #f87171;
}

.filter-card,
.table-card {
  background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
  border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 18px;
  color: #fff;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
}

.filter-card {
  padding: 16px;
}

.table-card {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.card-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
}

.card-header p {
  margin: 4px 0 0;
  color: #94a3b8;
  font-size: 12px;
}

.field-grid,
.date-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.form-group label {
  color: #cbd5e1;
  font-size: 12px;
  font-weight: 700;
}

.button-row {
  display: grid;
  grid-template-columns: 1fr 120px;
  gap: 10px;
}

.primary-button,
.secondary-button {
  height: 40px;
  border-radius: 12px;
  border: 0;
  font-weight: 800;
  cursor: pointer;
}

.primary-button {
  background: #22c55e;
  color: #052e16;
}

.secondary-button {
  background: rgba(148, 163, 184, 0.14);
  border: 1px solid rgba(148, 163, 184, 0.22);
  color: #e5e7eb;
}

.primary-button:disabled,
.secondary-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.error-message {
  margin: 8px 0 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.14);
  border: 1px solid rgba(239, 68, 68, 0.28);
  color: #fecaca;
  font-size: 13px;
}

/* summary */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
}

.summary-card {
  padding: 9px 10px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.82);
  border: 1px solid rgba(148, 163, 184, 0.14);
}

.summary-card span {
  display: block;
  font-size: 10px;
  color: #94a3b8;
}

.summary-card strong {
  display: block;
  margin-top: 4px;
  font-size: 14px;
}

.summary-card.running strong { color: #22c55e; }
.summary-card.idle strong { color: #eab308; }
.summary-card.parking strong { color: #ef4444; }
.summary-card.distance strong { color: #38bdf8; }

/* datatable */
.table-scroll {
  flex: 1;
  min-height: 0;
  display: flex;
  overflow: hidden;
}

:deep(.history-datatable) {
  flex: 1;
  min-height: 0;
  height: 100%;
  background: transparent;
  color: #e5e7eb;
  display: flex;
  flex-direction: column;
}

:deep(.history-datatable .p-datatable-wrapper) {
  flex: 1;
  min-height: 0;
  background: transparent;
  overflow-y: auto !important;
}

:deep(.history-datatable .p-datatable-table-container) {
  flex: 1;
  min-height: 0;
  max-height: none !important;
  background: transparent;
  overflow-y: auto !important;
  overflow-x: hidden !important;
}

:deep(.history-datatable .p-datatable-table) {
  background: transparent;
}

:deep(.history-datatable .p-datatable-thead > tr > th) {
  background: #020617;
  color: #94a3b8;
  border-color: rgba(148, 163, 184, 0.12);
  padding: 8px 10px;
  font-size: 11px;
  font-weight: 800;
}

:deep(.history-datatable .p-datatable-tbody > tr) {
  background: transparent;
  color: #e5e7eb;
  cursor: pointer;
}

:deep(.history-datatable .p-datatable-tbody > tr > td) {
  background: transparent;
  border-color: rgba(148, 163, 184, 0.10);
  padding: 10px;
}

:deep(.history-datatable .p-datatable-tbody > tr:hover > td) {
  background: #2d3748;
}

:deep(.history-datatable .p-highlight > td) {
  background: rgba(34, 197, 94, 0.18) !important;
}

/* history content */
.history-cell {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}

.mobile-history-index {
  display: none;
}

.main-line {
  display: grid;
  grid-template-columns: minmax(90px, 1fr) 150px 60px 60px 60px;
  gap: 6px;
  align-items: center;
}

.gps-time {
  color: #f8fafc;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 80px;
  height: 30px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.status-arrow {
  font-size: 15px;
  line-height: 1;
}

.status-run {
  background: #22c55e;
  color: #052e16;
}

.status-idle {
  background: #eab308;
  color: #422006;
}

.status-park {
  background: #64748b;
  color: #fff;
}

.status-no_gps {
  background: #3b82f6;
  color: #fff;
}

.mini-chip,
.sensor-chip {
  height: 34px;
  padding: 4px 6px;
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.86);
  border: 1px solid rgba(148, 163, 184, 0.14);
  text-align: center;
}

.mini-chip span,
.sensor-chip span {
  display: block;
  font-size: 9px;
  line-height: 1;
  color: #94a3b8;
}

.mini-chip strong,
.sensor-chip strong {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  line-height: 1;
  color: #f8fafc;
}

.mini-chip.danger {
  background: rgba(239, 68, 68, 0.13);
  border-color: rgba(239, 68, 68, 0.45);
}

.mini-chip.danger strong {
  color: #f87171;
}

.optional-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 0;
}

.history-input-line {
  height: 34px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 10px;
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.86);
  border: 1px solid rgba(148, 163, 184, 0.14);
}

.history-input-state {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
}

.history-input-dot {
  width: 9px;
  height: 9px;
  display: inline-block;
  border-radius: 999px;
  background: #64748b;
  box-shadow: 0 0 0 2px rgba(100, 116, 139, 0.2);
}

.history-input-state.on .history-input-dot {
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.22);
}

.history-input-state.off .history-input-dot {
  background: #ef4444;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.22);
}

.history-input-state.empty {
  color: #64748b;
}

.track3-text {
  flex: 1;
  min-width: 0;
  height: 34px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 10px;
  border-radius: 10px;
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.18);
  color: #34d399;
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sensor-chip {
  min-width: 58px;
}

.sensor-chip.fuel strong { color: #4ade80; }
.sensor-chip.temp strong { color: #fb923c; }
.sensor-chip.di strong { color: #a78bfa; }

.address-text {
  color: #94a3b8;
  font-size: 11px;
  line-height: 1.35;
  white-space: normal;
  word-break: break-word;
}

/* pagination */
.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 14px;
  background: #020617;
  border-top: 1px solid rgba(148, 163, 184, 0.12);
}

.pagination-info {
  color: #94a3b8;
  font-size: 12px;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-number {
  min-width: 40px;
  text-align: center;
  color: #fff;
  font-size: 13px;
  font-weight: 900;
}

/* responsive */
@media (max-width: 1100px) {
  .history-page {
    flex-direction: column;
    height: auto;
    overflow: visible;
  }

  .left-panel {
    width: 100%;
    min-width: 0;
    height: auto;
  }

  .table-card {
    height: 78vh;
    min-height: 600px;
    max-height: 820px;
  }

  .map-panel {
    height: 420px;
    min-height: 420px;
  }
}

@media (max-width: 640px) {
  .table-card {
    height: 74vh;
    min-height: 540px;
    max-height: 720px;
  }

  :deep(.history-datatable .p-datatable-table) {
    min-width: 0;
    table-layout: fixed;
  }

  :deep(.history-datatable .p-datatable-thead) {
    display: none;
  }

  :deep(.history-datatable .p-datatable-tbody > tr) {
    display: block;
  }

  :deep(.history-datatable .p-datatable-tbody > tr > td) {
    display: block;
    padding: 0;
    min-width: 0;
  }

  :deep(.history-datatable .p-datatable-tbody > tr > td:first-child) {
    display: none;
  }

  :deep(.history-datatable .p-datatable-tbody > tr > td:nth-child(2)) {
    padding: 10px;
  }

  .history-cell {
    position: relative;
    gap: 9px;
    padding: 10px;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(148, 163, 184, 0.14);
  }

  .mobile-history-index {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 900;
    line-height: 1;
  }

  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .field-grid,
  .date-grid {
    grid-template-columns: 1fr;
  }

  .main-line {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-template-areas:
      "status status time time"
      "speed direction satellite satellite";
    gap: 8px;
  }

  .status-pill {
    grid-area: status;
  }

  .gps-time {
    grid-area: time;
    justify-self: end;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 12px;
    align-self: center;
  }

  .status-pill {
    width: auto;
    min-width: 94px;
    justify-self: start;
    padding: 0 12px;
  }

  .main-line .mini-chip {
    min-width: 0;
    height: 38px;
  }

  .speed-chip {
    grid-area: speed;
  }

  .direction-chip {
    grid-area: direction;
  }

  .satellite-chip {
    grid-area: satellite;
    width: 100%;
  }

  .optional-line {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
  }

  .track3-text {
    grid-column: 1 / -1;
    flex-basis: auto;
  }

  .sensor-chip {
    min-width: 0;
    width: 100%;
  }
}

:deep(.history-datatable .p-paginator) {
  background: #020617;
  border-top: 1px solid rgba(148, 163, 184, 0.12);
  color: #cbd5e1;
  padding: 8px 10px;
}

:deep(.history-datatable .p-paginator .p-paginator-page),
:deep(.history-datatable .p-paginator .p-paginator-next),
:deep(.history-datatable .p-paginator .p-paginator-prev),
:deep(.history-datatable .p-paginator .p-paginator-first),
:deep(.history-datatable .p-paginator .p-paginator-last) {
  background: rgba(148, 163, 184, 0.10);
  color: #e5e7eb;
  border-radius: 10px;
  min-width: 32px;
  height: 32px;
}

:deep(.history-datatable .p-paginator .p-paginator-page.p-highlight) {
  background: #22c55e;
  color: #052e16;
  font-weight: 900;
}

:deep(.history-datatable .p-paginator .p-dropdown),
:deep(.history-datatable .p-paginator .p-select) {
  background: #0f172a;
  border-color: rgba(148, 163, 184, 0.22);
  color: #e5e7eb;
}

</style>
