<template>
  <div class="report-page">
    <ReportPageHeader
      :title="localized(definition.title)"
      :subtitle="localized(definition.subtitle)"
    >
      <template #actions>
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
      </template>
      <template #trailing>
        <img
          :src="reportLogoUrl"
          class="report-print-logo"
          alt="Brand logo"
          @error="onReportLogoError"
        />
      </template>
    </ReportPageHeader>

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
      :filtersLoading="filtersLoading"
      :hasRows="rows.length > 0"
      :enableTimeStart="definition.enableTimeStart"
      :enableTimeEnd="definition.enableTimeEnd"
      :timeStartRequired="definition.timeStartRequired"
      :timeEndRequired="definition.timeEndRequired"
      :vehicleRequired="definition.vehicleRequired"
      :enableExportCsv="definition.enableExportCsv"
      :exportLabel="definition.exportFormat === 'excel' ? t('exportExcel') : t('exportCsv')"
      :enablePdf="definition.enablePdf"
      :maxRangeDays="definition.maxRangeDays"
      :monthly="definition.monthly"
      :enableDateEnd="!definition.monthly"
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
          <label>{{ typeof criterion.label === 'string' ? criterion.label : localized(criterion.label) }}</label>
          <InputNumber
            v-if="criterion.type === 'number'"
            :modelValue="numberCriterionValue(criterion.key)"
            :min="criterion.min"
            :maxFractionDigits="criterion.maxFractionDigits"
            :suffix="criterion.suffix"
            @update:modelValue="criteria[criterion.key] = $event"
          />
          <Dropdown
            v-else
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

    <ReportSummaryCards
      class="report-summary"
      :items="summaryItems"
      :columns="3"
    />

    <div class="table-card print-area">
      <ReportDataTable
        class="screen-table"
        :rows="pagedRows"
        :columns="visibleColumns"
        :reportKey="definition.key"
        :loading="loading"
        paginator
        :pageSize="perPage"
        :rowsPerPageOptions="[20, 50, 100, 200]"
        :lazy="definition.serverPagination"
        :first="definition.serverPagination ? pageOffset : 0"
        :totalRecords="definition.serverPagination ? totalRows : rows.length"
        :emptyLabel="t('reportNoData')"
        @page="onPage"
      >
        <template #cell="{ data, column }">
          <VehicleStatusBadge
            v-if="isStatusColumn(column)"
            :status="reportVehicleStatus(data, column)"
          />
          <span
            v-else-if="isTemperatureStatusColumn(column)"
            :class="['temperature-status', temperatureStatus(data, column)]"
          >{{ getRowValue(data, column) || '-' }}</span>
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
          <span
            v-else
            :class="{ 'cell-over-limit': isCellHighlighted(data, column) }"
          >{{ formatCell(data, column) }}</span>
        </template>
      </ReportDataTable>

      <table class="print-only print-table">
        <thead>
          <tr>
            <th v-for="column in visibleColumns" :key="column.field">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="index"
            :class="reportRowClass(row)"
          >
            <td
              v-for="column in visibleColumns"
              :key="column.field"
              :class="{ 'cell-over-limit': isCellHighlighted(row, column) }"
            >
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
      <div v-if="definition.graph === 'temperature'" class="temperature-chart-toolbar">
        <label>{{ t('temperatureChartSensor') }}</label>
        <Dropdown
          v-model="temperatureSensorNo"
          :options="temperatureSensorOptions"
          optionLabel="label"
          optionValue="value"
          :disabled="graphLoading"
          @change="loadTemperatureChart"
        />
      </div>
      <div v-if="graphLoading" class="graph-loading">
        <i class="pi pi-spin pi-spinner"></i>
        {{ t('loading') }}
      </div>
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
      <TemperatureReportChart
        v-else-if="definition.graph === 'temperature'"
        :rows="graphRows"
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
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import BaseReportFilters from '@/components/reports/BaseReportFilters.vue'
import VehicleStatusBadge from '@/components/VehicleStatusBadge.vue'
import ReportDataTable from '@/components/reports/ReportDataTable.vue'
import ReportPageHeader from '@/components/reports/ReportPageHeader.vue'
import ReportSummaryCards, { type ReportSummaryItem } from '@/components/reports/ReportSummaryCards.vue'
import FuelReportChart from '@/components/reports/FuelReportChart.vue'
import SpeedReportChart from '@/components/reports/SpeedReportChart.vue'
import TemperatureReportChart from '@/components/reports/TemperatureReportChart.vue'
import {
  getReportGroups,
  getReportVehicles,
  type ReportGroupOption,
  type ReportVehicleOption,
} from '@/services/report'
import { locale, useI18n } from '@/i18n'
import { formatReportDuration, isReportDurationField } from '@/utils/reportDurationFormat'
import { downloadReportCsv, downloadReportExcel } from '@/utils/reportExport'
import { formatReportNumber, reportFractionDigits } from '@/utils/reportNumberFormat'
import { openReportPrintWindow, renderReportPrintWindow } from '@/utils/reportPrint'
import { normalizeVehicleStatus, vehicleStatusLabel } from '@/utils/vehicleStatus'
import type { ReportChartLoader, ReportColumn, ReportDefinition, ReportLoader } from './reportTypes'
import type { TemperatureSensorConfig } from './reportTypes'

const props = defineProps<{
  definition: ReportDefinition
  loadReport: ReportLoader
  loadChart?: ReportChartLoader
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

const criteria = reactive<Record<string, string | number | null>>({})
const groupOptions = ref<ReportGroupOption[]>([])
const vehicleOptions = ref<ReportVehicleOption[]>([])
const rows = ref<Record<string, unknown>[]>([])
const graphRows = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const filtersLoading = ref(false)
const errorMessage = ref('')
const graphVisible = ref(false)
const graphLoading = ref(false)
const temperatureSensorNo = ref(1)
const temperatureSensorOptions = [
  { label: 'Sensor A', value: 1 },
  { label: 'Sensor B', value: 2 },
]
const averageGraph = ref(false)
const graphCriteria = reactive({
  plateNo: '',
  rangeStart: '',
  rangeEnd: '',
})
const perPage = ref(50)
const pageOffset = ref(0)
const totalRows = ref(0)
const temperatureConfig = ref<{ sensor_a?: TemperatureSensorConfig; sensor_b?: TemperatureSensorConfig }>({})
const visibleFields = ref<string[]>([])

const columnOptions = computed(() =>
  definition.value.columns.map(({ field, label }) => ({ field, label }))
)
const visibleColumns = computed(() =>
  definition.value.columns.filter((column) => visibleFields.value.includes(column.field))
)
const pagedRows = computed(() => rows.value)
const periodLabel = computed(() => definition.value.monthly && filters.dateFrom
  ? filters.dateFrom.toLocaleDateString(locale.value === 'th' ? 'th-TH' : 'en-US', { month: 'long', year: 'numeric' })
  : `${toDateString(filters.dateFrom)} – ${toDateString(filters.dateTo)}`
)
const summaryItems = computed<ReportSummaryItem[]>(() => {
  const items: ReportSummaryItem[] = [
    {
      key: 'rows',
      label: t('totalRows'),
      value: (definition.value.serverPagination ? totalRows.value : rows.value.length).toLocaleString(),
    },
    { key: 'period', label: t('reportPeriod'), value: periodLabel.value },
  ]

  if (definition.value.maxRangeDays > 0) {
    items.push({
      key: 'range',
      label: t('reportRangeLimit'),
      value: `${definition.value.maxRangeDays} ${t('days')}`,
    })
  }

  if (definition.value.key === 'temperature') {
    items.push(
      { key: 'sensor-a', label: 'Sensor A', value: sensorConfigText(temperatureConfig.value.sensor_a) },
      { key: 'sensor-b', label: 'Sensor B', value: sensorConfigText(temperatureConfig.value.sensor_b) },
    )
  }

  return items
})
const graphHeader = computed(() => {
  if (definition.value.graph === 'speed') return t('speedChart')
  if (definition.value.graph === 'temperature') return t('temperatureChart')
  return t(averageGraph.value ? 'averageFuelChart' : 'fuelChart')
})

function onReportLogoError(event: Event) {
  const image = event.currentTarget as HTMLImageElement
  image.onerror = null
  image.src = '/logos/default.png'
}

function localized(value: { th: string; en: string }) {
  return value[locale.value]
}

function sensorConfigText(config?: TemperatureSensorConfig) {
  const display = (value: number | null | undefined) => value == null ? '-' : Number(value).toFixed(1)
  return `min: ${display(config?.min)} | max: ${display(config?.max)} | average: ${display(config?.average)}`
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
  temperatureSensorNo.value = 1
  averageGraph.value = false
  graphCriteria.plateNo = ''
  graphCriteria.rangeStart = ''
  graphCriteria.rangeEnd = ''
  pageOffset.value = 0
  totalRows.value = 0
  temperatureConfig.value = {}
  visibleFields.value = definition.value.columns.map((column) => column.field)

  for (const key of Object.keys(criteria)) delete criteria[key]
  for (const criterion of definition.value.criteria ?? []) {
    criteria[criterion.key] = criterion.defaultValue
  }
}

async function openGraph(average: boolean) {
  averageGraph.value = average
  graphRows.value = rows.value
  graphVisible.value = true
  if (props.loadChart) await loadTemperatureChart()
}

async function loadTemperatureChart() {
  if (!props.loadChart) return
  graphLoading.value = true
  try {
    const response = await props.loadChart({
      date_from: toDateString(filters.dateFrom),
      date_to: toDateString(filters.dateTo),
      time_from: filters.timeStart,
      time_to: filters.timeEnd,
      imei: filters.imei,
      sensor_no: temperatureSensorNo.value,
    })
    graphRows.value = response.data ?? []
  } catch (error: any) {
    graphRows.value = []
    errorMessage.value = error?.response?.data?.message || error?.message || t('reportLoadFailed')
  } finally {
    graphLoading.value = false
  }
}

async function loadOptions() {
  filtersLoading.value = true
  try {
    const [groups, vehicles] = await Promise.all([
      getReportGroups(),
      getReportVehicles(),
    ])
    groupOptions.value = groups
    vehicleOptions.value = vehicles
  } finally {
    filtersLoading.value = false
  }
}

async function loadVehicles() {
  filters.imei = null
  filtersLoading.value = true
  try {
    vehicleOptions.value = await getReportVehicles({
      group_ids: filters.groupId ? [filters.groupId] : [],
    })
  } finally {
    filtersLoading.value = false
  }
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
    const dateTo = definition.value.monthly && filters.dateFrom
      ? toDateString(new Date(filters.dateFrom.getFullYear(), filters.dateFrom.getMonth() + 1, 0))
      : toDateString(filters.dateTo)
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
    temperatureConfig.value = response.config ?? {}
    totalRows.value = response.meta?.total_rows ?? rows.value.length
    graphCriteria.plateNo = vehicleOptions.value.find(
      (vehicle) => vehicle.imei === filters.imei
    )?.plate_no ?? filters.imei ?? ''
    graphCriteria.rangeStart = `${dateFrom} ${filters.timeStart}`
    graphCriteria.rangeEnd = `${dateTo} ${filters.timeEnd}`
  } catch (error: any) {
    rows.value = []
    temperatureConfig.value = {}
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
    return vehicleStatusLabel(reportVehicleStatus(row, column), locale.value)
  }

  const value = getRowValue(row, column)
  if (value === '') return '-'
  const durationFields = [column.field, ...(column.aliases ?? [])].filter(isReportDurationField)
  const durationField = durationFields.find((field) => /_(?:s|mm|hhmm)$/.test(field.toLowerCase()))
    ?? durationFields[0]
  if (durationField) return formatReportDuration(value, durationField)
  const fractionDigits = reportFractionDigits(column.field, definition.value.key)
  if (column.type === 'number' || fractionDigits !== undefined) {
    return formatReportNumber(value, column.field, definition.value.key)
  }
  return value
}

function numberCriterionValue(key: string) {
  const value = criteria[key]
  return typeof value === 'number' ? value : null
}

function isCellOverLimit(row: Record<string, unknown>, column: ReportColumn) {
  const criterionKey = definition.value.dailyDistanceLimitCriterionKey
  if (!criterionKey || !/^d(?:[1-9]|[12]\d|3[01])$/i.test(column.field)) return false

  const rawLimit = criteria[criterionKey]
  if (typeof rawLimit !== 'number') return false

  const limit = rawLimit
  const distance = Number(getRowValue(row, column))
  return Number.isFinite(limit) && limit >= 0 && Number.isFinite(distance) && distance > limit
}

function isTemperatureOutOfRange(row: Record<string, unknown>, column: ReportColumn) {
  if (definition.value.key !== 'temperature') return false
  const sensor = column.field === 'temp_a'
    ? temperatureConfig.value.sensor_a
    : column.field === 'temp_b'
      ? temperatureConfig.value.sensor_b
      : undefined
  if (!sensor || sensor.min == null || sensor.max == null) return false

  const value = Number(getRowValue(row, column))
  return Number.isFinite(value) && (value < sensor.min || value > sensor.max)
}

function isCellHighlighted(row: Record<string, unknown>, column: ReportColumn) {
  return isCellOverLimit(row, column) || isTemperatureOutOfRange(row, column)
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
  return (definition.value.key === 'status-detail' && column.field === 'status')
    || (['fuel', 'temperature'].includes(definition.value.key)
      && column.field === 'vehicle_status')
}

function isTemperatureStatusColumn(column: ReportColumn) {
  return definition.value.key === 'temperature'
    && (column.field === 'temp_a_status' || column.field === 'temp_b_status')
}

function temperatureStatus(row: Record<string, unknown>, column: ReportColumn) {
  const status = getRowValue(row, column).trim().toLowerCase()
  return ['green', 'yellow', 'red'].includes(status) ? status : 'unknown'
}

function reportVehicleStatus(row: Record<string, unknown>, column: ReportColumn) {
  const rawStatus = getRowValue(row, column)
  if (definition.value.key !== 'fuel' || column.field !== 'vehicle_status') {
    return normalizeVehicleStatus(rawStatus) ?? rawStatus
  }

  const normalized = normalizeVehicleStatus(rawStatus)
  if (normalized === 'no_gps' || normalized === 'offline') return normalized

  const speed = Number(coordinate(row, ['speed']) || 0)
  if (speed > 0) return 'run'
  if (normalized) return normalized

  const state = coordinate(row, ['state', 'status', 'vehicle_status']).toLowerCase()
  if (['1', 'on', 'true', 'start', 'idle'].some((value) => state.includes(value))) {
    return 'idle'
  }
  return 'park'
}

async function exportCsv() {
  const header = visibleColumns.value.map((column) => column.label)
  const body = rows.value.map((row) =>
    visibleColumns.value
      .map((column) => formatCell(row, column))
  )

  if (definition.value.exportFormat === 'excel') {
    await downloadReportExcel(
      `${definition.value.key}-${toDateString(filters.dateFrom)}-${toDateString(filters.dateTo)}.xlsx`,
      localized(definition.value.title),
      header,
      rows.value.map((row, index) => ({
        cells: body[index],
        fill: statusRowExcelFill(row),
      })),
    )
    return
  }

  downloadReportCsv(
    `${definition.value.key}-${toDateString(filters.dateFrom)}-${toDateString(filters.dateTo)}.csv`,
    header,
    body,
  )
}

function reportRowStatus(row: Record<string, unknown>) {
  if (definition.value.key !== 'status-detail') return null
  const statusColumn = definition.value.columns.find((column) => column.field === 'status')
  if (!statusColumn) return null
  return normalizeVehicleStatus(getRowValue(row, statusColumn))
}

function reportRowClass(row: Record<string, unknown>) {
  const status = reportRowStatus(row)
  return status === 'park' || status === 'idle' || status === 'run'
    ? `status-row-${status}`
    : ''
}

function statusRowExcelFill(row: Record<string, unknown>) {
  const status = reportRowStatus(row)
  if (status === 'park') return 'FFFFC7CE'
  if (status === 'idle') return 'FFFFEB9C'
  if (status === 'run') return 'FFC6EFCE'
  return undefined
}

function savePdf() {
  const title = localized(definition.value.title)
  const target = openReportPrintWindow(title)
  if (!target) return

  renderReportPrintWindow(target, {
    title,
    period: `${t('reportPeriod')}: ${periodLabel.value}`,
    wide: definition.value.key === 'monthly-distance',
    headers: visibleColumns.value.map((column) => column.label),
    rows: rows.value.map((row) =>
      visibleColumns.value.map((column) => ({
        value: formatCell(row, column),
      highlighted: isCellHighlighted(row, column),
      }))
    ),
  })
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

.temperature-chart-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  color: #cbd5e1;
  font-size: 13px;
  font-weight: 600;
}

.temperature-chart-toolbar .p-dropdown {
  min-width: 150px;
}

.graph-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 80px;
  color: #94a3b8;
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

.cell-over-limit {
  display: inline-block;
  min-width: 100%;
  padding: 3px 7px;
  color: #fecaca;
  font-weight: 700;
  background: rgba(220, 38, 38, 0.24);
  border: 1px solid rgba(248, 113, 113, 0.5);
  border-radius: 6px;
}

.temperature-status {
  display: inline-flex;
  min-width: 64px;
  justify-content: center;
  padding: 3px 8px;
  color: #f8fafc;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  border-radius: 999px;
}

.temperature-status.green { background: #16a34a; }
.temperature-status.yellow { color: #422006; background: #facc15; }
.temperature-status.red { background: #dc2626; }
.temperature-status.unknown { background: #64748b; }

.print-table tr.status-row-park > td {
  background: rgba(239, 68, 68, 0.28) !important;
}

.print-table tr.status-row-idle > td {
  color: #422006;
  background: rgba(250, 204, 21, 0.72) !important;
}

.print-table tr.status-row-run > td {
  background: rgba(34, 197, 94, 0.28) !important;
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
