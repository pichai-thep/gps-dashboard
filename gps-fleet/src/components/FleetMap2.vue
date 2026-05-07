<template>
  <div class="map-shell">
    <div ref="mapEl" class="map"></div>

    <div class="map-provider-label">
      {{ providerLabel }}
    </div>

    <div
        v-if="selectedProvider === 'google'"
        class="map-control"
    >
      <Dropdown
          v-model="selectedLayer"
          :options="layerOptions"
          option-label="label"
          option-value="value"
          class="w-full"
      />
    </div>

    <div class="map-actions">
      <button title="Zoom in" type="button" @click.stop="zoomIn">
        +
      </button>

      <button title="Zoom out" type="button" @click.stop="zoomOut">
        −
      </button>

      <button title="Fit map" type="button" @click.stop="fitMap">
        <i class="pi pi-map-marker"></i>
      </button>

      <button
          v-if="mode !== 'history'"
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

    <div
        ref="popupEl"
        class="map-popup"
        v-show="showPopup && popupData"
    >
      <div class="popup-title">
        {{ popupData?.plate_no }}
      </div>

      <div class="popup-row">
        <span>IMEI</span>
        <strong>{{ popupData?.imei ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>Status</span>
        <strong>{{ popupData?.status ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>Speed</span>
        <strong>{{ popupData?.speed ?? 0 }} km/h</strong>
      </div>

      <div class="popup-row">
        <span>Heading</span>
        <strong>{{ popupData?.heading ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>ACC</span>
        <strong>{{ popupData?.acc_state ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>Fuel</span>
        <strong>{{ popupData?.fuel_left ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>GPS Time</span>
        <strong>{{ popupData?.gps_time ?? '-' }}</strong>
      </div>

      <div class="popup-row">
        <span>Updated</span>
        <strong>{{ popupData?.received_time ?? '-' }}</strong>
      </div>

      <div v-if="mode !== 'history'" class="popup-row">
        <span>ชื่อ</span>
        <strong>{{ formatDriverName(popupData?.track1) }}</strong>
      </div>

      <div v-if="mode !== 'history'" class="popup-row">
        <span>ใบขับขี่</span>
        <strong>{{ formatDriverLicense(popupData?.track3) }}</strong>
      </div>

      <div class="popup-row">
        <span>Address</span>

        <button
            v-if="!selectedAddress"
            type="button"
            class="address-link"
            :disabled="addressLoading"
            @click.stop="loadSelectedAddress"
        >
          {{ addressLoading ? 'Loading...' : 'Show address' }}
        </button>
      </div>

      <div v-if="selectedAddress" class="popup-address">
        {{ selectedAddress }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'

import Dropdown from 'primevue/dropdown'

import Map from 'ol/Map'
import View from 'ol/View'
import TileLayer from 'ol/layer/Tile'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import XYZ from 'ol/source/XYZ'
import Feature from 'ol/Feature'
import Point from 'ol/geom/Point'
import LineString from 'ol/geom/LineString'
import Overlay from 'ol/Overlay'

import { fromLonLat } from 'ol/proj'
import { boundingExtent } from 'ol/extent'
import { defaults as defaultControls } from 'ol/control'

import {
  Fill,
  Icon,
  Stroke,
  Style,
  Text,
} from 'ol/style'

import { useAuthStore } from '@/stores/auth'

import {
  DEFAULT_MAP_PROVIDER,
  mapProviders,
  type MapLayerType,
  type MapProviderKey,
} from '@/config/mapProviders'

import type {
  Vehicle,
  VehicleStatus,
} from '@/types/fleet'

type HistoryPoint = {
  lat?: number | string
  lng?: number | string
  latitude?: number | string
  longitude?: number | string

  speed?: number | string

  course?: number | string
  heading?: number | string
  direction?: number | string
  angle?: number | string
  degree?: number | string

  gps_time?: string
  gps_datetime?: string
  data_date?: string
  server_time?: string
  received_time?: string

  status?: string | number

  plate_no?: string
  imei?: string

  state?: number | string
  gps_status?: string

  acc?: string | number | boolean
  acc_state?: string | number | boolean

  fuel?: number | string
  fuel_per?: number | string
  fuel_left?: number | string
}

type PopupData = Partial<Vehicle> & {
  heading?: number | string
}

const props = withDefaults(
    defineProps<{
      vehicles?: Vehicle[]
      historyPoints?: HistoryPoint[]
      mode?: 'current' | 'history'
      focusVehicleId?: string | null
      focusHistoryIndex?: number | null
    }>(),
    {
      vehicles: () => [],
      historyPoints: () => [],
      mode: 'current',
      focusVehicleId: null,
      focusHistoryIndex: null,
    }
)

const emit = defineEmits<{
  'vehicle-click': [vehicle: Vehicle]
}>()

const auth = useAuthStore()

const mapEl = ref<HTMLDivElement | null>(null)
const popupEl = ref<HTMLDivElement | null>(null)

let map: Map | null = null
let baseLayer: TileLayer<XYZ> | null = null
let vehicleSource: VectorSource | null = null
let historySource: VectorSource | null = null
let popupOverlay: Overlay | null = null

const popupData = ref<PopupData | null>(null)

const addressLoading = ref(false)
const selectedAddress = ref<string | null>(null)
const addressCache = ref<Record<string, string>>({})

const followVehicle = ref(true)
const showPopup = ref(true)

const selectedLayer = ref<MapLayerType>('default')

const selectedProvider = computed<MapProviderKey>(() => {
  return resolveMapProvider(auth.config?.mapApi)
})

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

const providerLabel = computed(() => {
  return `${selectedProvider.value.toUpperCase()} • ${selectedLayer.value}`
})

onMounted(async () => {
  await nextTick()

  if (!mapEl.value) return

  baseLayer = createBaseLayer(
      selectedProvider.value,
      selectedLayer.value
  )

  vehicleSource = new VectorSource()
  historySource = new VectorSource()

  const historyLayer = new VectorLayer({
    source: historySource,
  })

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

    layers: [
      baseLayer,
      historyLayer,
      vehicleLayer,
    ],

    view: new View({
      center: fromLonLat([100.5018, 13.7563]),
      zoom: 6,
    }),
  })

  if (popupEl.value) {
    popupOverlay = new Overlay({
      element: popupEl.value,
      positioning: 'bottom-center',
      offset: [0, -20],
    })

    map.addOverlay(popupOverlay)
  }

  map.on('singleclick', handleMapClick)

  if (props.mode === 'history') {
    renderHistory()
  } else {
    renderVehicles()
  }
})

function handleMapClick(event: any) {
  if (!map) return

  if (props.mode === 'history') {
    handleHistoryClick(event)
    return
  }

  handleVehicleClick(event)
}

function handleHistoryClick(event: any) {
  if (!map) return

  let found = false

  map.forEachFeatureAtPixel(event.pixel, (feature) => {
    const history = feature.get('history') as HistoryPoint | undefined

    if (!history) return

    const geometry = feature.getGeometry()

    if (!(geometry instanceof Point)) return

    showHistoryPopup(
        history,
        geometry.getCoordinates()
    )

    found = true
  })

  if (!found) {
    closePopup()
  }
}

function handleVehicleClick(event: any) {
  if (!map) return

  let found = false

  map.forEachFeatureAtPixel(event.pixel, (feature) => {
    const vehicle = feature.get('vehicle') as Vehicle | undefined

    if (!vehicle) return

    popupData.value = vehicle
    selectedAddress.value = null
    followVehicle.value = true

    if (showPopup.value) {
      popupOverlay?.setPosition(event.coordinate)
    }

    emit('vehicle-click', vehicle)

    found = true
  })

  if (!found) {
    closePopup()
  }
}

function closePopup() {
  popupData.value = null
  selectedAddress.value = null
  popupOverlay?.setPosition(undefined)
}

function resolveMapProvider(value?: string | null): MapProviderKey {
  const mapApi = String(value || '').toLowerCase()

  if (mapApi === '') return 'osm'
  if (mapApi === 'google' || mapApi === 'googlemap') return 'google'
  if (mapApi === 'longdo') return 'longdo'
  if (mapApi === 'osm' || mapApi === 'openstreetmap') return 'osm'

  return DEFAULT_MAP_PROVIDER
}

function createTileSource(
    providerKey: MapProviderKey,
    layer: MapLayerType
): XYZ {
  const provider =
      mapProviders[providerKey] ||
      mapProviders[DEFAULT_MAP_PROVIDER]

  const url =
      typeof provider.url === 'function'
          ? provider.url(auth.config?.mapApi_key, layer)
          : provider.url

  return new XYZ({
    url,
    attributions: provider.attributions,
    crossOrigin: 'anonymous',
  })
}

function createBaseLayer(
    providerKey: MapProviderKey,
    layer: MapLayerType
): TileLayer<XYZ> {
  return new TileLayer({
    source: createTileSource(providerKey, layer),
  })
}

function getVehicleColor(status: VehicleStatus): string {
  return {
    running: '#22c55e',
    start: '#eab308',
    acc_on: '#f97316',
    parking: '#64748b',
    no_gps: '#3b82f6',
    offline: '#ef4444',
  }[status] || '#64748b'
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
  const carType = normalizeCarType(vehicle.icon)

  switch (vehicle.status) {
    case 'running':
      return `/cars/${carType}/${getDirectionName(vehicle.heading)}.png`

    case 'start':
      return `/cars/${carType}/start.png`

    case 'acc_on':
      return `/cars/${carType}/acc-on.png`

    case 'parking':
    case 'no_gps':
    case 'offline':
      return `/cars/${carType}/stop.png`

    default:
      return `/cars/${carType}/stop.png`
  }
}

function createVehicleStyle(
    vehicle: Vehicle,
    isSelected = false
): Style {
  const color = getVehicleColor(vehicle.status)

  return new Style({
    image: new Icon({
      src: getVehicleIcon(vehicle),
      scale: isSelected ? 0.85 : 0.68,
      anchor: [0.5, 0.5],
    }),

    text: new Text({
      text: vehicle.plate_no || '',
      offsetY: -28,
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
  if (props.mode === 'history') return
  if (!vehicleSource) return

  vehicleSource.clear()

  props.vehicles.forEach((vehicle) => {
    if (vehicle.lat == null || vehicle.lng == null) return

    const feature = new Feature({
      geometry: new Point(
          fromLonLat([
            Number(vehicle.lng),
            Number(vehicle.lat),
          ])
      ),
      vehicle,
    })

    const isSelected =
        Boolean(props.focusVehicleId) &&
        getVehicleKey(vehicle) === props.focusVehicleId

    feature.setStyle(
        createVehicleStyle(vehicle, isSelected)
    )

    vehicleSource?.addFeature(feature)
  })
}

function renderHistory() {
  if (!historySource) return

  historySource.clear()
  closePopup()

  const points = props.historyPoints || []

  if (!points.length) return

  const coordinates: number[][] = []
  const validPoints: HistoryPoint[] = []

  points.forEach((point) => {
    const latLng = getHistoryLatLng(point)

    if (!latLng) return

    const coordinate = fromLonLat([
      latLng.lng,
      latLng.lat,
    ])

    coordinates.push(coordinate)
    validPoints.push(point)
  })

  if (!coordinates.length) return

  const routeFeature = new Feature({
    geometry: new LineString(coordinates),
  })

  routeFeature.setStyle(
      new Style({
        stroke: new Stroke({
          color: '#ef4444',
          width: 4,
        }),
      })
  )

  historySource.addFeature(routeFeature)

  validPoints.forEach((point, index) => {
    const coordinate = coordinates[index]

    const feature = new Feature({
      geometry: new Point(coordinate),
      history: point,
      historyIndex: index,
    })

    feature.setStyle(
        createHistoryPointStyle(
            point,
            index,
            validPoints.length
        )
    )

    historySource?.addFeature(feature)
  })

  map?.getView().fit(
      boundingExtent(coordinates),
      {
        padding: [70, 70, 70, 70],
        duration: 500,
        maxZoom: 16,
      }
  )
}

function getHistoryLatLng(point: HistoryPoint) {
  const lat = Number(point.lat ?? point.latitude)
  const lng = Number(point.lng ?? point.longitude)

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null
  }

  if (lat === 0 || lng === 0) {
    return null
  }

  return { lat, lng }
}

function getHistoryHeading(point: HistoryPoint): number {
  const heading = Number(
      point.heading ??
      point.course ??
      point.direction ??
      point.angle ??
      point.degree ??
      0
  )

  if (!Number.isFinite(heading)) {
    return 0
  }

  return heading
}

function getHistoryStatusLabel(point: HistoryPoint): string {
  const state = Number(point.state ?? 0)
  const speed = Number(point.speed ?? 0)
  const gpsStatus = String(point.gps_status ?? '').toUpperCase()

  if (gpsStatus === 'V') return 'No GPS'
  if (state === 1 && speed > 0) return 'Running'
  if (state === 1 && speed <= 0) return 'Start'
  if (state === 0) return 'Parking'

  return String(point.status ?? '-')
}

function getHistoryColor(point: HistoryPoint): string {
  const state = Number(point.state ?? 0)
  const speed = Number(point.speed ?? 0)
  const gpsStatus = String(point.gps_status ?? '').toUpperCase()

  // gps_status = V => no_gps สีน้ำเงิน
  if (gpsStatus === 'V') {
    return '#3b82f6'
  }

  // state = 1 และ speed > 0 => running สีเขียว
  if (state === 1 && speed > 0) {
    return '#22c55e'
  }

  // state = 1 และ speed <= 0 => start สีเหลือง
  if (state === 1 && speed <= 0) {
    return '#eab308'
  }

  // state = 0 => parking สีแดง
  if (state === 0) {
    return '#ef4444'
  }

  return '#64748b'
}

function createArrowSvg(color: string): string {
  const svg = `
    <svg width="42" height="42" viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
      <circle cx="21" cy="21" r="16" fill="${color}" stroke="#020617" stroke-width="3"/>
      <path d="M21 6 L31 29 L21 23 L11 29 Z" fill="#ffffff"/>
    </svg>
  `

  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
}

function createHistoryPointStyle(
    point: HistoryPoint,
    index: number,
    total: number
): Style {
  const heading = getHistoryHeading(point)
  const color = getHistoryColor(point)

  const isStart = index === 0
  const isEnd = index === total - 1

  return new Style({

    // image: new Icon({
    //   src: createArrowSvg(color),
    //   scale: isStart || isEnd ? 1 : 0.82,
    //   anchor: [0.5, 0.5],
    //   rotation: (heading * Math.PI) / 180,
    // }),
    image: new Icon({
      src: createArrowSvg(color),
      scale: isStart || isEnd ? 0.62 : 0.6,
      anchor: [0.5, 0.5],
      rotation: (heading * Math.PI) / 180,
    }),

    text:
        isStart || isEnd
            ? new Text({
              text: isStart ? 'START' : 'END',
              offsetY: -24,
              font: '700 11px system-ui',
              fill: new Fill({ color: '#ffffff' }),
              stroke: new Stroke({
                color: '#020617',
                width: 4,
              }),
            })
            : undefined,
  })
}

function showHistoryPopup(
    point: HistoryPoint,
    coordinate: number[]
) {
  const latLng = getHistoryLatLng(point)

  popupData.value = {
    vehicle_id: 'history',
    plate_no: point.plate_no ?? 'History Point',
    imei: point.imei ?? '-',

    status: getHistoryStatusLabel(point),
    speed: Number(point.speed ?? 0),
    heading: getHistoryHeading(point),

    gps_time:
        point.gps_time ??
        point.data_date ??
        point.gps_datetime ??
        '-',

    received_time:
        point.server_time ??
        point.received_time ??
        point.gps_time ??
        point.data_date ??
        point.gps_datetime ??
        '-',

    lat: latLng?.lat,
    lng: latLng?.lng,

    acc_state:
        point.acc_state ??
        point.acc ??
        '-',

    fuel_left:
        point.fuel_left ??
        point.fuel ??
        point.fuel_per ??
        '-',
  } as PopupData

  selectedAddress.value = null

  if (showPopup.value) {
    popupOverlay?.setPosition(coordinate)
  }
}

function fitMap() {
  if (props.mode === 'history') {
    fitHistory()
    return
  }

  fitAllVehicles()
}

function fitHistory() {
  if (!map || !historySource) return

  const coordinates = historySource
      .getFeatures()
      .map((feature) => {
        const geometry = feature.getGeometry()

        if (geometry instanceof Point) {
          return geometry.getCoordinates()
        }

        return null
      })
      .filter((item): item is number[] => Array.isArray(item))

  if (!coordinates.length) return

  map.getView().fit(
      boundingExtent(coordinates),
      {
        padding: [70, 70, 70, 70],
        duration: 500,
        maxZoom: 16,
      }
  )
}

function fitAllVehicles() {
  if (!map || !vehicleSource) return

  const coordinates = vehicleSource
      .getFeatures()
      .map((feature) => feature.getGeometry()?.getCoordinates())
      .filter((item): item is number[] => Array.isArray(item))

  if (!coordinates.length) return

  map.getView().fit(
      boundingExtent(coordinates),
      {
        padding: [80, 80, 80, 80],
        duration: 500,
        maxZoom: 15,
      }
  )
}

function focusVehicle(vehicleId?: string | null) {
  if (props.mode === 'history') return
  if (!map || !vehicleSource || !vehicleId) return

  const feature = vehicleSource.getFeatures().find((f) => {
    const vehicle = f.get('vehicle') as Vehicle | undefined
    return vehicle ? getVehicleKey(vehicle) === vehicleId : false
  })

  if (!feature) return

  const geometry = feature.getGeometry()

  if (!(geometry instanceof Point)) return

  const vehicle = feature.get('vehicle') as Vehicle | undefined

  if (!vehicle) return

  const coordinate = geometry.getCoordinates()

  popupData.value = vehicle
  selectedAddress.value = null

  if (showPopup.value) {
    popupOverlay?.setPosition(coordinate)
  }

  map.getView().animate({
    center: coordinate,
    zoom: 16,
    duration: 400,
  })
}

function focusHistoryPoint(index: number) {
  if (props.mode !== 'history') return
  if (!map || !historySource) return

  const feature = historySource
      .getFeatures()
      .find((item) => item.get('historyIndex') === index)

  if (!feature) return

  const geometry = feature.getGeometry()

  if (!(geometry instanceof Point)) return

  const coordinate = geometry.getCoordinates()
  const history = feature.get('history') as HistoryPoint

  showHistoryPopup(history, coordinate)

  map.getView().animate({
    center: coordinate,
    zoom: 17,
    duration: 300,
  })
}

function changeLayer() {
  if (!map || !baseLayer) return

  const source = createTileSource(
      selectedProvider.value,
      selectedLayer.value
  )

  baseLayer.setSource(source)
  source.refresh()
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

function togglePopup() {
  showPopup.value = !showPopup.value

  if (!showPopup.value) {
    popupOverlay?.setPosition(undefined)
    return
  }

  if (!popupData.value) return

  if (props.mode === 'history') {
    const gpsTime = String(popupData.value.gps_time ?? '')

    const feature = historySource?.getFeatures().find((item) => {
      const history = item.get('history') as HistoryPoint | undefined

      if (!history) return false

      const historyTime = String(
          history.gps_time ??
          history.data_date ??
          history.gps_datetime ??
          ''
      )

      return historyTime === gpsTime
    })

    const geometry = feature?.getGeometry()

    if (geometry instanceof Point) {
      popupOverlay?.setPosition(geometry.getCoordinates())
    }

    return
  }

  const feature = vehicleSource?.getFeatures().find((item) => {
    const vehicle = item.get('vehicle') as Vehicle | undefined

    if (!vehicle) return false

    return getVehicleKey(vehicle) === getVehicleKey(popupData.value as Vehicle)
  })

  const geometry = feature?.getGeometry()

  if (geometry instanceof Point) {
    popupOverlay?.setPosition(geometry.getCoordinates())
  }
}

function getVehicleKey(vehicle: Vehicle): string {
  return String(vehicle.vehicle_id || vehicle.id || vehicle.plate_no)
}

async function loadSelectedAddress() {
  if (!popupData.value) return

  const lat = Number(popupData.value.lat)
  const lon = Number(popupData.value.lng)

  if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
    selectedAddress.value = 'ไม่พบพิกัด'
    return
  }

  const cacheKey = `${lat},${lon}`

  if (addressCache.value[cacheKey]) {
    selectedAddress.value = addressCache.value[cacheKey]
    return
  }

  const key =
      auth.config?.mapApi_key ||
      import.meta.env.VITE_LONGDOMAP_API_KEY

  if (!key) {
    selectedAddress.value = 'ไม่ได้ตั้งค่า Longdo API Key'
    return
  }

  try {
    addressLoading.value = true

    const url =
        `https://api.longdo.com/map/services/address?lon=${lon}&lat=${lat}&noelevation=1&key=${key}`

    const response = await fetch(url)
    const data = await response.json()
    const address = formatLongdoAddress(data)

    addressCache.value[cacheKey] = address
    selectedAddress.value = address
  } catch (e) {
    selectedAddress.value = 'โหลดที่อยู่ไม่สำเร็จ'
  } finally {
    addressLoading.value = false
  }
}

function formatLongdoAddress(data: any): string {
  return [
    data.aoi,
    data.road,
    data.subdistrict,
    data.district,
    data.province,
    data.postcode,
    data.country,
  ]
      .filter(Boolean)
      .join(' ')
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

  return [prefix, firstname, lastname]
      .filter(Boolean)
      .join(' ') || '-'
}

function formatDriverLicense(value?: string | null): string {
  return value?.trim() || '-'
}

watch(
    () => props.focusVehicleId,
    async (vehicleId) => {
      await nextTick()

      if (props.mode === 'history') return

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

      if (props.mode === 'history') return

      renderVehicles()

      if (props.focusVehicleId && followVehicle.value) {
        focusVehicle(props.focusVehicleId)
      }
    },
    { deep: true }
)

watch(
    () => props.historyPoints,
    async () => {
      await nextTick()

      if (props.mode !== 'history') return

      renderHistory()
    },
    { deep: true }
)

watch(
    () => props.focusHistoryIndex,
    async (index) => {
      await nextTick()

      if (props.mode !== 'history') return
      if (index === null || index === undefined) return

      focusHistoryPoint(index)
    }
)

watch(selectedLayer, () => {
  changeLayer()
})

watch(
    () => auth.config?.mapApi,
    () => {
      if (!map) return

      const nextLayer = createBaseLayer(
          selectedProvider.value,
          selectedLayer.value
      )

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
  width: 100%;
  height: 100%;
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
  min-width: 250px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.95);
  color: #ffffff;
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
  font-weight: 800;
  margin-bottom: 6px;
}

.popup-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 3px 0;
}

.popup-row span {
  color: #cbd5e1;
}

.popup-row strong {
  text-align: right;
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
  color: #ffffff;
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

.address-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: #60a5fa;
  font-weight: 700;
  cursor: pointer;
}

.address-link:disabled {
  opacity: 0.6;
  cursor: wait;
}

.popup-address {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
  color: #e5e7eb;
  line-height: 1.35;
}
</style>