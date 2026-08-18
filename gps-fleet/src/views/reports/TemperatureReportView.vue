<template>
  <BaseStoredProcedureReportView
    :definition="definition"
    :loadReport="getTemperatureReport"
    :loadChart="getTemperatureChart"
  />
</template>

<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getTemperatureChart, getTemperatureReport } from '@/services/reports/temperature'
import type { ReportDefinition } from './reportTypes'

const definition: ReportDefinition = {
  key: 'temperature',
  title: { th: 'รายงานอุณหภูมิ', en: 'Temperature Report' },
  subtitle: { th: 'ข้อมูลอุณหภูมิของรถและกราฟตามสถานะการทำงาน', en: 'Vehicle temperature data and status chart' },
  maxRangeDays: 3,
  enableTimeStart: true,
  enableTimeEnd: true,
  timeStartRequired: true,
  timeEndRequired: true,
  vehicleRequired: true,
  graph: 'temperature',
  criteria: [
    {
      key: 'temp_status',
      label: { th: 'สถานะอุณหภูมิ', en: 'Temperature status' },
      defaultValue: 'all',
      options: [
        { label: 'ทั้งหมด / All', value: 'all' },
        { label: 'Green', value: 'green' },
        { label: 'Yellow', value: 'yellow' },
        { label: 'Red', value: 'red' },
      ],
    },
  ],
  columns: [
    { field: 'plate_no', label: 'Plate no' },
    { field: 'data_date', label: 'Date/time', type: 'datetime' },
    { field: 'vehicle_status', label: 'Vehicle status' },
    { field: 'speed', label: 'Speed (km/h)', type: 'number' },
    { field: 'temp_a', label: 'Temperature A (°C)', type: 'number' },
    // { field: 'temp_a_status', label: 'Status A' },
    { field: 'temp_b', label: 'Temperature B (°C)', type: 'number' },
    // { field: 'temp_b_status', label: 'Status B' },
    { field: 'input2', label: 'Input 2', type: 'number' },
    { field: 'address', label: 'Location', type: 'location' },
  ],
}
</script>
