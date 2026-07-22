<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getFuelReport"/>
</template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import {getFuelReport} from '@/services/reports/fuel'
import type {ReportDefinition} from './reportTypes'

const definition: ReportDefinition = {
  key: 'fuel',
  title: {th: 'รายงานน้ำมัน/เชื้อเพลิง', en: 'Fuel Report'},
  subtitle: {th: 'สถานะรถ ความเร็ว และระดับเชื้อเพลิงตามเวลา', en: 'Vehicle status, speed and fuel timeline'},
  maxRangeDays: 3,
  enableTimeStart: true,
  enableTimeEnd: true,
  timeStartRequired: true,
  timeEndRequired: true,
  vehicleRequired: true,
  graph: true,
  columns: [{field: 'data_date', label: 'Date/time', type: 'datetime'}, {
    field: 'vehicle_status',
    label: 'Vehicle status',
    aliases: ['state', 'status']
  }, {field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number'}, {
    field: 'fuel',
    label: 'Fuel',
    aliases: ['Fuel', 'fuel_left'],
    type: 'number'
  }]
}
</script>
