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
          @click="graphVisible = true"
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
      :enableTime="definition.enableTime"
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
        <strong>{{ rows.length.toLocaleString() }}</strong>
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
        :totalRecords="rows.length"
        @page="perPage = $event.rows"
      >
        <Column
          v-for="column in visibleColumns"
          :key="column.field"
          :header="column.label"
          sortable
          :sortField="column.field"
        >
          <template #body="{ data }">
            <a
              v-if="column.type === 'location' && getRowValue(data, column) !== ''"
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
      :header="t('fuelChart')"
      maximizable
      class="fuel-dialog"
      :style="{ width: '90vw' }"
    >
      <FuelReportChart :rows="rows" />
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import BaseReportFilters from '@/components/reports/BaseReportFilters.vue'
import FuelReportChart from '@/components/reports/FuelReportChart.vue'
import {
  getLegacyReport,
  getReportGroups,
  getReportVehicles,
  type ReportGroupOption,
  type ReportVehicleOption,
} from '@/services/report'
import { locale, useI18n } from '@/i18n'
import {
  legacyReportMap,
  type LegacyColumn,
  type LegacyReportKey,
} from './legacyReportDefinitions'

const route = useRoute()
const { t } = useI18n()

const reportKey = computed(() => route.meta.reportKey as LegacyReportKey)
const definition = computed(() => legacyReportMap[reportKey.value])

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
const perPage = ref(50)
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
  visibleFields.value = definition.value.columns.map((column) => column.field)

  for (const key of Object.keys(criteria)) delete criteria[key]
  for (const criterion of definition.value.criteria ?? []) {
    criteria[criterion.key] = criterion.defaultValue
  }
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

async function search() {
  if (definition.value.requireVehicle && !filters.imei) {
    errorMessage.value = t('reportVehicleRequired')
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    const response = await getLegacyReport(definition.value.key, {
      date_from: toDateString(filters.dateFrom),
      date_to: toDateString(filters.dateTo),
      time_from: filters.timeStart,
      time_to: filters.timeEnd,
      group_id: filters.groupId,
      imei: filters.imei,
      criteria: { ...criteria },
    })
    rows.value = response.data ?? []
  } catch (error: any) {
    rows.value = []
    errorMessage.value =
      error?.response?.data?.message || error?.message || t('reportLoadFailed')
  } finally {
    loading.value = false
  }
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

function getRowValue(row: Record<string, unknown>, column: LegacyColumn) {
  const candidates = [column.field, ...(column.aliases ?? [])].map((key) => key.toLowerCase())
  const actualKey = Object.keys(row).find((key) => candidates.includes(key.toLowerCase()))
  if (actualKey) return String(row[actualKey] ?? '')

  if (column.type === 'location') {
    const lat = coordinate(row, ['lat', 'latitude'])
    const lon = coordinate(row, ['lon', 'lng', 'longitude'])
    if (lat && lon) return `${lat}, ${lon}`
  }

  return ''
}

function formatCell(row: Record<string, unknown>, column: LegacyColumn) {
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

function mapUrl(row: Record<string, unknown>, column: LegacyColumn) {
  const lat = coordinate(row, ['lat', 'latitude'])
  const lon = coordinate(row, ['lon', 'lng', 'longitude'])
  const query = lat && lon ? `${lat},${lon}` : getRowValue(row, column)
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`
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

watch(reportKey, async () => {
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

.empty-state {
  padding: 30px;
  color: #94a3b8;
  text-align: center;
}

.print-only {
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
