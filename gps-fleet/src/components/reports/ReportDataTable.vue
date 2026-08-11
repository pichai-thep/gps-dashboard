<template>
  <DataTable
    v-bind="$attrs"
    :value="rows"
    :loading="loading"
    stripedRows
    responsiveLayout="scroll"
    :paginator="paginator"
    :rows="pageSize"
    :rowsPerPageOptions="rowsPerPageOptions"
    :lazy="lazy"
    :first="first"
    :totalRecords="totalRecords"
    :sortField="sortField"
    :sortOrder="sortOrder"
    @page="$emit('page', $event)"
    @sort="$emit('sort', $event)"
  >
    <Column
      v-for="column in columns"
      :key="column.field"
      :field="column.field"
      :header="column.label"
      :sortable="column.sortable ?? true"
      :sortField="column.sortField ?? column.field"
      :style="columnStyle(column)"
    >
      <template #body="{ data }">
        <slot
          name="cell"
          :data="data"
          :column="column"
          :value="data[column.field]"
          :formattedValue="formatValue(data[column.field], column.field)"
        >
          {{ formatValue(data[column.field], column.field) }}
        </slot>
      </template>
    </Column>

    <template #empty>
      <slot name="empty">{{ emptyLabel }}</slot>
    </template>
  </DataTable>
</template>

<script setup lang="ts">
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import { formatReportDuration, isReportDurationField } from '@/utils/reportDurationFormat'
import { formatReportNumber, reportFractionDigits } from '@/utils/reportNumberFormat'

defineOptions({ inheritAttrs: false })

export interface ReportTableColumn {
  field: string
  label: string
  sortable?: boolean
  sortField?: string
  width?: string
  minWidth?: string
  whiteSpace?: string
}

const props = withDefaults(defineProps<{
  rows: Record<string, any>[]
  columns: ReportTableColumn[]
  loading?: boolean
  paginator?: boolean
  pageSize?: number
  rowsPerPageOptions?: number[]
  lazy?: boolean
  first?: number
  totalRecords?: number
  sortField?: string
  sortOrder?: number
  emptyLabel?: string
  reportKey?: string
}>(), {
  loading: false,
  paginator: true,
  pageSize: 50,
  rowsPerPageOptions: () => [20, 50, 100, 200],
  lazy: false,
  first: 0,
  totalRecords: 0,
  sortOrder: 1,
  emptyLabel: '',
  reportKey: '',
})

defineEmits<{
  page: [event: any]
  sort: [event: any]
}>()

function columnStyle(column: ReportTableColumn) {
  const isPlateNumber = column.field.toLowerCase() === 'plate_no'
  const width = column.width ?? (isPlateNumber ? '140px' : undefined)
  const minWidth = column.minWidth ?? (isPlateNumber ? '140px' : undefined)
  const whiteSpace = column.whiteSpace ?? (isPlateNumber ? 'nowrap' : undefined)

  return { width, minWidth, whiteSpace }
}

function formatValue(value: unknown, field: string) {
  if (value === null || value === undefined || value === '') return '-'
  if (isReportDurationField(field)) return formatReportDuration(value, field)
  if (reportFractionDigits(field, props.reportKey) === undefined) return value
  return formatReportNumber(value, field, props.reportKey)
}
</script>

<style scoped>
@import '@/views/reports/report-dark.css';
</style>
