<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getSwipeReport" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getSwipeReport } from '@/services/reports/swipe'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'swipe', title: { th: 'รายงานการรูดบัตร', en: 'Card Swipe Report' }, subtitle: { th: 'รายละเอียดข้อมูลการรูดบัตรผู้ขับขี่', en: 'Driver card swipe details' }, maxRangeDays: 7, enableTimeStart: true, enableTimeEnd: true, timeStartRequired: true, timeEndRequired: true, criteria: [{ key: 'swipe_type', label: 'Swipe type', defaultValue: '', options: [{ label: 'ทั้งหมด / All', value: '' }, { label: 'Check in', value: 'in' }, { label: 'Check out', value: 'out' }] }], columns: [{ field: 'imei', label: 'IMEI', aliases: ['IMEI'] }, { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'swipe_time', label: 'Swipe time', aliases: ['Swipe_time'], type: 'datetime' }, { field: 'swipe_type', label: 'Swipe type' }, { field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number' }, { field: 'swipe_data', label: 'Swipe data' }] }
</script>
