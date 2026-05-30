<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>Status Timeline Report</h1>
        <p>รายงานช่วงเวลาสถานะรถ</p>
      </div>
      <Button
          label="Export CSV"
          icon="pi pi-download"
          severity="secondary"
          :disabled="totalRows === 0"
          @click="exportCsv"
      />
    </div>

    <div class="filter-card">
      <Calendar v-model="dateFrom" dateFormat="yy-mm-dd" showIcon />
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />

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
      />

      <Dropdown
          v-model="status"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Status"
          showClear
      />

      <Button label="Search" icon="pi pi-search" :loading="loading" @click="search" />
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <span>Total Rows</span>
        <strong>{{ summary.total_rows }}</strong>
      </div>

      <div class="summary-card">
        <span>Total Vehicles</span>
        <strong>{{ summary.total_vehicle }}</strong>
      </div>

      <div class="summary-card">
        <span>Total Duration</span>
        <strong>{{ formatDuration(summary.duration_s) }}</strong>
      </div>
    </div>

    <div class="table-card">

      <DataTable :value="rows" :loading="loading" stripedRows responsiveLayout="scroll">
        <Column field="data_date" header="วันที่" />
<!--        <Column field="imei" header="IMEI" />-->
        <Column field="plate_no" header="Plate" style="width: 180px" />

        <Column header="Status">
          <template #body="{ data }">
            <Tag :value="data.gps_status" :severity="statusSeverity(data.gps_status)" />
          </template>
        </Column>

        <Column field="start_time" header="เริ่ม" />
        <Column field="end_time" header="สิ้นสุด" />

        <Column header="ระยะเวลา">
          <template #body="{ data }">
            <b>{{ formatDuration(data.duration_s) }}</b>
          </template>
        </Column>
      </DataTable>

      <Paginator
          :rows="perPage"
          :totalRecords="totalRows"
          :first="(page - 1) * perPage"
          :rowsPerPageOptions="[10, 20, 50, 100, 200, 500]"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
          currentPageReportTemplate="{first} - {last} / {totalRecords}"
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
import Dropdown from 'primevue/dropdown'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import MultiSelect from 'primevue/multiselect'
import {
  getStatusSummary,
  getReportGroups,
  getReportVehicles,
  type StatusSummaryRow,
} from '@/services/report'

const loading = ref(false)

const now = new Date()
const yesterday = new Date()
yesterday.setDate(yesterday.getDate() - 1)
yesterday.setHours(0, 0, 0, 0)

const dateFrom = ref<Date | null>(yesterday)
const dateTo = ref<Date | null>(now)
const selectedGroups = ref<number[]>([])
const selectedVehicles = ref<string[]>([])

const groupOptions = ref<any[]>([])
const vehicleOptions = ref<any[]>([])
const status = ref<string | null>(null)

const rows = ref<StatusSummaryRow[]>([])
const page = ref(1)
const perPage = ref(50)
const totalRows = ref(0)
const totalPages = ref(0)


const summary = ref({
  total_rows: 0,
  total_vehicle: 0,
  duration_s: 0,
})

const statusOptions = [
  { label: 'Running', value: 'running' },
  { label: 'Idle', value: 'idle' },
  { label: 'Parking', value: 'parking' },
  { label: 'Offline', value: 'offline' },
  { label: 'No GPS', value: 'no_gps' },
]

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
}

async function onGroupChange() {
  selectedVehicles.value = []

  const vehiclesRes = await getReportVehicles({
    group_ids: selectedGroups.value,
  })

  vehicleOptions.value = normalizeOptions(vehiclesRes)
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

function statusSeverity(value: string) {
  const v = String(value || '')
      .trim()
      .toLowerCase()

  if (v === 'run') return 'success'
  if (v === 'idle') return 'warn'
  if (v === 'park') return 'info'
  if (v === 'offline') return 'danger'
  if (v === 'no_gps') return 'secondary'

  return 'contrast'
}

async function loadData() {
  loading.value = true

  try {
    const res = await getStatusSummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      group_ids: selectedGroups.value,
      imeis: selectedVehicles.value,
      status: status.value || '',
      page: page.value,
      per_page: perPage.value,
    })

    rows.value = res.data ?? []

    summary.value = {
      total_rows: res.summary?.total_rows ?? 0,
      total_vehicle: res.summary?.total_vehicle ?? 0,
      duration_s: res.summary?.duration_s ?? 0,
    }

    page.value = res.pagination?.current_page ?? page.value
    perPage.value = res.pagination?.per_page ?? perPage.value
    totalRows.value = res.pagination?.total_rows ?? 0
    totalPages.value = res.pagination?.total_pages ?? 0

  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  loadData()
}

async function onPage(event: any) {
  perPage.value = event.rows
  page.value = Math.floor(event.first / event.rows) + 1
  await loadData()
}

async function exportCsv() {
  const res = await getStatusSummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    status: status.value || '',
    page: 1,
    per_page: totalRows.value || 100000,
    export: true,
  })

  const exportRows = res.data ?? []

  const header = [
    'date',
    'imei',
    'plate_no',
    'status',
    'start_time',
    'end_time',
    'duration',
  ]

  const body = exportRows.map((r: any) => [
    r.data_date,
    r.imei,
    r.plate_no,
    r.gps_status,
    r.start_time,
    r.end_time,
    formatDuration(r.duration_s),
  ])

  downloadCsv('status-summary.csv', header, body)
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
</style>