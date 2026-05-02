<template>
  <div class="map-shell">
    <div ref="mapEl" class="map"></div>

    <div class="map-control">
      <Dropdown
          v-model="selectedProvider"
          :options="providerOptions"
          option-label="label"
          option-value="value"
          class="w-full"
          @change="changeProvider"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Dropdown from 'primevue/dropdown'

import Map from 'ol/Map'
import View from 'ol/View'
import TileLayer from 'ol/layer/Tile'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import XYZ from 'ol/source/XYZ'
import Feature from 'ol/Feature'
import Point from 'ol/geom/Point'
import { fromLonLat } from 'ol/proj'
import { Circle, Fill, Stroke, Style } from 'ol/style'
import 'ol/ol.css'

import {
  DEFAULT_MAP_PROVIDER,
  getSavedMapProvider,
  mapProviders,
  saveMapProvider,
  type MapProviderKey,
} from '../config/mapProviders'

import type { Vehicle, VehicleStatus } from '../types/fleet'

const props = defineProps<{
  vehicles: Vehicle[]
  focusVehicleId?: string | null
}>()

const emit = defineEmits<{
  'vehicle-click': [vehicle: Vehicle]
}>()

const mapEl = ref<HTMLDivElement | null>(null)
const selectedProvider = ref<MapProviderKey>(getSavedMapProvider())

let map: Map | null = null
let baseLayer: TileLayer<XYZ> | null = null
let vehicleSource: VectorSource | null = null

const providerOptions = computed(() =>
    Object.entries(mapProviders).map(([value, provider]) => ({
      value: value as MapProviderKey,
      label: provider.label,
    }))
)

function createBaseLayer(providerKey: MapProviderKey): TileLayer<XYZ> {
  const provider = mapProviders[providerKey] || mapProviders[DEFAULT_MAP_PROVIDER]

  return new TileLayer({
    source: new XYZ({
      url: provider.url,
      attributions: provider.attributions,
      crossOrigin: 'anonymous',
    }),
  })
}

function getVehicleColor(status: VehicleStatus): string {
  return {
    running: '#22c55e',
    idle: '#f59e0b',
    parking: '#64748b',
    offline: '#ef4444',
    no_gps: '#8b5cf6',
  }[status]
}

function createVehicleStyle(status: VehicleStatus): Style {
  return new Style({
    image: new Circle({
      radius: 8,
      fill: new Fill({ color: getVehicleColor(status) }),
      stroke: new Stroke({ color: '#ffffff', width: 3 }),
    }),
  })
}

function renderVehicles() {
  if (!vehicleSource) return

  vehicleSource.clear()

  props.vehicles.forEach((vehicle) => {
    if (vehicle.lat === null || vehicle.lng === null) return

    const feature = new Feature({
      geometry: new Point(fromLonLat([Number(vehicle.lng), Number(vehicle.lat)])),
      vehicle,
    })

    feature.setStyle(createVehicleStyle(vehicle.status))
    vehicleSource?.addFeature(feature)
  })
}

function changeProvider() {
  if (!map) return

  const nextLayer = createBaseLayer(selectedProvider.value)
  map.getLayers().setAt(0, nextLayer)

  baseLayer = nextLayer
  saveMapProvider(selectedProvider.value)
}

function getVehicleKey(vehicle: Vehicle): string {
  return String(
      vehicle.vehicle_id ||
      vehicle.id ||
      vehicle.plate_no
  )
}

function focusVehicle(vehicleId?: string | null) {
  console.log('FOCUS VEHICLE ID', vehicleId)

  if (!map || !vehicleSource || !vehicleId) return

  const feature = vehicleSource.getFeatures().find((item) => {
    const vehicle = item.get('vehicle') as Vehicle | undefined
    if (!vehicle) return false

    return getVehicleKey(vehicle) === vehicleId
  })

  console.log('FOCUS FEATURE', feature)

  if (!feature) return

  const geometry = feature.getGeometry()
  if (!geometry) return

  map.getView().animate({
    center: geometry.getCoordinates(),
    zoom: 15,
    duration: 400,
  })
}

onMounted(async () => {

  console.log('FLEET MAP MOUNTED')

  await nextTick()

  if (!mapEl.value) return

  baseLayer = createBaseLayer(selectedProvider.value)
  vehicleSource = new VectorSource()

  const vehicleLayer = new VectorLayer({
    source: vehicleSource,
  })

  map = new Map({
    target: mapEl.value,
    layers: [baseLayer, vehicleLayer],
    view: new View({
      center: fromLonLat([100.5018, 13.7563]),
      zoom: 6,
    }),
  })

  map.on('singleclick', (event) => {
    map?.forEachFeatureAtPixel(event.pixel, (feature) => {
      const vehicle = feature.get('vehicle') as Vehicle | undefined
      if (vehicle) emit('vehicle-click', vehicle)
    })
  })

  renderVehicles()
})

watch(
    () => props.vehicles,
    () => renderVehicles(),
    { deep: true }
)

watch(
    () => props.focusVehicleId,
    (vehicleId) => focusVehicle(vehicleId)
)

onBeforeUnmount(() => {
  if (map) {
    map.setTarget(undefined)
    map = null
  }
})
</script>

<style scoped>

.map-shell {
  flex: 1;              /* 👈 เปลี่ยนจาก height */
  min-height: 0;
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  border: 1px solid var(--p-surface-700);
}

.map {
  width: 100%;
  height: 100%;
}

.map-control {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
  width: 230px;
  padding: 10px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--p-surface-900) 88%, transparent);
  border: 1px solid var(--p-surface-700);
  backdrop-filter: blur(14px);
}
</style>