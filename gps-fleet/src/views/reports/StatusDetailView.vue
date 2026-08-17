<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getStatusDetail"/>
</template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import {getStatusDetail} from '@/services/reports/statusDetail'
import type {ReportDefinition} from './reportTypes'

const definition: ReportDefinition = {
  key: 'status-detail',
  title: {th: 'รายงานรายละเอียดสถานะรถ', en: 'Vehicle Status Detail Report'},
  subtitle: {th: 'รายละเอียดสถานะและช่วงเวลาการเดินทางของรถ', en: 'Vehicle travel status and duration details'},
  maxRangeDays: 7,
  vehicleRequired: true,
  exportFormat: 'excel',
  criteria: [
    {
      key: 'status',
      label: 'Status',
      defaultValue: 'all',
      options: [
          {label: 'ทั้งหมด / All', value: 'all'}
        , {label: 'RUN', value: 'RUN'}
        , {label: 'IDLE', value: 'IDLE'}
        , {label: 'PARK', value: 'PARK'}]
    },
    {
      key: 'duration',
      label: 'Minimum duration',
      defaultValue: 0,
      options: [{label: 'ทั้งหมด / All', value: 0}, {label: '5 min', value: 5}, {
        label: '10 min',
        value: 10
      }, {label: '30 min', value: 30}, {label: '60 min', value: 60}]
    },
  ],
  columns: [{field: 'status', label: 'Status', aliases: ['state_status']}, {
    field: 'datetime_start',
    label: 'Date time start',
    aliases: ['start_date_time'],
    type: 'datetime'
  }, {field: 'datetime_end', label: 'Date time end', aliases: ['end_date_time'], type: 'datetime'}, {
    field: 'duration',
    label: 'Duration',
    aliases: ['duration_time', 'duration_mm']
  }, {field: 'max_speed', label: 'Max speed', type: 'number'}, {
    field: 'distance',
    label: 'Distance',
    type: 'number'
  }, {
    field: 'location',
    label: 'Location / Map',
    aliases: ['end_station', 'end_address', 'start_station'],
    type: 'location'
  }]
}
</script>
