<script setup lang="ts">
import { onMounted, ref, reactive, nextTick } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { useI18n } from '@/i18n'
import { formatDateTime } from '@/utils/dateTime'

import BaseMap from '@/components/maps/BaseMap.vue'
import Map from 'ol/Map'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import Draw from 'ol/interaction/Draw'
import Feature from 'ol/Feature'
import Polygon from 'ol/geom/Polygon'
import { fromLonLat, transform } from 'ol/proj'
import { Style, Fill, Stroke, Circle as CircleStyle, Text } from 'ol/style'
import { useRoute, useRouter } from 'vue-router'

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
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const zones = ref<ForbiddenZone[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)
const createCenter = ref<[number, number]>([100.5018, 13.7563])
const locationZoom = ref(12)
const navigationLat = ref<number | null>(null)
const navigationLng = ref<number | null>(null)

const baseMapRef = ref<InstanceType<typeof BaseMap> | null>(null)

let map: Map | null = null
let draw: Draw | null = null

const existingSource = new VectorSource()
const previewSource = new VectorSource()

const existingLayer = new VectorLayer({
  source: existingSource,
  style: (feature) => new Style({
    stroke: new Stroke({
      color: '#2563eb',
      width: 2,
    }),
    fill: new Fill({
      color: 'rgba(37, 99, 235, 0.12)',
    }),
    text: new Text({
      text: feature.get('name') || '',
      font: '600 12px sans-serif',
      fill: new Fill({ color: '#1e3a8a' }),
      stroke: new Stroke({ color: '#ffffff', width: 3 }),
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

existingLayer.setZIndex(10)
previewLayer.setZIndex(20)

const form = reactive<{
  zone_name: string
  polygon: Array<{ lat: number; lng: number }>
}>({
  zone_name: '',
  polygon: [],
})

onMounted(async () => {
  await loadForbiddenZones()

  const location = getCreateLocation()
  if (location) {
    await openCreate(location)
    await router.replace({ name: 'forbidden-zones' })
  }
})

function getCreateLocation() {
  if (route.query.create !== '1') return null

  const lat = Number(route.query.lat)
  const lng = Number(route.query.lng)

  if (!isValidMapLocation(lat, lng)) return null

  return { lat, lng }
}

async function loadForbiddenZones() {
  loading.value = true

  try {
    zones.value = await getForbiddenZones()
    renderExistingZones()
  } finally {
    loading.value = false
  }
}

function onMapReady(payload: { map: Map }) {
  map = payload.map
  ensureOverlayLayers()
  renderExistingZones()
  fitCurrentZone()
}

function ensureOverlayLayers() {
  if (!map) return

  if (!map.getLayers().getArray().includes(existingLayer)) {
    map.addLayer(existingLayer)
  }

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }
}

async function openCreate(location?: { lat: number; lng: number }) {
  editingId.value = null
  resetForm()
  renderExistingZones()

  createCenter.value = location
      ? [location.lng, location.lat]
      : [100.5018, 13.7563]
  locationZoom.value = location ? 16 : 12
  navigationLat.value = location?.lat ?? null
  navigationLng.value = location?.lng ?? null
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
  renderExistingZones()

  form.zone_name = row.zone_name
  form.polygon = parsePolygonWkt(row.polygon_wkt)

  const polygonCenter = getPointsCenter(form.polygon)
  navigationLat.value = polygonCenter?.lat ?? null
  navigationLng.value = polygonCenter?.lng ?? null

  dialogVisible.value = true
}

function resetForm() {
  form.zone_name = ''
  form.polygon = []
  navigationLat.value = null
  navigationLng.value = null

  clearDraw()
  previewSource.clear()
}

function initMap() {
  map = baseMapRef.value?.getMap() || map

  if (!map) return

  ensureOverlayLayers()
  renderExistingZones()
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
          lng: Number(lng.toFixed(8)),
          lat: Number(lat.toFixed(8)),
        }
      }),
    )

    const polygonCenter = getPointsCenter(form.polygon)
    navigationLat.value = polygonCenter?.lat ?? navigationLat.value
    navigationLng.value = polygonCenter?.lng ?? navigationLng.value

    clearDraw()
  })
}

function clearShape() {
  form.polygon = []

  clearDraw()
  previewSource.clear()
}

function isValidMapLocation(lat: unknown, lng: unknown) {
  if (lat === null || lat === undefined || lng === null || lng === undefined) {
    return false
  }

  const latitude = Number(lat)
  const longitude = Number(lng)

  return (
      Number.isFinite(latitude) &&
      Number.isFinite(longitude) &&
      latitude >= -90 &&
      latitude <= 90 &&
      longitude >= -180 &&
      longitude <= 180
  )
}

function getPointsCenter(points: Array<{ lat: number; lng: number }>) {
  if (!points.length) return null

  const total = points.reduce(
      (result, point) => ({
        lat: result.lat + Number(point.lat),
        lng: result.lng + Number(point.lng),
      }),
      { lat: 0, lng: 0 },
  )

  return {
    lat: total.lat / points.length,
    lng: total.lng / points.length,
  }
}

function goToCoordinates() {
  if (!isValidMapLocation(navigationLat.value, navigationLng.value)) {
    toast.add({
      severity: 'warn',
      summary: t('invalidMapCoordinates'),
      life: 2500,
    })
    return
  }

  navigationLat.value = Number(Number(navigationLat.value).toFixed(8))
  navigationLng.value = Number(Number(navigationLng.value).toFixed(8))

  initMap()
  map?.getView().animate({
    center: fromLonLat([
      Number(navigationLng.value),
      Number(navigationLat.value),
    ]),
    zoom: 16,
    duration: 500,
  })
}

function handleCoordinatePaste(event: ClipboardEvent) {
  const text = event.clipboardData?.getData('text')?.trim()
  const values = text?.match(/[-+]?(?:\d+(?:\.\d*)?|\.\d+)/g)

  if (!values || values.length < 2) return

  let lat = Number(values[0])
  let lng = Number(values[1])

  if (!isValidMapLocation(lat, lng) && isValidMapLocation(lng, lat)) {
    [lat, lng] = [lng, lat]
  }

  event.preventDefault()

  navigationLat.value = lat
  navigationLng.value = lng
  nextTick(goToCoordinates)
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

function renderExistingZones() {
  existingSource.clear()

  for (const zone of zones.value) {
    if (Number(zone.id) === editingId.value) continue

    const points = parsePolygonWkt(zone.polygon_wkt)

    if (points.length < 3) continue

    const coordinates = points.map((point) => fromLonLat([point.lng, point.lat]))
    const feature = new Feature(new Polygon([closeRing(coordinates)]))
    feature.set('name', zone.zone_name)
    existingSource.addFeature(feature)
  }
}

async function saveForbiddenZone() {
  if (!form.zone_name.trim()) {
    toast.add({
      severity: 'warn',
      summary: t('zoneNameRequired'),
      life: 2500,
    })
    return
  }

  if (form.polygon.length < 3) {
    toast.add({
      severity: 'warn',
      summary: t('forbiddenPolygonRequired'),
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
      summary: t('saveForbiddenZoneSuccess'),
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
    message: t('deleteConfirmMessage', { name: row.zone_name }),
    header: t('confirmDelete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('delete'),
    rejectLabel: t('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deleteForbiddenZone(row.id)

      toast.add({
        severity: 'success',
        summary: t('deleteForbiddenZoneSuccess'),
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
          <h1 class="page-title">{{ t('forbiddenZoneManagement') }}</h1>
          <div class="page-subtitle">
            {{ t('forbiddenZoneManagementSubtitle') }}
          </div>
        </div>
      </div>

      <div class="header-actions">
        <Button
          :label="t('addForbiddenZone')"
          icon="pi pi-plus"
          @click="() => openCreate()"
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
      <Column field="zone_name" :header="t('zoneName')" />

      <Column field="created_at" :header="t('createdAt')" style="min-width: 170px">
        <template #body="{ data }">
          {{ formatDateTime(data.created_at, locale) }}
        </template>
      </Column>
      <Column field="modified_at" :header="t('modifiedAt')" style="min-width: 170px">
        <template #body="{ data }">
          {{ formatDateTime(data.modified_at, locale) }}
        </template>
      </Column>

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

      <Column :header="t('actions')" style="width: 160px">
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
        :header="editingId ? t('editForbiddenZone') : t('addForbiddenZone')"
        class="station-dialog"
        :style="{ width: '960px', maxWidth: '96vw' }"
        @show="fitCurrentZone"
        @hide="clearDraw"
    >
      <div class="form-grid">
        <div class="form-panel">
          <label>{{ t('zoneName') }}</label>
          <InputText
            v-model="form.zone_name"
            :placeholder="t('zoneNamePlaceholder')"
            class="w-full"
          />

          <div class="hint">
            {{ t('drawPolygonHint') }}
          </div>

          <div class="coords">
            {{ t('pointsCount') }}: {{ form.polygon.length }}
          </div>

          <div class="coordinate-fields">
            <div class="coordinate-field">
              <label for="forbidden-latitude">{{ t('latitude') }}</label>
              <InputNumber
                  inputId="forbidden-latitude"
                  v-model="navigationLat"
                  class="w-full"
                  :min="-90"
                  :max="90"
                  :maxFractionDigits="8"
                  :useGrouping="false"
                  placeholder="13.75630000"
                  @paste="handleCoordinatePaste"
                  @keyup.enter="goToCoordinates"
              />
            </div>

            <div class="coordinate-field">
              <label for="forbidden-longitude">{{ t('longitude') }}</label>
              <InputNumber
                  inputId="forbidden-longitude"
                  v-model="navigationLng"
                  class="w-full"
                  :min="-180"
                  :max="180"
                  :maxFractionDigits="8"
                  :useGrouping="false"
                  placeholder="100.50180000"
                  @paste="handleCoordinatePaste"
                  @keyup.enter="goToCoordinates"
              />
            </div>
          </div>

          <div class="tool-buttons">
            <Button
              :label="t('goToCoordinates')"
              icon="pi pi-crosshairs"
              @click="goToCoordinates"
            />

            <Button
              :label="t('drawPolygon')"
              icon="pi pi-pencil"
              severity="secondary"
              outlined
              @click="startDrawPolygon"
            />

            <Button
              :label="t('clear')"
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
            :center="createCenter"
            :zoom="locationZoom"
            :show-zoom-control="true"
            :show-fit-control="false"
            :show-fullscreen-control="false"
            @ready="onMapReady"
          >
            <template #map-controls>
              <button
                type="button"
                :title="t('clearShape')"
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
          :label="t('cancel')"
          severity="secondary"
          outlined
          @click="dialogVisible = false"
        />
        <Button
          :label="t('save')"
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

.coordinate-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.coordinate-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.coordinate-field :deep(.p-inputnumber),
.coordinate-field :deep(.p-inputnumber-input) {
  width: 100%;
  min-width: 0;
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
