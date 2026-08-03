<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>{{ t('stationVisitReport') }}</h1>
        <p>{{ t('stationVisitSubtitle') }}</p>
      </div>

      <div class="header-actions">
        <Button
            :label="t('exportCsv')"
            icon="pi pi-download"
            severity="secondary"
            :disabled="totalRows === 0"
            @click="exportCsv"
        />
        <Button
            :label="t('savePdf')"
            icon="pi pi-file-pdf"
            severity="secondary"
            :disabled="totalRows === 0"
            @click="savePdf"
        />
      </div>
    </div>

    <div class="filter-card">
      <Calendar v-model="dateFrom" dateFormat="yy-mm-dd" showIcon />
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />

      <MultiSelect
          v-model="selectedGroups"
          :options="groupOptions"
          optionLabel="group_name"
          optionValue="group_id"
          :placeholder="t('selectGroup')"
          display="chip"
          filter
          @change="onGroupChange"
      />

      <MultiSelect
          v-model="selectedVehicles"
          :options="vehicleOptions"
          optionLabel="plate_no"
          optionValue="imei"
          :placeholder="t('selectVehicle')"
          display="chip"
          filter
      />

      <Dropdown
          v-model="stationId"
          :options="stationOptions"
          optionLabel="station_name"
          optionValue="station_id"
          :placeholder="t('selectStation')"
          showClear
          filter
      />

      <Button :label="t('search')" icon="pi pi-search" :loading="loading" @click="search" />
      <Button
          :label="t('reset')"
          icon="pi pi-refresh"
          severity="secondary"
          outlined
          @click="resetFilter"
      />
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <span>{{ t('totalRows') }}</span>
        <strong>{{ summary.total_rows }}</strong>
      </div>

      <div class="summary-card">
        <span>{{ t('totalVehicles') }}</span>
        <strong>{{ summary.total_vehicle }}</strong>
      </div>

      <div class="summary-card">
        <span>{{ t('totalStations') }}</span>
        <strong>{{ summary.total_station }}</strong>
      </div>

      <div class="summary-card">
        <span>{{ t('totalDuration') }}</span>
        <strong>{{ formatDuration(summary.duration_s) }}</strong>
      </div>
    </div>

    <div class="table-card">
      <DataTable
          :value="rows"
          :loading="loading"
          stripedRows
          responsiveLayout="scroll"
          lazy
          :sortField="sortField"
          :sortOrder="sortOrder === 'asc' ? 1 : -1"
          @sort="onSort"
      >

        <Column field="data_date" :header="t('date')" sortable style="width: 130px" />
        <Column field="plate_no" :header="t('plate')" sortable style="width: 200px" />
<!--        <Column field="imei" header="IMEI" sortable style="width: 180px" />-->

        <Column field="station_name" :header="t('station')" sortable style="width: 250px" >
          <template #body="{ data }">
            <div class="station-cell">
              <b>{{ data.station_name || '-' }}</b>
              <small>ID: {{ data.station_id }}</small>
            </div>
          </template>
        </Column>

        <Column field="start_time" :header="t('start')" sortable style="width: 200px" />
        <Column field="end_time" :header="t('end')" sortable style="width: 200px" />

        <Column field="duration_s" :header="t('duration')" sortable style="width: 130px">
          <template #body="{ data }">
            <Tag :value="formatDuration(data.duration_s)" severity="info" />
          </template>
        </Column>

        <Column field="updated_at" :header="t('updated')" sortable style="width: 200px" />
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
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import {
  getStationSummary,
  getReportGroups,
  getReportVehicles,
  getReportStations,
  type StationSummaryRow,
} from '@/services/report'
import { useI18n } from '@/i18n'
import { openReportPrintWindow, renderReportPrintWindow } from '@/utils/reportPrint'

const { t } = useI18n()

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
const stationOptions = ref<any[]>([])
const stationId = ref<number | null>(null)

const rows = ref<StationSummaryRow[]>([])
const page = ref(1)
const perPage = ref(50)
const totalRows = ref(0)
const totalPages = ref(0)
const sortField = ref('data_date')
const sortOrder = ref<'asc' | 'desc'>('desc')

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
      group_ids: selectedGroups.value,
      imeis: selectedVehicles.value,
      station_id: stationId.value || 0,
      page: page.value,
      per_page: perPage.value,
      sort_by: sortField.value,
      sort_order: sortOrder.value,
    })

    rows.value = res.data ?? []

    summary.value = {
      total_rows: res.summary?.total_rows ?? 0,
      total_vehicle: res.summary?.total_vehicle ?? 0,
      total_station: res.summary?.total_station ?? 0,
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

async function resetFilter() {
  selectedGroups.value = []
  selectedVehicles.value = []
  stationId.value = null

  const nowDate = new Date()
  const yesterdayDate = new Date()

  yesterdayDate.setDate(yesterdayDate.getDate() - 1)
  yesterdayDate.setHours(0, 0, 0, 0)

  dateFrom.value = yesterdayDate
  dateTo.value = nowDate

  sortField.value = 'data_date'
  sortOrder.value = 'desc'

  page.value = 1
  perPage.value = 50

  rows.value = []
  totalRows.value = 0
  totalPages.value = 0
  summary.value = {
    total_rows: 0,
    total_vehicle: 0,
    total_station: 0,
    duration_s: 0,
  }

  await loadOptions()
}

async function onSort(event: any) {
  sortField.value = event.sortField

  sortOrder.value =
      event.sortOrder === 1
          ? 'asc'
          : 'desc'

  page.value = 1

  await loadData()
}

async function onPage(event: any) {
  perPage.value = event.rows
  page.value = Math.floor(event.first / event.rows) + 1
  await loadData()
}

function normalizeOptions(res: any) {
  if (Array.isArray(res)) return res
  if (Array.isArray(res?.data)) return res.data
  return []
}

async function loadOptions() {
  const groupsRes = await getReportGroups()
  const vehiclesRes = await getReportVehicles()
  const stationsRes = await getReportStations()

  groupOptions.value = normalizeOptions(groupsRes)
  vehicleOptions.value = normalizeOptions(vehiclesRes)
  stationOptions.value = normalizeOptions(stationsRes)
}

async function onGroupChange() {
  selectedVehicles.value = []

  const vehiclesRes = await getReportVehicles({
    group_ids: selectedGroups.value,
  })

  vehicleOptions.value = normalizeOptions(vehiclesRes)
}

async function exportCsv() {
  const res = await getStationSummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    station_id: stationId.value || 0,
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    page: 1,
    per_page: totalRows.value || 100000,
    sort_by: sortField.value,
    sort_order: sortOrder.value,
    export: true,
  })

  const exportRows = res.data ?? []

  const header = [
    'date',
    'imei',
    'plate_no',
    'station_id',
    'start_time',
    'end_time',
    'duration',
    'updated_at',
  ]

  const body = exportRows.map((r: any) => [
    r.data_date,
    r.imei,
    r.plate_no,
    r.station_id,
    r.start_time,
    r.end_time,
    formatDuration(r.duration_s),
    r.updated_at,
  ])

  downloadCsv(
      `station-summary-${toDateString(dateFrom.value)}-${toDateString(dateTo.value)}.csv`,
      header,
      body
  )
}

async function savePdf() {
  const target = openReportPrintWindow(t('stationVisitReport'))
  if (!target) return

  const res = await getStationSummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    station_id: stationId.value || 0,
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    page: 1,
    per_page: totalRows.value || 100000,
    sort_by: sortField.value,
    sort_order: sortOrder.value,
    export: true,
  })

  const printRows = (res.data ?? []).map((row: any) => [
    row.data_date,
    row.imei,
    row.plate_no,
    row.station_name || row.station_id,
    row.start_time,
    row.end_time,
    formatDuration(row.duration_s),
    row.updated_at,
  ])

  renderReportPrintWindow(target, {
    title: t('stationVisitReport'),
    period: `${t('reportPeriod')}: ${toDateString(dateFrom.value)} - ${toDateString(dateTo.value)}`,
    headers: [
      t('date'),
      'IMEI',
      t('plate'),
      t('station'),
      t('start'),
      t('end'),
      t('duration'),
      t('updated'),
    ],
    rows: printRows,
  })
}

function downloadCsv(
    filename: string,
    header: string[],
    rows: Array<Array<string | number>>
) {
  const csv = [
    header.join(','),
    ...rows.map((row) =>
        row
            .map((value) =>
                `"${String(value ?? '').replace(/"/g, '""')}"`
            )
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
})

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
