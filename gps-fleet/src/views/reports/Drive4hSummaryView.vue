<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getDrive4hSummary" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getDrive4hSummary } from '@/services/reports/drive4hSummary'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'drive4h-summary', title: { th: 'รายงานสรุปการขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Summary' }, subtitle: { th: 'สรุปจำนวนและระยะเวลาการขับต่อเนื่องเกินกำหนด', en: 'Continuous driving violation summary' }, maxRangeDays: 0, columns: [{ field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'count4h', label: 'Count 4h', type: 'number' }, { field: 'max_duration_mm', label: 'Max duration (min)', type: 'number' }] }
</script>
