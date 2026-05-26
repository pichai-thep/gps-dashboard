<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>Station Visit Report</h1>
        <p>รายงานรถเข้า-ออกสถานี / จุดจอด</p>
      </div>

      <Button label="Refresh" icon="pi pi-refresh" :loading="loading" @click="loadData" />
    </div>

    <div class="filter-card">
      <Calendar v-model="dateFrom" dateFormat="yy-mm-dd" showIcon />
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />
      <InputText v-model="imei" placeholder="IMEI" @keyup.enter="search" />
      <InputText v-model="stationId" placeholder="Station ID" @keyup.enter="search" />

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
        <span>Total Stations</span>
        <strong>{{ summary.total_station }}</strong>
      </div>

      <div class="summary-card">
        <span>Total Duration</span>
        <strong>{{ formatDuration(summary.duration_s) }}</strong>
      </div>
    </div>

    <div class="table-card">
      <DataTable :value="rows" :loading="loading" stripedRows responsiveLayout="scroll">
        <Column field="data_date" header="วันที่" style="width: 130px" />
        <Column field="imei" header="IMEI" style="width: 180px" />

        <Column header="Station">
          <template #body="{ data }">
            <div class="station-cell">
              <b>{{ data.station_name || '-' }}</b>
              <small>ID: {{ data.station_id }}</small>
            </div>
          </template>
        </Column>

        <Column field="start_time" header="เข้า" style="width: 180px" />
        <Column field="end_time" header="ออก" style="width: 180px" />

        <Column header="ระยะเวลา" style="width: 130px">
          <template #body="{ data }">
            <Tag :value="formatDuration(data.duration_s)" severity="info" />
          </template>
        </Column>

        <Column field="updated_at" header="Updated" style="width: 180px" />
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

import { getStationSummary, type StationSummaryRow } from '@/services/report'

const loading = ref(false)

const dateFrom = ref<Date | null>(new Date())
const dateTo = ref<Date | null>(new Date())

const imei = ref('')
const stationId = ref('')

const rows = ref<StationSummaryRow[]>([])
const page = ref(1)
const perPage = ref(50)
const total = ref(0)

const summary = ref({
  total_rows: 0,
  total_vehicle: 0,
  total_station: 0,
  duration_s: 0,
})

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

async function loadData() {
  loading.value = true

  try {
    const res = await getStationSummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      imei: imei.value,
      station_id: stationId.value,
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
    'station_id',
    'station_name',
    'start_time',
    'end_time',
    'duration',
  ]

  const body = rows.value.map((r) => [
    r.data_date,
    r.imei,
    r.station_id,
    r.station_name || '',
    r.start_time,
    r.end_time,
    formatDuration(r.duration_s),
  ])

  downloadCsv('station-summary.csv', header, body)
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

.station-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.station-cell small {
  color: #94a3b8;
}
</style>