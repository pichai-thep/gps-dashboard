<template>
  <div class="report-page">
    <ReportPageHeader :title="t('dailySummaryReport')" :subtitle="t('dailySummarySubtitle')">
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
          <label>{{ t('distanceFromKm') }}</label>
          <InputNumber
            v-model="distanceFromKm"
            :min="0"
            :minFractionDigits="0"
            :maxFractionDigits="1"
            suffix=" km"
          />
        </div>
      </template>
    </BaseReportFilters>

    <ReportSummaryCards :items="summaryItems" :columns="6" />

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
          <Tag
            v-if="durationFields.includes(column.field)"
            :value="formatDuration(data[column.field])"
            :severity="durationSeverity(column.field)"
          />
          <b v-else-if="column.field === 'distance_m'">{{ formatKm(data.distance_m) }}</b>
          <Tag v-else-if="column.field === 'ur_formula'" :value="data.ur_formula" severity="secondary" />
          <Tag
            v-else-if="column.field === 'ur_rate'"
            :value="formatPercent(data.ur_rate)"
            :severity="urRateSeverity(data.ur_rate)"
          />
          <span v-else>{{ formattedValue }}</span>
        </template>
      </ReportDataTable>
    </div>
  </div>
</template>

<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import BaseReportFilters from '@/components/reports/BaseReportFilters.vue'
import ReportDataTable, { type ReportTableColumn } from '@/components/reports/ReportDataTable.vue'
import ReportPageHeader from '@/components/reports/ReportPageHeader.vue'
import ReportSummaryCards, { type ReportSummaryItem } from '@/components/reports/ReportSummaryCards.vue'
import {
  getDailySummary,
  getReportGroups,
  getReportVehicles,
  type DailySummaryRow,
} from '@/services/report'
import { useI18n } from '@/i18n'
import { formatReportDuration } from '@/utils/reportDurationFormat'
import { downloadReportCsv } from '@/utils/reportExport'
import { formatDistanceKmFromMeters, formatReportInteger } from '@/utils/reportNumberFormat'
import { openReportPrintWindow, renderReportPrintWindow } from '@/utils/reportPrint'

const { t } = useI18n()

const selectedGroups = ref<number[]>([])
const selectedVehicles = ref<string[]>([])
const distanceFromKm = ref<number | null>(null)

const groupOptions = ref<any[]>([])
const vehicleOptions = ref<any[]>([])

const loading = ref(false)

// const dateFrom = ref<Date | null>(new Date())
// const dateTo = ref<Date | null>(new Date())

const now = new Date()
const yesterday = new Date()
yesterday.setDate(yesterday.getDate() - 1)
yesterday.setHours(0, 0, 0, 0)

const dateFrom = ref<Date | null>(yesterday)
const dateTo = ref<Date | null>(now)

const rows = ref<DailySummaryRow[]>([])

const page = ref(1)
const perPage = ref(50)

const totalRows = ref(0)
const totalPages = ref(0)
const sortField = ref('data_date')
const sortOrder = ref<'asc' | 'desc'>('desc')
const durationFields = ['run_time_s', 'idle_time_s', 'park_time_s']

const tableColumns = computed<ReportTableColumn[]>(() => [
  { field: 'data_date', label: t('date'), width: '150px' },
  { field: 'plate_no', label: t('plate') },
  { field: 'run_time_s', label: t('running'), width: '120px' },
  { field: 'idle_time_s', label: t('idle'), width: '120px' },
  { field: 'park_time_s', label: t('parking'), width: '120px' },
  { field: 'distance_m', label: t('distance'), width: '150px' },
  { field: 'ur_formula', label: t('formula'), sortable: false, width: '170px' },
  { field: 'ur_rate', label: t('urRate'), width: '120px' },
  { field: 'updated_at', label: t('updated'), width: '200px' },
])

const summary = ref({
  total_rows: 0,
  total_vehicle: 0,
  run_time_s: 0,
  idle_time_s: 0,
  park_time_s: 0,
  distance_m: 0,
  ur_rate_avg: 0,
})

const avgUrRate = computed(() => {
  if (!rows.value.length) return 0

  let total = 0
  let count = 0
  for (const row of rows.value) {
    const ur = Number((row as any).ur_rate)
    if (!isNaN(ur)) {
      total += ur
      count++
    }
  }
  if (count <= 0) return 0
  return total / count
})

const summaryItems = computed<ReportSummaryItem[]>(() => [
  { key: 'vehicles', label: t('totalVehicles'), value: formatReportInteger(summary.value.total_vehicle) },
  { key: 'distance', label: t('totalDistance'), value: formatKm(summary.value.distance_m) },
  { key: 'ur-rate', label: t('urRateAvg'), value: formatPercent(avgUrRate.value), className: 'ur-rate' },
  { key: 'running', label: t('running'), value: formatDuration(summary.value.run_time_s), className: 'running' },
  { key: 'idle', label: t('idle'), value: formatDuration(summary.value.idle_time_s), className: 'idle' },
  { key: 'parking', label: t('parking'), value: formatDuration(summary.value.park_time_s), className: 'parking' },
])

async function onPage(event: any) {
  perPage.value = event.rows
  page.value = Math.floor(event.first / event.rows) + 1
  await loadData()
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

async function resetFilter() {
  selectedGroups.value = []
  selectedVehicles.value = []
  distanceFromKm.value = null

  const nowDate = new Date()
  const yesterdayDate = new Date()
  yesterdayDate.setDate(yesterdayDate.getDate() - 1)
  yesterdayDate.setHours(0, 0, 0, 0)

  dateFrom.value = yesterdayDate
  dateTo.value = nowDate

  page.value = 1
  perPage.value = 50

  sortField.value = 'data_date'
  sortOrder.value = 'desc'

  rows.value = []
  totalRows.value = 0
  totalPages.value = 0
  summary.value = {
    total_rows: 0,
    total_vehicle: 0,
    run_time_s: 0,
    idle_time_s: 0,
    park_time_s: 0,
    distance_m: 0,
    ur_rate_avg: 0,
  }

  await loadOptions()
}

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
  return formatReportDuration(seconds, 'duration_s')
}

function formatKm(meter: number) {
  return formatDistanceKmFromMeters(meter)
}

function durationSeverity(field: string) {
  if (field === 'run_time_s') return 'success'
  if (field === 'idle_time_s') return 'warning'
  return 'info'
}

async function loadData() {
  loading.value = true

  try {

    const res = await getDailySummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      group_ids: selectedGroups.value,
      imeis: selectedVehicles.value,
      distance_from_km: distanceFromKm.value,
      page: page.value,
      per_page: perPage.value,
      sort_by: sortField.value,
      sort_order: sortOrder.value,
    })

    rows.value = res.data ?? []

    summary.value = {
      total_rows: res.summary?.total_rows ?? 0,
      total_vehicle: res.summary?.total_vehicle ?? 0,
      run_time_s: res.summary?.run_time_s ?? 0,
      idle_time_s: res.summary?.idle_time_s ?? 0,
      park_time_s: res.summary?.park_time_s ?? 0,
      distance_m: res.summary?.distance_m ?? 0,
      ur_rate_avg: res.summary?.ur_rate_avg ?? 0,
    }

    page.value = res.pagination?.current_page ?? page.value
    perPage.value = res.pagination?.per_page ?? perPage.value
    totalRows.value = res.pagination?.total_rows ?? 0
    totalPages.value = res.pagination?.total_pages ?? 0

  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await loadData()
}

async function exportCsv() {

  const res = await getDailySummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    distance_from_km: distanceFromKm.value,
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
    'running',
    'idle',
    'parking',
    'distance_km',
    'ur_formula',
    'ur_rate',
    'updated_at',
  ]

  const body = exportRows.map((r: any) => [
    r.data_date,
    r.imei,
    r.plate_no,
    formatDuration(r.run_time_s),
    formatDuration(r.idle_time_s),
    formatDuration(r.park_time_s),
    formatDistanceKmFromMeters(r.distance_m, false),
    r.ur_formula,
    formatPercent(r.ur_rate),
    r.updated_at,
  ])

  downloadReportCsv('daily-summary.csv', header, body)
}

async function savePdf() {
  const target = openReportPrintWindow(t('dailySummaryReport'))
  if (!target) return

  const res = await getDailySummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    distance_from_km: distanceFromKm.value,
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
    formatDuration(row.run_time_s),
    formatDuration(row.idle_time_s),
    formatDuration(row.park_time_s),
    formatDistanceKmFromMeters(row.distance_m, false),
    row.ur_formula,
    formatPercent(row.ur_rate),
    row.updated_at,
  ])

  renderReportPrintWindow(target, {
    title: t('dailySummaryReport'),
    period: `${t('reportPeriod')}: ${toDateString(dateFrom.value)} - ${toDateString(dateTo.value)}`,
    headers: [
      t('date'),
      'IMEI',
      t('plate'),
      t('running'),
      t('idle'),
      t('parking'),
      t('distance'),
      t('formula'),
      t('urRate'),
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

.summary-card.running {
  border-color: rgba(34, 197, 94, 0.35);
}

.summary-card.idle {
  border-color: rgba(245, 158, 11, 0.35);
}

.summary-card.parking {
  border-color: rgba(239, 68, 68, 0.35);
}

.summary-card.ur-rate {
  border-color: rgba(168, 85, 247, 0.35);
}

.summary-card.ur-rate strong {
  color: #c084fc;
}

</style>
