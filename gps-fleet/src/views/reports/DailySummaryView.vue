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
            :label="t('saveXlsx')"
            icon="pi pi-file-excel"
            severity="secondary"
            :loading="xlsxLoading"
            :disabled="totalRows === 0"
            @click="saveXlsx"
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
    />

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
import {
  downloadReportCsv,
  downloadReportExcelSheet,
  type ReportExcelSheetRow,
} from '@/utils/reportExport'
import { formatDistanceKmFromMeters, formatReportInteger } from '@/utils/reportNumberFormat'
import { openReportPrintWindow, renderReportPrintWindow } from '@/utils/reportPrint'

const { t } = useI18n()

const selectedGroups = ref<number[]>([])
const selectedVehicles = ref<string[]>([])

const groupOptions = ref<any[]>([])
const vehicleOptions = ref<any[]>([])

const loading = ref(false)
const filtersLoading = ref(false)
const xlsxLoading = ref(false)

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
  { field: 'data_date', label: t('date'), width: '170px', minWidth: '170px', whiteSpace: 'nowrap' },
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
  return calculateAverageUrRate(rows.value)
})

function calculateAverageUrRate(sourceRows: DailySummaryRow[]) {
  let total = 0
  let count = 0
  for (const row of sourceRows) {
    const ur = Number((row as any).ur_rate)
    if (!isNaN(ur)) {
      total += ur
      count++
    }
  }
  if (count <= 0) return 0
  return total / count
}

const summaryItems = computed<ReportSummaryItem[]>(() => [
  { key: 'vehicles', label: t('totalVehicles'), value: formatReportInteger(summary.value.total_vehicle) },
  { key: 'distance', label: t('totalDistance'), value: formatKm(summary.value.distance_m) },
  { key: 'ur-rate', label: t('urRateAvg'), value: formatPercent(avgUrRate.value), className: 'ur-rate' },
  { key: 'running', label: `${t('running')} (dd:hh:mm)`, value: formatSummaryDuration(summary.value.run_time_s), className: 'running' },
  { key: 'idle', label: `${t('idle')} (dd:hh:mm)`, value: formatSummaryDuration(summary.value.idle_time_s), className: 'idle' },
  { key: 'parking', label: `${t('parking')} (dd:hh:mm)`, value: formatSummaryDuration(summary.value.park_time_s), className: 'parking' },
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
  filtersLoading.value = true
  try {
    const [groupsRes, vehiclesRes] = await Promise.all([getReportGroups(), getReportVehicles()])
    groupOptions.value = normalizeOptions(groupsRes)
    vehicleOptions.value = normalizeOptions(vehiclesRes)
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

function formatSummaryDuration(seconds: number) {
  const totalMinutes = Math.max(0, Math.floor(Number(seconds) / 60))
  const days = Math.floor(totalMinutes / (24 * 60))
  const hours = Math.floor((totalMinutes % (24 * 60)) / 60)
  const minutes = totalMinutes % 60

  return [days, hours, minutes]
    .map((value) => String(value).padStart(2, '0'))
    .join(':')
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

function selectedGroupNames() {
  if (!selectedGroups.value.length) return t('allGroups')

  return groupOptions.value
    .filter((group) => selectedGroups.value.some((id) => String(id) === String(group.group_id)))
    .map((group) => group.group_name)
    .join(', ')
}

function selectedVehicleNames() {
  if (!selectedVehicles.value.length) return t('allVehicles')

  return vehicleOptions.value
    .filter((vehicle) => selectedVehicles.value.includes(String(vehicle.imei)))
    .map((vehicle) => vehicle.plate_no)
    .join(', ')
}

async function saveXlsx() {
  xlsxLoading.value = true

  try {
    const res = await getDailySummary({
      date_from: toDateString(dateFrom.value),
      date_to: toDateString(dateTo.value),
      group_ids: selectedGroups.value,
      imeis: selectedVehicles.value,
      page: 1,
      per_page: totalRows.value || 100000,
      sort_by: sortField.value,
      sort_order: sortOrder.value,
      export: true,
    })

    const exportRows = res.data ?? []
    const columnCount = 10
    const sectionRow = (title: string): ReportExcelSheetRow => ({
      cells: [title, ...Array(columnCount - 1).fill('')],
      style: 'section',
    })
    const exportAverageUrRate = calculateAverageUrRate(exportRows)
    const xlsxRows: ReportExcelSheetRow[] = [
      sectionRow(t('xlsxCriteria')),
      { cells: [t('reportDateStart'), toDateString(dateFrom.value)] },
      { cells: [t('reportDateEnd'), toDateString(dateTo.value)] },
      { cells: [t('selectGroup'), selectedGroupNames()] },
      { cells: [t('selectVehicle'), selectedVehicleNames()] },
      { cells: [] },
      sectionRow(t('xlsxSummary')),
      { cells: [t('totalRows'), formatReportInteger(res.summary?.total_rows ?? exportRows.length)] },
      { cells: [t('totalVehicles'), formatReportInteger(res.summary?.total_vehicle ?? 0)] },
      { cells: [t('totalDistance'), formatKm(res.summary?.distance_m ?? 0)] },
      { cells: [t('urRateAvg'), formatPercent(exportAverageUrRate)] },
      { cells: [`${t('running')} (dd:hh:mm)`, formatSummaryDuration(res.summary?.run_time_s ?? 0)] },
      { cells: [`${t('idle')} (dd:hh:mm)`, formatSummaryDuration(res.summary?.idle_time_s ?? 0)] },
      { cells: [`${t('parking')} (dd:hh:mm)`, formatSummaryDuration(res.summary?.park_time_s ?? 0)] },
      { cells: [] },
      sectionRow(t('xlsxData')),
      {
        cells: [
          t('date'),
          'IMEI',
          t('plate'),
          t('running'),
          t('idle'),
          t('parking'),
          `${t('distance')} (km)`,
          t('formula'),
          t('urRate'),
          t('updated'),
        ],
        style: 'header',
      },
      ...exportRows.map((row) => ({
        cells: [
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
        ],
      })),
    ]
    const dataHeaderRow = 17
    const dataEndRow = dataHeaderRow + exportRows.length

    await downloadReportExcelSheet(
      `daily-summary-${toDateString(dateFrom.value)}-${toDateString(dateTo.value)}.xlsx`,
      t('dailySummaryReport'),
      xlsxRows,
      `A${dataHeaderRow}:J${dataEndRow}`,
    )
  } finally {
    xlsxLoading.value = false
  }
}

async function savePdf() {
  const target = openReportPrintWindow(t('dailySummaryReport'))
  if (!target) return

  const res = await getDailySummary({
    date_from: toDateString(dateFrom.value),
    date_to: toDateString(dateTo.value),
    group_ids: selectedGroups.value,
    imeis: selectedVehicles.value,
    page: 1,
    per_page: totalRows.value || 100000,
    sort_by: sortField.value,
    sort_order: sortOrder.value,
    export: true,
  })

  const exportRows = res.data ?? []
  const printRows = exportRows.map((row: any) => [
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
    criteriaTitle: t('xlsxCriteria'),
    criteria: [
      { label: t('reportDateStart'), value: toDateString(dateFrom.value) },
      { label: t('reportDateEnd'), value: toDateString(dateTo.value) },
      { label: t('selectGroup'), value: selectedGroupNames() },
      { label: t('selectVehicle'), value: selectedVehicleNames() },
    ],
    summaryTitle: t('xlsxSummary'),
    summary: [
      { label: t('totalRows'), value: formatReportInteger(res.summary?.total_rows ?? exportRows.length) },
      { label: t('totalVehicles'), value: formatReportInteger(res.summary?.total_vehicle ?? 0) },
      { label: t('totalDistance'), value: formatKm(res.summary?.distance_m ?? 0) },
      { label: t('urRateAvg'), value: formatPercent(calculateAverageUrRate(exportRows)) },
      { label: `${t('running')} (dd:hh:mm)`, value: formatSummaryDuration(res.summary?.run_time_s ?? 0) },
      { label: `${t('idle')} (dd:hh:mm)`, value: formatSummaryDuration(res.summary?.idle_time_s ?? 0) },
      { label: `${t('parking')} (dd:hh:mm)`, value: formatSummaryDuration(res.summary?.park_time_s ?? 0) },
    ],
    dataTitle: t('xlsxData'),
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
