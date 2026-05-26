
http://localhost:8000/api/reports/daily-summary?date_from=2026-05-24&date_to=2026-05-26&imeis%5B%5D=864606041741959&imeis%5B%5D=864606041747246&imeis%5B%5D=860470063304947&page=1&per_page=50

<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>Daily Summary Report</h1>
        <p>รายงานสรุปการวิ่ง / จอด / Idle รายวัน</p>
      </div>

      <Button
          label="Export CSV"
          icon="pi pi-download"
          severity="secondary"
          :disabled="rows.length === 0"
          @click="exportCsv"
      />


    </div>

    <div class="filter-card">
      <Calendar
          v-model="dateFrom"
          dateFormat="yy-mm-dd"
          showIcon
      />

      <Calendar
          v-model="dateTo"
          dateFormat="yy-mm-dd"
          showIcon
      />

      <MultiSelect
          v-model="selectedGroups"
          :options="groupOptions"
          optionLabel="group_name"
          optionValue="group_id"
          placeholder="Select Group"
          display="chip"
          filter
          @change="onGroupChange"
      />

      <MultiSelect
          v-model="selectedVehicles"
          :options="vehicleOptions"
          optionLabel="plate_no"
          optionValue="imei"
          placeholder="Select Vehicle"
          display="chip"
          filter
          aria-multiline="true"
      />

      <Button
          label="Search"
          icon="pi pi-search"
          :loading="loading"
          @click="search"
      />

    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <span>Total Vehicles</span>
        <strong>{{ summary.total_vehicle }}</strong>
      </div>

      <div class="summary-card">
        <span>Total Distance</span>
        <strong>{{ formatKm(summary.distance_m) }}</strong>
      </div>

      <div class="summary-card running">
        <span>Running</span>
        <strong>{{ formatDuration(summary.run_time_s) }}</strong>
      </div>

      <div class="summary-card idle">
        <span>Idle</span>
        <strong>{{ formatDuration(summary.idle_time_s) }}</strong>
      </div>

      <div class="summary-card parking">
        <span>Parking</span>
        <strong>{{ formatDuration(summary.park_time_s) }}</strong>
      </div>
    </div>

    <div class="table-card">
      <DataTable
          :value="rows"
          :loading="loading"
          stripedRows
          responsiveLayout="scroll"
          class="summary-table"
      >
        <Column field="data_date" header="วันที่" style="width: 120px" />

<!--        <Column field="imei" header="IMEI" style="width: 180px" />-->
        <Column field="plate_no" header="Plate" style="width: 180px" />

        <Column header="Running" style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.run_time_s)"
                severity="success"
            />
          </template>
        </Column>

        <Column header="Idle" style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.idle_time_s)"
                severity="warning"
            />
          </template>
        </Column>

        <Column header="Parking" style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.park_time_s)"
                severity="info"
            />
          </template>
        </Column>

        <Column header="Distance" style="width: 120px">
          <template #body="{ data }">
            <b>{{ formatKm(data.distance_m) }}</b>
          </template>
        </Column>

        <Column field="ur_formula" header="Formula" style="width: 170px">
          <template #body="{ data }">
            <Tag
                :value="data.ur_formula"
                severity="secondary"
            />
          </template>
        </Column>

        <Column header="UR Rate" style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatPercent(data.ur_rate)"
                :severity="urRateSeverity(data.ur_rate)"
            />
          </template>
        </Column>

        <Column field="updated_at" header="Updated" style="width: 200px" />
      </DataTable>

      <Paginator
          :rows="perPage"
          :totalRecords="total"
          :first="(page - 1) * perPage"
          :rowsPerPageOptions="[20, 50, 100, 200]"
          @page="onPage"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Calendar from 'primevue/calendar'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import MultiSelect from 'primevue/multiselect'
import {
  getDailySummary,
  getReportGroups,
  getReportVehicles,
  type DailySummaryRow,
} from '@/services/report'


const selectedGroups = ref<number[]>([])
const selectedVehicles = ref<string[]>([])

const groupOptions = ref<any[]>([])
const vehicleOptions = ref<any[]>([])

const loading = ref(false)

const dateFrom = ref<Date | null>(new Date())
const dateTo = ref<Date | null>(new Date())

const rows = ref<DailySummaryRow[]>([])

const page = ref(1)
const perPage = ref(50)
const total = ref(0)

const summary = ref({
  total_rows: 0,
  total_vehicle: 0,
  run_time_s: 0,
  idle_time_s: 0,
  park_time_s: 0,
  distance_m: 0,
})

function formatPercent(value: number | null | undefined) {
  if (value === null || value === undefined) return '-'
  return `${Number(value).toFixed(2)}%`
}

function urRateSeverity(value?: number | null) {
  if (value == null) return 'secondary'

  if (value >= 80) return 'success'
  if (value >= 50) return 'warning'

  return 'danger'
}

function normalizeOptions(res: any) {
  if (Array.isArray(res)) return res
  if (Array.isArray(res?.data)) return res.data
  return []
}

async function loadOptions() {
  const groupsRes = await getReportGroups()
  const vehiclesRes = await getReportVehicles()

  groupOptions.value = normalizeOptions(groupsRes)
  vehicleOptions.value = normalizeOptions(vehiclesRes)

  console.log('groups', groupOptions.value)
  console.log('vehicles', vehicleOptions.value)
}

async function onGroupChange() {
  selectedVehicles.value = []

  const vehiclesRes = await getReportVehicles({
    group_ids: selectedGroups.value,
  })

  vehicleOptions.value = normalizeOptions(vehiclesRes)

  console.log('vehicles by group', vehicleOptions.value)
}

function toDateString(date: Date | null) {
  if (!date) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function formatDuration(seconds: number) {
  seconds = Number(seconds || 0)

  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)

  if (h <= 0) return `${m}m`
  return `${h}h ${m}m`
}

function formatKm(meter: number) {
  const km = Number(meter || 0) / 1000
  return `${km.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} km`
}

async function loadData() {
  loading.value = true

  try {
    const res = await getDailySummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      group_ids: selectedGroups.value,
      imeis: selectedVehicles.value,
      page: page.value,
      per_page: perPage.value,
    })

    rows.value = res.data
    summary.value = res.summary
    total.value = res.pagination.total
  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  loadData()
}

function onPage(event: any) {
  page.value = event.page + 1
  perPage.value = event.rows
  loadData()
}

function exportCsv() {
  const header = [
    'date',
    'imei',
    'plate_no',
    'running',
    'idle',
    'parking',
    'distance_km',
    'ur_formula',
    'ur_rate',
    'updated_at',
  ]

  const body = rows.value.map((r) => [
    r.data_date,
    r.imei,
    r.plate_no,
    formatDuration(r.run_time_s),
    formatDuration(r.idle_time_s),
    formatDuration(r.park_time_s),
    (Number(r.distance_m || 0) / 1000).toFixed(2),
    (r as any).ur_formula,
    formatPercent((r as any).ur_rate),
    r.updated_at,
  ])

  downloadCsv('daily-summary.csv', header, body)
}

function downloadCsv(filename: string, header: string[], rows: Array<Array<string | number>>) {
  const csv = [
    header.join(','),
    ...rows.map((row) =>
        row
            .map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`)
            .join(',')
    ),
  ].join('\n')

  const blob = new Blob(['\ufeff' + csv], {
    type: 'text/csv;charset=utf-8;',
  })

  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()

  URL.revokeObjectURL(link.href)
}

onMounted(async () => {
  await loadOptions()
  await loadData()
})

</script>

<style scoped>
@import './report-dark.css';

.summary-card.running {
  border-color: rgba(34, 197, 94, 0.35);
}

.summary-card.idle {
  border-color: rgba(245, 158, 11, 0.35);
}

.summary-card.parking {
  border-color: rgba(59, 130, 246, 0.35);
}
</style>