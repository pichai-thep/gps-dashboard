<script setup lang="ts">
import {onMounted, ref, reactive, nextTick} from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import Dropdown from 'primevue/dropdown'

import {useConfirm} from 'primevue/useconfirm'
import {useToast} from 'primevue/usetoast'

import Map from 'ol/Map'
import View from 'ol/View'
import TileLayer from 'ol/layer/Tile'
import VectorLayer from 'ol/layer/Vector'
import OSM from 'ol/source/OSM'
import VectorSource from 'ol/source/Vector'
import Feature from 'ol/Feature'
import Point from 'ol/geom/Point'
import {fromLonLat, toLonLat} from 'ol/proj'
import {Style, Fill, Stroke, Circle as CircleStyle} from 'ol/style'

import {
  getPois,
  createPoi,
  updatePoi,
  deletePoi,
  type Poi,
  type PoiPayload,
} from '@/services/poi'

const confirm = useConfirm()
const toast = useToast()

const pois = ref<Poi[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const mapEl = ref<HTMLDivElement | null>(null)
const poiIconOptions = [
  {label: 'Gas Station', value: 'gas', icon: 'pi pi-bolt'},
  {label: 'Warehouse', value: 'warehouse', icon: 'pi pi-building'},
  {label: 'Office', value: 'office', icon: 'pi pi-briefcase'},
  {label: 'Home', value: 'home', icon: 'pi pi-home'},
  {label: 'Customer', value: 'customer', icon: 'pi pi-user'},
  {label: 'Factory', value: 'factory', icon: 'pi pi-box'},
  {label: 'Parking', value: 'parking', icon: 'pi pi-car'},
  {label: 'Restaurant', value: 'restaurant', icon: 'pi pi-shop'},
  {label: 'Hospital', value: 'hospital', icon: 'pi pi-heart-fill'},
]

let map: Map | null = null

const previewSource = new VectorSource()

const previewLayer = new VectorLayer({
  source: previewSource,
  style: new Style({
    image: new CircleStyle({
      radius: 8,
      fill: new Fill({color: '#10b981'}),
      stroke: new Stroke({color: '#ffffff', width: 2}),
    }),
  }),
})

const form = reactive<{
  poi_name: string
  icon: string | null
  lat: number | null
  lng: number | null
}>({
  poi_name: '',
  icon: null,
  lat: null,
  lng: null,
})

onMounted(async () => {
  await loadPois()
})

async function loadPois() {
  loading.value = true
  try {
    pois.value = await getPois()
  } finally {
    loading.value = false
  }
}

async function openCreate() {
  editingId.value = null
  resetForm()
  dialogVisible.value = true

  await nextTick()
  initMap()
  renderPreview()
}

async function openEdit(row: Poi) {
  editingId.value = row.poi_id
  form.poi_name = row.poi_name
  form.icon = row.icon || null
  form.lat = row.lat ? Number(row.lat) : null
  form.lng = row.lng ? Number(row.lng) : null

  dialogVisible.value = true

  await nextTick()
  initMap()
  renderPreview()

  if (form.lng && form.lat) {
    map?.getView().animate({
      center: fromLonLat([form.lng, form.lat]),
      zoom: 16,
    })
  }
}

function resetForm() {
  form.poi_name = ''
  form.icon = null
  form.lat = null
  form.lng = null
  previewSource.clear()
}

function initMap() {
  if (!mapEl.value) return

  if (!map) {
    map = new Map({
      target: mapEl.value,
      layers: [
        new TileLayer({
          source: new OSM(),
        }),
        previewLayer,
      ],
      view: new View({
        center: fromLonLat([100.5018, 13.7563]),
        zoom: 12,
      }),
    })

    map.on('click', (event) => {
      const [lng, lat] = toLonLat(event.coordinate)

      form.lng = Number(lng.toFixed(7))
      form.lat = Number(lat.toFixed(7))

      renderPreview()
    })
  } else {
    map.setTarget(mapEl.value)
    setTimeout(() => map?.updateSize(), 100)
  }
}

function renderPreview() {
  previewSource.clear()

  if (!form.lng || !form.lat) return

  previewSource.addFeature(
      new Feature(new Point(fromLonLat([form.lng, form.lat]))),
  )
}

function clearPoint() {
  form.lat = null
  form.lng = null
  previewSource.clear()
}

async function savePoi() {
  if (!form.poi_name.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณากรอกชื่อ POI',
      life: 2500,
    })
    return
  }

  if (!form.lat || !form.lng) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณาคลิกเลือกตำแหน่งบนแผนที่',
      life: 2500,
    })
    return
  }

  const payload: PoiPayload = {
    poi_name: form.poi_name,
    icon: form.icon,
    lat: form.lat,
    lng: form.lng,
  }

  saving.value = true

  try {
    if (editingId.value) {
      await updatePoi(editingId.value, payload)
    } else {
      await createPoi(payload)
    }

    toast.add({
      severity: 'success',
      summary: 'บันทึก POI สำเร็จ',
      life: 2500,
    })

    dialogVisible.value = false
    await loadPois()
  } finally {
    saving.value = false
  }
}

function confirmDelete(row: Poi) {
  confirm.require({
    message: `ต้องการลบ "${row.poi_name}" ใช่หรือไม่?`,
    header: 'ยืนยันการลบ',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'ลบ',
    rejectLabel: 'ยกเลิก',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deletePoi(row.poi_id)

      toast.add({
        severity: 'success',
        summary: 'ลบ POI แล้ว',
        life: 2500,
      })

      await loadPois()
    },
  })
}

function findPoiIcon(value?: string | null) {
  return (
      poiIconOptions.find((x) => x.value === value)?.icon
      || 'pi pi-map-marker'
  )
}

function findPoiLabel(value?: string | null) {
  return (
      poiIconOptions.find((x) => x.value === value)?.label
      || value
  )
}

</script>

<template>
  <div class="station-page">
    <ConfirmDialog/>

    <div class="fleet-page-header">
      <div class="title-section">
        <!--        <div class="page-icon">-->
        <!--          <i class="pi pi-map"></i>-->
        <!--        </div>-->

        <div>
          <h1 class="page-title">POI Management</h1>
          <div class="page-subtitle">
            จัดการจุดสนใจบนแผนที่
          </div>
        </div>
      </div>

      <div class="header-actions">
        <Button
            label="Add POI"
            icon="pi pi-plus"
            @click="openCreate"
        />
      </div>
    </div>

    <DataTable
        :value="pois"
        :loading="loading"
        dataKey="poi_id"
        paginator
        :rows="20"
        stripedRows
        class="station-table p-datatable-sm"
    >
      <Column field="poi_name" header="POI Name"/>

      <Column header="Icon">
        <template #body="{ data }">
          <div class="poi-icon-cell">
            <i :class="findPoiIcon(data.icon)"></i>

            <span>
        {{ findPoiLabel(data.icon) }}
      </span>
          </div>
        </template>
      </Column>

      <Column header="Location">
        <template #body="{ data }">
          <span v-if="data.lat && data.lng">
            {{ Number(data.lat).toFixed(6) }},
            {{ Number(data.lng).toFixed(6) }}
          </span>
          <span v-else>-</span>
        </template>
      </Column>

      <Column header="Actions" style="width: 160px">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button
                icon="pi pi-pencil"
                text
                rounded
                @click="openEdit(data)"
            />
            <Button
                icon="pi pi-trash"
                text
                rounded
                severity="danger"
                @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
        v-model:visible="dialogVisible"
        modal
        :header="editingId ? 'Edit POI' : 'Add POI'"
        class="station-dialog"
        :style="{ width: '900px', maxWidth: '96vw' }"
    >
      <div class="form-grid">
        <div class="form-panel">
          <label>POI Name</label>
          <InputText
              v-model="form.poi_name"
              placeholder="ชื่อ POI"
              class="w-full"
          />

          <label>POI Icon</label>

          <Dropdown
              v-model="form.icon"
              :options="poiIconOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="เลือก Icon"
              class="w-full"
          >
            <template #value="slotProps">
              <div
                  v-if="slotProps.value"
                  class="poi-icon-option"
              >
                <i :class="findPoiIcon(slotProps.value)"></i>

                <span>
        {{ findPoiLabel(slotProps.value) }}
      </span>
              </div>

              <span v-else>
      เลือก Icon
    </span>
            </template>

            <template #option="slotProps">
              <div class="poi-icon-option">
                <i :class="slotProps.option.icon"></i>

                <span>
        {{ slotProps.option.label }}
      </span>
              </div>
            </template>
          </Dropdown>

          <div class="hint">
            คลิกบนแผนที่เพื่อเลือกตำแหน่ง POI
          </div>

          <div class="coords">
            <div>Lat: {{ form.lat || '-' }}</div>
            <div>Lng: {{ form.lng || '-' }}</div>
          </div>

          <div class="tool-buttons">
            <Button
                label="ล้างตำแหน่ง"
                icon="pi pi-times"
                severity="danger"
                outlined
                @click="clearPoint"
            />
          </div>
        </div>

        <div class="map-panel">
          <div ref="mapEl" class="station-map"></div>
        </div>
      </div>

      <template #footer>
        <Button
            label="Cancel"
            severity="secondary"
            outlined
            @click="dialogVisible = false"
        />
        <Button
            label="Save"
            icon="pi pi-save"
            :loading="saving"
            @click="savePoi"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.station-page {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* =========================
   PAGE HEADER
========================= */

.fleet-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 14px;
}

.page-icon {
  background: rgba(59, 130, 246, 0.18);
  color: #60a5fa;
}

.page-title {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  color: var(--text-color) !important;
}

.page-subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: var(--text-color-secondary) !important;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* =========================
   TABLE
========================= */

.station-table {
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
}

:deep(.station-table .p-datatable-header) {
  background: var(--surface-card);
  border: none;
}

:deep(.station-table .p-datatable-thead > tr > th) {
  background: var(--surface-100);
  color: var(--text-color);
  font-size: 13px;
  font-weight: 600;
  border-color: var(--surface-border);
}

:deep(.station-table .p-datatable-tbody > tr) {
  background: var(--surface-card);
  transition: background 0.15s ease;
}

:deep(.station-table .p-datatable-tbody > tr:hover) {
  background: var(--surface-100);
}

:deep(.station-table .p-datatable-tbody > tr > td) {
  border-color: var(--surface-border);
  color: var(--text-color);
}

/* =========================
   BADGES
========================= */

.type-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 82px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.type-badge.circle {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}

.type-badge.polygon {
  background: rgba(249, 115, 22, 0.15);
  color: #fb923c;
}

/* =========================
   ACTIONS
========================= */

.action-buttons {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* =========================
   DIALOG
========================= */

:deep(.station-dialog .p-dialog-content) {
  background: var(--surface-card);
}

.form-grid {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 18px;
}

.form-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.form-panel label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-color);
}

.hint {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-100);
  color: var(--text-color-secondary);
  font-size: 13px;
  line-height: 1.5;
  border: 1px solid var(--surface-border);
}

.coords {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-50);
  border: 1px solid var(--surface-border);
  color: var(--text-color);
  font-size: 13px;
  line-height: 1.5;
}

.tool-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}

/* =========================
   MAP
========================= */

.map-panel {
  min-height: 540px;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  background: #0f172a;
}

.station-map {
  width: 100%;
  height: 540px;
}

/* =========================
   FORM
========================= */

.w-full {
  width: 100%;
}

/* =========================
   DARK MODE
========================= */

:global(.app-dark) .page-icon {
  background: linear-gradient(
      135deg,
      rgba(59, 130, 246, 0.2),
      rgba(30, 41, 59, 0.8)
  );
}

:global(.app-dark) .station-table {
  background: #111827;
}

:global(.app-dark) .map-panel {
  border-color: #334155;
}


:deep(.p-datatable) {
  background: transparent;
  color: var(--text-color);
}

:deep(.p-datatable-table) {
  background: var(--surface-card);
}

:deep(.p-datatable-thead > tr > th) {
  background: var(--surface-card) !important;
  color: var(--text-color) !important;
  border-color: var(--surface-border) !important;
}

:deep(.p-datatable-tbody > tr) {
  background: var(--surface-card) !important;
  color: var(--text-color) !important;
}

:deep(.p-datatable-tbody > tr > td) {
  color: var(--text-color) !important;
  border-color: var(--surface-border) !important;
}

:deep(.p-datatable-tbody > tr:hover) {
  background: var(--surface-hover) !important;
}

:deep(.p-paginator) {
  background: var(--surface-card) !important;
  color: var(--text-color) !important;
  border-color: var(--surface-border) !important;
  border-radius: 0 0 18px 18px;
}

:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-first),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next),
:deep(.p-paginator .p-paginator-last) {
  color: var(--text-color-secondary) !important;
}

:deep(.p-paginator .p-paginator-page.p-highlight) {
  background: rgba(16, 185, 129, 0.16) !important;
  color: #34d399 !important;
}

.station-page {
  color: #e5e7eb;
}

.station-page :deep(*) {
  color-scheme: dark;
}

.page-title,
.page-subtitle,
:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr > td) {
  color: #e5e7eb !important;
}

.page-subtitle {
  color: #94a3b8 !important;
}

.station-table {
  background: #020617 !important;
  border: 1px solid #1e293b !important;
}

:deep(.p-datatable),
:deep(.p-datatable-table),
:deep(.p-datatable-wrapper) {
  background: #020617 !important;
}

:deep(.p-datatable-thead > tr > th) {
  background: #020617 !important;
  border-bottom: 1px solid #1e293b !important;
}

:deep(.p-datatable-tbody > tr) {
  background: #020617 !important;
}

:deep(.p-datatable-tbody > tr:hover) {
  background: #0f172a !important;
}

:deep(.p-datatable-tbody > tr > td) {
  border-bottom: 1px solid #1e293b !important;
}

:deep(.p-paginator) {
  background: #020617 !important;
  border: none !important;
  border-top: 1px solid #1e293b !important;
  color: #94a3b8 !important;
}

:deep(.p-paginator button) {
  color: #94a3b8 !important;
}

:deep(.p-paginator .p-paginator-page.p-highlight),
:deep(.p-paginator .p-paginator-page.p-paginator-page-selected) {
  background: #d1fae5 !important;
  color: #064e3b !important;
}

.page-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: rgba(59, 130, 246, 0.18) !important;
  color: #60a5fa !important;
}

.page-icon i {
  color: #60a5fa !important;
  font-size: 22px;
}

.poi-icon-option,
.poi-icon-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.poi-icon-option i,
.poi-icon-cell i {
  font-size: 16px;
  color: #60a5fa;
}

/* =========================
   MOBILE
========================= */

@media (max-width: 960px) {
  .fleet-page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-end;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .map-panel,
  .station-map {
    min-height: 420px;
    height: 420px;
  }
}
</style>