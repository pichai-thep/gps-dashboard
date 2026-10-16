<template>
  <BaseStoredProcedureReportView :definition="definition" :loadReport="getFuelAbnormalReport" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getFuelAbnormalReport } from '@/services/reports/fuelAbnormal'
import type { ReportDefinition } from './reportTypes'

const { locale } = useI18n()
const label = (th: string, en: string) => locale.value === 'th' ? th : en
const definition = computed<ReportDefinition>(() => ({
  key: 'fuel-abnormal',
  title: { th: 'รายงานน้ำมันผิดปกติ', en: 'Abnormal Fuel Report' },
  subtitle: { th: 'รายการตรวจพบระดับน้ำมันผิดปกติตามช่วงวันที่', en: 'Abnormal fuel readings by date range' },
  maxRangeDays: 30,
  enableVehicle: true,
  enableTimeStart: false,
  enableTimeEnd: false,
  columns: [
    { field: 'plate_no', label: label('ทะเบียนรถ', 'Plate no') },
    { field: 'imei', label: 'IMEI' },
    { field: 'tracker_model', label: label('รุ่นอุปกรณ์', 'Tracker model') },
    { field: 'data_date', label: label('วันที่/เวลาเกิดเหตุการณ์', 'Event date/time'), type: 'datetime' },
    { field: 'fuel_avg', label: label('ค่าน้ำมันเฉลี่ย', 'Average fuel'), type: 'number' },
    { field: 'fuel_last', label: label('ค่าน้ำมันล่าสุด', 'Last fuel'), type: 'number' },
    { field: 'fuel_max_diff', label: label('ค่าความต่างน้ำมันสูงสุด', 'Maximum fuel difference'), type: 'number' },
    { field: 'location', label: label('พิกัด / แผนที่', 'Coordinates / Map'), type: 'location' },
    { field: 'created_time', label: label('วันที่/เวลาบันทึก', 'Created date/time'), type: 'datetime' },
  ],
}))
</script>
