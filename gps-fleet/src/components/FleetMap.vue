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

    <!-- 🔥 POPUP -->
    <div ref="popupEl" class="map-popup" v-show="selectedVehicle">
      <div class="popup-title">
        {{ selectedVehicle?.plate_no }}
      </div>

      <div class="popup-row">
        <span>Status</span>
        <strong>{{ selectedVehicle?.status }}</strong>
      </div>

      <div class="popup-row">
        <span>Speed</span>
        <strong>{{ selectedVehicle?.speed ?? 0 }} km/h</strong>
      </div>

      <div class="popup-row">
        <span>Fuel</span>
        <strong>{{ selectedVehicle?.fuel_left ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>GPS</span>
        <strong>{{ selectedVehicle?.gps_time ?? '-' }}</strong>
      </div>
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
import { Fill, Icon, Stroke, Style, Text } from 'ol/style'
import Overlay from 'ol/Overlay'

import {
  DEFAULT_MAP_PROVIDER,
  getSavedMapProvider,
  mapProviders,
  saveMapProvider,
  type MapProviderKey,
} from '@/config/mapProviders'

import type { Vehicle, VehicleStatus } from '@/types/fleet'

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

// 🔥 popup state
const popupEl = ref<HTMLDivElement | null>(null)
const selectedVehicle = ref<Vehicle | null>(null)
let popupOverlay: Overlay | null = null

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

function getVehicleKey(vehicle: Vehicle): string {
  return String(vehicle.vehicle_id || vehicle.id || vehicle.plate_no)
}


function normalizeCarType(value?: string | null): string {
  return String(value || 'bus')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, '-')
}

function getDirectionName(heading?: number | string | null): string {
  const deg = Number(heading ?? 0)

  if (Number.isNaN(deg)) return 'run'

  const normalized = ((deg % 360) + 360) % 360

  if (normalized >= 337.5 || normalized < 22.5) return 'run-n'
  if (normalized < 67.5) return 'run-en'
  if (normalized < 112.5) return 'run-e'
  if (normalized < 157.5) return 'run-es'
  if (normalized < 202.5) return 'run-s'
  if (normalized < 247.5) return 'run-ws'
  if (normalized < 292.5) return 'run-w'
  return 'run-wn'
}

function getVehicleIcon(vehicle: Vehicle): string {
  const carType = normalizeCarType(
      vehicle.icon ||
      vehicle.icon
  )

  if (vehicle.status === 'parking') {
    return `/cars/${carType}/stop.png`
  }

  if (vehicle.status === 'idle') {
    return `/cars/${carType}/acc-on.png`
  }

  if (vehicle.status === 'offline' || vehicle.status === 'no_gps') {
    return `/cars/${carType}/stop.png`
  }

  return `/cars/${carType}/${getDirectionName(vehicle.heading)}.png`
}

function getDirectionIcon(heading?: number | string | null): string {
  const deg = Number(heading ?? 0)

  if (Number.isNaN(deg)) return '/cars/bus/run.png'

  const normalized = ((deg % 360) + 360) % 360

  if (normalized >= 337.5 || normalized < 22.5) return '/cars/bus/run-n.png'
  if (normalized < 67.5) return '/cars/bus/run-en.png'
  if (normalized < 112.5) return '/cars/bus/run-e.png'
  if (normalized < 157.5) return '/cars/bus/run-es.png'
  if (normalized < 202.5) return '/cars/bus/run-s.png'
  if (normalized < 247.5) return '/cars/bus/run-ws.png'
  if (normalized < 292.5) return '/cars/bus/run-w.png'
  return '/cars/bus/run-wn.png'
}

function createVehicleStyle(vehicle: Vehicle, isSelected = false): Style {
  const color = getVehicleColor(vehicle.status)
  // const icon_path = vehicle.

  return new Style({
    // image: new Icon({
    //   src: '/icons/car.svg',
    //   color,
    //   scale: isSelected ? 0.72 : 0.56,
    //   anchor: [0.5, 1],
    //   rotation: vehicle.heading ? (Number(vehicle.heading) * Math.PI) / 180 : 0,
    // }),


    image: new Icon({
      src: getVehicleIcon(vehicle),
      scale: isSelected ? 0.85 : 0.68,
      anchor: [0.5, 0.5],
    }),

    text: new Text({
      text: vehicle.plate_no || '',
      offsetY: -28, // 🔥 ลดลง
      font: '700 12px system-ui',
      fill: new Fill({ color: '#ffffff' }),
      stroke: new Stroke({
        color: '#020617',
        width: 4,
      }),
    }),

  })
}

function renderVehicles() {
  if (!vehicleSource) return



  vehicleSource.clear()

  props.vehicles.forEach((vehicle) => {
    if (vehicle.lat == null || vehicle.lng == null) return

    const feature = new Feature({
      geometry: new Point(fromLonLat([Number(vehicle.lng), Number(vehicle.lat)])),
      vehicle,
    })

    const isSelected =
        Boolean(props.focusVehicleId) &&
        getVehicleKey(vehicle) === props.focusVehicleId

    feature.setStyle(createVehicleStyle(vehicle, isSelected))

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

// 🔥 focus + popup
function focusVehicle(vehicleId?: string | null) {
  if (!map || !vehicleSource || !vehicleId) return

  const feature = vehicleSource.getFeatures().find((f) => {
    const v = f.get('vehicle') as Vehicle | undefined
    return v ? getVehicleKey(v) === vehicleId : false
  })

  if (!feature) return

  const geometry = feature.getGeometry()
  if (!geometry) return

  const vehicle = feature.get('vehicle') as Vehicle
  selectedVehicle.value = vehicle
  popupOverlay?.setPosition(geometry.getCoordinates())

  map.getView().animate({
    center: geometry.getCoordinates(),
    zoom: 16,
    duration: 400,
  })
}

onMounted(async () => {
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

  // 🔥 init popup
  if (popupEl.value) {
    popupOverlay = new Overlay({
      element: popupEl.value,
      positioning: 'bottom-center',
      offset: [0, -20],
    })

    map.addOverlay(popupOverlay)
  }

  // 🔥 click marker
  map.on('singleclick', (event) => {
    let found = false

    map?.forEachFeatureAtPixel(event.pixel, (feature) => {
      const vehicle = feature.get('vehicle') as Vehicle

      if (vehicle) {
        selectedVehicle.value = vehicle
        popupOverlay?.setPosition(event.coordinate)
        emit('vehicle-click', vehicle)
        found = true
      }
    })

    if (!found) {
      selectedVehicle.value = null
      popupOverlay?.setPosition(undefined)
    }
  })

  renderVehicles()
})

watch(() => props.vehicles, renderVehicles, { deep: true })

watch(
    () => props.focusVehicleId,
    async (vehicleId) => {
      await nextTick()
      renderVehicles()
      focusVehicle(vehicleId)
    }
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
  flex: 1;
  position: relative;
  border-radius: 18px;
  overflow: hidden;
}

.map {
  width: 100%;
  height: 100%;
}

.map-popup {
  min-width: 220px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.95);
  color: #fff;
  font-size: 12px;
}

.popup-title {
  font-weight: 700;
  margin-bottom: 6px;
}

.popup-row {
  display: flex;
  justify-content: space-between;
  padding: 2px 0;
}

.map-control {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 20;
  width: 230px;
  padding: 10px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.88);
  border: 1px solid rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(14px);
}

</style>