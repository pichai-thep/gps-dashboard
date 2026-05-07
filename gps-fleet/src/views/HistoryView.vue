<template>
  <div class="history-page">
    <!-- LEFT -->
    <div class="left-panel">
      <!-- FILTER -->
      <div class="filter-card">
        <div class="card-header">
          <div>
            <h2>History Tracking</h2>
            <p>ดึงประวัติการเดินรถ</p>
          </div>
        </div>

        <!-- GROUP + VEHICLE -->
        <div class="field-grid">
          <div class="form-group">
            <label>Group</label>

            <select
                v-model="selectedGroup"
                :disabled="pageLoading"
            >
              <option :value="null">
                All Group
              </option>

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
                {{
                  pageLoading
                      ? 'Loading...'
                      : 'Select Vehicle'
                }}
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

        <!-- START -->
        <div class="date-grid">
          <div class="form-group">
            <label>Start Date</label>

            <input
                type="date"
                v-model="startDate"
            />
          </div>

          <div class="form-group">
            <label>Start Time</label>

            <input
                type="time"
                v-model="startTime"
            />
          </div>
        </div>

        <!-- END -->
        <div class="date-grid">
          <div class="form-group">
            <label>End Date</label>

            <input
                type="date"
                v-model="endDate"
            />
          </div>

          <div class="form-group">
            <label>End Time</label>

            <input
                type="time"
                v-model="endTime"
            />
          </div>
        </div>

        <!-- ERROR -->
        <div
            v-if="errorMessage"
            class="error-message"
        >
          {{ errorMessage }}
        </div>

        <!-- BUTTON -->
        <div class="button-row">
          <button
              type="button"
              class="primary-button"
              :disabled="loading || pageLoading"
              @click="loadHistory"
          >
            {{
              loading
                  ? 'Loading...'
                  : 'Load History'
            }}
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

      <!-- TABLE -->
      <div class="table-card">
        <div class="table-header">
          <div>
            <h3>Data List</h3>

            <p>
              {{ rows.length }} records
            </p>
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
              <td
                  colspan="4"
                  class="empty-cell"
              >
                No history data
              </td>
            </tr>

            <tr
                v-for="(row, index) in rows"
                :key="index"
            >
              <td>
                {{ index + 1 }}
              </td>

              <td>
                {{
                  row.gps_time ??
                  '-'
                }}
              </td>

              <td>
                {{ row.speed ?? 0 }}
              </td>

              <td>
                {{ row.status ?? '-' }}
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MAP -->
    <div class="map-panel">
      <FleetMap
          mode="history"
          :history-points="rows"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * --------------------------------------------------
 * IMPORTS
 * --------------------------------------------------
 */
import {
  computed,
  ref,
  watch,
} from 'vue'

import { useAuthStore } from '@/stores/auth'

import FleetMap from '@/components/FleetMap.vue'

import {
  getHistoryTracking,
  type HistoryPoint,
} from '@/services/history'

import { getGroups } from '@/services/groups'

import { getVehicles } from '@/services/vehicles'

/**
 * --------------------------------------------------
 * TYPES
 * --------------------------------------------------
 */
type GroupItem = {
  group_id: number | string
  group_name: string
}

type VehicleItem = {
  vehicle_id: number | string
  plate_no: string
  imei: string
  group_id?: number | string
}

/**
 * --------------------------------------------------
 * STORE
 * --------------------------------------------------
 */
const auth = useAuthStore()

/**
 * --------------------------------------------------
 * COMPUTED
 * --------------------------------------------------
 */
const customerId = computed(() => {
  return (
      auth.customer?.id ??
      auth.user?.customer_id ??
      auth.config?.customer_id ??
      localStorage.getItem('customer_id') ??
      null
  )
})

/**
 * --------------------------------------------------
 * STATE
 * --------------------------------------------------
 */
const pageLoading = ref(false)
const loading = ref(false)

const errorMessage = ref('')

const initializedCustomerId = ref<
    number | string | null
>(null)

const groups = ref<GroupItem[]>([])

const allVehicles = ref<VehicleItem[]>(
    []
)

const vehicles = ref<VehicleItem[]>(
    []
)

const selectedGroup = ref<
    number | string | null
>(null)

const selectedVehicle = ref<
    number | string | null
>(null)

const rows = ref<HistoryPoint[]>([])

/**
 * --------------------------------------------------
 * DEFAULT DATE/TIME
 * --------------------------------------------------
 */
const today = new Date()

const yyyy = today.getFullYear()

const mm = String(
    today.getMonth() + 1
).padStart(2, '0')

const dd = String(
    today.getDate()
).padStart(2, '0')

const todayText = `${yyyy}-${mm}-${dd}`

const startDate = ref(todayText)
const endDate = ref(todayText)

const startTime = ref('00:00')
const endTime = ref('23:59')

/**
 * --------------------------------------------------
 * WATCHERS
 * --------------------------------------------------
 */
watch(
    customerId,
    async (id) => {
      if (!id) return

      if (
          String(
              initializedCustomerId.value
          ) === String(id)
      ) {
        return
      }

      initializedCustomerId.value = id

      await initPage()
    },
    {
      immediate: true,
    }
)

watch(selectedGroup, (groupId) => {
  selectedVehicle.value = null

  rows.value = []

  errorMessage.value = ''

  if (!groupId) {
    vehicles.value =
        allVehicles.value

    return
  }

  vehicles.value =
      allVehicles.value.filter(
          (vehicle) => {
            return (
                String(vehicle.group_id) ===
                String(groupId)
            )
          }
      )
})

/**
 * --------------------------------------------------
 * INIT
 * --------------------------------------------------
 */
async function initPage() {
  errorMessage.value = ''

  pageLoading.value = true

  try {
    await Promise.all([
      loadGroups(),
      loadVehicles(),
    ])
  } finally {
    pageLoading.value = false
  }
}

async function loadGroups() {
  try {
    const response = await getGroups()

    const list = normalizeList<any>(
        response,
        [
          'data',
          'groups',
          'result',
        ]
    )

    groups.value = list.map((item) => ({
      group_id:
          item.group_id ??
          item.customer_group_id ??
          item.id ??
          item.value,

      group_name:
          item.group_name ??
          item.customer_group_name ??
          item.name ??
          item.label ??
          item.text ??
          '-',
    }))
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'โหลดกลุ่มรถไม่สำเร็จ'
  }
}

async function loadVehicles() {
  try {
    const response = await getVehicles()

    const list = normalizeList<any>(
        response,
        [
          'data',
          'vehicles',
          'result',
        ]
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

      group_id:
          item.group_id ??
          item.customer_group_id ??
          item.vehicle_group_id ??
          item.group,
    }))

    vehicles.value = allVehicles.value
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'โหลดรถไม่สำเร็จ'
  }
}

/**
 * --------------------------------------------------
 * ACTIONS
 * --------------------------------------------------
 */
async function loadHistory() {
  if (!validateRange()) {
    return
  }

  const vehicle =
      findSelectedVehicle()

  if (!vehicle?.imei) {
    errorMessage.value =
        'ไม่พบ IMEI ของรถ'

    return
  }

  loading.value = true

  rows.value = []

  try {
    const response =
        await getHistoryTracking({
          imei: vehicle.imei,

          start_date:
          startDate.value,

          end_date:
          endDate.value,

          start_time:
          startTime.value,

          end_time:
          endTime.value,
        })

    rows.value =
        normalizeList<HistoryPoint>(
            response,
            [
              'data',
              'rows',
              'result',
            ]
        )
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data
            ?.message ||
        'โหลดประวัติไม่สำเร็จ'
  } finally {
    loading.value = false
  }
}

function exportXls() {
  const header = [
    'No',
    'GPS Time',
    'Speed',
    'Status',
    'Latitude',
    'Longitude',
  ]

  const lines = rows.value.map(
      (row, index) => {
        const lat =
            row.lat ??
            row.latitude ??
            ''

        const lng =
            row.lng ??
            row.longitude ??
            ''

        return [
          index + 1,

          row.gps_time ??
          '',

          row.speed ?? 0,

          row.status ?? '',

          lat,
          lng,
        ].join('\t')
      }
  )

  const content = [
    header.join('\t'),
    ...lines,
  ].join('\n')

  const blob = new Blob(
      [content],
      {
        type: 'application/vnd.ms-excel;charset=utf-8;',
      }
  )

  const url =
      URL.createObjectURL(blob)

  const link =
      document.createElement('a')

  link.href = url

  link.download =
      `history-${startDate.value}-${endDate.value}.xls`

  link.click()

  URL.revokeObjectURL(url)
}

/**
 * --------------------------------------------------
 * VALIDATION
 * --------------------------------------------------
 */
function validateRange() {
  errorMessage.value = ''

  if (!customerId.value) {
    errorMessage.value =
        'ไม่พบ customer_id'

    return false
  }

  if (!selectedVehicle.value) {
    errorMessage.value =
        'กรุณาเลือกรถ'

    return false
  }

  const start = new Date(
      `${startDate.value}T${startTime.value}:00`
  )

  const end = new Date(
      `${endDate.value}T${endTime.value}:59`
  )

  if (start >= end) {
    errorMessage.value =
        'เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด'

    return false
  }

  const diffDays =
      (end.getTime() -
          start.getTime()) /
      86400000

  if (diffDays > 7) {
    errorMessage.value =
        'ดึงประวัติได้ไม่เกิน 7 วัน'

    return false
  }

  return true
}

/**
 * --------------------------------------------------
 * HELPERS
 * --------------------------------------------------
 */
function findSelectedVehicle() {
  return vehicles.value.find(
      (item) => {
        return (
            String(item.vehicle_id) ===
            String(
                selectedVehicle.value
            )
        )
      }
  )
}

function normalizeList<T>(
    response: any,
    keys: string[] = []
): T[] {
  if (Array.isArray(response)) {
    return response
  }

  for (const key of keys) {
    if (
        Array.isArray(
            response?.[key]
        )
    ) {
      return response[key]
    }
  }

  return []
}
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
  background: rgba(
      15,
      23,
      42,
      0.96
  );

  border: 1px solid
  rgba(
      148,
      163,
      184,
      0.16
  );

  border-radius: 18px;

  color: #ffffff;

  box-shadow: 0 18px 40px
  rgba(2, 6, 23, 0.3);
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

  font-size: 18px;
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

  grid-template-columns:
    1fr 1fr;

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

  border: 1px solid
  rgba(
      148,
      163,
      184,
      0.22
  );

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

  background: rgba(
      239,
      68,
      68,
      0.14
  );

  border: 1px solid
  rgba(
      239,
      68,
      68,
      0.28
  );

  color: #fecaca;

  font-size: 13px;
}

.button-row {
  display: grid;

  grid-template-columns:
    1fr 120px;

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
  background: rgba(
      148,
      163,
      184,
      0.16
  );

  color: #ffffff;

  border: 1px solid
  rgba(
      148,
      163,
      184,
      0.22
  );
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

  border-bottom: 1px solid
  rgba(
      148,
      163,
      184,
      0.12
  );

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
</style>