<template>
  <section class="base-report-filters">
    <div v-if="enableDateStart" class="filter-field">
      <label>{{ t('reportDateStart') }}</label>
      <Calendar v-model="dateFrom" :dateFormat="monthly ? 'mm/yy' : 'yy-mm-dd'" :view="monthly ? 'month' : 'date'" showIcon />
    </div>

    <div v-if="enableDateEnd" class="filter-field">
      <label>{{ t('reportDateEnd') }}</label>
      <Calendar v-model="dateTo" dateFormat="yy-mm-dd" showIcon />
    </div>

    <div v-if="timeStartEnabled" class="filter-field">
      <label>{{ t('reportTimeStart') }}<span v-if="timeStartRequired" class="required-mark"> *</span></label>
      <InputText v-model="timeStart" type="time" :invalid="timeStartRequired && !timeStart" />
    </div>

    <div v-if="timeEndEnabled" class="filter-field">
      <label>{{ t('reportTimeEnd') }}<span v-if="timeEndRequired" class="required-mark"> *</span></label>
      <InputText v-model="timeEnd" type="time" :invalid="timeEndRequired && !timeEnd" />
    </div>

    <div v-if="enableGroup" class="filter-field">
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

    <div v-if="enableVehicle" class="filter-field vehicle-field">
      <label>{{ t('selectVehicle') }}<span v-if="vehicleRequired" class="required-mark"> *</span></label>
      <Dropdown
        v-model="imei"
        :options="vehicleOptions"
        optionLabel="plate_no"
        optionValue="imei"
        :placeholder="t('allVehicles')"
        showClear
        filter
        :invalid="vehicleRequired && !imei"
      />
    </div>

    <slot name="criteria" />

    <div class="filter-actions">
      <Button
        v-if="enableSearch"
        :label="t('search')"
        icon="pi pi-search"
        :loading="loading"
        @click="submit"
      />
      <Button
        v-if="enableReset"
        :label="t('reset')"
        icon="pi pi-refresh"
        severity="secondary"
        outlined
        :disabled="loading"
        @click="resetFilters"
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
import { computed, ref } from 'vue'
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
  enableTimeStart?: boolean
  enableTimeEnd?: boolean
  timeStartRequired?: boolean
  timeEndRequired?: boolean
  enableDateStart?: boolean
  enableDateEnd?: boolean
  enableGroup?: boolean
  enableVehicle?: boolean
  vehicleRequired?: boolean
  enableSearch?: boolean
  enableReset?: boolean
  enableExportCsv?: boolean
  enablePdf?: boolean
  maxRangeDays?: number
  monthly?: boolean
}>(), {
  loading: false,
  hasRows: false,
  enableTime: false,
  enableTimeStart: undefined,
  enableTimeEnd: undefined,
  timeStartRequired: false,
  timeEndRequired: false,
  enableDateStart: true,
  enableDateEnd: true,
  enableGroup: true,
  enableVehicle: true,
  vehicleRequired: false,
  enableSearch: true,
  enableReset: true,
  enableExportCsv: true,
  enablePdf: true,
  maxRangeDays: 0,
  monthly: false,
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
const timeStartEnabled = computed(() => props.enableTimeStart ?? props.enableTime)
const timeEndEnabled = computed(() => props.enableTimeEnd ?? props.enableTime)

function submit() {
  validationMessage.value = ''

  if ((props.enableDateStart && !dateFrom.value) || (props.enableDateEnd && !dateTo.value)) {
    validationMessage.value = t('reportDateRequired')
    return
  }

  if (timeStartEnabled.value && props.timeStartRequired && !timeStart.value) {
    validationMessage.value = t('reportTimeStartRequired')
    return
  }

  if (timeEndEnabled.value && props.timeEndRequired && !timeEnd.value) {
    validationMessage.value = t('reportTimeEndRequired')
    return
  }

  if (props.enableVehicle && props.vehicleRequired && !imei.value) {
    validationMessage.value = t('reportVehicleRequired')
    return
  }

  const startValue = dateFrom.value ?? dateTo.value
  const endValue = dateTo.value ?? dateFrom.value

  if (!startValue || !endValue) {
    emit('search')
    return
  }

  const start = new Date(startValue)
  const end = new Date(endValue)
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

  if (timeStartEnabled.value && timeEndEnabled.value && timeStart.value > timeEnd.value && inclusiveDays === 1) {
    validationMessage.value = t('reportTimeOrderInvalid')
    return
  }

  emit('search')
}

function resetFilters() {
  validationMessage.value = ''
  emit('reset')
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

.required-mark {
  color: #f87171;
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
