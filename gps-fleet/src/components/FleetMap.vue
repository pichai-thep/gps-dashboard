<template>
  <div class="map-shell">
    <div ref="mapEl" class="map"></div>

    <div class="map-provider-label">
      {{ providerLabel }}
    </div>


    <div class="map-control" v-if="selectedProvider == 'google'">
      <Dropdown
          v-model="selectedLayer"
          :options="layerOptions"
          option-label="label"
          option-value="value"
          class="w-full"
      />
    </div>


    <div class="map-actions">
      <button title="Zoom in" type="button" @click.stop="zoomIn">+</button>
      <button title="Zoom out" type="button" @click.stop="zoomOut">−</button>
      <button type="button" @click.stop="fitAllVehicles" title="Fit all vehicles">
        <i class="pi pi-map-marker"></i>
      </button>
      <button
          title="Follow Vehicle"
          type="button"
          :class="{ active: followVehicle }"
          @click.stop="followVehicle = !followVehicle"
      >
        <i class="pi pi-send"></i>
      </button>

      <button
          title="Show Popup"
          type="button"
          :class="{ active: showPopup }"
          @click.stop="togglePopup"
      >
        <i class="pi pi-info-circle"></i>
      </button>

    </div>

    <!-- 🔥 POPUP -->
    <div ref="popupEl" class="map-popup" v-show="showPopup && selectedVehicle">

      <div class="popup-title">
        {{ selectedVehicle?.plate_no }}
      </div>

      <div class="popup-row">
        <span>IMEI</span>
        <strong>{{ selectedVehicle?.imei }}</strong>
      </div>

      <div class="popup-row">
        <span>acc_state</span>
        <strong>{{ selectedVehicle?.acc_state }}</strong>
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
        <span>Fuel left(%)</span>
        <strong>{{ selectedVehicle?.fuel_left ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>GPS Time</span>
        <strong>{{ selectedVehicle?.gps_time ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>Updated</span>
        <strong>{{ selectedVehicle?.received_time ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>ชื่อ</span>
        <strong>{{ formatDriverName(selectedVehicle?.track1) }}</strong>
      </div>

      <div class="popup-row">
        <span>ใบขับขี่</span>
        <strong>{{ formatDriverLicense(selectedVehicle?.track3) }}</strong>
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
import { defaults as defaultControls } from 'ol/control'
import { boundingExtent } from 'ol/extent'
import { useAuthStore } from '@/stores/auth'


import {
  DEFAULT_MAP_PROVIDER,
  mapProviders,
  type MapLayerType,
  type MapProviderKey,
} from '@/config/mapProviders'

import type { Vehicle, VehicleStatus } from '@/types/fleet'
const props = defineProps<{
  vehicles: Vehicle[]
  focusVehicleId?: string | null
}>()

const auth = useAuthStore()

const selectedProvider = computed<MapProviderKey>(() => {
  return resolveMapProvider(auth.config?.mapApi)
})

const selectedLayer = ref<MapLayerType>('default')

const layerOptions = computed(() => {
  if (selectedProvider.value === 'longdo') {
    return [
      { label: 'Default', value: 'default' },
      { label: 'Road', value: 'road' },
    ]
  }

  return [
    { label: 'Default', value: 'default' },
    { label: 'Satellite', value: 'satellite' },
    { label: 'Hybrid', value: 'hybrid' },
  ]
})

const followVehicle = ref(true)
const showPopup = ref(true);

const emit = defineEmits<{
  'vehicle-click': [vehicle: Vehicle]
}>()

const mapEl = ref<HTMLDivElement | null>(null)

let map: Map | null = null
let baseLayer: TileLayer<XYZ> | null = null
let vehicleSource: VectorSource | null = null

// 🔥 popup state
const popupEl = ref<HTMLDivElement | null>(null)
const selectedVehicle = ref<Vehicle | null>(null)
let popupOverlay: Overlay | null = null

function resolveMapProvider(value?: string | null): MapProviderKey {
  const mapApi = String(value || '').toLowerCase()
  if (mapApi === '' ) return 'osm'
  if (mapApi === 'google' || mapApi === 'googleMap') return 'google'
  if (mapApi === 'longdo') return 'longdo'
  if (mapApi === 'osm' || mapApi === 'openstreetmap') return 'osm'

  return DEFAULT_MAP_PROVIDER
}

const providerOptions = computed(() =>
    Object.entries(mapProviders).map(([value, provider]) => ({
      value: value as MapProviderKey,
      label: provider.label,
    }))
)

const providerLabel = computed(() => {
  return `${selectedProvider.value.toUpperCase()} • ${selectedLayer.value}`
})

onMounted(async () => {
  await nextTick()

  if (!mapEl.value) return

  baseLayer = createBaseLayer(selectedProvider.value, selectedLayer.value)
  vehicleSource = new VectorSource()

  const vehicleLayer = new VectorLayer({
    source: vehicleSource,
  })

  map = new Map({
    target: mapEl.value,
    controls: defaultControls({
      zoom: false,
      rotate: false,
      attribution: false,
    }),
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
        followVehicle.value = true
        selectedVehicle.value = vehicle

        if (showPopup.value) {
          popupOverlay?.setPosition(event.coordinate)
        }

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

// function createBaseLayer(providerKey: MapProviderKey): TileLayer<XYZ> {
//   const provider = mapProviders[providerKey] || mapProviders[DEFAULT_MAP_PROVIDER]
//
//   return new TileLayer({
//     source: new XYZ({
//       url: provider.url,
//       attributions: provider.attributions,
//       crossOrigin: 'anonymous',
//     }),
//   })
// }

function createTileSource(providerKey: MapProviderKey, layer: MapLayerType): XYZ {
  const provider = mapProviders[providerKey] || mapProviders[DEFAULT_MAP_PROVIDER]

  const url =
      typeof provider.url === 'function'
          ? provider.url(auth.config?.mapApi_key, layer)
          : provider.url

  console.log('CREATE TILE SOURCE', {
    providerKey,
    layer,
    url,
  })

  return new XYZ({
    url,
    attributions: provider.attributions,
    crossOrigin: 'anonymous',
  })
}

function createBaseLayer(providerKey: MapProviderKey, layer: MapLayerType): TileLayer<XYZ> {
  return new TileLayer({
    source: createTileSource(providerKey, layer),
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

function changeLayer() {
  if (!map || !baseLayer) return

  const source = createTileSource(selectedProvider.value, selectedLayer.value)

  baseLayer.setSource(source)
  source.refresh()

  console.log('LAYER CHANGED', selectedProvider.value, selectedLayer.value)
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
  if (!(geometry instanceof Point)) return

  const vehicle = feature.get('vehicle') as Vehicle | undefined
  if (!vehicle) return

  const coords = geometry.getCoordinates()

  selectedVehicle.value = vehicle

  if (showPopup.value) {
    popupOverlay?.setPosition(coords)
  }

  map.getView().animate({
    center: coords,
    zoom: 16,
    duration: 400,
  })
}

function zoomIn() {
  if (!map) return

  const view = map.getView()
  const zoom = view.getZoom() ?? 6

  view.animate({
    zoom: zoom + 1,
    duration: 200,
  })
}

function zoomOut() {
  if (!map) return

  const view = map.getView()
  const zoom = view.getZoom() ?? 6

  view.animate({
    zoom: zoom - 1,
    duration: 200,
  })
}

function fitAllVehicles() {
  if (!map || !vehicleSource) return

  const coordinates = vehicleSource
      .getFeatures()
      .map((feature) => feature.getGeometry()?.getCoordinates())
      .filter((item): item is number[] => Array.isArray(item))

  if (!coordinates.length) return

  map.getView().fit(boundingExtent(coordinates), {
    padding: [80, 80, 80, 80],
    duration: 500,
    maxZoom: 15,
  })
}

function cleanDriverText(value?: string | null): string {
  return String(value || '')
      .replace(/\^/g, ' ')
      .replace(/%/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
}

function formatDriverName(value?: string | null): string {
  if (!value) return '-'

  const parts = value.split('$').map(cleanDriverText)

  const lastname = parts[0] || ''
  const firstname = parts[1] || ''
  const prefix = parts[2] || ''

  return [prefix, firstname, lastname].filter(Boolean).join(' ') || '-'
}

function formatDriverLicense(value?: string | null): string {
  return value?.trim() || '-'
}

function togglePopup() {
  showPopup.value = !showPopup.value

  if (!showPopup.value) {
    popupOverlay?.setPosition(undefined)
    return
  }

  if (selectedVehicle.value) {
    const feature = vehicleSource?.getFeatures().find((f) => {
      const vehicle = f.get('vehicle') as Vehicle | undefined
      return vehicle ? getVehicleKey(vehicle) === getVehicleKey(selectedVehicle.value!) : false
    })

    const geometry = feature?.getGeometry()
    if (geometry) {
      popupOverlay?.setPosition(geometry.getCoordinates())
    }
  }
}

watch(
    () => props.focusVehicleId,
    async (vehicleId) => {
      await nextTick()
      renderVehicles()

      if (vehicleId) {
        focusVehicle(vehicleId)
      }
    }
)

watch(
    () => props.vehicles,
    async () => {
      await nextTick()
      renderVehicles()

      if (props.focusVehicleId && followVehicle.value) {
        focusVehicle(props.focusVehicleId)
      }
    },
    { deep: true }
)

watch(selectedLayer, () => {
  changeLayer()
})

watch(
    () => auth.config?.mapApi,
    () => {
      if (!map) return

      const nextLayer = createBaseLayer(selectedProvider.value, selectedLayer.value)

      map.getLayers().setAt(0, nextLayer)
      baseLayer = nextLayer
    }
)

watch(selectedProvider, (provider) => {
  if (provider === 'longdo') {
    selectedLayer.value = 'default'
  }
})

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
  min-width: 240px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.95);
  color: #fff;
  font-size: 12px;
}

.map-provider-label {
  position: absolute;
  bottom: 12px;
  left: 12px;
  z-index: 20;

  font-size: 11px;
  color: #cbd5f5;

  background: rgba(15, 23, 42, 0.7);
  padding: 4px 8px;
  border-radius: 6px;

  backdrop-filter: blur(6px);
  pointer-events: none;
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
  width: 150px;
  padding: 10px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.88);
  border: 1px solid rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(14px);
}

.map-actions {
  position: absolute;
  top: 16px;
  left: 16px;
  z-index: 30;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.map-actions button {
  width: 36px;
  height: 36px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.9);
  color: #fff;
  font-size: 18px;
  font-weight: 800;
  cursor: pointer;
  backdrop-filter: blur(12px);
}

.map-actions button:hover,
.map-actions button.active {
  background: #22c55e;
  color: #052e16;
}

</style>