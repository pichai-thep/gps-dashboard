<template>
  <section class="base-report-filters">
    <div class="filter-field">
      <label>{{ t('reportDateStart') }}</label>
      <Calendar v-model="dateFrom" dateFormat="yy-mm-dd" showIcon />
    </div>

    <div class="filter-field">
      <label>{{ t('reportDateEnd') }}</label>
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />
    </div>

    <div v-if="enableTime" class="filter-field">
      <label>{{ t('reportTimeStart') }}</label>
      <InputText v-model="timeStart" type="time" />
    </div>

    <div v-if="enableTime" class="filter-field">
      <label>{{ t('reportTimeEnd') }}</label>
      <InputText v-model="timeEnd" type="time" />
    </div>

    <div class="filter-field">
      <label>{{ t('selectGroup') }}</label>
      <Dropdown
        v-model="groupId"
        :options="groupOptions"
        optionLabel="group_name"
        optionValue="group_id"
        :placeholder="t('allGroups')"
        showClear
        filter
        @change="$emit('group-change')"
      />
    </div>

    <div class="filter-field vehicle-field">
      <label>{{ t('selectVehicle') }}</label>
      <Dropdown
        v-model="imei"
        :options="vehicleOptions"
        optionLabel="plate_no"
        optionValue="imei"
        :placeholder="t('allVehicles')"
        showClear
        filter
      />
    </div>

    <slot name="criteria" />

    <div class="filter-actions">
      <Button
        :label="t('search')"
        icon="pi pi-search"
        :loading="loading"
        @click="submit"
      />
      <Button
        :label="t('reset')"
        icon="pi pi-refresh"
        severity="secondary"
        outlined
        :disabled="loading"
        @click="$emit('reset')"
      />
      <Button
        v-if="enableExportCsv"
        :label="t('exportCsv')"
        icon="pi pi-download"
        severity="secondary"
        :disabled="!hasRows || loading"
        @click="$emit('export-csv')"
      />
      <Button
        v-if="enablePdf"
        :label="t('savePdf')"
        icon="pi pi-file-pdf"
        severity="secondary"
        :disabled="!hasRows || loading"
        @click="$emit('save-pdf')"
      />
    </div>

    <Message v-if="validationMessage" severity="warn" :closable="false">
      {{ validationMessage }}
    </Message>

    <small v-if="maxRangeDays" class="range-hint">
      {{ t('reportRangeHint', { days: maxRangeDays }) }}
    </small>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import Calendar from 'primevue/calendar'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useI18n } from '@/i18n'
import type { ReportGroupOption, ReportVehicleOption } from '@/services/report'

const props = withDefaults(defineProps<{
  groupOptions: ReportGroupOption[]
  vehicleOptions: ReportVehicleOption[]
  loading?: boolean
  hasRows?: boolean
  enableTime?: boolean
  enableExportCsv?: boolean
  enablePdf?: boolean
  maxRangeDays?: number
}>(), {
  loading: false,
  hasRows: false,
  enableTime: false,
  enableExportCsv: true,
  enablePdf: true,
  maxRangeDays: 0,
})

const emit = defineEmits<{
  search: []
  reset: []
  'group-change': []
  'export-csv': []
  'save-pdf': []
}>()

const dateFrom = defineModel<Date | null>('dateFrom', { required: true })
const dateTo = defineModel<Date | null>('dateTo', { required: true })
const timeStart = defineModel<string>('timeStart', { default: '00:00' })
const timeEnd = defineModel<string>('timeEnd', { default: '23:59' })
const groupId = defineModel<number | null>('groupId', { default: null })
const imei = defineModel<string | null>('imei', { default: null })

const { t } = useI18n()
const validationMessage = ref('')

function submit() {
  validationMessage.value = ''

  if (!dateFrom.value || !dateTo.value) {
    validationMessage.value = t('reportDateRequired')
    return
  }

  const start = new Date(dateFrom.value)
  const end = new Date(dateTo.value)
  start.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)

  if (end.getTime() < start.getTime()) {
    validationMessage.value = t('reportDateOrderInvalid')
    return
  }

  const inclusiveDays = Math.floor((end.getTime() - start.getTime()) / 86400000) + 1
  if (props.maxRangeDays > 0 && inclusiveDays > props.maxRangeDays) {
    validationMessage.value = t('reportRangeExceeded', { days: props.maxRangeDays })
    return
  }

  if (props.enableTime && timeStart.value > timeEnd.value && inclusiveDays === 1) {
    validationMessage.value = t('reportTimeOrderInvalid')
    return
  }

  emit('search')
}
</script>

<style scoped>
.base-report-filters {
  display: grid;
  grid-template-columns: repeat(6, minmax(150px, 1fr));
  gap: 12px;
  padding: 16px;
  margin-bottom: 16px;
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
}

.filter-field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 6px;
}

.filter-field label {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 600;
}

.vehicle-field {
  grid-column: span 2;
}

.filter-actions {
  display: flex;
  grid-column: 1 / -1;
  flex-wrap: wrap;
  gap: 10px;
}

.range-hint {
  grid-column: 1 / -1;
  color: #94a3b8;
}

.p-message {
  grid-column: 1 / -1;
  margin: 0;
}

@media (max-width: 1200px) {
  .base-report-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .base-report-filters {
    grid-template-columns: 1fr;
  }

  .vehicle-field {
    grid-column: auto;
  }
}
</style>
