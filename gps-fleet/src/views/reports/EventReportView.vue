<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getEventReport"/>
</template>
<script setup lang="ts">
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import {getEventReport} from '@/services/reports/event'
import type {ReportDefinition} from './reportTypes'

const eventTypes = [['gps_disconnect', 'GPS antenna disconnect'], ['sos', 'SOS'], ['over_speed_system', 'Speed over (web)'], ['over_speed_device', 'Speed over (device)'], ['gps_signal_lost', 'GPS signal lost'], ['gps_antenna_disconnect', 'GPS antenna disconnect'], ['ext_power_disconnect', 'External power disconnect'], ['harsh_break', 'Harsh brake'], ['harsh_accelerate', 'Harsh accelerate'], ['Tamper', 'Tamper'], ['cover_removed', 'Cover removed'], ['Low internal battery', 'Low internal battery'], ['Powered off due to low battery', 'Powered off due to low battery'], ['Vibration', 'Vibration'], ['DRIVE_330', 'Drive reach 3:30 hr'], ['DRIVE_345', 'Drive reach 3:45 hr'], ['DRIVE_340', 'Drive reach 4:00 hr']].map(([value, label]) => ({
  value,
  label
}))
const definition: ReportDefinition = {
  key: 'event',
  title: {th: 'รายงานเหตุการณ์สำคัญ', en: 'Important Event Report'},
  subtitle: {th: 'เหตุการณ์แจ้งเตือนสำคัญจากรถ', en: 'Important vehicle alert events'},
  maxRangeDays: 7,
  vehicleRequired: true,
  criteria: [{
    key: 'event_type',
    label: 'Event type',
    defaultValue: '',
    options: [{label: 'ทั้งหมด / All', value: ''}, ...eventTypes]
  }],
  columns: [{field: 'plate_no', label: 'Plate no', aliases: ['Plate_no']}, {
    field: 'event_type',
    label: 'Event type',
    aliases: ['Event type']
  }, {field: 'event_time', label: 'Event time', aliases: ['event time'], type: 'datetime'}, {
    field: 'driver_id',
    label: 'Driver ID',
    aliases: ['Driver id']
  }, {field: 'speed', label: 'Speed', aliases: ['Speed'], type: 'number'}, {
    field: 'address',
    label: 'Address / Map',
    aliases: ['Address'],
    type: 'location'
  }]
}
</script>
