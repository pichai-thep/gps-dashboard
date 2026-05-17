<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

import {
  createVehicleGroup,
  deleteVehicleGroup,
  getVehicle,
  getVehicleGroups,
  getVehicles,
  moveVehiclesToGroup,
  updateMileage,
  updateVehicle,
  type VehicleDetail,
  type VehicleGroup,
  type VehicleListItem,
} from '@/services/vehicleManagement'

const toast = useToast()

const loading = ref(false)
const saving = ref(false)

const keyword = ref('')
const selectedGroupFilter = ref<number | null>(null)
const selectedMoveGroup = ref<number | null>(null)

const newGroupName = ref('')

const vehicles = ref<VehicleListItem[]>([])
const groups = ref<VehicleGroup[]>([])

const selectedRows = ref<VehicleListItem[]>([])
const selectedVehicle = ref<VehicleListItem | null>(null)

const showFilterPanel = ref(false)
const showGroupPanel = ref(false)

const form = reactive<VehicleDetail>({
  imei: '',
  plate_no: '',
  sequen_no: null,

  driver_id: '',
  driver_name: '',
  driver_phone: '',

  speed_limited: null,
  icon_path: null,

  fuel_min_vol: null,
  fuel_max_vol: null,
  input_fuel_reverse: false,

  fuel_kmpl: null,
  fuel_lph: null,
  fuel_tank_size: null,
  fuel_price: null,

  fuel_mont: false,
  remark: '',

  current_mileage: null,
})

const vehicleIcons = [
  { label: 'Truck', value: 'truck.png' },
  { label: 'Car', value: 'car.png' },
  { label: 'Van', value: 'van.png' },
  { label: 'Motorcycle', value: 'motorcycle.png' },
]

function boolValue(value: unknown): boolean {
  return value === true || value === 1 || value === '1'
}

async function loadGroups() {
  groups.value = await getVehicleGroups()
}

async function loadVehicles() {
  loading.value = true

  try {
    vehicles.value = await getVehicles({
      keyword: keyword.value,
      group_id: selectedGroupFilter.value,
    })
  } finally {
    loading.value = false
  }
}

async function selectVehicle(row: VehicleListItem) {
  console.log('clicked vehicle:', row)

  selectedVehicle.value = row

  // เปิด form ทันที ก่อนเรียก API
  Object.assign(form, {
    imei: row.imei,
    plate_no: row.plate_no || '',
    sequen_no: row.sequen_no ?? null,
  })

  try {
    const data = await getVehicle(row.imei)

    console.log('vehicle detail:', data)

    Object.assign(form, data, {
      input_fuel_reverse: boolValue(data.input_fuel_reverse),
      fuel_mont: boolValue(data.fuel_mont),
    })
  } catch (error) {
    console.error('getVehicle error:', error)

    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Cannot load vehicle detail',
      life: 3000,
    })
  }
}

async function saveVehicle() {
  if (!form.imei) return

  saving.value = true

  try {
    await updateVehicle(form.imei, {
      plate_no: form.plate_no,
      sequen_no: form.sequen_no,

      driver_id: form.driver_id,
      driver_name: form.driver_name,
      driver_phone: form.driver_phone,

      speed_limited: form.speed_limited,
      icon_path: form.icon_path,

      fuel_min_vol: form.fuel_min_vol,
      fuel_max_vol: form.fuel_max_vol,
      input_fuel_reverse: !!form.input_fuel_reverse,

      fuel_kmpl: form.fuel_kmpl,
      fuel_lph: form.fuel_lph,
      fuel_tank_size: form.fuel_tank_size,
      fuel_price: form.fuel_price,

      fuel_mont: !!form.fuel_mont,
      remark: form.remark,
    })

    toast.add({
      severity: 'success',
      summary: 'Saved',
      detail: 'Vehicle updated',
      life: 2500,
    })

    await loadVehicles()
  } finally {
    saving.value = false
  }
}

async function saveMileage() {
  if (!form.imei || form.current_mileage == null) return

  await updateMileage(
      form.imei,
      Number(form.current_mileage)
  )

  toast.add({
    severity: 'success',
    summary: 'Saved',
    detail: 'Mileage updated',
    life: 2500,
  })
}

async function createGroup() {
  const name = newGroupName.value.trim()

  if (!name) return

  await createVehicleGroup(name)

  newGroupName.value = ''

  await loadGroups()

  toast.add({
    severity: 'success',
    summary: 'Created',
    detail: 'Group created',
    life: 2500,
  })
}

async function removeGroup() {
  if (!selectedGroupFilter.value) return

  await deleteVehicleGroup(selectedGroupFilter.value)

  selectedGroupFilter.value = null

  await loadGroups()
  await loadVehicles()

  toast.add({
    severity: 'success',
    summary: 'Deleted',
    detail: 'Group deleted',
    life: 2500,
  })
}

async function moveGroup() {
  if (!selectedMoveGroup.value) return

  if (selectedRows.value.length === 0) return

  await moveVehiclesToGroup(
      selectedRows.value.map(v => v.imei),
      selectedMoveGroup.value
  )

  selectedRows.value = []

  await loadVehicles()

  toast.add({
    severity: 'success',
    summary: 'Moved',
    detail: 'Vehicles moved to group',
    life: 2500,
  })
}

onMounted(async () => {
  await loadGroups()
  await loadVehicles()
})
</script>

<template>
  <div class="vehicle-page">

    <div class="page-header">
      <div>
        <h2>Vehicle Management</h2>
        <p>Manage vehicle information and vehicle groups</p>
      </div>

      <Button
          label="Refresh"
          icon="pi pi-refresh"
          outlined
          @click="loadVehicles"
      />
    </div>

    <div class="toolbar card compact-toolbar">

      <InputText
          v-model="keyword"
          placeholder="Search IMEI / Plate / Driver"
          class="search-input"
          @keyup.enter="loadVehicles"
      />

      <Button
          label="Search"
          icon="pi pi-search"
          size="small"
          @click="loadVehicles"
      />

      <Button
          label="Filter"
          icon="pi pi-filter"
          size="small"
          outlined
          @click="showFilterPanel = !showFilterPanel"
      />

      <Button
          label="Groups"
          icon="pi pi-folder"
          size="small"
          outlined
          @click="showGroupPanel = !showGroupPanel"
      />

    </div>

    <div class="toolbar card">

      <Dropdown
          v-model="selectedMoveGroup"
          :options="groups"
          option-label="customer_group_name"
          option-value="customer_group_id"
          placeholder="Move to Group"
          class="group-dropdown"
      />

      <Button
          label="Apply"
          icon="pi pi-arrow-right"
          :disabled="selectedRows.length === 0 || !selectedMoveGroup"
          @click="moveGroup"
      />

      <span class="selected-label">
        Selected:
        {{ selectedRows.length }}
      </span>

    </div>

    <div class="main-layout">

      <div class="vehicle-list card">

        <div class="list-header">
          <div>
            Vehicle List
            <span class="vehicle-count">
        {{ vehicles.length }}
      </span>
          </div>

          <div class="list-subtitle">
            {{ selectedGroupFilter ? 'Filtered by group' : 'All vehicles' }}
          </div>
        </div>

        <DataTable
            v-model:selection="selectedRows"
            :value="vehicles"
            data-key="imei"
            :loading="loading"
            scrollable
            scroll-height="calc(100vh - 320px)"
            size="small"
        >
          <Column
              selection-mode="multiple"
              header-style="width: 3rem"
          />

          <Column header="Vehicle">
            <template #body="{ data }">
              <button
                  type="button"
                  class="vehicle-row-button"
                  :class="{ active: selectedVehicle?.imei === data.imei }"
                  @click.stop.prevent="selectVehicle(data)"
              >
                {{ data.plate_no || '-' }}
              </button>
            </template>
          </Column>
        </DataTable>

      </div>

      <div class="edit-panel">

        <Card>

          <template #title>
            General Info
          </template>

          <template #content>

            <div
                v-if="!form.imei"
                class="empty-state"
            >
              Select vehicle from list
            </div>

            <div
                v-else
                class="form-grid"
            >

              <div class="field">
                <label>IMEI</label>
                <InputText
                    v-model="form.imei"
                    readonly
                />
              </div>

              <div class="field">
                <label>Plate</label>
                <InputText v-model="form.plate_no" />
              </div>

              <div class="field">
                <label>Sequence No</label>
                <InputNumber v-model="form.sequen_no" />
              </div>

              <div class="field">
                <label>Driver License</label>
                <InputText v-model="form.driver_id" />
              </div>

              <div class="field">
                <label>Driver Name</label>
                <InputText v-model="form.driver_name" />
              </div>

              <div class="field">
                <label>Driver Phone</label>
                <InputText v-model="form.driver_phone" />
              </div>

              <div class="field">
                <label>Speed Limit</label>
                <InputNumber
                    v-model="form.speed_limited"
                    suffix=" km/h"
                />
              </div>

              <div class="field">
                <label>Vehicle Icon</label>

                <Dropdown
                    v-model="form.icon_path"
                    :options="vehicleIcons"
                    option-label="label"
                    option-value="value"
                    placeholder="Select Icon"
                />
              </div>

              <div class="section-title">
                Fuel Setting
              </div>

              <div class="field">
                <label>Fuel Min Voltage</label>

                <InputNumber
                    v-model="form.fuel_min_vol"
                    mode="decimal"
                    :min-fraction-digits="2"
                />
              </div>

              <div class="field">
                <label>Fuel Max Voltage</label>

                <InputNumber
                    v-model="form.fuel_max_vol"
                    mode="decimal"
                    :min-fraction-digits="2"
                />
              </div>

              <div class="field checkbox-field">

                <Checkbox
                    v-model="form.input_fuel_reverse"
                    binary
                    input-id="reverse"
                />

                <label for="reverse">
                  Inverse Calculation
                </label>

              </div>

              <div class="field">
                <label>Km / Litre</label>

                <InputNumber
                    v-model="form.fuel_kmpl"
                    mode="decimal"
                />
              </div>

              <div class="field">
                <label>Litre / Hr</label>

                <InputNumber
                    v-model="form.fuel_lph"
                    mode="decimal"
                />
              </div>

              <div class="field">
                <label>Fuel Tank Size</label>

                <InputNumber
                    v-model="form.fuel_tank_size"
                    suffix=" L"
                />
              </div>

              <div class="field">
                <label>Fuel Price</label>

                <InputNumber
                    v-model="form.fuel_price"
                    mode="currency"
                    currency="THB"
                    locale="th-TH"
                />
              </div>

              <div class="field checkbox-field">

                <Checkbox
                    v-model="form.fuel_mont"
                    binary
                    input-id="fuelmont"
                />

                <label for="fuelmont">
                  Fuel Monitor
                </label>

              </div>

              <div class="field full">
                <label>Remark</label>

                <Textarea
                    v-model="form.remark"
                    rows="3"
                />
              </div>

              <div class="actions full">

                <Button
                    label="Save Vehicle"
                    icon="pi pi-save"
                    :loading="saving"
                    @click="saveVehicle"
                />

              </div>

            </div>

          </template>

        </Card>

        <Card>

          <template #title>
            Mileage
          </template>

          <template #content>

            <div class="inline-form">

              <div class="field">
                <label>Current Mileage</label>

                <InputNumber
                    v-model="form.current_mileage"
                    suffix=" km"
                />
              </div>

              <Button
                  label="Save Mileage"
                  icon="pi pi-save"
                  :disabled="!form.imei"
                  @click="saveMileage"
              />

            </div>

          </template>

        </Card>

      </div>

    </div>

  </div>
</template>

<style scoped>
.vehicle-page {
  width: 100%;
  min-height: 100%;
  padding: 1rem;
  color: #e5e7eb;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.page-header h2 {
  margin: 0;
}

.page-header p {
  margin-top: 0.25rem;
  opacity: 0.7;
}

.card {
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
  padding: 1rem;
}

.toolbar {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.separator {
  width: 1px;
  height: 30px;
  background: var(--surface-border);
}

.search-input {
  width: 300px;
}

.group-dropdown,
.group-input {
  width: 220px;
}

.selected-label {
  opacity: 0.7;
}

.main-layout {
  display: grid;
  grid-template-columns: 35% 65%;
  gap: 1rem;
}

.vehicle-list {
  min-height: calc(100vh - 280px);
}

.vehicle-row {
  cursor: pointer;
  padding: 0.35rem 0;
}

.vehicle-row.active {
  color: var(--primary-color);
  font-weight: 600;
}

.plate {
  font-weight: 600;
}

.imei,
.group {
  font-size: 0.8rem;
  opacity: 0.7;
}

.edit-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.empty-state {
  padding: 2rem;
  text-align: center;
  opacity: 0.65;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field label {
  font-size: 0.85rem;
  opacity: 0.8;
}

.checkbox-field {
  flex-direction: row;
  align-items: center;
  padding-top: 1.6rem;
}

.full {
  grid-column: 1 / -1;
}

.section-title {
  grid-column: 1 / -1;
  border-top: 1px solid var(--surface-border);
  padding-top: 1rem;
  margin-top: 0.5rem;
  font-weight: 700;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.inline-form {
  display: flex;
  align-items: end;
  gap: 1rem;
}

.inline-form .field {
  width: 260px;
}

@media (max-width: 1100px) {

  .main-layout {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .search-input,
  .group-dropdown,
  .group-input {
    width: 100%;
  }
}

.vehicle-page {
  color: #e5e7eb;
}

:deep(.p-card) {
  background: #0f172a;
  color: #e5e7eb;
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

:deep(.p-card-title) {
  color: #e5e7eb;
}

:deep(.p-card-content) {
  color: #cbd5e1;
}

:deep(.p-datatable),
:deep(.p-datatable-wrapper),
:deep(.p-datatable-table),
:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr),
:deep(.p-datatable-tbody > tr > td) {
  background: #0f172a;
  color: #e5e7eb;
  border-color: rgba(255, 255, 255, 0.1);
}

:deep(.p-datatable-tbody > tr:hover) {
  background: #1e293b;
}

:deep(.p-inputtext),
:deep(.p-inputnumber-input),
:deep(.p-dropdown),
:deep(.p-textarea) {
  background: #111827;
  color: #e5e7eb;
  border-color: rgba(255, 255, 255, 0.18);
}

:deep(.p-inputtext::placeholder) {
  color: #94a3b8;
}

:deep(.p-dropdown-label),
:deep(.p-dropdown-trigger) {
  color: #e5e7eb;
}

:deep(.p-checkbox-box) {
  background: #111827;
  border-color: rgba(255, 255, 255, 0.25);
}

.vehicle-row.active {
  color: #34d399;
}

.empty-state {
  color: #94a3b8;
}

.vehicle-row-button {
  width: 100%;
  display: block;
  text-align: left;
  cursor: pointer;
  border: 0;
  background: transparent;
  color: #e5e7eb;
  font-weight: 600;
  padding: 0.45rem 0;
}

.vehicle-row-button:hover {
  color: #34d399;
}

.vehicle-row-button.active {
  color: #34d399;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  color: #e5e7eb;
  font-weight: 700;
}

.vehicle-count {
  margin-left: 0.4rem;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: #334155;
  color: #e5e7eb;
  font-size: 0.8rem;
}

.list-subtitle {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 400;
}

</style>