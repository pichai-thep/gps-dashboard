<template>
  <BaseStoredProcedureReportView class="risk-event-report" :definition="definition" :loadReport="getRiskEventReport" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'
import BaseStoredProcedureReportView from './BaseStoredProcedureReportView.vue'
import { getRiskEventReport } from '@/services/reports/riskEvent'
import type { ReportDefinition } from './reportTypes'

const { locale } = useI18n()
const label = (th: string, en: string) => locale.value === 'th' ? th : en
const definition = computed<ReportDefinition>(() => ({
  key: 'risk-event',
  title: { th: 'รายงานเหตุการณ์พื้นที่และเส้นทางเสี่ยง', en: 'Risk Area and Route Event Report' },
  subtitle: { th: 'เหตุการณ์เข้าใกล้ เข้า อยู่ภายใน และออกจากพื้นที่หรือเส้นทางเสี่ยง', en: 'Near, entry, inside and exit events for risk areas and routes' },
  maxRangeDays: 7,
  enableVehicle: false,
  enableTimeStart: false,
  enableTimeEnd: false,
  criteria: [
    {
      key: 'risk_obj', label: label('ประเภทจุดเสี่ยง', 'Risk object'), defaultValue: '',
      options: [
        { label: label('ทั้งหมด', 'All'), value: '' },
        { label: label('พื้นที่ (AREA)', 'Area (AREA)'), value: 'AREA' },
        { label: label('เส้นทาง (ROUTE)', 'Route (ROUTE)'), value: 'ROUTE' },
      ],
    },
    {
      key: 'risk_type', label: label('ประเภทเหตุการณ์', 'Event type'), defaultValue: '',
      options: [
        { label: label('ทั้งหมด', 'All'), value: '' },
        { label: label('เข้าใกล้ (NEAR)', 'Near (NEAR)'), value: 'NEAR' },
        { label: label('เข้า (ENTER)', 'Enter (ENTER)'), value: 'ENTER' },
        { label: label('อยู่ภายใน (INSIDE)', 'Inside (INSIDE)'), value: 'INSIDE' },
        { label: label('ออก (EXIT)', 'Exit (EXIT)'), value: 'EXIT' },
      ],
    },
  ],
  columns: [
    { field: 'plate_no', label: label('ทะเบียนรถ', 'Plate no') },
    { field: 'event_time', label: label('เวลาเกิดเหตุการณ์', 'Event time'), type: 'datetime' },
    { field: 'risk_obj', label: label('ประเภทจุดเสี่ยง', 'Risk object') },
    { field: 'risk_type', label: label('ประเภทเหตุการณ์', 'Event type') },
    { field: 'risk_name', label: label('ชื่อพื้นที่/เส้นทางเสี่ยง', 'Risk area / route name') },
    { field: 'distance_meter', label: label('ระยะห่าง (เมตร)', 'Distance (meters)'), type: 'number' },
    { field: 'location', label: label('พิกัด / แผนที่', 'Coordinates / Map'), type: 'location' },
  ],
}))
</script>

<style scoped>
.risk-event-report :deep(.base-report-filters) {
  grid-template-columns: repeat(5, minmax(0, 1fr));
  grid-template-areas:
    "date-start date-end group risk-object risk-type";
  gap: 16px 12px;
  align-items: end;
}

.risk-event-report :deep(.date-start-field) { grid-area: date-start; }
.risk-event-report :deep(.date-end-field) { grid-area: date-end; }
.risk-event-report :deep(.group-field) { grid-area: group; }
.risk-event-report :deep([data-criterion="risk_obj"]) { grid-area: risk-object; }
.risk-event-report :deep([data-criterion="risk_type"]) { grid-area: risk-type; }

.risk-event-report :deep(.filter-field input),
.risk-event-report :deep(.p-datepicker),
.risk-event-report :deep(.p-select) {
  width: 100%;
  min-width: 0;
}

.risk-event-report :deep(.filter-actions) {
  padding-top: 16px;
  border-top: 1px solid #1f2937;
  gap: 10px;
}

@media (max-width: 1200px) {
  .risk-event-report :deep(.base-report-filters) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-areas:
      "date-start date-end"
      "group group"
      "risk-object risk-type";
  }
}

@media (max-width: 640px) {
  .risk-event-report :deep(.base-report-filters) {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      "date-start"
      "date-end"
      "group"
      "risk-object"
      "risk-type";
  }

  .risk-event-report :deep(.filter-actions .p-button) {
    flex: 1 1 calc(50% - 10px);
  }
}
</style>
