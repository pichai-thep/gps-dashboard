<template><BaseStoredProcedureReportView :definition="definition" :loadReport="getForbiddenInsideReport" /></template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getForbiddenInsideReport } from '@/services/reports/forbiddenInside'
import type { ReportDefinition } from './reportTypes'
const definition: ReportDefinition = { key: 'forbidden-inside', title: { th: 'รายงานการเข้าพื้นที่ห้ามเข้า', en: 'Forbidden Area Entry Report' }, subtitle: { th: 'รายละเอียดรถที่ตรวจพบภายในพื้นที่ห้ามเข้า', en: 'Vehicles detected inside forbidden areas' }, maxRangeDays: 30, columns: [{ field: 'imei', label: 'IMEI', aliases: ['IMEI'] }, { field: 'plate_no', label: 'Plate no', aliases: ['Plate_no'] }, { field: 'zone_name', label: 'Zone name' }, { field: 'gps_time', label: 'GPS time', aliases: ['Gps_time'], type: 'datetime' }, { field: 'lat', label: 'Latitude', aliases: ['Lat'] }, { field: 'lon', label: 'Longitude' }, { field: 'address', label: 'Address / Map', aliases: ['Address'], type: 'location' }] }
</script>
