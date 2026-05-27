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

              <select
                  v-model="selectedGroup"
                  :disabled="pageLoading"
              >
                <option :value="null">All Group</option>

                <option
                    v-for="group in groups"
                    :key="group.group_id"
                    :value="group.group_id"
                >
                  {{ group.group_name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Vehicle</label>

              <select
                  v-model="selectedVehicle"
                  :disabled="pageLoading"
              >
                <option :value="null">
                  {{ pageLoading ? 'Loading...' : 'Select Vehicle' }}
                </option>

                <option
                    v-for="vehicle in vehicles"
                    :key="vehicle.vehicle_id"
                    :value="vehicle.vehicle_id"
                >
                  {{ vehicle.plate_no }}
                </option>
              </select>
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
              <DatePicker id="datepicker-24h" v-model="datetime1" date-format="dd/mm/yy" showTime hourFormat="24" fluid />
            </div>

            <div class="form-group">
              <label>End Date-time</label>
              <DatePicker id="datepicker-24h" v-model="datetime2" date-format="dd/mm/yy" showTime hourFormat="24" fluid />
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
              <th>Speed</th>
              <th>Status</th>
            </tr>
            </thead>

            <tbody>
            <tr v-if="!rows.length">
              <td colspan="4" class="empty-cell">
                No history data
              </td>
            </tr>

            <tr
                v-for="(row, index) in rows"
                :key="index"
                class="history-row"
                :class="{ active: selectedHistoryIndex === index }"
                @click="selectHistoryRow(index)"
            >
              <td>{{ index + 1 }}</td>
              <td>
                <div class="gps-time">
                  {{ row.gps_time ?? '-' }}
                </div>

                <div
                    class="address-text"
                    :title="row.address"
                >
                  {{ row.address ?? '-' }}
                </div>

                <div v-if="row.track3"
                    class="track3-text"
                    :title="row.address"
                >
                  <i class="pi pi-id-card" />
                  {{ row.track3 ?? '-' }}
                </div>

              </td>
              <td>{{ row.speed ?? 0 }}</td>
              <td>
                <span
                    class="status-pill"
                    :class="
                    getStatusClass(
                      row.state,
                      row.speed,
                      row.gps_status,
                    )
                  "
                >
                  <i
                      :class="
                      getStatusIcon(
                        row.state,
                        row.speed,
                        row.gps_status,
                      )
                    "
                  />

                  {{
                    getStatusLabel(
                        row.state,
                        row.speed,
                        row.gps_status,
                    )
                  }}
                </span>
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
const perPage = ref(100)
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
    return 'running'
  }

  // start
  if (stateValue === 1 && speedValue <= 0) {
    return 'start'
  }

  // parking
  if (stateValue === 0) {
    return 'parking'
  }

  return 'parking'
}

function getStatusLabel(
    state: any,
    speed: any,
    gpsStatus: any,
): string {

  return {
    running: 'Running',
    start: 'Start',
    parking: 'Parking',
    no_gps: 'No GPS',
  }[
      normalizeStatus(state, speed, gpsStatus)
      ] ?? '-'
}

function getStatusIcon(
    state: any,
    speed: any,
    gpsStatus: any,
): string {

  return {
    running: 'pi pi-play-circle',
    start: 'pi pi-pause-circle',
    parking: 'pi pi-stop-circle',
    no_gps: 'pi pi-times-circle',
  }[
      normalizeStatus(
          state,
          speed,
          gpsStatus,
      )
      ] ?? 'pi pi-circle'
}

// function getStatusIcon(
//     state: any,
//     speed: any,
//     gpsStatus: any,
// ): string {
//
//   return {
//     running: 'pi pi-play-circle',
//     start: 'pi pi-pause-circle',
//     parking: 'pi pi-stop-circle',
//     no_gps: 'pi pi-exclamation-circle',
//   }[
//       normalizeStatus(state, speed, gpsStatus)
//       ] ?? 'pi pi-circle'
// }

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

    address: item.address ?? ''
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

  width: 110px;
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

.status-running {
  color: #052e16;
  background: #22c55e;
}

.status-start {
  color: #422006;
  background: #eab308;
}

.status-parking {
  color: #ffffff;
  background: #ef4444;
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
</style>