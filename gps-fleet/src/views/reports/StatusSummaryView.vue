<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>Status Timeline Report</h1>
        <p>รายงานช่วงเวลาสถานะรถ</p>
      </div>

      <Button label="Refresh" icon="pi pi-refresh" :loading="loading" @click="loadData" />
    </div>

    <div class="filter-card">
      <Calendar v-model="dateFrom" dateFormat="yy-mm-dd" showIcon />
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />
      <InputText v-model="imei" placeholder="IMEI" @keyup.enter="search" />

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
        <Column field="imei" header="IMEI" />

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
import Dropdown from 'primevue/dropdown'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'

import { getStatusSummary, type StatusSummaryRow } from '@/services/report'

const loading = ref(false)

const dateFrom = ref<Date | null>(new Date())
const dateTo = ref<Date | null>(new Date())
const imei = ref('')
const status = ref<string | null>(null)

const rows = ref<StatusSummaryRow[]>([])
const page = ref(1)
const perPage = ref(50)
const total = ref(0)

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
  if (value === 'running') return 'success'
  if (value === 'idle') return 'warning'
  if (value === 'parking') return 'info'
  if (value === 'offline') return 'danger'
  return 'secondary'
}

async function loadData() {
  loading.value = true

  try {
    const res = await getStatusSummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      imei: imei.value,
      status: status.value || '',
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
    'status',
    'start_time',
    'end_time',
    'duration',
  ]

  const body = rows.value.map((r) => [
    r.data_date,
    r.imei,
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

onMounted(loadData)
</script>

<style scoped>
@import './report-dark.css';
</style>