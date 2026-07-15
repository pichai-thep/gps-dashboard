<template>
  <ConfirmPopup/>
  <div class="vehicle-page">

<!--        <div class="page-header">-->
<!--          <div>-->
<!--            <h2>Vehicle Management</h2>-->
<!--            <p>Manage vehicle information and vehicle groups</p>-->
<!--          </div>-->

<!--          <Button-->
<!--              label="Refresh"-->
<!--              icon="pi pi-refresh"-->
<!--              outlined-->
<!--              size="small"-->
<!--              @click="loadVehicles"-->
<!--          />-->
<!--        </div>-->

    <div class="toolbar card compact-toolbar">
      <Dropdown
          v-model="targetGroup"
          :options="groups"
          option-label="customer_group_name"
          option-value="customer_group_id"
          :placeholder="t('changeMoveToGroup')"
          show-clear
          class="group-dropdown"
      />

      <Button
          :label="t('moveToGroup')"
          icon="pi pi-arrow-right"
          size="small"
          :disabled="selectedRows.length === 0 || !targetGroup"
          @click="moveGroup"
      />

      <Button v-if="targetGroup"
              :label="t('deleteGroup')"
              icon="pi pi-trash"
              severity="danger"
              outlined
              size="small"
              :disabled="!targetGroup"
              @click="confirmDeleteGroup($event)"
      />

      <button
          type="button"
          class="toolbar-link"
          @click="showCreateGroupPanel = !showCreateGroupPanel"
      >
        <i class="pi pi-plus"/>
        {{ t('createGroup') }}
      </button>

      <span class="selected-label">
        {{ t('selected') }}: {{ selectedRows.length }}
      </span>

    </div>

    <div
        v-if="showCreateGroupPanel"
        class="toolbar card sub-toolbar"
    >
      <InputText
          v-model="newGroupName"
          :placeholder="t('newGroup')"
          class="group-input"
          @keyup.enter="createGroup"
      />

      <Button
          :label="t('create')"
          icon="pi pi-plus"
          severity="success"
          size="small"
          @click="createGroup"
      />
    </div>

    <div class="main-layout">
      <div class="vehicle-list card">
        <div class="list-header">

          <!--          <div class="list-title">-->
          <!--            <div>-->
          <!--              Vehicle List-->
          <!--              <span class="vehicle-count">{{ vehicles.length }}</span>-->
          <!--            </div>-->

          <!--            <div class="list-subtitle">-->
          <!--              {{ filterGroup ? 'Filtered by group' : 'All vehicles' }}-->
          <!--            </div>-->
          <!--          </div>-->

          <div class="list-search">

            <Dropdown
                v-model="filterGroup"
                :options="groups"
                option-label="customer_group_name"
                option-value="customer_group_id"
                :placeholder="t('filterGroup')"
                show-clear
                class="table-group-filter"
                @change="loadVehicles"
            />

            <InputText
                v-model="keyword"
                :placeholder="t('searchVehicleManagement')"
                class="table-search-input"
                @keyup.enter="loadVehicles"
            />

            <Button
                icon="pi pi-search"
                size="small"
                @click="loadVehicles"
            />

            <Button
                v-if="filterGroup"
                :label="t('removeFromGroup')"
                icon="pi pi-times"
                severity="danger"
                outlined
                size="small"
                :disabled="selectedRows.length === 0 || !filterGroup"
                @click="confirmRemoveFromGroup"
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

          <Column :header="t('vehicle')">
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
            <div class="vehicle-form-header">
              <div>
                <div class="vehicle-plate">
                  {{ form.plate_no || '-' }}
                </div>

                <div
                    v-if="form.imei"
                    class="vehicle-imei"
                >
                  IMEI: {{ form.imei }}
                </div>
              </div>
            </div>
          </template>

          <template #content>
            <div
                v-if="!form.imei"
                class="empty-state"
            >
              {{ t('selectVehicleFromList') }}
            </div>

            <div
                v-else
                class="custom-tabs"
            >
              <div class="custom-tab-nav">
                <button
                    type="button"
                    :class="{ active: activeTab === 'info' }"
                    @click="activeTab = 'info'"
                >
                  {{ t('vehicleInfo') }}
                </button>

                <button
                    type="button"
                    :class="{ active: activeTab === 'icon' }"
                    @click="activeTab = 'icon'"
                >
                  {{ t('vehicleIcon') }}
                </button>

                <button
                    type="button"
                    :class="{ active: activeTab === 'fuel' }"
                    @click="activeTab = 'fuel'"
                >
                  {{ t('fuelSetting') }}
                </button>

                <button
                    type="button"
                    :class="{ active: activeTab === 'mileage' }"
                    @click="activeTab = 'mileage'"
                >
                  {{ t('mileage') }}
                </button>

                <button
                    type="button"
                    :class="{ active: activeTab === 'config' }"
                    @click="activeTab = 'config'"
                >
                  {{ t('vehicleConfig') }}
                </button>
              </div>

              <div class="custom-tab-panel">
                <div
                    v-if="activeTab === 'info'"
                    class="form-grid"
                >
                  <div class="field">
                    <label>IMEI</label>
                    <InputText v-model="form.imei" readonly/>
                  </div>

                  <div class="field">
                    <label>{{ t('plate') }}</label>
                    <InputText v-model="form.plate_no"/>
                  </div>

                  <div class="field">
                    <label>{{ t('driverName') }}</label>
                    <InputText v-model="form.driver_name"/>
                  </div>

                  <div class="field">
                    <label>{{ t('driverPhone') }}</label>
                    <InputText v-model="form.driver_phone"/>
                  </div>

                  <div class="field full">
                    <label>{{ t('remark') }}</label>
                    <Textarea
                        v-model="form.remark"
                        rows="4"
                    />
                  </div>

                  <div class="actions full">
                    <Button
                        :label="t('saveInfo')"
                        icon="pi pi-save"
                        :loading="saving"
                        @click="saveVehicle"
                    />
                  </div>
                </div>

                <div v-else-if="activeTab === 'icon'">
                  <div class="icon-picker-grid">
                    <button
                        v-for="item in vehicleIcons"
                        :key="item.value"
                        type="button"
                        class="icon-choice"
                        :class="{ active: form.icon_path === item.value }"
                        @click="form.icon_path = item.value"
                    >
                      <img
                          :src="item.image"
                          :alt="item.label"
                      />
                    </button>
                  </div>

                  <div class="actions full icon-actions">
                    <Button
                        :label="t('saveIcon')"
                        icon="pi pi-save"
                        :loading="saving"
                        @click="saveVehicle"
                    />
                  </div>
                </div>

                <div
                    v-else-if="activeTab === 'fuel'"
                    class="form-grid"
                >
                  <div class="field">
                    <label>{{ t('kmPerLitre') }}</label>
                    <InputNumber
                        v-model="form.fuel_kmpl"
                        mode="decimal"
                    />
                  </div>

                  <div class="field">
                    <label>{{ t('litrePerHour') }}</label>
                    <InputNumber
                        v-model="form.fuel_lph"
                        mode="decimal"
                    />
                  </div>

                  <div class="field">
                    <label>{{ t('fuelTankSize') }}</label>
                    <InputNumber
                        v-model="form.fuel_tank_size"
                        suffix=" L"
                    />
                  </div>

                  <div class="field">
                    <label>{{ t('fuelPrice') }}</label>
                    <InputNumber
                        v-model="form.fuel_price"
                        mode="currency"
                        currency="THB"
                        locale="th-TH"
                    />
                  </div>

                  <div class="actions full">
                    <Button
                        :label="t('saveFuelSetting')"
                        icon="pi pi-save"
                        :loading="saving"
                        @click="saveVehicle"
                    />
                  </div>
                </div>

                <div
                    v-else-if="activeTab === 'mileage'"
                    class="form-grid"
                >
                  <div class="field">
                    <label>{{ t('currentMileage') }}</label>
                    <InputNumber
                        v-model="form.current_mileage"
                        suffix=" km"
                    />
                  </div>

                  <div class="actions full">
                    <Button
                        :label="t('saveMileage')"
                        icon="pi pi-save"
                        severity="success"
                        @click="saveMileage"
                    />
                  </div>
                </div>

                <div
                    v-else-if="activeTab === 'config'"
                    class="form-grid"
                >
                  <div class="field">
                    <label>{{ t('speedLimit') }}</label>
                    <InputNumber
                        v-model="form.speed_limited"
                        suffix=" km/h"
                    />
                  </div>

                  <div class="field">
                    <label>{{ t('urRateType') }}</label>

                    <Dropdown
                        v-model="form.ur_rate_type"
                        :options="urRateOptions"
                        option-label="label"
                        option-value="value"
                        :placeholder="t('selectUrRateType')"
                    />
                  </div>

                  <div class="field">
                    <label>{{ t('targetDistanceKm') }}</label>
                    <InputNumber
                        v-model="form.ur_rate_target_km"
                        :min="0"
                        :max="1000"
                        suffix=" km"
                    />
                  </div>

                  <div class="field checkbox-field">
                    <Checkbox
                        v-model="form.ur_rate_satsun"
                        binary
                        input-id="ur-rate-satsun"
                    />

                    <label for="ur-rate-satsun">
                      {{ t('includeWeekend') }}
                    </label>
                  </div>

                  <div class="field">
                    <label>{{ t('workingHourDay') }}</label>

                    <InputNumber
                        v-model="form.ur_rate_work_hour"
                        :min="0"
                        :max="24"
                        suffix=" hr"
                    />
                  </div>



                  <div class="field checkbox-field">
                    <Checkbox
                        v-model="form.export_to_active"
                        binary
                        input-id="export-to-active"
                    />

                    <label for="export-to-active">
                      {{ t('synchExportExternalApi') }}
                    </label>
                  </div>

                  <div class="actions full">
                    <Button
                        :label="t('saveConfig')"
                        icon="pi pi-save"
                        :loading="saving"
                        @click="saveVehicleConfig"
                    />
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import {computed, onMounted, reactive, ref} from 'vue'
import {useToast} from 'primevue/usetoast'
import {useConfirm} from 'primevue/useconfirm'

import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmPopup from 'primevue/confirmpopup'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useI18n } from '@/i18n'

import {
  createVehicleGroup,
  deleteVehicleGroup,
  getVehicle,
  getVehicleGroups,
  getVehicles,
  moveVehiclesToGroup, removeVehiclesFromGroup,
  updateMileage,
  updateVehicle,
  type VehicleDetail,
  type VehicleGroup,
  type VehicleListItem,
} from '@/services/vehicleManagement'

type ActiveTab = 'info' | 'icon' | 'fuel' | 'mileage' | 'config'

const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)

const keyword = ref('')
const filterGroup = ref<number | null>(null)
const targetGroup = ref<number | null>(null)
const showCreateGroupPanel = ref(false)
const newGroupName = ref('')
const activeTab = ref<ActiveTab>('info')

const urRateOptions = computed(() => [
  { label: t('none'), value: '' },
  { label: t('timeBase'), value: 'A' },
  { label: t('engineBase'), value: 'B' },
  { label: t('distanceBase'), value: 'C' },
])

const vehicles = ref<VehicleListItem[]>([])
const groups = ref<VehicleGroup[]>([])
const selectedRows = ref<VehicleListItem[]>([])
const selectedVehicle = ref<VehicleListItem | null>(null)

const vehicleIcons = [
  // 'default',
  'sedan',
  'van',
  'truck1',
  'truck2',
  'truck3',
  'truck_danger',
  'bus',
  'trailer',
  'backhole',
  'water_spray',
  'water_truck',
  'cement',
  'crane',
  'crane_truck',
  'crawler_tractor',
  'dump',
  'excavator',
  'grader',

  'boat',
].map((name) => ({
  label: name.replace(/_/g, ' '),
  value: name,
  image: `/cars/${name}/run.png`,
}))

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

  ur_rate_type: 'A',
  ur_rate_satsun: false,
  ur_rate_work_hour: 8,
  ur_rate_target_km: 100,

  export_to_active: 0
})

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
      group_id: filterGroup.value,
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
      ur_rate_satsun: boolValue(data.ur_rate_satsun),
      ur_rate_type: data.ur_rate_type || '',
      ur_rate_work_hour: data.ur_rate_work_hour ?? 8,
      export_to_active: boolValue(data.export_to_active),
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

      ur_rate_type: form.ur_rate_type,
      ur_rate_satsun: form.ur_rate_satsun ? 1 : 0,
      ur_rate_work_hour: form.ur_rate_work_hour,
      ur_rate_target_km: form.ur_rate_target_km,

      export_to_active: form.export_to_active ? 1 : 0,

    })

    toast.add({
      severity: 'success',
      summary: t('saveInfo'),
      detail: t('vehicleUpdated'),
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
    summary: t('saveMileage'),
    detail: t('mileageUpdated'),
    life: 2500,
  })
}

async function saveVehicleConfig() {
  if (!form.imei) return

  saving.value = true

  try {
    await updateVehicle(form.imei, {
      speed_limited: form.speed_limited,
      remark: form.remark,

      ur_rate_type: form.ur_rate_type,
      ur_rate_satsun: form.ur_rate_satsun ? 1 : 0,
      ur_rate_work_hour: form.ur_rate_work_hour,
      ur_rate_target_km: form.ur_rate_target_km,
      export_to_active: form.export_to_active ? 1 : 0,
    })

    toast.add({
      severity: 'success',
      summary: t('saveConfig'),
      detail: t('vehicleConfigUpdated'),
      life: 2500,
    })

    await loadVehicles()
  } finally {
    saving.value = false
  }
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
    summary: t('createGroup'),
    detail: t('groupCreated'),
    life: 2500,
  })
}

function confirmDeleteGroup(event: Event) {
  if (!targetGroup.value) return

  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: t('deleteVehicleGroupMessage'),
    header: t('confirmDelete'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('cancel'),
    acceptLabel: t('delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await removeGroup()
    },
  })
}

async function removeGroup() {
  if (!targetGroup.value) return

  await deleteVehicleGroup(targetGroup.value)

  filterGroup.value = null
  targetGroup.value = null

  await loadGroups()
  await loadVehicles()

  toast.add({
    severity: 'success',
    summary: t('deleteGroup'),
    detail: t('groupDeleted'),
    life: 2500,
  })
}

async function moveGroup() {
  if (!targetGroup.value) return
  if (selectedRows.value.length === 0) return

  await moveVehiclesToGroup(
      selectedRows.value.map(v => v.imei),
      targetGroup.value,
  )

  selectedRows.value = []

  await loadVehicles()

  toast.add({
    severity: 'success',
    summary: t('moveToGroup'),
    detail: t('vehiclesMovedToGroup'),
    life: 2500,
  })
}


function confirmRemoveFromGroup(event: Event) {
  if (!filterGroup.value) return

  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: t('removeVehicleFromGroupMessage'),
    header: t('confirmRemove'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('cancel'),
    acceptLabel: t('remove'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await removeVehiclesFromCurrentGroup()
    },
  })
}

async function removeVehiclesFromCurrentGroup() {
  if (!filterGroup.value) return
  if (selectedRows.value.length === 0) return

  await removeVehiclesFromGroup(
      filterGroup.value,
      selectedRows.value.map(v => v.imei),
  )

  selectedRows.value = []

  await loadVehicles()

  toast.add({
    severity: 'success',
    summary: t('removeFromGroup'),
    detail: t('vehiclesRemovedFromGroup'),
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
  padding: 0.4rem 0.2rem 0.2rem 0.2rem;
  color: #e5e7eb;

  box-sizing: border-box;
  overflow-x: hidden;
}

.vehicle-page *,
.vehicle-page *::before,
.vehicle-page *::after {
  box-sizing: border-box;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.page-header h2 {
  margin: 0;
  color: #f8fafc;
  font-size: 1.55rem;
  font-weight: 900;
}

.page-header p {
  margin: 0.25rem 0 0;
  color: #94a3b8;
}

.card {
  width: 100%;
  background: #0f172a;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 14px;
  padding: 1rem;
}

.toolbar {
  width: 100%;
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;

  margin-bottom: 0.75rem;
}

.compact-toolbar,
.sub-toolbar {
  padding: 0.55rem 0.75rem;
}

.group-dropdown,
.group-input {
  width: 240px;
}

.selected-label {
  color: #94a3b8;
  font-size: 0.9rem;
  font-weight: 700;
}

.main-layout {
  display: grid;

  grid-template-columns:
    minmax(360px, 35%)
    minmax(0, 1fr);

  gap: 1rem;

  width: 100%;
  align-items: start;
}

.vehicle-list,
.edit-panel {
  min-width: 0;
}

.vehicle-list {
  min-height: calc(100vh - 250px);
}

.edit-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;

  gap: 1rem;
  margin-bottom: 0.9rem;

  color: #e5e7eb;
  font-weight: 800;
}

.list-title {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.vehicle-count {
  margin-left: 0.4rem;
  padding: 0.12rem 0.48rem;

  border-radius: 999px;

  background: #334155;
  color: #e5e7eb;

  font-size: 0.8rem;
}

.list-subtitle {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}

.list-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  flex-wrap: wrap;
}

.table-group-filter {
  width: 230px;
}

.table-search-input {
  width: 220px;
}

.vehicle-row-button {
  width: 100%;

  display: block;
  text-align: left;

  cursor: pointer;

  border: 0;
  border-radius: 10px;

  background: transparent;

  color: #e5e7eb;

  font-size: 17px;
  font-weight: 800;
  line-height: 1.25;

  padding: 0.55rem 0.4rem;

  transition:
      background 0.15s ease,
      color 0.15s ease;
}

.vehicle-row-button:hover {
  color: #34d399;
  background: rgba(52, 211, 153, 0.08);
}

.vehicle-row-button.active {
  color: #34d399;
  background: rgba(52, 211, 153, 0.14);
}

.empty-state {
  padding: 2rem;

  text-align: center;
  color: #94a3b8;
}

.vehicle-form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;

  width: 100%;
}

.vehicle-plate {
  font-size: 1.25rem;
  font-weight: 900;

  color: #34d399;
  line-height: 1.1;
}

.vehicle-imei {
  margin-top: 0.25rem;

  font-size: 0.8rem;
  color: #94a3b8;

  font-family: monospace;
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
  color: #cbd5e1;
  font-size: 0.85rem;
  font-weight: 700;
}

.checkbox-field {
  flex-direction: row;
  align-items: center;

  padding-top: 1.6rem;
}

.full {
  grid-column: 1 / -1;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.custom-tabs {
  margin-top: 0.75rem;
}

.custom-tab-nav {
  display: inline-flex;

  width: auto;
  max-width: 100%;

  gap: 0.35rem;
  padding: 0.35rem;

  overflow-x: auto;

  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.22);

  background: #020617;
}

.custom-tab-nav button {
  flex: 0 0 auto;

  border: 0;
  border-radius: 10px;

  background: transparent;
  color: #94a3b8;

  padding: 0.65rem 0.9rem;

  font-size: 0.9rem;
  font-weight: 800;

  cursor: pointer;

  transition:
      background 0.15s ease,
      color 0.15s ease;
}

.custom-tab-nav button:hover {
  background: rgba(148, 163, 184, 0.12);
  color: #e5e7eb;
}

.custom-tab-nav button.active {
  background: rgba(52, 211, 153, 0.14);
  color: #34d399;

  box-shadow:
      inset 0 0 0 1px rgba(52, 211, 153, 0.5);
}

.custom-tab-panel {
  padding-top: 1rem;
  color: #e5e7eb;
}

.icon-picker-grid {
  display: grid;

  grid-template-columns:
    repeat(auto-fill, minmax(120px, 1fr));

  gap: 0.75rem;
}

.icon-choice {
  display: flex;
  flex-direction: column;
  align-items: center;

  gap: 0.55rem;

  padding: 0.85rem 0.5rem;

  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.22);

  background: #111827;
  color: #cbd5e1;

  cursor: pointer;

  transition: all 0.15s ease;
}

.icon-choice img {
  width: 56px;
  height: 56px;

  object-fit: contain;
}

.icon-choice span {
  font-size: 0.85rem;
  font-weight: 800;
}

.icon-choice:hover {
  border-color: rgba(52, 211, 153, 0.6);
  background: #0f1f1c;
}

.icon-choice.active {
  border-color: #34d399;

  background: rgba(52, 211, 153, 0.14);
  color: #34d399;

  box-shadow:
      0 0 0 2px rgba(52, 211, 153, 0.15);
}

.icon-actions {
  margin-top: 1rem;
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
  font-weight: 800;

  padding: 0.35rem 0.2rem;
}

.toolbar-link:hover {
  color: #6ee7b7;
  text-decoration: underline;
}

:deep(.p-card) {
  width: 100%;

  background: #0f172a !important;
  color: #e5e7eb !important;

  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 14px;

  box-shadow: none;
}

:deep(.p-card-body) {
  padding: 1rem;
}

:deep(.p-card-title) {
  color: #e5e7eb;
}

:deep(.p-card-content) {
  color: #cbd5e1;
}

:deep(.p-inputtext),
:deep(.p-inputnumber-input),
:deep(.p-dropdown),
:deep(.p-select),
:deep(.p-textarea),
:deep(textarea) {
  background: #111827 !important;
  color: #e5e7eb !important;

  border-color:
      rgba(148, 163, 184, 0.35) !important;
}

:deep(.p-inputtext:enabled:focus),
:deep(.p-inputnumber-input:enabled:focus),
:deep(.p-dropdown:not(.p-disabled).p-focus),
:deep(.p-select:not(.p-disabled).p-focus),
:deep(.p-textarea:enabled:focus) {
  border-color: #34d399 !important;

  box-shadow:
      0 0 0 2px rgba(52, 211, 153, 0.18) !important;
}

:deep(.p-inputtext::placeholder) {
  color: #64748b !important;
}

:deep(.p-datatable),
:deep(.p-datatable-wrapper),
:deep(.p-datatable-table),
:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr),
:deep(.p-datatable-tbody > tr > td) {
  background: #0f172a !important;
  color: #e5e7eb !important;

  border-color:
      rgba(148, 163, 184, 0.18) !important;
}

:deep(.p-datatable-thead > tr > th) {
  font-size: 0.95rem;
  font-weight: 900;
}

:deep(.p-datatable-tbody > tr:hover) {
  background: #1e293b !important;
}

:deep(.p-checkbox-box) {
  background: #f8fafc;
  border-color: #cbd5e1;
}

@media (max-width: 1200px) {
  .main-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {

  .table-group-filter,
  .table-search-input,
  .group-dropdown,
  .group-input {
    width: 100%;
  }

  .toolbar,
  .list-search {
    width: 100%;
  }

  .list-header {
    flex-direction: column;
    align-items: stretch;
  }

  .vehicle-form-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .main-layout {
    grid-template-columns: 1fr;
  }
}

</style>
