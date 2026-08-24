<template>
  <div class="report-page">
    <ReportPageHeader :title="t('stationVisitReport')" :subtitle="t('stationVisitSubtitle')">
      <template #actions>
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
      </template>
    </ReportPageHeader>

    <BaseReportFilters
      v-model:dateFrom="dateFrom"
      v-model:dateTo="dateTo"
      v-model:groupIds="selectedGroups"
      v-model:imeis="selectedVehicles"
      :groupOptions="groupOptions"
      :vehicleOptions="vehicleOptions"
      :loading="loading"
      :filtersLoading="filtersLoading"
      :hasRows="totalRows > 0"
      multiple
      :enableExportCsv="false"
      :enablePdf="false"
      @group-change="onGroupChange"
      @search="search"
      @reset="resetFilter"
    >
      <template #criteria>
        <div class="custom-filter-field">
          <label>{{ t('selectStation') }}</label>
          <Dropdown
            v-model="stationId"
            :options="stationOptions"
            optionLabel="station_name"
            optionValue="station_id"
            :placeholder="t('selectStation')"
            showClear
            filter
          />
        </div>
      </template>
    </BaseReportFilters>

    <ReportSummaryCards :items="summaryItems" :columns="4" />

    <div class="table-card">
      <ReportDataTable
          :rows="rows"
          :columns="tableColumns"
          :loading="loading"
          paginator
          lazy
          :pageSize="perPage"
          :rowsPerPageOptions="[10, 20, 50, 100, 200, 500]"
          :first="(page - 1) * perPage"
          :totalRecords="totalRows"
          :sortField="sortField"
          :sortOrder="sortOrder === 'asc' ? 1 : -1"
          :emptyLabel="t('reportNoData')"
          @sort="onSort"
          @page="onPage"
      >
        <template #cell="{ data, column, formattedValue }">
          <template v-if="column.field === 'station_name'">
            <div class="station-cell">
              <b>{{ data.station_name || '-' }}</b>
              <small>ID: {{ formatReportInteger(data.station_id) }}</small>
            </div>
          </template>
          <Tag
            v-else-if="column.field === 'duration_s'"
            :value="formatDuration(data.duration_s)"
            severity="info"
          />
          <span v-else-if="column.field === 'distance_from_previous_m'">
            {{ formatDistanceKmFromMeters(data.distance_from_previous_m, false) }}
          </span>
          <span v-else>{{ formattedValue }}</span>
        </template>
      </ReportDataTable>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dropdown from 'primevue/dropdown'
import BaseReportFilters from '@/components/reports/BaseReportFilters.vue'
import ReportDataTable, { type ReportTableColumn } from '@/components/reports/ReportDataTable.vue'
import ReportPageHeader from '@/components/reports/ReportPageHeader.vue'
import ReportSummaryCards, { type ReportSummaryItem } from '@/components/reports/ReportSummaryCards.vue'
import {
  getStationSummary,
  getReportGroups,
  getReportVehicles,
  getReportStations,
  type StationSummaryRow,
} from '@/services/report'
import { useI18n } from '@/i18n'
import { formatReportDuration } from '@/utils/reportDurationFormat'
import { downloadReportCsv } from '@/utils/reportExport'
import { formatDistanceKmFromMeters, formatReportInteger } from '@/utils/reportNumberFormat'
import { openReportPrintWindow, renderReportPrintWindow } from '@/utils/reportPrint'

const { t } = useI18n()

const loading = ref(false)
const filtersLoading = ref(false)

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

const tableColumns = computed<ReportTableColumn[]>(() => [
  { field: 'data_date', label: t('date'), width: '170px', minWidth: '170px', whiteSpace: 'nowrap' },
  { field: 'plate_no', label: t('plate') },
  { field: 'station_name', label: t('station'), width: '250px' },
  { field: 'start_time', label: t('start'), width: '200px' },
  { field: 'end_time', label: t('end'), width: '200px' },
  { field: 'duration_s', label: t('duration'), width: '130px' },
  {
    field: 'distance_from_previous_m',
    label: `${t('distanceFromPreviousStation')} (km)`,
    width: '190px',
  },
  { field: 'updated_at', label: t('updated'), width: '200px' },
])

const summary = ref({
  total_rows: 0,
  total_vehicle: 0,
  total_station: 0,
  duration_s: 0,
})

const summaryItems = computed<ReportSummaryItem[]>(() => [
  { key: 'rows', label: t('totalRows'), value: formatReportInteger(summary.value.total_rows) },
  { key: 'vehicles', label: t('totalVehicles'), value: formatReportInteger(summary.value.total_vehicle) },
  { key: 'stations', label: t('totalStations'), value: formatReportInteger(summary.value.total_station) },
  { key: 'duration', label: t('totalDuration'), value: formatDuration(summary.value.duration_s) },
])

function toDateString(date: Date | null) {
  if (!date) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function formatDuration(seconds: number) {
  return formatReportDuration(seconds, 'duration_s')
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
  filtersLoading.value = true
  try {
    const [groupsRes, vehiclesRes, stationsRes] = await Promise.all([
      getReportGroups(), getReportVehicles(), getReportStations(),
    ])
    groupOptions.value = normalizeOptions(groupsRes)
    vehicleOptions.value = normalizeOptions(vehiclesRes)
    stationOptions.value = normalizeOptions(stationsRes)
  } finally {
    filtersLoading.value = false
  }
}

async function onGroupChange() {
  selectedVehicles.value = []

  filtersLoading.value = true
  try {
    const vehiclesRes = await getReportVehicles({ group_ids: selectedGroups.value })
    vehicleOptions.value = normalizeOptions(vehiclesRes)
  } finally {
    filtersLoading.value = false
  }
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
    'distance_from_previous_station_km',
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
    formatDistanceKmFromMeters(r.distance_from_previous_m, false),
    r.updated_at,
  ])

  downloadReportCsv(
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
    formatDistanceKmFromMeters(row.distance_from_previous_m, false),
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
      `${t('distanceFromPreviousStation')} (km)`,
      t('updated'),
    ],
    rows: printRows,
  })
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
