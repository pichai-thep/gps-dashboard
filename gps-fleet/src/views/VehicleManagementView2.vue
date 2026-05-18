<template>
  <div class="vehicle-page">

    <ConfirmPopup />

    <div class="page-header">

      <div>
        <h2>Vehicle Management</h2>
        <p>Manage vehicle information and vehicle groups</p>
      </div>

      <Button
          label="Refresh"
          icon="pi pi-refresh"
          outlined
          size="small"
          @click="loadVehicles"
      />

    </div>

    <div class="toolbar card compact-toolbar">

      <Dropdown
          v-model="selectedGroup"
          :options="groups"
          option-label="customer_group_name"
          option-value="customer_group_id"
          placeholder="Group"
          show-clear
          class="group-dropdown"
          @change="loadVehicles"
      />
      <Button
          label="Move"
          icon="pi pi-arrow-right"
          size="small"
          :disabled="
          selectedRows.length === 0 ||
          !selectedGroup
        "
          @click="moveGroup"
      />

      <Button
          label="Delete"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          :disabled="!selectedGroup"
          @click="confirmDeleteGroup($event)"
      />

      <button
          type="button"
          class="toolbar-link"
          @click="showCreateGroupPanel = !showCreateGroupPanel"
      >
        <i class="pi pi-plus" />

        {{
          showCreateGroupPanel
              ? 'Hide Create Group'
              : 'Create Group'
        }}
      </button>

      <span class="selected-label">
        Selected:
        {{ selectedRows.length }}
      </span>

    </div>

    <div
        v-if="showCreateGroupPanel"
        class="toolbar card sub-toolbar"
    >

      <InputText
          v-model="newGroupName"
          placeholder="New Group"
          class="group-input"
          @keyup.enter="createGroup"
      />

      <Button
          label="Create"
          icon="pi pi-plus"
          severity="success"
          size="small"
          @click="createGroup"
      />

    </div>

    <div class="main-layout">

      <div class="vehicle-list card">

        <div class="list-header">

          <div class="list-title">

            <div>
              Vehicle List

              <span class="vehicle-count">
        {{ vehicles.length }}
      </span>
            </div>

            <div class="list-subtitle">
              {{
                selectedGroup
                    ? 'Filtered by group'
                    : 'All vehicles'
              }}
            </div>

          </div>

          <div class="list-search">

            <InputText
                v-model="keyword"
                placeholder="Search IMEI / Plate / Driver"
                class="table-search-input"
                @keyup.enter="loadVehicles"
            />

            <Button
                icon="pi pi-search"
                size="small"
                @click="loadVehicles"
            />

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
                  :class="{
                  active:
                    selectedVehicle?.imei === data.imei
                }"
                  @click.stop.prevent="
                  selectVehicle(data)
                "
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

                <InputText
                    v-model="form.plate_no"
                />
              </div>

              <div class="field">
                <label>Sequence No</label>

                <InputNumber
                    v-model="form.sequen_no"
                />
              </div>

              <div class="field">
                <label>Driver License</label>

                <InputText
                    v-model="form.driver_id"
                />
              </div>

              <div class="field">
                <label>Driver Name</label>

                <InputText
                    v-model="form.driver_name"
                />
              </div>

              <div class="field">
                <label>Driver Phone</label>

                <InputText
                    v-model="form.driver_phone"
                />
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

      </div>

    </div>

  </div>
</template>

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
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

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
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)

const keyword = ref('')

const selectedGroup = ref<number | null>(null)

const showCreateGroupPanel = ref(false)

const newGroupName = ref('')

const vehicles = ref<VehicleListItem[]>([])
const groups = ref<VehicleGroup[]>([])

const selectedRows = ref<VehicleListItem[]>([])
const selectedVehicle = ref<VehicleListItem | null>(null)


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
      group_id: selectedGroup.value,
    })
  } finally {
    loading.value = false
  }
}

async function selectVehicle(row: VehicleListItem) {
  selectedVehicle.value = row

  Object.assign(form, {
    imei: row.imei,
    plate_no: row.plate_no || '',
    sequen_no: row.sequen_no ?? null,
  })

  try {
    const data = await getVehicle(row.imei)

    Object.assign(form, data, {
      input_fuel_reverse: boolValue(data.input_fuel_reverse),
      fuel_mont: boolValue(data.fuel_mont),
    })
  } catch (error) {
    console.error(error)
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
      Number(form.current_mileage),
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
  showCreateGroupPanel.value = false

  await loadGroups()

  toast.add({
    severity: 'success',
    summary: 'Created',
    detail: 'Group created',
    life: 2500,
  })
}

function confirmDeleteGroup(event: Event) {
  if (!selectedGroup.value) return

  confirm.require({
    target: event.currentTarget as HTMLElement,

    message:
        'Delete this vehicle group ?',

    header: 'Confirm Delete',

    icon: 'pi pi-exclamation-triangle',

    rejectLabel: 'Cancel',
    acceptLabel: 'Delete',

    acceptClass: 'p-button-danger',

    accept: async () => {
      await removeGroup()
    },
  })
}

async function removeGroup() {
  if (!selectedGroup.value) return

  await deleteVehicleGroup(selectedGroup.value)

  selectedGroup.value = null

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
  if (!selectedGroup.value) return
  if (selectedRows.value.length === 0) return

  await moveVehiclesToGroup(
      selectedRows.value.map(v => v.imei),
      selectedGroup.value,
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
  border: 1px solid rgba(255,255,255,0.12);
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

.compact-toolbar {
  padding: 0.75rem;
  gap: 0.5rem;
}

.sub-toolbar {
  padding: 0.75rem;
}

.search-input {
  width: 320px;
}

.group-dropdown,
.group-input {
  width: 220px;
}

.selected-label {
  opacity: 0.7;
  font-size: 0.85rem;
}

.main-layout {
  display: grid;
  grid-template-columns: 35% 65%;
  gap: 1rem;
}

.vehicle-list {
  min-height: calc(100vh - 250px);
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

.vehicle-row-button {
  width: 100%;
  display: block;
  text-align: left;
  cursor: pointer;
  border: 0;
  background: transparent;
  color: #e5e7eb;
  font-size: 16px;
  padding: 0.45rem 0;
}

.vehicle-row-button:hover {
  color: #34d399;
}

.vehicle-row-button.active {
  color: #34d399;
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
  color: #94a3b8;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2,minmax(0,1fr));
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
  padding-top: 1rem;
  margin-top: 0.5rem;
  font-weight: 700;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.toolbar-link {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: transparent;
  border: 0;
  color: #34d399;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.35rem 0.2rem;
}

.toolbar-link:hover {
  color: #6ee7b7;
  text-decoration: underline;
}

.toolbar-link .pi {
  font-size: 0.85rem;
}

:deep(.p-card) {
  background: #0f172a;
  color: #e5e7eb;
  border: 1px solid rgba(255,255,255,0.12);
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
  border-color: rgba(255,255,255,0.1);
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
  border-color: rgba(255,255,255,0.18);
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.list-title {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.list-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.table-search-input {
  width: 320px;
}

@media (max-width: 900px) {

  .list-header {
    flex-direction: column;
    align-items: stretch;
  }

  .list-search {
    width: 100%;
  }

  .table-search-input {
    width: 100%;
  }
}

</style>
