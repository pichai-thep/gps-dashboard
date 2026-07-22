<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getDrive4hReport" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getDrive4hReport } from '@/services/reports/drive4h'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'drive4h', title: { th: 'รายงานขับรถเกิน 4 ชั่วโมง', en: 'Drive Over 4 Hours Report' }, subtitle: { th: 'รายละเอียดการขับรถต่อเนื่องเกินกำหนด', en: 'Continuous driving violation details' }, maxRangeDays: 30, columns: [{ field: 'imei', label: 'IMEI', aliases: ['IMEI'] }, { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'driver_id', label: 'Driver ID' }, { field: 'start_time', label: 'Start time', aliases: ['Start_time'], type: 'datetime' }, { field: 'alert1_time', label: 'Alert 1', aliases: ['Alert1_time'], type: 'datetime' }, { field: 'alert2_time', label: 'Alert 2', aliases: ['Alert2_time'], type: 'datetime' }, { field: 'alert3_time', label: 'Alert 3', aliases: ['Alert3_time'], type: 'datetime' }, { field: 'end_time', label: 'End time', type: 'datetime' }, { field: 'duration_mm', label: 'Duration (min)', type: 'number' }] }
</script>
