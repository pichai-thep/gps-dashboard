<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>{{ localized(definition.title) }}</h1>
        <p>{{ localized(definition.subtitle) }}</p>
      </div>

      <div class="header-actions">
        <Button
          v-if="definition.graph"
          :label="t('viewGraph')"
          icon="pi pi-chart-line"
          severity="secondary"
          :disabled="rows.length === 0"
          @click="openGraph(false)"
        />
        <Button
          v-if="definition.graph === true || definition.graph === 'fuel'"
          :label="t('viewAverageGraph')"
          icon="pi pi-chart-line"
          severity="secondary"
          outlined
          :disabled="rows.length === 0"
          @click="openGraph(true)"
        />
        <MultiSelect
          v-model="visibleFields"
          :options="columnOptions"
          optionLabel="label"
          optionValue="field"
          :placeholder="t('visibleColumns')"
          display="chip"
          class="column-picker"
        />
      </div>

      <img
        :src="reportLogoUrl"
        class="report-print-logo"
        alt="Brand logo"
        @error="onReportLogoError"
      />
    </div>

    <BaseReportFilters
      v-model:dateFrom="filters.dateFrom"
      v-model:dateTo="filters.dateTo"
      v-model:timeStart="filters.timeStart"
      v-model:timeEnd="filters.timeEnd"
      v-model:groupId="filters.groupId"
      v-model:imei="filters.imei"
      :groupOptions="groupOptions"
      :vehicleOptions="vehicleOptions"
      :loading="loading"
      :hasRows="rows.length > 0"
      :enableTimeStart="definition.enableTimeStart"
      :enableTimeEnd="definition.enableTimeEnd"
      :timeStartRequired="definition.timeStartRequired"
      :timeEndRequired="definition.timeEndRequired"
      :vehicleRequired="definition.vehicleRequired"
      :enableExportCsv="definition.enableExportCsv"
      :enablePdf="definition.enablePdf"
      :maxRangeDays="definition.maxRangeDays"
      @group-change="loadVehicles"
      @search="search"
      @reset="reset"
      @export-csv="exportCsv"
      @save-pdf="savePdf"
    >
      <template v-if="definition.criteria?.length" #criteria>
        <div
          v-for="criterion in definition.criteria"
          :key="criterion.key"
          class="custom-filter-field"
        >
          <label>{{ criterion.label }}</label>
          <Dropdown
            v-model="criteria[criterion.key]"
            :options="criterion.options"
            optionLabel="label"
            optionValue="value"
          />
        </div>
      </template>
    </BaseReportFilters>

    <Message v-if="errorMessage" severity="error" :closable="false">
      {{ errorMessage }}
    </Message>

    <div class="summary-grid report-summary">
      <div class="summary-card">
        <span>{{ t('totalRows') }}</span>
        <strong>{{ (definition.serverPagination ? totalRows : rows.length).toLocaleString() }}</strong>
      </div>
      <div class="summary-card">
        <span>{{ t('reportPeriod') }}</span>
        <strong>{{ periodLabel }}</strong>
      </div>
      <div class="summary-card">
        <span>{{ t('reportRangeLimit') }}</span>
        <strong>{{ definition.maxRangeDays }} {{ t('days') }}</strong>
      </div>
    </div>

    <div class="table-card print-area">
      <DataTable
        class="screen-table"
        :value="pagedRows"
        :loading="loading"
        stripedRows
        responsiveLayout="scroll"
        paginator
        :rows="perPage"
        :rowsPerPageOptions="[20, 50, 100, 200]"
        :lazy="definition.serverPagination"
        :first="definition.serverPagination ? pageOffset : 0"
        :totalRecords="definition.serverPagination ? totalRows : rows.length"
        @page="onPage"
      >
        <Column
          v-for="column in visibleColumns"
          :key="column.field"
          :header="column.label"
          sortable
          :sortField="column.field"
        >
          <template #body="{ data }">
            <span
              v-if="isStatusColumn(column)"
              :class="['status-badge', statusClass(getRowValue(data, column))]"
            >
              {{ statusLabel(getRowValue(data, column)) }}
            </span>
            <a
              v-else-if="shouldLinkMap(data, column)"
              :href="mapUrl(data, column)"
              target="_blank"
              rel="noopener noreferrer"
              class="map-link"
            >
              {{ getRowValue(data, column) }}
              <i class="pi pi-map-marker"></i>
            </a>
            <span v-else>{{ formatCell(data, column) }}</span>
          </template>
        </Column>

        <template #empty>
          <div class="empty-state">{{ t('reportNoData') }}</div>
        </template>
      </DataTable>

      <table class="print-only print-table">
        <thead>
          <tr>
            <th v-for="column in visibleColumns" :key="column.field">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="index">
            <td v-for="column in visibleColumns" :key="column.field">
              {{ formatCell(row, column) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Dialog
      v-model:visible="graphVisible"
      modal
      :header="graphHeader"
      maximizable
      class="fuel-dialog"
      :style="{ width: '90vw' }"
    >
      <FuelReportChart
        v-if="definition.graph === true || definition.graph === 'fuel'"
        :rows="rows"
        :plateNo="graphCriteria.plateNo"
        :rangeStart="graphCriteria.rangeStart"
        :rangeEnd="graphCriteria.rangeEnd"
        :average="averageGraph"
      />
      <SpeedReportChart
        v-else-if="definition.graph === 'speed'"
        :rows="rows"
        :plateNo="graphCriteria.plateNo"
        :rangeStart="graphCriteria.rangeStart"
        :rangeEnd="graphCriteria.rangeEnd"
      />
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import BaseReportFilters from '@/components/reports/BaseReportFilters.vue'
import FuelReportChart from '@/components/reports/FuelReportChart.vue'
import SpeedReportChart from '@/components/reports/SpeedReportChart.vue'
import {
  getReportGroups,
  getReportVehicles,
  type ReportGroupOption,
  type ReportVehicleOption,
} from '@/services/report'
import { locale, useI18n } from '@/i18n'
import type { ReportColumn, ReportDefinition, ReportLoader } from './reportTypes'

const props = defineProps<{
  definition: ReportDefinition
  loadReport: ReportLoader
}>()
const { t } = useI18n()

const definition = computed(() => props.definition)
const reportLogoUrl = ref(`/logos/${encodeURIComponent(window.location.hostname)}.png`)

const filters = reactive({
  dateFrom: yesterday(),
  dateTo: new Date(),
  timeStart: '00:00',
  timeEnd: '23:59',
  groupId: null as number | null,
  imei: null as string | null,
})

const criteria = reactive<Record<string, string | number>>({})
const groupOptions = ref<ReportGroupOption[]>([])
const vehicleOptions = ref<ReportVehicleOption[]>([])
const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const errorMessage = ref('')
const graphVisible = ref(false)
const averageGraph = ref(false)
const graphCriteria = reactive({
  plateNo: '',
  rangeStart: '',
  rangeEnd: '',
})
const perPage = ref(50)
const pageOffset = ref(0)
const totalRows = ref(0)
const visibleFields = ref<string[]>([])

const columnOptions = computed(() =>
  definition.value.columns.map(({ field, label }) => ({ field, label }))
)
const visibleColumns = computed(() =>
  definition.value.columns.filter((column) => visibleFields.value.includes(column.field))
)
const pagedRows = computed(() => rows.value)
const periodLabel = computed(() =>
  `${toDateString(filters.dateFrom)} – ${toDateString(filters.dateTo)}`
)
const graphHeader = computed(() => definition.value.graph === 'speed'
  ? t('speedChart')
  : t(averageGraph.value ? 'averageFuelChart' : 'fuelChart'))

function onReportLogoError(event: Event) {
  const image = event.currentTarget as HTMLImageElement
  image.onerror = null
  image.src = '/logos/default.png'
}

function localized(value: { th: string; en: string }) {
  return value[locale.value]
}

function yesterday() {
  const value = new Date()
  value.setDate(value.getDate() - 1)
  value.setHours(0, 0, 0, 0)
  return value
}

function toDateString(value: Date | null) {
  if (!value) return ''
  const year = value.getFullYear()
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function initializeDefinition() {
  rows.value = []
  errorMessage.value = ''
  graphVisible.value = false
  averageGraph.value = false
  graphCriteria.plateNo = ''
  graphCriteria.rangeStart = ''
  graphCriteria.rangeEnd = ''
  pageOffset.value = 0
  totalRows.value = 0
  visibleFields.value = definition.value.columns.map((column) => column.field)

  for (const key of Object.keys(criteria)) delete criteria[key]
  for (const criterion of definition.value.criteria ?? []) {
    criteria[criterion.key] = criterion.defaultValue
  }
}

function openGraph(average: boolean) {
  averageGraph.value = average
  graphVisible.value = true
}

async function loadOptions() {
  const [groups, vehicles] = await Promise.all([
    getReportGroups(),
    getReportVehicles(),
  ])
  groupOptions.value = groups
  vehicleOptions.value = vehicles
}

async function loadVehicles() {
  filters.imei = null
  vehicleOptions.value = await getReportVehicles({
    group_ids: filters.groupId ? [filters.groupId] : [],
  })
}

async function search(resetPage = true) {
  if (definition.value.vehicleRequired && !filters.imei) {
    errorMessage.value = t('reportVehicleRequired')
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    if (resetPage) pageOffset.value = 0
    const dateFrom = toDateString(filters.dateFrom)
    const dateTo = toDateString(filters.dateTo)
    const response = await props.loadReport({
      date_from: dateFrom,
      date_to: dateTo,
      time_from: filters.timeStart,
      time_to: filters.timeEnd,
      group_id: filters.groupId,
      imei: filters.imei,
      criteria: { ...criteria },
      offset: definition.value.serverPagination ? pageOffset.value : undefined,
      size: definition.value.serverPagination ? perPage.value : undefined,
    })
    rows.value = response.data ?? []
    totalRows.value = response.meta?.total_rows ?? rows.value.length
    graphCriteria.plateNo = vehicleOptions.value.find(
      (vehicle) => vehicle.imei === filters.imei
    )?.plate_no ?? filters.imei ?? ''
    graphCriteria.rangeStart = `${dateFrom} ${filters.timeStart}`
    graphCriteria.rangeEnd = `${dateTo} ${filters.timeEnd}`
  } catch (error: any) {
    rows.value = []
    errorMessage.value =
      error?.response?.data?.message || error?.message || t('reportLoadFailed')
  } finally {
    loading.value = false
  }
}

function onPage(event: { first: number; rows: number }) {
  perPage.value = event.rows
  if (!definition.value.serverPagination) return
  pageOffset.value = event.first
  void search(false)
}

async function reset() {
  filters.dateFrom = yesterday()
  filters.dateTo = new Date()
  filters.timeStart = '00:00'
  filters.timeEnd = '23:59'
  filters.groupId = null
  filters.imei = null
  initializeDefinition()
  await loadOptions()
}

function getRowValue(row: Record<string, unknown>, column: ReportColumn) {
  if (definition.value.key === 'status-detail' && column.field === 'location') {
    const station = coordinate(row, ['end_station'])
    if (station.trim()) return station

    const address = coordinate(row, ['end_address'])
    if (address.trim()) return address

    const lat = coordinate(row, ['end_lat'])
    const lon = coordinate(row, ['end_lng'])
    return lat && lon ? `${lat}, ${lon}` : ''
  }

  const candidates = [column.field, ...(column.aliases ?? [])]
  for (const candidate of candidates) {
    const actualKey = Object.keys(row).find(
      (key) => key.toLowerCase() === candidate.toLowerCase(),
    )
    const value = actualKey ? String(row[actualKey] ?? '') : ''
    if (value.trim()) return value
  }

  if (column.type === 'location') {
    const lat = coordinate(row, ['lat', 'latitude'])
    const lon = coordinate(row, ['lon', 'lng', 'longitude'])
    if (lat && lon) return `${lat}, ${lon}`
  }

  return ''
}

function formatCell(row: Record<string, unknown>, column: ReportColumn) {
  if (isStatusColumn(column)) {
    return statusLabel(getRowValue(row, column))
  }

  if (definition.value.key === 'fuel' && column.field === 'vehicle_status') {
    const speed = Number(coordinate(row, ['speed']) || 0)
    const state = coordinate(row, ['state', 'status', 'vehicle_status']).toLowerCase()

    if (speed > 0) return t('running')
    if (['1', 'on', 'true', 'start', 'idle'].some((value) => state.includes(value))) {
      return t('idle')
    }
    return t('parking')
  }

  const value = getRowValue(row, column)
  if (value === '') return '-'
  if (column.type === 'number') {
    const number = Number(value)
    return Number.isFinite(number) ? number.toLocaleString() : value
  }
  return value
}

function coordinate(row: Record<string, unknown>, names: string[]) {
  const key = Object.keys(row).find((item) =>
    names.includes(item.toLowerCase())
  )
  return key ? String(row[key] ?? '') : ''
}

function mapUrl(row: Record<string, unknown>, column: ReportColumn) {
  const lat = coordinate(row, ['lat', 'latitude', 'end_lat', 'start_lat'])
  const lon = coordinate(row, ['lon', 'lng', 'longitude', 'end_lng', 'start_lng'])
  const query = lat && lon ? `${lat},${lon}` : getRowValue(row, column)
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`
}

function shouldLinkMap(row: Record<string, unknown>, column: ReportColumn) {
  if (column.type !== 'location' || getRowValue(row, column) === '') return false

  if (definition.value.key === 'status-detail' && column.field === 'location') {
    return coordinate(row, ['end_station']).trim() === ''
  }

  return true
}

function isStatusColumn(column: ReportColumn) {
  return definition.value.key === 'status-detail' && column.field === 'status'
}

function normalizedStatus(value: string) {
  return value.trim().toLowerCase()
}

function statusClass(value: string) {
  const status = normalizedStatus(value)
  if (status === 'run') return 'status-run'
  if (status === 'park') return 'status-park'
  if (status === 'start' || status === 'idle') return 'status-start'
  return 'status-unknown'
}

function statusLabel(value: string) {
  const status = normalizedStatus(value)
  if (status === 'run') return 'RUN'
  if (status === 'park') return 'PARK'
  if (status === 'start' || status === 'idle') return 'IDLE'
  return value || '-'
}

function csvCell(value: string) {
  return `"${value.replace(/"/g, '""')}"`
}

function exportCsv() {
  const header = visibleColumns.value.map((column) => csvCell(column.label)).join(',')
  const body = rows.value.map((row) =>
    visibleColumns.value
      .map((column) => csvCell(formatCell(row, column)))
      .join(',')
  )
  const blob = new Blob(['\ufeff' + [header, ...body].join('\n')], {
    type: 'text/csv;charset=utf-8',
  })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${definition.value.key}-${toDateString(filters.dateFrom)}-${toDateString(filters.dateTo)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function savePdf() {
  window.print()
}

watch(() => props.definition.key, async () => {
  initializeDefinition()
  await loadOptions()
}, { immediate: true })
</script>

<style scoped>
@import './report-dark.css';

.column-picker {
  width: min(440px, 45vw);
}

.custom-filter-field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 6px;
}

.custom-filter-field label {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 600;
}

.report-summary {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.report-summary strong {
  font-size: 18px;
}

.map-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #60a5fa;
  text-decoration: none;
}

.status-badge {
  display: inline-flex;
  min-width: 64px;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.status-run {
  color: #86efac;
  background: rgb(34 197 94 / 16%);
  border-color: rgb(34 197 94 / 35%);
}

.status-park {
  color: #fca5a5;
  background: rgb(239 68 68 / 16%);
  border-color: rgb(239 68 68 / 35%);
}

.status-start {
  color: #fde047;
  background: rgb(234 179 8 / 16%);
  border-color: rgb(234 179 8 / 35%);
}

.status-unknown {
  color: #cbd5e1;
  background: rgb(148 163 184 / 12%);
  border-color: rgb(148 163 184 / 25%);
}

.empty-state {
  padding: 30px;
  color: #94a3b8;
  text-align: center;
}

.print-only {
  display: none;
}

.report-print-logo {
  display: none;
}

@media print {
  :global(.sidebar),
  :global(.topbar),
  .page-header .header-actions,
  .base-report-filters,
  .summary-grid,
  .p-paginator {
    display: none !important;
  }

  .report-page {
    padding: 0;
    color: #000;
  }

  .page-header {
    align-items: flex-start;
  }

  .page-header p {
    color: #475569;
  }

  .report-print-logo {
    display: block;
    width: auto;
    max-width: 180px;
    height: 56px;
    object-fit: contain;
  }

  .table-card {
    padding: 0;
    border: 0;
  }

  .screen-table {
    display: none !important;
  }

  .print-only {
    display: table;
  }

  .print-table {
    width: 100%;
    border-collapse: collapse;
    color: #000;
    font-size: 9pt;
  }

  .print-table th,
  .print-table td {
    padding: 5px;
    border: 1px solid #999;
    text-align: left;
  }
}
</style>
