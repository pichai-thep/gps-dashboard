<template>
  <BaseMap
      ref="baseMapRef"
      @ready="handleMapReady"
      @fit="fitVehicles"
  >
    <template #default="{ map }">
    </template>

    <template #map-controls>
      <button
          type="button"
          title="Follow Vehicle"
          :class="{ active: followVehicle }"
          @click.stop="toggleFollowVehicle"
      >
        <i class="pi pi-send"></i>
      </button>

      <button
          type="button"
          title="Show Popup"
          :class="{ active: showPopup }"
          @click.stop="toggleShowPopup"
      >
        <i class="pi pi-comment"></i>
      </button>

      <CustomerLayerMap
          v-if="map"
          :map="map"
          :show-pois="false"
          :show-stations="false"
          :show-forbidden-zones="false"
      />

    </template>

    <template #popup>
      <!-- popup เดิมทั้งหมด -->
    </template>
  </BaseMap>
</template>

<script setup lang="ts">
import {
  nextTick,
  ref,
  watch,
} from 'vue'

import BaseMap from './BaseMap.vue'

import Map from 'ol/Map'
import Feature from 'ol/Feature'
import type { FeatureLike } from 'ol/Feature'
import Point from 'ol/geom/Point'

import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

import { fromLonLat } from 'ol/proj'
import { boundingExtent } from 'ol/extent'
import CustomerLayerMap from '@/components/maps/CustomerLayerMap.vue'

import {
  Fill,
  Icon,
  Stroke,
  Style,
  Text,
} from 'ol/style'

import type {
  Vehicle,
  VehicleStatus,
} from '@/types/fleet'
import {useAuthStore} from "@/stores/auth";

const auth = useAuthStore()

const props = defineProps<{
  vehicles?: Vehicle[]
  focusVehicleId?: string | null
}>()

const emit = defineEmits<{
  'vehicle-click': [vehicle: Vehicle]
}>()

const baseMapRef = ref()

// const map = ref<OlMap | null>(null)
const map = ref<Map | null>(null)

const vehicleSource =
    new VectorSource()

const vehicleLayer =
    new VectorLayer({
      source: vehicleSource,
    })

const followVehicle = ref(false)
const showPopup = ref(true)
const addressLoading = ref(false)
const selectedAddress = ref<string | null>(null)
const addressCache = ref<Record<string, string>>({})
const clickedFromMap = ref(false)
const vehicleFeatureMap = new globalThis.Map<string, Feature<Point>>()
let selectedFeatureKey: string | null = null

async function loadSelectedAddress() {
  if (!popupVehicle.value) return

  const lat = Number(popupVehicle.value.lat)
  const lon = Number(popupVehicle.value.lng)

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
    // data.postcode,
    // data.country,
  ]
      .filter(Boolean)
      .join(' ')
}


function toggleFollowVehicle() {
  followVehicle.value = !followVehicle.value

  if (
      followVehicle.value &&
      props.focusVehicleId
  ) {
    focusVehicle(
        props.focusVehicleId,
        false
    )
  }
}


const popupVehicle =
    ref<Vehicle | null>(null)

function toggleShowPopup() {
  showPopup.value = !showPopup.value

  if (!showPopup.value) {
    closePopup()
  }
}

let popupOverlay: any = null

function handleMapReady(payload: any) {

  map.value = payload.map
  if (!map.value) return

  popupOverlay =
      payload.popupOverlay

  map.value.addLayer(
      vehicleLayer
  )

  map.value.on(
      'singleclick',
      handleMapClick
  )

  renderVehicles()
}

function handleMapClick(event: any) {
  if (!map.value) return

  let found = false

  map.value.forEachFeatureAtPixel(
      event.pixel,
      (feature: FeatureLike) => {

        if (!(feature instanceof Feature)) {
          return
        }

        const vehicle = feature.get('vehicle')

        if (!vehicle) return

        const geometry = feature.getGeometry()

        if (!(geometry instanceof Point)) {
          return
        }

        const coordinate = geometry.getCoordinates()

        if (showPopup.value && clickedFromMap.value) {
          selectedAddress.value = null
          addressLoading.value = false
          popupVehicle.value = vehicle
          popupOverlay?.setPosition(coordinate)
        } else {
          closePopup()
        }

        clickedFromMap.value = true
        emit('vehicle-click', vehicle)

        found = true
      }
  )

  if (!found) {
    closePopup()
  }
}

function renderVehicles() {
  const vehicles = props.vehicles || []
  const nextKeys = new Set<string>()

  vehicles.forEach((vehicle) => {
    if (vehicle.lat == null || vehicle.lng == null) return

    const key = getVehicleKey(vehicle)
    nextKeys.add(key)

    const coordinate = fromLonLat([
      Number(vehicle.lng),
      Number(vehicle.lat),
    ])

    let feature = vehicleFeatureMap.get(key)

    if (!feature) {
      feature = new Feature({
        geometry: new Point(coordinate),
        vehicle,
      })

      vehicleFeatureMap.set(key, feature)
      vehicleSource.addFeature(feature)
    } else {
      feature.set('vehicle', vehicle)

      const geometry = feature.getGeometry()
      geometry?.setCoordinates(coordinate)
    }

    feature.setStyle(
        createVehicleStyle(
            vehicle,
            key === selectedFeatureKey
        )
    )
  })

  vehicleFeatureMap.forEach((feature, key) => {
    if (nextKeys.has(key)) return

    vehicleSource.removeFeature(feature)
    vehicleFeatureMap.delete(key)
  })
}

function fitVehicles() {

  if (!map.value) return

  const coordinates = vehicleSource
      .getFeatures()
      .map((feature: Feature) => {

        const geometry = feature.getGeometry()

        if (geometry instanceof Point) {
          return geometry.getCoordinates()
        }

        return null
      })
      .filter(
          (item): item is number[] =>
              Array.isArray(item)
      )

  if (!coordinates.length) {
    return
  }

  map.value.getView().fit(
      boundingExtent(coordinates),
      {
        padding: [80, 80, 80, 80],
        duration: 400,
        maxZoom: 15,
      }
  )
}

function focusVehicle(
    vehicleId: string,
    animate = true
) {
  if (!map.value) return

  const feature = vehicleFeatureMap.get(vehicleId)
  selectedFeatureKey = vehicleId

  vehicleFeatureMap.forEach((itemFeature, key) => {
    const itemVehicle = itemFeature.get('vehicle')
    itemFeature.setStyle(
        createVehicleStyle(
            itemVehicle,
            key === selectedFeatureKey
        )
    )
  })

  if (!feature) return

  const geometry = feature.getGeometry()

  if (!(geometry instanceof Point)) return

  const coordinate = geometry.getCoordinates()
  const vehicle = feature.get('vehicle')

  if (showPopup.value && clickedFromMap.value) {
    selectedAddress.value = null
    addressLoading.value = false
    popupVehicle.value = vehicle
    popupOverlay?.setPosition(coordinate)
  } else {
    closePopup()
  }

  if (!animate) return

  const view = map.value.getView()
  const currentZoom = view.getZoom() ?? 16
  const targetZoom = currentZoom < 15 ? 16 : currentZoom

  view.cancelAnimations()

  view.animate({
    center: coordinate,
    zoom: targetZoom,
    duration: 250,
  })
}

function closePopup() {

  popupVehicle.value = null

  selectedAddress.value = null
  addressLoading.value = false

  popupOverlay?.setPosition(
      undefined
  )
}

function getVehicleKey(
    vehicle: Vehicle
): string {

  return String(
      vehicle.vehicle_id ||
      vehicle.id ||
      vehicle.plate_no
  )
}

function normalizeCarType(
    value?: string | null
): string {

  return String(
      value || 'bus'
  )
      .trim()
      .toLowerCase()
      .replace(/\s+/g, '-')
}

function getDirectionName(
    heading?: number | string | null
): string {

  const deg =
      Number(heading ?? 0)

  if (Number.isNaN(deg)) {
    return 'run'
  }

  const normalized =
      ((deg % 360) + 360) % 360

  if (
      normalized >= 337.5 ||
      normalized < 22.5
  ) return 'run-n'

  if (normalized < 67.5) {
    return 'run-en'
  }

  if (normalized < 112.5) {
    return 'run-e'
  }

  if (normalized < 157.5) {
    return 'run-es'
  }

  if (normalized < 202.5) {
    return 'run-s'
  }

  if (normalized < 247.5) {
    return 'run-ws'
  }

  if (normalized < 292.5) {
    return 'run-w'
  }

  return 'run-wn'
}

function getVehicleIcon(
    vehicle: Vehicle
): string {

  const carType =
      normalizeCarType(
          vehicle.icon
      )

  switch (vehicle.status) {

    case 'running':
      return `/cars/${carType}/${getDirectionName(vehicle.heading)}.png`

    case 'idle':
      return `/cars/${carType}/start.png`

    case 'acc_on':
      return `/cars/${carType}/acc-on.png`

    case 'parking':
    case 'offline':
    case 'no_gps':

      return `/cars/${carType}/stop.png`

    default:
      return `/cars/${carType}/stop.png`
  }
}

function createVehicleStyle(
    vehicle: Vehicle,
    isSelected = false,
): Style {

  return new Style({

    image: new Icon({

      src:
          getVehicleIcon(
              vehicle
          ),

      scale:
          isSelected
              ? 0.85
              : 0.68,

      anchor: [0.5, 0.5],
    }),

    text: new Text({

      text:
          vehicle.plate_no || '',

      offsetY: -28,

      font:
          '700 12px system-ui',

      fill:
          new Fill({
            color: '#ffffff',
          }),

      stroke:
          new Stroke({
            color: '#020617',
            width: 4,
          }),
    }),
  })
}

function cleanDriverText(
    value?: string | null
): string {

  return String(value || '')
      .replace(/\^/g, ' ')
      .replace(/%/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
}

function formatDriverName(
    value?: string | null
): string {

  if (!value) {
    return '-'
  }

  const parts =
      value
          .split('$')
          .map(cleanDriverText)

  const lastname =
      parts[0] || ''

  const firstname =
      parts[1] || ''

  const prefix =
      parts[2] || ''

  return [
    prefix,
    firstname,
    lastname,
  ]
      .filter(Boolean)
      .join(' ')
}

watch(
    () => props.vehicles,
    async () => {
      await nextTick()

      renderVehicles()

      if (
          props.focusVehicleId &&
          followVehicle.value
      ) {
        focusVehicle(
            props.focusVehicleId,
            true
        )
      }
    }
)

watch(
    () => props.focusVehicleId,
    async (vehicleId, oldVehicleId) => {
      await nextTick()

      if (!vehicleId) {
        closePopup()
        return
      }

      if (vehicleId === oldVehicleId) {
        clickedFromMap.value = false
        return
      }

      focusVehicle(
          vehicleId,
          !clickedFromMap.value
      )

      clickedFromMap.value = false
    }
)
</script>

<style scoped>
.popup-title {
  font-size: large;
  font-weight: 800;
  margin-bottom: 8px;
}

.popup-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 0;
}

.popup-row span {
  color: #cbd5e1;
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