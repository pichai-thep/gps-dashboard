<script setup lang="ts">
import { onMounted, ref, reactive, nextTick } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
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
import Polygon from 'ol/geom/Polygon'
import { fromLonLat, transform } from 'ol/proj'
import { Style, Fill, Stroke, Circle as CircleStyle } from 'ol/style'

import {
  getForbiddenZones,
  createForbiddenZone,
  updateForbiddenZone,
  deleteForbiddenZone,
  type ForbiddenZone,
  type ForbiddenZonePayload,
} from '@/services/forbiddenZone'

const confirm = useConfirm()
const toast = useToast()

const zones = ref<ForbiddenZone[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const baseMapRef = ref<InstanceType<typeof BaseMap> | null>(null)

let map: Map | null = null
let draw: Draw | null = null

const previewSource = new VectorSource()

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

const form = reactive<{
  zone_name: string
  polygon: Array<{ lat: number; lng: number }>
}>({
  zone_name: '',
  polygon: [],
})

onMounted(async () => {
  await loadForbiddenZones()
})

async function loadForbiddenZones() {
  loading.value = true

  try {
    zones.value = await getForbiddenZones()
  } finally {
    loading.value = false
  }
}

function onMapReady(payload: { map: Map }) {
  map = payload.map
  ensurePreviewLayer()
  fitCurrentZone()
}

function ensurePreviewLayer() {
  if (!map) return

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }
}

async function openCreate() {
  editingId.value = null
  resetForm()
  dialogVisible.value = true

  await nextTick()
  initMap()
  clearShape()
}

function fitCurrentZone(retry = 0) {
  initMap()

  if (!map || !form.polygon.length) {
    if (retry < 20) {
      setTimeout(() => fitCurrentZone(retry + 1), 150)
    }
    return
  }

  if (form.polygon.length < 3) {
    console.warn('Polygon must have at least 3 points', form.polygon)
    return
  }

  renderPreview(false)

  setTimeout(() => {
    map?.updateSize()

    const coords = form.polygon.map((p) => fromLonLat([p.lng, p.lat]))
    const feature = new Feature(new Polygon([closeRing(coords)]))
    const extent = feature.getGeometry()?.getExtent()

    if (!extent || extent.some((v) => !Number.isFinite(v))) {
      console.warn('Invalid extent', extent, form.polygon)
      return
    }

    map?.getView().fit(extent, {
      padding: [80, 80, 80, 80],
      maxZoom: 17,
      duration: 600,
    })
  }, 300)
}
function focusCurrentZone(retry = 0) {
  if (!map) {
    if (retry < 10) {
      setTimeout(() => focusCurrentZone(retry + 1), 150)
    }
    return
  }

  if (!form.polygon.length) return

  setTimeout(() => {
    map?.updateSize()

    const coords = form.polygon.map((p) =>
        fromLonLat([p.lng, p.lat])
    )

    const polygon = new Polygon([closeRing(coords)])
    const extent = polygon.getExtent()

    map?.getView().fit(extent, {
      padding: [80, 80, 80, 80],
      maxZoom: 17,
      duration: 500,
    })
  }, 300)
}

async function openEdit(row: ForbiddenZone) {
  editingId.value = Number(row.id)
  resetForm()

  form.zone_name = row.zone_name
  form.polygon = parsePolygonWkt(row.polygon_wkt)

  console.log('polygon=', form.polygon)

  dialogVisible.value = true
}

function resetForm() {
  form.zone_name = ''
  form.polygon = []

  clearDraw()
  previewSource.clear()
}

function initMap() {
  map = baseMapRef.value?.getMap() || map

  if (!map) return

  ensurePreviewLayer()
  setTimeout(() => map?.updateSize(), 100)
}

function clearDraw() {
  if (draw && map) {
    map.removeInteraction(draw)
  }

  draw = null
}

function startDrawPolygon() {
  if (!map) return

  clearDraw()
  form.polygon = []
  previewSource.clear()

  draw = new Draw({
    source: previewSource,
    type: 'Polygon',
  })

  map.addInteraction(draw)

  draw.on('drawend', (event) => {
    const geometry = event.feature.getGeometry() as Polygon
    const coords = geometry.getCoordinates()[0]

    form.polygon = removeClosingPoint(
      coords.map((coord) => {
        const [lng, lat] = transform(coord, 'EPSG:3857', 'EPSG:4326')

        return {
          lng: Number(lng.toFixed(7)),
          lat: Number(lat.toFixed(7)),
        }
      }),
    )

    clearDraw()
  })
}

function clearShape() {
  form.polygon = []

  clearDraw()
  previewSource.clear()
}

function renderPreview(fit = false) {
  previewSource.clear()

  if (!form.polygon.length) return

  const coords = form.polygon.map((p) => fromLonLat([p.lng, p.lat]))
  const closedCoords = closeRing(coords)
  const feature = new Feature(new Polygon([closedCoords]))

  previewSource.addFeature(feature)

  if (fit && map) {
    const extent = feature.getGeometry()?.getExtent()

    if (extent) {
      map.getView().fit(extent, {
        padding: [80, 80, 80, 80],
        maxZoom: 17,
        duration: 250,
      })
    }
  }
}

async function saveForbiddenZone() {
  if (!form.zone_name.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณากรอกชื่อเขตห้ามเข้า',
      life: 2500,
    })
    return
  }

  if (form.polygon.length < 3) {
    toast.add({
      severity: 'warn',
      summary: 'กรุณาวาด Polygon อย่างน้อย 3 จุด',
      life: 2500,
    })
    return
  }

  const payload: ForbiddenZonePayload = {
    zone_name: form.zone_name,
    polygon: form.polygon,
  }

  saving.value = true

  try {
    if (editingId.value) {
      await updateForbiddenZone(editingId.value, payload)
    } else {
      await createForbiddenZone(payload)
    }

    toast.add({
      severity: 'success',
      summary: 'บันทึกเขตห้ามเข้าสำเร็จ',
      life: 2500,
    })

    dialogVisible.value = false
    clearShape()
    await loadForbiddenZones()
  } finally {
    saving.value = false
  }
}

function confirmDelete(row: ForbiddenZone) {
  confirm.require({
    message: `ต้องการลบ "${row.zone_name}" ใช่หรือไม่?`,
    header: 'ยืนยันการลบ',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'ลบ',
    rejectLabel: 'ยกเลิก',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deleteForbiddenZone(row.id)

      toast.add({
        severity: 'success',
        summary: 'ลบเขตห้ามเข้าแล้ว',
        life: 2500,
      })

      await loadForbiddenZones()
    },
  })
}

function parsePolygonWkt(wkt?: string | null) {
  if (!wkt) return []

  const text = wkt
    .replace(/^POLYGON\s*\(\(/i, '')
    .replace(/\)\)$/i, '')

  const points = text
    .split(',')
    .map((pair) => {
      const [lng, lat] = pair.trim().split(/\s+/).map(Number)
      return { lng, lat }
    })
    .filter((p) => Number.isFinite(p.lng) && Number.isFinite(p.lat))

  return removeClosingPoint(points)
}

function removeClosingPoint(points: Array<{ lat: number; lng: number }>) {
  if (points.length < 2) return points

  const first = points[0]
  const last = points[points.length - 1]

  if (first.lat === last.lat && first.lng === last.lng) {
    return points.slice(0, -1)
  }

  return points
}

function closeRing(coords: number[][]) {
  if (!coords.length) return coords

  const first = coords[0]
  const last = coords[coords.length - 1]

  if (first[0] === last[0] && first[1] === last[1]) {
    return coords
  }

  return [...coords, first]
}
</script>

<template>
  <div class="station-page">
    <ConfirmDialog />

    <div class="fleet-page-header">
      <div class="title-section">
        <div>
          <h1 class="page-title">Forbidden Zone Management</h1>
          <div class="page-subtitle">
            จัดการเขตห้ามเข้า
          </div>
        </div>
      </div>

      <div class="header-actions">
        <Button
          label="Add Forbidden Zone"
          icon="pi pi-plus"
          @click="openCreate"
        />
      </div>
    </div>

    <DataTable
      :value="zones"
      :loading="loading"
      dataKey="id"
      paginator
      :rows="20"
      stripedRows
      class="station-table p-datatable-sm"
    >
      <Column field="zone_name" header="Zone Name" />

<!--      <Column header="Type">-->
<!--        <template #body>-->
<!--          <span class="type-badge polygon">polygon</span>-->
<!--        </template>-->
<!--      </Column>-->

<!--      <Column header="Polygon">-->
<!--        <template #body="{ data }">-->
<!--          {{ parsePolygonWkt(data.polygon_wkt).length }} จุด-->
<!--        </template>-->
<!--      </Column>-->

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
        :header="editingId ? 'Edit Forbidden Zone' : 'Add Forbidden Zone'"
        class="station-dialog"
        :style="{ width: '960px', maxWidth: '96vw' }"
        @show="fitCurrentZone"
        @hide="clearDraw"
    >
      <div class="form-grid">
        <div class="form-panel">
          <label>Zone Name</label>
          <InputText
            v-model="form.zone_name"
            placeholder="ชื่อเขตห้ามเข้า"
            class="w-full"
          />

          <div class="hint">
            กดปุ่มวาด Polygon แล้วคลิกบนแผนที่หลายจุด ดับเบิลคลิกเพื่อจบ
          </div>

          <div class="coords">
            จำนวนจุด: {{ form.polygon.length }}
          </div>

          <div class="tool-buttons">
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
                title="Clear polygon"
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
          @click="saveForbiddenZone"
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

.type-badge.polygon {
  background: rgba(249, 115, 22, 0.15);
  color: #fb923c;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 4px;
}

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

.w-full {
  width: 100%;
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
