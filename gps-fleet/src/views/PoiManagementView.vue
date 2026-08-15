<script setup lang="ts">
import {onMounted, ref, reactive, nextTick} from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import Dropdown from 'primevue/dropdown'

import {useConfirm} from 'primevue/useconfirm'
import {useToast} from 'primevue/usetoast'
import { useI18n } from '@/i18n'
import { formatDateTime } from '@/utils/dateTime'
import BaseMap from '@/components/maps/BaseMap.vue'
import Map from 'ol/Map'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import Modify from 'ol/interaction/Modify'
import Feature from 'ol/Feature'
import Point from 'ol/geom/Point'
import {fromLonLat, toLonLat} from 'ol/proj'
import {Style, Fill, Stroke, Circle as CircleStyle, Text} from 'ol/style'
import { poiIconRegistry } from '@/constants/poiIcons'
import Icon from 'ol/style/Icon'
import { useRoute, useRouter } from 'vue-router'

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
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const pois = ref<Poi[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const baseMapRef = ref<InstanceType<typeof BaseMap> | null>(null)

const poiIconOptions = Object.entries(poiIconRegistry).map(
    ([value, item]) => ({
      value,
      label: item.label,
      icon: item.pi,
    }),
)

let map: Map | null = null
let pointModify: Modify | null = null

const existingSource = new VectorSource()
const previewSource = new VectorSource()

const existingLayer = new VectorLayer({
  source: existingSource,
  style: (feature) => [
    new Style({
      image: new CircleStyle({
        radius: 15,
        fill: new Fill({ color: '#f8fafc' }),
        stroke: new Stroke({ color: '#64748b', width: 2 }),
      }),
    }),
    new Style({
      image: new Icon({
        src: getPoiMapIcon(feature.get('icon')),
        scale: 0.75,
        anchor: [0.5, 0.5],
      }),
      text: new Text({
        text: feature.get('name') || '',
        offsetY: 26,
        font: '12px sans-serif',
        fill: new Fill({ color: '#334155' }),
        stroke: new Stroke({ color: '#ffffff', width: 3 }),
      }),
    }),
  ],
})

const previewLayer = new VectorLayer({
  source: previewSource,
  style: () => [
    // BG circle
    new Style({
      image: new CircleStyle({
        radius: 18,
        fill: new Fill({ color: '#f8fafc' }),
        stroke: new Stroke({ color: '#2563eb', width: 2 }),
      }),
    }),

    // SVG icon
    new Style({
      image: new Icon({
        src: getPoiMapIcon(form.icon),
        scale: 0.9,
        anchor: [0.5, 0.5],
      }),
    }),
  ],
})

existingLayer.setZIndex(10)
previewLayer.setZIndex(20)

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

  const location = getCreateLocation()
  if (location) {
    await openCreate(location)
    await router.replace({ name: 'pois' })
  }
})

function getCreateLocation() {
  if (route.query.create !== '1') return null

  const lat = Number(route.query.lat)
  const lng = Number(route.query.lng)

  if (!isValidPoiLocation(lat, lng)) return null

  return { lat, lng }
}

// function getPoiMapIcon(value?: string | null) {
//   return poiIconRegistry[value as keyof typeof poiIconRegistry]?.emoji
//       || '📍'
// }

// function getPoiMapIcon(value?: string | null) {
//   return poiIconRegistry[value as keyof typeof poiIconRegistry]?.pi
//       || '📍'
// }

function getPoiMapIcon(value?: string | null) {
  return poiIconRegistry[value as keyof typeof poiIconRegistry]?.mapIcon
      || '/poi-icons/map-pin-pen.svg'
}


function findPoiIcon(value?: string | null) {
  return poiIconRegistry[value as keyof typeof poiIconRegistry]?.pi
      || 'pi pi-map-marker'
}



async function loadPois() {
  loading.value = true
  try {
    pois.value = await getPois()
    renderExistingPois()
  } finally {
    loading.value = false
  }
}

async function openCreate(location?: { lat: number; lng: number }) {
  editingId.value = null
  resetForm()
  renderExistingPois()

  if (location) {
    form.lat = location.lat
    form.lng = location.lng
  }

  dialogVisible.value = true

  await nextTick()
  initMap()
  renderPreview()
  if (location) focusCurrentPoi()
}

function focusCurrentPoi(retry = 0) {
  if (!map) {
    if (retry < 10) {
      setTimeout(() => focusCurrentPoi(retry + 1), 150)
    }
    return
  }

  if (!isValidPoiLocation(form.lat, form.lng)) return

  setTimeout(() => {
    map?.updateSize()

    map?.getView().animate({
      center: fromLonLat([form.lng!, form.lat!]),
      zoom: 16,
      duration: 500,
    })
  }, 300)
}
async function openEdit(row: Poi) {
  editingId.value = row.poi_id
  renderExistingPois()
  form.poi_name = row.poi_name
  form.icon = row.icon || null
  form.lat = row.lat !== null && row.lat !== undefined
      ? Number(row.lat)
      : null
  form.lng = row.lng !== null && row.lng !== undefined
      ? Number(row.lng)
      : null

  dialogVisible.value = true

  await nextTick()
  initMap()
  renderPreview()
  focusCurrentPoi()
}

function resetForm() {
  form.poi_name = ''
  form.icon = null
  form.lat = null
  form.lng = null
  previewSource.clear()
}

function initMap() {
  map = baseMapRef.value?.getMap() || null

  if (!map) return

  if (!map.getLayers().getArray().includes(existingLayer)) {
    map.addLayer(existingLayer)
  }

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }

  initPointModify()

  setTimeout(() => map?.updateSize(), 100)
}

function onMapReady(payload: { map: Map }) {
  map = payload.map

  if (!map.getLayers().getArray().includes(existingLayer)) {
    map.addLayer(existingLayer)
  }

  if (!map.getLayers().getArray().includes(previewLayer)) {
    map.addLayer(previewLayer)
  }

  initPointModify()
  renderExistingPois()

  map.on('click', (event) => {
    const [lng, lat] = toLonLat(event.coordinate)

    form.lng = Number(lng.toFixed(8))
    form.lat = Number(lat.toFixed(8))

    renderPreview()
  })

  setTimeout(() => {
    map?.updateSize()
    if (editingId.value) {
      renderPreview()
      focusCurrentPoi()
    }
  }, 100)
}

function initPointModify() {
  if (!map || pointModify) return

  pointModify = new Modify({
    source: previewSource,
    pixelTolerance: 18,
    hitDetection: previewLayer,
    filter: (feature) => feature.getGeometry() instanceof Point,
  })

  pointModify.on('modifyend', (event) => {
    const geometry = event.features
        .getArray()
        .map((feature) => feature.getGeometry())
        .find((item): item is Point => item instanceof Point)

    if (!geometry) return

    const [lng, lat] = toLonLat(geometry.getCoordinates())

    form.lng = Number(lng.toFixed(8))
    form.lat = Number(lat.toFixed(8))
  })

  map.addInteraction(pointModify)
}

function clearPointModify() {
  if (pointModify && map) {
    map.removeInteraction(pointModify)
  }

  pointModify = null
}

function onDialogShow() {
  initMap()
  renderPreview()
  focusCurrentPoi()
}

function renderPreview() {
  previewSource.clear()

  if (!isValidPoiLocation(form.lat, form.lng)) return

  previewSource.addFeature(
      new Feature(new Point(fromLonLat([
        Number(form.lng),
        Number(form.lat),
      ]))),
  )
}

function renderExistingPois() {
  existingSource.clear()

  for (const poi of pois.value) {
    if (poi.poi_id === editingId.value || !isValidPoiLocation(poi.lat, poi.lng)) {
      continue
    }

    const feature = new Feature(new Point(fromLonLat([
      Number(poi.lng),
      Number(poi.lat),
    ])))
    feature.setProperties({ icon: poi.icon, name: poi.poi_name })
    existingSource.addFeature(feature)
  }
}

function isValidPoiLocation(lat: unknown, lng: unknown) {
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

function goToCoordinates() {
  if (!isValidPoiLocation(form.lat, form.lng)) {
    toast.add({
      severity: 'warn',
      summary: t('invalidPoiCoordinates'),
      life: 2500,
    })
    return
  }

  form.lat = Number(Number(form.lat).toFixed(8))
  form.lng = Number(Number(form.lng).toFixed(8))

  renderPreview()
  focusCurrentPoi()
}

function handleCoordinatePaste(event: ClipboardEvent) {
  const text = event.clipboardData?.getData('text')?.trim()

  if (!text) return

  const values = text.match(/[-+]?(?:\d+(?:\.\d*)?|\.\d+)/g)

  // Keep the browser's normal paste behavior when copying one value
  // into an individual latitude or longitude field.
  if (!values || values.length < 2) return

  let latitude = Number(values[0])
  let longitude = Number(values[1])

  // Also accept coordinate sources that use longitude, latitude order.
  if (
      !isValidPoiLocation(latitude, longitude) &&
      isValidPoiLocation(longitude, latitude)
  ) {
    [latitude, longitude] = [longitude, latitude]
  }

  event.preventDefault()

  if (!isValidPoiLocation(latitude, longitude)) {
    toast.add({
      severity: 'warn',
      summary: t('invalidPoiCoordinates'),
      life: 2500,
    })
    return
  }

  form.lat = Number(latitude.toFixed(8))
  form.lng = Number(longitude.toFixed(8))

  nextTick(goToCoordinates)
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
      summary: t('poiNameRequired'),
      life: 2500,
    })
    return
  }

  if (!isValidPoiLocation(form.lat, form.lng)) {
    toast.add({
      severity: 'warn',
      summary: t('poiLocationRequired'),
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
      summary: t('savePoiSuccess'),
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
    message: t('deleteConfirmMessage', { name: row.poi_name }),
    header: t('confirmDelete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('delete'),
    rejectLabel: t('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deletePoi(row.poi_id)

      toast.add({
        severity: 'success',
        summary: t('deletePoiSuccess'),
        life: 2500,
      })

      await loadPois()
    },
  })
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
          <h1 class="page-title">{{ t('poiManagement') }}</h1>
          <div class="page-subtitle">
            {{ t('poiManagementSubtitle') }}
          </div>
        </div>
      </div>

      <div class="header-actions">
        <Button
            :label="t('addPoi')"
            icon="pi pi-plus"
            @click="() => openCreate()"
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
      <Column field="poi_name" :header="t('poiName')"/>

      <Column :header="t('poiIcon')">
        <template #body="{ data }">
          <div class="poi-icon-cell">
            <img
                :src="getPoiMapIcon(data.icon)"
                class="poi-table-icon"
            />

            <span>
        {{ findPoiLabel(data.icon) }}
      </span>
          </div>
        </template>
      </Column>

      <Column :header="t('location')">
        <template #body="{ data }">
          <span v-if="data.lat && data.lng">
            {{ Number(data.lat).toFixed(6) }},
            {{ Number(data.lng).toFixed(6) }}
          </span>
          <span v-else>-</span>
        </template>
      </Column>

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
        :header="editingId ? t('editPoi') : t('addPoi')"
        class="station-dialog"
        :style="{ width: '900px', maxWidth: '96vw' }"
        @show="onDialogShow"
        @hide="clearPointModify"
    >
      <div class="form-grid">
        <div class="form-panel">
          <label>{{ t('poiName') }}</label>
          <InputText
              v-model="form.poi_name"
              :placeholder="t('poiName')"
              class="w-full"
          />

          <label>{{ t('poiIcon') }}</label>

          <Dropdown
              v-model="form.icon"
              :options="poiIconOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="t('selectIcon')"
              class="w-full"
              @change="renderPreview"
          >
            <template #value="slotProps">
              <div
                  v-if="slotProps.value"
                  class="poi-icon-option"
              >

                <img
                    :src="getPoiMapIcon(slotProps.value)"
                    class="poi-dropdown-icon"
                />

                <span>
        {{ findPoiLabel(slotProps.value) }}
      </span>
              </div>

              <span v-else>
      {{ t('selectIcon') }}
    </span>
            </template>

            <template #option="slotProps">
              <div class="poi-icon-option">
                <img
                    :src="getPoiMapIcon(slotProps.option.value)"
                    class="poi-dropdown-icon"
                />

                <span>
        {{ slotProps.option.label }}
      </span>
              </div>
            </template>
          </Dropdown>

          <div class="hint">
            {{ t('clickMapForPoi') }}
          </div>

          <div class="coordinate-fields">
            <div class="coordinate-field">
              <label for="poi-latitude">{{ t('latitude') }}</label>
              <InputNumber
                  inputId="poi-latitude"
                  v-model="form.lat"
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
              <label for="poi-longitude">{{ t('longitude') }}</label>
              <InputNumber
                  inputId="poi-longitude"
                  v-model="form.lng"
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
                :label="t('clearLocation')"
                icon="pi pi-times"
                severity="danger"
                outlined
                @click="clearPoint"
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
                  :title="t('clearPoi')"
                  @click.stop="clearPoint"
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

.poi-dropdown-icon,
.poi-table-icon {
  width: 22px;
  height: 22px;
  object-fit: contain;
  border: #cbd5e0;
  background-color: #cbd5e0;

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
