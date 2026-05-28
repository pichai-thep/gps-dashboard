<template>
  <div class="history-page">
    <div class="left-panel">

      <div class="filter-card">
        <div class="card-header">
          <div>
            <h2>History Tracking</h2>
            <p>ดึงประวัติการเดินรถ</p>
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
        </div>



        <div v-show="showFilters" class="tracking-filters">
          <div class="field-grid">
            <div class="form-group">
              <label>Group</label>

              <Select
                  v-model="selectedGroup"
                  :options="groups"
                  optionLabel="group_name"
                  optionValue="group_id"
                  placeholder="All Group"
                  fluid
                  filter
                  showClear
                  :disabled="pageLoading"
              />

            </div>
            <div class="form-group">
              <label>Vehicle</label>

              <AutoComplete
                  v-model="selectedVehicleOption"
                  :suggestions="filteredVehicles"
                  optionLabel="plate_no"
                  placeholder="Select Vehicle"
                  dropdown
                  forceSelection
                  fluid
                  :disabled="pageLoading"
                  @complete="searchVehicle"
                  @item-select="onVehicleSelect"
              />

            </div>
          </div>

<!--          <div class="date-grid">-->
<!--            <div class="form-group">-->
<!--              <label>Start Date</label>-->
<!--              <input type="date" v-model="startDate" />-->
<!--            </div>-->

<!--            <div class="form-group">-->
<!--              <label>Start Time</label>-->
<!--              <input type="time" v-model="startTime" />-->
<!--            </div>-->
<!--          </div>-->

<!--          <div class="date-grid">-->
<!--            <div class="form-group">-->
<!--              <label>End Date</label>-->
<!--              <input type="date" v-model="endDate" />-->
<!--            </div>-->

<!--            <div class="form-group">-->
<!--              <label>End Time</label>-->
<!--              <input type="time" v-model="endTime" />-->
<!--            </div>-->
<!--          </div>-->

          <div class="date-grid">
            <div class="form-group">
              <label>Start Date-time</label>
              <DatePicker id="datepicker-24h" v-model="datetime1" :hide-on-date-time-select="true" date-format="dd/mm/yy" showTime hourFormat="24" fluid />
            </div>

            <div class="form-group">
              <label>End Date-time</label>
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
                @click="loadHistory"
            >
              {{ loading ? 'Loading...' : 'Load History' }}
            </button>

            <button
                type="button"
                class="secondary-button"
                :disabled="!rows.length"
                @click="exportXls"
            >
              Save XLS
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
            <span>Run-time</span>
            <strong>{{ summary.run_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card idle">
            <span>Idle-time</span>
            <strong>{{ summary.idle_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card parking">
            <span>Park-time</span>
            <strong>{{ summary.park_time ?? '00:00:00' }}</strong>
          </div>

          <div class="summary-card distance">
            <span>Distance</span>
            <strong>{{ summary.distance_km ?? 0 }} km</strong>
          </div>
        </div>

        <div class="table-scroll">
          <table>
            <thead>
            <tr>
              <th>#</th>
              <th>GPS Time</th>
              <th>Status</th>
              <th></th>
            </tr>
            </thead>


            <tbody>
              <tr v-if="!rows.length">
                <td colspan="4" class="empty-cell">No history data</td>
              </tr>

              <tr
                  v-for="(row, index) in rows"
                  :key="index"
                  class="history-row"
                  :class="{ active: selectedHistoryIndex === index }"
                  @click="selectHistoryRow(index)"
              >
                <td colspan="4">
                  <div class="history-item">
                    <div class="row-index">
                      {{ index + 1 }}
                    </div>

                    <div class="row-body">
                      <div class="top-line">
                        <div class="gps-time">
                          {{ row.gps_time ?? '-' }}
                        </div>

                        <div class="meta-row">
                          <div class="mini-chip">
                            <span>SP</span>
                            <strong>{{ row.speed ?? 0 }}</strong>
                          </div>

                          <div
                              class="mini-chip"
                              :class="{ danger: Number(row.num_sats ?? 0) < 4 }"
                          >
                            <span>SAT</span>
                            <strong>{{ row.num_sats ?? 0 }}</strong>
                          </div>

                          <div class="mini-chip">
                            <span>DIR</span>
                            <strong>{{ Number(row.heading ?? 0).toFixed(0) }}°</strong>
                          </div>
                        </div>
                      </div>

                      <div class="second-line">
      <span
          class="status-pill"
          :class="getStatusClass(row.state, row.speed, row.gps_status)"
      >
        <span
            class="status-arrow"
            :style="{ transform: `rotate(${(Number(row.heading ?? 0) - 90)}deg)` }"
        >
          ➤
        </span>

        {{ getStatusLabel(row.state, row.speed, row.gps_status) }}
      </span>

                        <div class="sensor-row">
                          <div
                              v-if="row.fuel_left !== null && row.fuel_left !== undefined"
                              class="sensor-chip fuel"
                          >
                            <span>Fuel</span>
                            <strong>{{ row.fuel_left }}</strong>
                          </div>

                          <div
                              v-if="row.temperature !== null && row.temperature !== undefined"
                              class="sensor-chip temp"
                          >
                            <span>Temp</span>
                            <strong>{{ row.temperature }}</strong>
                          </div>
                        </div>
                      </div>

                      <div v-if="row.track3" class="track3-text">
                        <i class="pi pi-id-card" />
                        {{ row.track3 }}
                      </div>

                      <div v-if="row.address" class="address-text">
                        {{ row.address }}
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>

        <div
            v-if="totalRows > 0"
            class="pagination-footer"
        >
          <div class="pagination-info">
            {{ totalRows }} records
            / {{ totalPages }} pages
          </div>

          <div class="pagination-buttons">

            <button
                class="secondary-button"
                :disabled="currentPage <= 1"
                @click="goToPage(currentPage - 1)"
            >
              Prev
            </button>

            <div class="page-number">
              {{ currentPage }}
            </div>

            <button
                class="secondary-button"
                :disabled="currentPage >= totalPages"
                @click="goToPage(currentPage + 1)"
            >
              Next
            </button>

          </div>
        </div>

      </div>
    </div>

    <div class="map-panel">
      <HistoryMap
          :history-points="rows"
          :focus-history-index="selectedHistoryIndex"
      />
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
import Button from "primevue/button";
import {DatePicker} from "primevue";
import AutoComplete from 'primevue/autocomplete'
import Select from 'primevue/select'

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
const errorMessage = ref('')
const initializedCustomerId = ref<number | string | null>(null)

const groups = ref<GroupItem[]>([])
const allVehicles = ref<VehicleItem[]>([])
const vehicles = ref<VehicleItem[]>([])

const selectedGroup = ref<number | string | null>(null)
const selectedVehicle = ref<number | string | null>(null)
const selectedHistoryIndex = ref<number | null>(null)

const rows = ref<HistoryPoint[]>([])

const currentPage = ref(1)
const perPage = ref(1000)
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

const showFilters = ref(false)
const selectedVehicleOption = ref<VehicleItem | null>(null)
const filteredVehicles = ref<VehicleItem[]>([])

function searchVehicle(event: any) {
  const keyword = normalizeText(event.query)

  if (!keyword) {
    filteredVehicles.value = vehicles.value
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
    run: 'Run',
    idle: 'Idle',
    park: 'Park',
    no_gps: 'No-GPS',
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
    await loadGroups()
    await loadVehicles(-1)
  } finally {
    pageLoading.value = false
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
        'โหลดกลุ่มรถไม่สำเร็จ'
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
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'โหลดรถไม่สำเร็จ'
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
    errorMessage.value = 'ไม่พบ IMEI ของรถ'
    return
  }

  loading.value = true
  rows.value = []
  selectedHistoryIndex.value = null

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

    console.log('HISTORY RAW FIRST ROW', list[0])

    console.log('NORMALIZED FIRST ROW', normalizeHistoryPoint(list[0]))

    rows.value = list.map(normalizeHistoryPoint)
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'โหลดประวัติไม่สำเร็จ'
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
  }
}

function selectHistoryRow(index: number) {
  selectedHistoryIndex.value = index
}

async function exportXls() {
  const vehicle = findSelectedVehicle()

  if (!vehicle?.imei || !datetime1.value || !datetime2.value) {
    errorMessage.value = 'กรุณาเลือกรถและช่วงเวลา'
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
    errorMessage.value = 'ไม่พบ customer_id'
    return false
  }

  if (!selectedVehicle.value) {
    errorMessage.value = 'กรุณาเลือกรถ'
    return false
  }

  const start = new Date(`${startDate.value}T${startTime.value}:00`)
  const end = new Date(`${endDate.value}T${endTime.value}:59`)

  if (start >= end) {
    errorMessage.value = 'เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด'
    return false
  }

  const diffDays = (end.getTime() - start.getTime()) / 86400000

  if (diffDays > 7) {
    errorMessage.value = 'ดึงประวัติได้ไม่เกิน 7 วัน'
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

watch(selectedGroup, async (groupId) => {

  selectedVehicle.value = null
  selectedVehicleOption.value = null
  filteredVehicles.value = []

  selectedHistoryIndex.value = null
  rows.value = []
  errorMessage.value = ''

  await loadVehicles(groupId || -1)
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
  width: 420px;
  min-width: 420px;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex-shrink: 0;
}

.filter-card,
.table-card {
  background: rgba(15, 23, 42, 0.96);
  border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 18px;
  color: #ffffff;
  box-shadow: 0 18px 40px rgba(2, 6, 23, 0.3);
}

.filter-card {
  padding: 18px;
}

.table-card {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.card-header,
.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.card-header h2,
.table-header h3 {
  margin: 0;
  font-weight: 800;
}

.card-header p,
.table-header p {
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

.form-group input,
.form-group select {
  height: 40px;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: #020617;
  color: #ffffff;
  padding: 0 12px;
  outline: none;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #22c55e;
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

.button-row {
  display: grid;
  grid-template-columns: 1fr 120px;
  gap: 10px;
}

.primary-button,
.secondary-button {
  height: 42px;
  border: 0;
  border-radius: 12px;
  font-weight: 800;
  cursor: pointer;
}

.primary-button {
  background: #22c55e;
  color: #052e16;
}

.secondary-button {
  background: rgba(148, 163, 184, 0.16);
  color: #ffffff;
  border: 1px solid rgba(148, 163, 184, 0.22);
}

.primary-button:disabled,
.secondary-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.table-header {
  padding: 16px 16px 0;
}

.table-scroll {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

thead {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #0f172a;
}

th,
td {
  padding: 9px 10px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  text-align: left;
  white-space: nowrap;
}

th {
  color: #94a3b8;
  font-weight: 800;
}

td {
  color: #e5e7eb;
}

.history-row {
  cursor: pointer;
}

.history-row:hover,
.history-row.active {
  background: rgba(34, 197, 94, 0.12);
}


.empty-cell {
  text-align: center;
  color: #94a3b8;
  padding: 28px 10px;
}

.map-panel {
  flex: 1;
  min-width: 0;
  height: 100%;
  min-height: 560px;
  background: #111827;
  border-radius: 18px;
  overflow: hidden;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;

  width: 100px;
  height: 24px;

  border-radius: 999px;

  font-size: 14px;
  font-weight: 700;
}

.status-pill i {
  width: 18px;
  height: 18px;

  display: flex;
  align-items: center;
  justify-content: center;

  font-size: 14px;
  line-height: 14px;

  flex-shrink: 0;
}

.status-run {
  color: #052e16;
  background: #22c55e;
}

.status-idle {
  color: #422006;
  background: #eab308;
}

.status-park {
  color: #ffffff;
  background: #64748b;
}

.status-no_gps {
  color: #ffffff;
  background: #3b82f6;
}

.gps-time {
  font-weight: 700;
  color: #f8fafc;
  margin-bottom: 4px;
}

.address-text {
  font-size: 11px;
  line-height: 1.4;
  color: #94a3b8;

  white-space: normal;
  word-break: break-word;

  max-width: 260px;
}

.track3-text {
  font-size: 12px;
  line-height: 1.4;
  color: #22c55e;
  white-space: normal;
  word-break: break-word;
  max-width: 260px;
}

@media (max-width: 1100px) {
  .history-page {
    flex-direction: column;
    height: auto;
    overflow: visible;
  }

  .left-panel {
    width: 100%;
    min-width: 0;
  }

  .map-panel {
    height: 560px;
  }
}

.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 12px 16px;

  border-top: 1px solid rgba(148, 163, 184, 0.12);

  background: #0f172a;
}

.pagination-info {
  font-size: 12px;
  color: #94a3b8;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-number {
  min-width: 44px;
  text-align: center;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
}

.summary-card {
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.14);
}

.summary-card span {
  display: block;
  font-size: 11px;
  color: #94a3b8;
  margin-bottom: 4px;
}

.summary-card strong {
  display: block;
  font-size: 16px;
  color: #ffffff;
}

.summary-card.running strong {
  color: #22c55e;
}

.summary-card.idle strong {
  color: #eab308;
}

.summary-card.parking strong {
  color: #ef4444;
}

.summary-card.distance strong {
  color: #38bdf8;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  min-width: 105px;
  height: 28px;

  padding: 0 10px;

  border-radius: 999px;

  font-size: 13px;
  font-weight: 700;
}

.status-arrow {
  width: 18px;
  height: 18px;

  display: flex;
  align-items: center;
  justify-content: center;

  font-size: 16px;
  font-weight: 900;

  transition: transform 0.2s ease;
}


.history-row > td {
  padding: 0;
  white-space: normal;
}

.history-item {
  display: grid;
  grid-template-columns: 34px 1fr 150px 110px;
  gap: 12px;
  align-items: center;
  padding: 14px 14px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.14);
}

.history-row:hover .history-item,
.history-row.active .history-item {
  background: rgba(34, 197, 94, 0.11);
}

.row-index {
  font-size: 13px;
  font-weight: 800;
  color: #cbd5e1;
}

.row-main {
  min-width: 0;
}

.gps-time {
  font-size: 13px;
  font-weight: 800;
  color: #f8fafc;
  margin-bottom: 5px;
}

.address-text {
  font-size: 11px;
  line-height: 1.35;
  color: #94a3b8;
  white-space: normal;
  word-break: break-word;
}

.track3-text {
  margin-top: 5px;
  font-size: 11px;
  color: #34d399;
  display: flex;
  gap: 5px;
  align-items: center;
}

.row-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}

.info-chip {
  min-height: 38px;
  padding: 6px 8px;
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.14);
}

.info-chip span {
  display: block;
  font-size: 10px;
  color: #94a3b8;
  line-height: 1;
}

.info-chip strong {
  display: block;
  margin-top: 4px;
  font-size: 13px;
  color: #f8fafc;
}

.info-chip.danger {
  border-color: rgba(239, 68, 68, 0.45);
  background: rgba(239, 68, 68, 0.12);
}

.info-chip.danger strong {
  color: #f87171;
}

.row-status {
  display: flex;
  justify-content: flex-end;
  align-items: center;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-width: 96px;
  height: 34px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 800;
}

.status-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  line-height: 1;
  transition: transform 0.2s ease;
}

.status-run {
  color: #052e16;
  background: #22c55e;
}

.status-idle {
  color: #422006;
  background: #eab308;
}

.status-park {
  color: #ffffff;
  background: #64748b;
}

.status-no_gps {
  color: #ffffff;
  background: #3b82f6;
}

@media (max-width: 640px) {
  .history-item {
    grid-template-columns: 28px 1fr;
  }

  .row-info {
    grid-column: 2;
    grid-template-columns: repeat(3, 1fr);
  }

  .row-status {
    grid-column: 2;
    justify-content: flex-start;
  }
}

.history-item {
  display: grid;
  grid-template-columns: 42px minmax(150px, 1fr) 170px 110px;
  gap: 14px;
  align-items: center;
  padding: 14px 16px;
}

.row-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.info-chip {
  min-height: 44px;
}

.row-status {
  display: flex;
  justify-content: flex-end;
}
.gps-time {
  white-space: nowrap;
  font-size: 13px;
}

.history-item {
  grid-template-columns: 34px 1fr;
}

.row-info,
.row-status {
  grid-column: 2;
}

.row-info {
  grid-template-columns: repeat(3, 1fr);
}

.row-status {
  justify-content: flex-start;
}

.history-item {
  display: grid;
  grid-template-columns: 34px 1fr 220px;
  gap: 14px;
  align-items: center;
  padding: 14px 16px;
}

.row-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.meta-row {
  display: flex;
  gap: 6px;
}

.mini-chip {
  min-width: 58px;
  padding: 6px 8px;
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.14);
  text-align: center;
}

.mini-chip span {
  display: block;
  font-size: 9px;
  color: #94a3b8;
  line-height: 1;
}

.mini-chip strong {
  display: block;
  margin-top: 4px;
  font-size: 13px;
  color: #f8fafc;
}

.mini-chip.danger {
  border-color: rgba(239, 68, 68, 0.4);
  background: rgba(239, 68, 68, 0.12);
}

.mini-chip.danger strong {
  color: #f87171;
}

.extra-row {
  display: flex;
  gap: 10px;
  font-size: 11px;
  color: #94a3b8;
}

.gps-time {
  white-space: nowrap;
  font-size: 14px;
  font-weight: 800;
}

@media (max-width: 640px) {
  .history-item {
    grid-template-columns: 30px 1fr;
  }

  .row-right {
    grid-column: 2;
    align-items: flex-start;
  }

  .meta-row {
    width: 100%;
  }

  .mini-chip {
    flex: 1;
  }
}



.history-item {
  display: grid;
  grid-template-columns: 34px 1fr;
  gap: 14px;
  padding: 14px 16px;
  align-items: start;
}

.row-index {
  padding-top: 4px;
  font-size: 13px;
  font-weight: 800;
  color: #cbd5e1;
}

.row-body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.top-line,
.second-line {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
}

.gps-time {
  font-size: 14px;
  font-weight: 800;
  color: #f8fafc;
  white-space: nowrap;
}

.meta-row,
.sensor-row {
  display: flex;
  gap: 6px;
}

.mini-chip,
.sensor-chip {
  min-width: 58px;
  padding: 6px 8px;
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.14);
  text-align: center;
}

.mini-chip span,
.sensor-chip span {
  display: block;
  font-size: 9px;
  color: #94a3b8;
  line-height: 1;
}

.mini-chip strong,
.sensor-chip strong {
  display: block;
  margin-top: 4px;
  font-size: 13px;
  color: #f8fafc;
}

.sensor-chip.fuel strong {
  color: #4ade80;
}

.sensor-chip.temp strong {
  color: #fb923c;
}

.mini-chip.danger {
  border-color: rgba(239, 68, 68, 0.45);
  background: rgba(239, 68, 68, 0.12);
}

.mini-chip.danger strong {
  color: #f87171;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  min-width: 96px;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
}

.status-arrow {
  font-size: 16px;
  transition: transform 0.2s ease;
}

.track3-text {
  font-size: 11px;
  color: #34d399;
  display: flex;
  gap: 5px;
  align-items: center;
}

.address-text {
  font-size: 11px;
  line-height: 1.4;
  color: #94a3b8;
  white-space: normal;
  word-break: break-word;
}
</style>