<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getFuelReport"/>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import {getFuelReport} from '@/services/reports/fuel'
import type {ReportDefinition} from './reportTypes'
import { locale } from '@/i18n'
import { vehicleStatusLabel } from '@/utils/vehicleStatus'

const definition = computed<ReportDefinition>(() => ({
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
  criteria: [{
    key: 'status',
    label: 'สถานะ / Status',
    defaultValue: '',
    options: [
      {label: locale.value === 'th' ? 'ทั้งหมด' : 'All', value: ''},
      {label: vehicleStatusLabel('park', locale.value), value: 'park'},
      {label: vehicleStatusLabel('idle', locale.value), value: 'idle'},
      {label: vehicleStatusLabel('run', locale.value), value: 'run'},
    ],
  }],
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
}))
</script>
