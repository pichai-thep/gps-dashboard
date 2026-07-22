<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getSpeedOverSummary" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getSpeedOverSummary } from '@/services/reports/speedOverSummary'
import type { ReportDefinition } from './reportTypes'

const definition: ReportDefinition = {
  key: 'speed-over-summary', title: { th: 'รายงานสรุปความเร็วเกินกำหนด', en: 'Speed Over Summary' },
  subtitle: { th: 'สรุปเหตุการณ์ความเร็วเกินแยกตามรถ', en: 'Speed violation summary by vehicle' }, maxRangeDays: 30,
  criteria: [{ key: 'over_type', label: 'Over type', defaultValue: '', options: [{ label: 'ทั้งหมด / All', value: '' }, { label: 'System', value: 'system' }, { label: 'Device', value: 'device' }] }],
  columns: [
    { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'max_speed_date', label: 'Max speed date' },
    { field: 'max_speed_time', label: 'Max speed time' }, { field: 'speed_limited', label: 'Speed limited', aliases: ['Speed_limited'], type: 'number' },
    { field: 'max_speed', label: 'Max speed', aliases: ['Max speed'], type: 'number' }, { field: 'address', label: 'Address', aliases: ['Address'] },
    { field: 'total_events', label: 'Total events', type: 'number' }, { field: 'total_days', label: 'Total days', type: 'number' },
  ],
}
</script>
