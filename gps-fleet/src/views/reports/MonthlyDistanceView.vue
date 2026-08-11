<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getMonthlyDistance" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getMonthlyDistance } from '@/services/reports/monthlyDistance'
import type { ReportDefinition } from './reportTypes'

const definition: ReportDefinition = {
  key: 'monthly-distance',
  title: { th: 'ระยะทางรายเดือน', en: 'Monthly Distance' },
  subtitle: { th: 'สรุประยะทางรายวันแยกตามรถในแต่ละเดือน', en: 'Daily distance by vehicle for the selected month' },
  maxRangeDays: 0,
  monthly: true,
  dailyDistanceLimitCriterionKey: 'daily_distance_limit_km',
  criteria: [{
    key: 'daily_distance_limit_km',
    label: { th: 'วิ่งไม่เกิน (กม./วัน)', en: 'Maximum distance (km/day)' },
    type: 'number',
    defaultValue: null,
    min: 0,
    maxFractionDigits: 0,
    suffix: ' km',
  }],
  columns: [
    { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] },
    { field: 'total_km', label: 'Total (km)', type: 'number' },
    { field: 'd1', label: '1', type: 'number' },
    { field: 'd2', label: '2', type: 'number' },
    { field: 'd3', label: '3', type: 'number' },
    { field: 'd4', label: '4', type: 'number' },
    { field: 'd5', label: '5', type: 'number' },
    { field: 'd6', label: '6', type: 'number' },
    { field: 'd7', label: '7', type: 'number' },
    { field: 'd8', label: '8', type: 'number' },
    { field: 'd9', label: '9', type: 'number' },
    { field: 'd10', label: '10', type: 'number' },
    { field: 'd11', label: '11', type: 'number' },
    { field: 'd12', label: '12', type: 'number' },
    { field: 'd13', label: '13', type: 'number' },
    { field: 'd14', label: '14', type: 'number' },
    { field: 'd15', label: '15', type: 'number' },
    { field: 'd16', label: '16', type: 'number' },
    { field: 'd17', label: '17', type: 'number' },
    { field: 'd18', label: '18', type: 'number' },
    { field: 'd19', label: '19', type: 'number' },
    { field: 'd20', label: '20', type: 'number' },
    { field: 'd21', label: '21', type: 'number' },
    { field: 'd22', label: '22', type: 'number' },
    { field: 'd23', label: '23', type: 'number' },
    { field: 'd24', label: '24', type: 'number' },
    { field: 'd25', label: '25', type: 'number' },
    { field: 'd26', label: '26', type: 'number' },
    { field: 'd27', label: '27', type: 'number' },
    { field: 'd28', label: '28', type: 'number' },
    { field: 'd29', label: '29', type: 'number' },
    { field: 'd30', label: '30', type: 'number' },
    { field: 'd31', label: '31', type: 'number' },
  ],
}
</script>
