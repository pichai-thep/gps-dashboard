<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getSpeedOverReport"/>
</template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import {getSpeedOverReport} from '@/services/reports/speedOver'
import type {ReportDefinition} from './reportTypes'

const definition: ReportDefinition = {
  key: 'speed-over',
  title: {th: 'รายงานความเร็วเกินกำหนด', en: 'Speed Over Report'},
  subtitle: {th: 'รายละเอียดช่วงเวลาที่รถใช้ความเร็วเกินกำหนด', en: 'Detailed speed violation events'},
  maxRangeDays: 31,
  enableTimeStart: true,
  enableTimeEnd: true,
  timeStartRequired: true,
  timeEndRequired: true,
  criteria: [{
    key: 'over_type',
    label: 'Over type',
    defaultValue: '',
    options: [{label: 'ทั้งหมด / All', value: ''}, {label: 'Cloud', value: 'cloud'}, {
      label: 'Device',
      value: 'device'
    }]
  }],
  columns: [
    // {field: 'imei', label: 'IMEI', aliases: ['IMEI']},
    {
      field: 'plate_no',
      label: 'Plate no',
      aliases: ['Plate_no']
    },
    {field: 'over_type', label: 'Over type', aliases: ['Over-type']},
    {
      field: 'event_time',
      label: 'Event time',
      aliases: ['event-time'],
      type: 'datetime'
    },
    {field: 'end_time', label: 'End time', aliases: ['end-time'], type: 'datetime'},
    {
      field: 'duration',
      label: 'Duration',
      aliases: ['Duration']
    },
    {field: 'speed_limited', label: 'Speed limited', aliases: ['Speed limited'], type: 'number'},
    {
      field: 'speed',
      label: 'Speed',
      aliases: ['Speed'],
      type: 'number'
    },
    {field: 'lat_lon', label: 'Location', aliases: ['Lat/lon'], type: 'location'}
  ]
}
</script>
