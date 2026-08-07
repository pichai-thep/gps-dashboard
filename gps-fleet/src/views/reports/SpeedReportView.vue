<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getSpeedReport" />
</template>

<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getSpeedReport } from '@/services/reports/speed'
import type { ReportDefinition } from './reportTypes'

const definition: ReportDefinition = {
  key: 'speed',
  title: { th: 'รายงานความเร็ว', en: 'Speed Report' },
  subtitle: { th: 'รายละเอียดความเร็วของรถตามช่วงเวลา', en: 'Vehicle speed timeline' },
  maxRangeDays: 3,
  enableTimeStart: true,
  enableTimeEnd: true,
  timeStartRequired: true,
  timeEndRequired: true,
  vehicleRequired: true,
  serverPagination: true,
  graph: 'speed',
  criteria: [{
    key: 'speed',
    label: 'ความเร็วขั้นต่ำ (กม./ชม.) / Minimum speed',
    defaultValue: 0,
    options: [0, 20, 40, 60, 80, 100].map((value) => ({ label: String(value), value })),
  }],
  columns: [
    { field: 'plate_no', label: 'Plate no' },
    { field: 'data_date', label: 'Date/time', type: 'datetime' },
    { field: 'state', label: 'State' },
    { field: 'speed', label: 'Speed (km/h)', type: 'number' },
    { field: 'isSpeedOver', label: 'Speed over' },
    { field: 'address', label: 'Location', type: 'location' },
  ],
}
</script>
