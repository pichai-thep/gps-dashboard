<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getPassengerSummary" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getPassengerSummary } from '@/services/reports/passengerSummary'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'passenger-summary', title: { th: 'รายงานสรุปจำนวนผู้โดยสาร', en: 'Passenger Summary' }, subtitle: { th: 'สรุปจำนวนผู้โดยสารขึ้น ลง และลืมลง', en: 'Passenger check-in and check-out summary' }, maxRangeDays: 0, columns: [{ field: 'checkin_date', label: 'Date' }, { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'checkin_people', label: 'Check-in', type: 'number' }, { field: 'checkout_people', label: 'Check-out', type: 'number' }, { field: 'forget_checkout_people', label: 'Forgot check-out', type: 'number' }, { field: 'runtime_hhmm', label: 'Runtime' }] }
</script>
