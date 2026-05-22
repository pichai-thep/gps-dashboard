<script setup lang="ts">
import { onMounted, ref, reactive, nextTick } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

import BaseMap from '@/components/maps/BaseMap.vue'
import Map from 'ol/Map'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import Draw from 'ol/interaction/Draw'
import Feature from 'ol/Feature'
import Point from 'ol/geom/Point'
import Polygon from 'ol/geom/Polygon'
import CircleGeom from 'ol/geom/Circle'
import { fromLonLat, toLonLat, transform } from 'ol/proj'
import { Style, Fill, Stroke, Circle as CircleStyle } from 'ol/style'
import { isEmpty } from 'ol/extent'

import {
  getStations,
  createStation,
  updateStation,
  deleteStation,
  type Station,
  type StationPayload,
  type StationType,
} from '@/services/station'

const confirm = useConfirm()
const toast = useToast()

const stations = ref<Station[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const mapEl = ref<HTMLDivElement | null>(null)

let map: Map | null = null
let draw: Draw | null = null

const stationSource = new VectorSource()
const previewSource = new VectorSource()
const shouldFocusAfterMapReady = ref(false)

const baseMapRef = ref<InstanceType<typeof BaseMap> | null>(null)

const stationLayer = new VectorLayer({
  source: stationSource,
  style: new Style({
    stroke: new Stroke({
      color: '#2563eb',
      width: 2,
    }),
    fill: new Fill({
      color: 'rgba(37, 99, 235, 0.15)',
    }),
    image: new CircleStyle({
      radius: 7,
      fill: new Fill({ color: '#ef4444' }),
      stroke: new Stroke({ color: '#ffffff', width: 2 }),
    }),
  }),
})

const previewLayer = new VectorLayer({
  source: previewSource,
  style: new Style({
    stroke: new Stroke({
      color: '#f97316',
      width: 2,
    }),
    fill: new Fill({
      color: 'rgba(249, 115, 22, 0.18)',
    }),
    image: new CircleStyle({
      radius: 7,
      fill: new Fill({ color: '#f97316' }),
      stroke: new Stroke({ color: '#ffffff', width: 2 }),
    }),
  }),
})

const typeOptions = [
  { label: 'Circle / รัศมี', value: 'circle' },
  { label: 'Polygon / วาดพื้นที่', value: 'polygon' },
]

const form = reactive<{
  station_name: string
  station_type: StationType
  lat: number | null
  lng: number | null
  radius: number | null
  polygon: Array<{ lat: number; lng: number }>
}>({
  station_name: '',
  station_type: 'circle',
  lat: null,
  lng: null,
  radius: 300,
  polygon: [],
})

onMounted(async () => {
  await loadStations()
})

function focusToCurrentShape(retry = 0) {
  map = baseMapRef.value?.getMap() || map

  if (!map) {
    if (retry < 20) {
      setTimeout(() => focusToCurrentShape(retry + 1), 150)
    }
    return
  }

  map.updateSize()

  if (form.station_type === 'circle') {
    if (!isValidLatLng(form.lat, form.lng)) return

    const center = fromLonLat([
      Number(form.lng),
      Number(form.lat),
    ])

    map.getView().animate({
      center,
      zoom: 16,
      duration: 700,
    })

    return
  }

  const extent = previewSource.getExtent()

  if (extent !== null && !isEmpty(extent)) {
    map.getView().fit(extent, {
      padding: [80, 80, 80, 80],
      duration: 700,
      maxZoom: 17,
    })
  }

}

function onMapReady(payload: { map: Map }) {
  map = payload.map

  if (!map.getLayers().getArray().includes(stationLayer)) {
    map.addLayer(stationLayer)
  }

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }

  map.on('click', (event) => {
    if (form.station_type !== 'circle') return

    const [lng, lat] = toLonLat(event.coordinate)

    form.lng = Number(lng.toFixed(7))
    form.lat = Number(lat.toFixed(7))

    renderPreview()
    focusToCurrentShape()
  })

  setTimeout(() => {
    map?.updateSize()

  }, 100)
}
async function loadStations() {
  loading.value = true

  try {
    stations.value = await getStations()
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

function isValidLatLng(lat: any, lng: any) {
  const nLat = Number(lat)
  const nLng = Number(lng)

  return (
      Number.isFinite(nLat) &&
      Number.isFinite(nLng) &&
      nLat >= -90 &&
      nLat <= 90 &&
      nLng >= -180 &&
      nLng <= 180 &&
      !(nLat === 0 && nLng === 0)
  )
}

function safeFitExtent(extent: any, retry = 0) {
  if (!map || !extent || isEmpty(extent)) return

  const size = map.getSize()

  if (!size || size[0] === 0 || size[1] === 0) {
    if (retry < 20) {
      setTimeout(() => safeFitExtent(extent, retry + 1), 150)
    }
    return
  }

  map.updateSize()

  map.getView().fit(extent, {
    size,
    padding: [80, 80, 80, 80],
    duration: 800,
    maxZoom: 17,
  })

  shouldFocusAfterMapReady.value = false
}

async function openEdit(row: Station) {
  editingId.value = row.station_id
  shouldFocusAfterMapReady.value = true

  form.station_name = row.station_name
  form.station_type = row.station_type || 'circle'

  // form.lat = row.lat ? Number(row.lat) : null
  // form.lng = row.lng ? Number(row.lng) : null

  const lat = Number(row.lat)
  const lng = Number(row.lng)

  form.lat = isValidLatLng(lat, lng) ? lat : null
  form.lng = isValidLatLng(lat, lng) ? lng : null

  // form.radius = row.radius ? Number(row.radius) : 300

  const radius = Number(row.radius)
  form.radius = Number.isFinite(radius) && radius > 0 ? radius : 300

  form.polygon = parsePolygonWkt(row.polygon_wkt)

  dialogVisible.value = true

}

function onDialogShow() {
  setTimeout(() => {
    initMap()
    renderPreview()
    focusToCurrentShape()
  }, 500)
}
function resetForm() {
  form.station_name = ''
  form.station_type = 'circle'
  form.lat = null
  form.lng = null
  form.radius = 300
  form.polygon = []
  previewSource.clear()
}

function initMap() {
  map = baseMapRef.value?.getMap() || null

  if (!map) return

  if (!map.getLayers().getArray().includes(stationLayer)) {
    map.addLayer(stationLayer)
  }

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }

  setTimeout(() => map?.updateSize(), 100)
}

function clearDraw() {
  if (draw && map) {
    map.removeInteraction(draw)
    draw = null
  }
}

function startDrawPolygon() {
  if (!map) return

  form.station_type = 'polygon'
  form.polygon = []
  form.lat = null
  form.lng = null
  form.radius = null

  previewSource.clear()
  clearDraw()

  draw = new Draw({
    source: previewSource,
    type: 'Polygon',
  })

  map.addInteraction(draw)

  draw.on('drawend', (event) => {
    const geometry = event.feature.getGeometry() as Polygon
    const coords = geometry.getCoordinates()[0]

    form.polygon = coords.map((coord) => {
      const [lng, lat] = transform(coord, 'EPSG:3857', 'EPSG:4326')
      return {
        lng: Number(lng.toFixed(7)),
        lat: Number(lat.toFixed(7)),
      }
    })

    clearDraw()
    setTimeout(() => {
      renderPreview()
      focusToCurrentShape()
    }, 100)
  })
}

function selectCircleMode() {
  form.station_type = 'circle'
  form.polygon = []
  if (!form.radius) form.radius = 300

  clearDraw()
  renderPreview()
}

function clearShape() {
  form.lat = null
  form.lng = null
  form.radius = form.station_type === 'circle' ? 300 : null
  form.polygon = []

  clearDraw()
  previewSource.clear()
}

function renderPreview() {
  previewSource.clear()

  if (form.station_type === 'circle') {
    if (!isValidLatLng(form.lat, form.lng)) return

    const center = fromLonLat([
      Number(form.lng),
      Number(form.lat),
    ])

    const radius = Number(form.radius || 300)

    if (!Number.isFinite(radius) || radius <= 0) return

    previewSource.addFeature(
        new Feature(new CircleGeom(center, radius))
    )

    previewSource.addFeature(
        new Feature(new Point(center))
    )
  }

  if (form.station_type === 'polygon') {
    const validPoints = form.polygon.filter((p) =>
        isValidLatLng(p.lat, p.lng)
    )

    if (validPoints.length < 3) return

    const coords = validPoints.map((p) =>
        fromLonLat([
          Number(p.lng),
          Number(p.lat),
        ])
    )

    coords.push(coords[0])

    previewSource.addFeature(
        new Feature(new Polygon([coords]))
    )
  }
}

async function saveStation() {
  if (!form.station_name.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณากรอกชื่อ Station',
      life: 2500,
    })
    return
  }

  if (form.station_type === 'circle' && (!form.lat || !form.lng || !form.radius)) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณาเลือกจุดและกำหนดรัศมี',
      life: 2500,
    })
    return
  }

  if (form.station_type === 'polygon' && form.polygon.length < 3) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณาวาด Polygon อย่างน้อย 3 จุด',
      life: 2500,
    })
    return
  }

  const payload: StationPayload = {
    station_name: form.station_name,
    station_type: form.station_type,
  }

  if (form.station_type === 'circle') {
    payload.lat = form.lat
    payload.lng = form.lng
    payload.radius = form.radius
  } else {
    payload.polygon = form.polygon
  }

  saving.value = true

  try {
    if (editingId.value) {
      await updateStation(editingId.value, payload)
    } else {
      await createStation(payload)
    }

    toast.add({
      severity: 'success',
      summary: 'บันทึก Station สำเร็จ',
      life: 2500,
    })

    dialogVisible.value = false
    await loadStations()
  } finally {
    saving.value = false
  }
}

function confirmDelete(row: Station) {
  confirm.require({
    message: `ต้องการลบ "${row.station_name}" ใช่หรือไม่?`,
    header: 'ยืนยันการลบ',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'ลบ',
    rejectLabel: 'ยกเลิก',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deleteStation(row.station_id)

      toast.add({
        severity: 'success',
        summary: 'ลบ Station แล้ว',
        life: 2500,
      })

      await loadStations()
    },
  })
}

function parsePolygonWkt(wkt?: string | null) {
  if (!wkt) return []

  const text = wkt
      .replace('POLYGON((', '')
      .replace('))', '')

  return text
      .split(',')
      .map((pair) => {
        const [lng, lat] = pair.trim().split(' ').map(Number)
        return { lng, lat }
      })
      .filter((p) => Number.isFinite(p.lng) && Number.isFinite(p.lat))
}

function onTypeChange() {
  clearDraw()
  previewSource.clear()

  if (form.station_type === 'circle') {
    form.polygon = []
    if (!form.radius) form.radius = 300
  } else {
    form.lat = null
    form.lng = null
    form.radius = null
  }
}
</script>

<template>
  <div class="station-page">
    <ConfirmDialog />

    <div class="fleet-page-header">
      <div class="title-section">

<!--        <div class="page-icon">-->
<!--          <i class="pi pi-map-marker"></i>-->
<!--        </div>-->

        <div>
          <h1 class="page-title">Station Management</h1>
          <div class="page-subtitle">
            จัดการสถานี / Geofence / Polygon Area
          </div>
        </div>
      </div>

      <div class="header-actions">
        <Button
            label="Add Station"
            icon="pi pi-plus"
            @click="openCreate"
        />
      </div>
    </div>

    <DataTable
        :value="stations"
        :loading="loading"
        dataKey="station_id"
        paginator
        :rows="20"
        stripedRows
        class="station-table p-datatable-sm"
    >
      <Column field="station_name" header="Station Name" />
      <Column field="station_type" header="Type">
        <template #body="{ data }">
          <span class="type-badge" :class="data.station_type">
            {{ data.station_type }}
          </span>
        </template>
      </Column>
      <Column field="radius" header="Radius">
        <template #body="{ data }">
          {{ data.radius ? `${data.radius} m` : '-' }}
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
        :header="editingId ? 'Edit Station' : 'Add Station'"
        class="station-dialog"
        :style="{ width: '960px', maxWidth: '96vw' }"
        @show="onDialogShow"
        @hide="clearDraw"
    >
      <div class="form-grid">
        <div class="form-panel">
          <label>Station Name</label>
          <InputText
              v-model="form.station_name"
              placeholder="ชื่อ Station"
              class="w-full"
          />

          <label>Type</label>
          <Dropdown
              v-model="form.station_type"
              :options="typeOptions"
              optionLabel="label"
              optionValue="value"
              class="w-full"
              @change="onTypeChange"
          />

          <template v-if="form.station_type === 'circle'">
            <label>Radius / เมตร</label>
            <InputNumber
                v-model="form.radius"
                class="w-full"
                :min="1"
                suffix=" m"
                @input="renderPreview"
            />

            <div class="hint">
              คลิกบนแผนที่เพื่อเลือกจุดศูนย์กลาง
            </div>

            <div class="coords">
              <div>Lat: {{ form.lat || '-' }}</div>
              <div>Lng: {{ form.lng || '-' }}</div>
            </div>
          </template>

          <template v-else>
            <div class="hint">
              กดปุ่มวาด Polygon แล้วคลิกบนแผนที่หลายจุด ดับเบิลคลิกเพื่อจบ
            </div>

            <div class="coords">
              จำนวนจุด: {{ form.polygon.length }}
            </div>
          </template>

          <div class="tool-buttons">
            <Button
                label="Circle"
                icon="pi pi-circle"
                severity="secondary"
                outlined
                @click="selectCircleMode"
            />

            <Button
                label="วาด Polygon"
                icon="pi pi-pencil"
                severity="secondary"
                outlined
                @click="startDrawPolygon"
            />

            <Button
                label="ล้าง"
                icon="pi pi-times"
                severity="danger"
                outlined
                @click="clearShape"
            />
          </div>
        </div>

        <div class="map-panel">
          <BaseMap
              ref="baseMapRef"
              class="station-map"
              :center="[100.5018, 13.7563]"
              :zoom="12"
              :show-zoom-control="true"
              :show-fit-control="false"
              :show-fullscreen-control="false"
              @ready="onMapReady"
          >
            <template #map-controls>
              <button
                  type="button"
                  title="Clear shape"
                  @click.stop="clearShape"
              >
                <i class="pi pi-times"></i>
              </button>
            </template>
          </BaseMap>
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
            @click="saveStation"
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