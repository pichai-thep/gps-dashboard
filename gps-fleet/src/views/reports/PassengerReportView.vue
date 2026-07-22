<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getPassengerReport" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getPassengerReport } from '@/services/reports/passenger'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'passenger', title: { th: 'รายงานผู้โดยสาร', en: 'Passenger Report' }, subtitle: { th: 'รายละเอียดการขึ้นและลงของผู้โดยสาร', en: 'Passenger check-in and check-out details' }, maxRangeDays: 30, columns: [{ field: 'checkin_date', label: 'Date' }, { field: 'plate_no', label: 'Plate no' }, { field: 'checkin_time', label: 'Check-in time', type: 'datetime' }, { field: 'checkout_time', label: 'Check-out time', type: 'datetime' }, { field: 'uid', label: 'UID' }, { field: 'full_name', label: 'Full name', aliases: ['Full_name'] }, { field: 'custom1', label: 'Custom 1' }, { field: 'custom2', label: 'Custom 2' }, { field: 'custom3', label: 'Custom 3', aliases: ['Custom3'] }, { field: 'checkin_location', label: 'Check-in location', aliases: ['Checkin_location'], type: 'location' }, { field: 'checkout_location', label: 'Check-out location', aliases: ['Checkout_location'], type: 'location' }, { field: 'duration_hhmm', label: 'Duration', aliases: ['Duration_hhmm'] }] }
</script>
