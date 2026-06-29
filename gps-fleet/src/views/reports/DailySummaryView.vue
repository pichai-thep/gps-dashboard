<template>
  <div class="report-page">
    <div class="page-header">
      <div>
        <h1>{{ t('dailySummaryReport') }}</h1>
        <p>{{ t('dailySummarySubtitle') }}</p>
      </div>

      <Button
          :label="t('exportCsv')"
          icon="pi pi-download"
          severity="secondary"
          :disabled="totalRows === 0"
          @click="exportCsv"
      />


    </div>

    <div class="filter-card">
      <Calendar
          v-model="dateFrom"
          dateFormat="yy-mm-dd"
          showIcon
      />

      <Calendar
          v-model="dateTo"
          dateFormat="yy-mm-dd"
          showIcon
      />

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
          aria-multiline="true"
      />

      <Button
          :label="t('search')"
          icon="pi pi-search"
          :loading="loading"
          @click="search"
      />

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
        <span>{{ t('totalVehicles') }}</span>
        <strong>{{ summary.total_vehicle }}</strong>
      </div>

      <div class="summary-card">
        <span>{{ t('totalDistance') }}</span>
        <strong>{{ formatKm(summary.distance_m) }}</strong>
      </div>

      <div class="summary-card ur-rate">
        <span>{{ t('urRateAvg') }}</span>

        <strong>
          {{ formatPercent(avgUrRate) }}
        </strong>
      </div>

      <div class="summary-card running">
        <span>{{ t('running') }}</span>
        <strong>{{ formatDuration(summary.run_time_s) }}</strong>
      </div>

      <div class="summary-card idle">
        <span>{{ t('idle') }}</span>
        <strong>{{ formatDuration(summary.idle_time_s) }}</strong>
      </div>

      <div class="summary-card parking">
        <span>{{ t('parking') }}</span>
        <strong>{{ formatDuration(summary.park_time_s) }}</strong>
      </div>
    </div>

    <div class="table-card">

      <DataTable
          :value="rows"
          :loading="loading"
          stripedRows
          responsiveLayout="scroll"
          class="summary-table"
          :sortField="sortField"
          :sortOrder="sortOrder === 'asc' ? 1 : -1"
          @sort="onSort"
      >
        <Column field="data_date" :header="t('date')" sortable style="width: 150px" />

<!--        <Column field="imei" header="IMEI" style="width: 180px" />-->
        <Column field="plate_no" :header="t('plate')" sortable style="width: 180px" />

        <Column field="run_time_s" :header="t('running')" sortable  style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.run_time_s)"
                severity="success"
            />
          </template>
        </Column>

        <Column field="idle_time_s" :header="t('idle')" sortable  style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.idle_time_s)"
                severity="warning"
            />
          </template>
        </Column>

        <Column field="park_time_s" :header="t('parking')" sortable  style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatDuration(data.park_time_s)"
                severity="info"
            />
          </template>
        </Column>

        <Column field="distance_m" :header="t('distance')" sortable  style="width: 150px">
          <template #body="{ data }">
            <b>{{ formatKm(data.distance_m) }}</b>
          </template>
        </Column>

        <Column field="ur_formula" :header="t('formula')" style="width: 170px">
          <template #body="{ data }">
            <Tag
                :value="data.ur_formula"
                severity="secondary"
            />
          </template>
        </Column>

        <Column field="ur_rate" :header="t('urRate')" sortable  style="width: 120px">
          <template #body="{ data }">
            <Tag
                :value="formatPercent(data.ur_rate)"
                :severity="urRateSeverity(data.ur_rate)"
            />
          </template>
        </Column>

        <Column field="updated_at" :header="t('updated')" sortable  style="width: 200px" />
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
import {computed, onMounted, ref} from 'vue'
import Button from 'primevue/button'
import Calendar from 'primevue/calendar'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import MultiSelect from 'primevue/multiselect'
import {
  getDailySummary,
  getReportGroups,
  getReportVehicles,
  type DailySummaryRow,
} from '@/services/report'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const selectedGroups = ref<number[]>([])
const selectedVehicles = ref<string[]>([])

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

  await loadOptions()
  await loadData()
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
  seconds = Number(seconds || 0)

  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)

  if (h <= 0) return `${m}m`
  return `${h}h ${m}m`
}

function formatKm(meter: number) {
  const km = Number(meter || 0) / 1000
  return `${km.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} km`
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
    (Number(r.distance_m || 0) / 1000).toFixed(2),
    r.ur_formula,
    formatPercent(r.ur_rate),
    r.updated_at,
  ])

  downloadCsv('daily-summary.csv', header, body)
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

.summary-card.running {
  border-color: rgba(34, 197, 94, 0.35);
}

.summary-card.idle {
  border-color: rgba(245, 158, 11, 0.35);
}

.summary-card.parking {
  border-color: rgba(59, 130, 246, 0.35);
}

.summary-card.ur-rate {
  border-color: rgba(168, 85, 247, 0.35);
}

.summary-card.ur-rate strong {
  color: #c084fc;
}

</style>
