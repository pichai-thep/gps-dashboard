<template>
  <BaseMap
      ref="baseMapRef"
      @ready="handleMapReady"
      @fit="fitVehicles"
  >
    <template #popup>
      <div v-if="popupVehicle">
        <div class="popup-title">
          {{ popupVehicle.plate_no }}
        </div>

        <div class="popup-row">
          <span>Status</span>
          <strong>{{ popupVehicle.status }}</strong>
        </div>

        <div class="popup-row">
          <span>Speed</span>
          <strong>
            {{ popupVehicle.speed ?? 0 }}
            km/h
          </strong>
        </div>

        <div class="popup-row">
          <span>GPS Time</span>
          <strong>
            {{ popupVehicle.gps_time ?? '-' }}
          </strong>
        </div>

        <div class="popup-row">
          <span>Driver</span>

          <strong>
            {{
              formatDriverName(
                  popupVehicle.track1
              )
            }}
          </strong>
        </div>

        <div class="popup-row">
          <span>License</span>

          <strong>
            {{
              popupVehicle.track3 ??
              '-'
            }}
          </strong>
        </div>
      </div>
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
import Point from 'ol/geom/Point'

import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

import { fromLonLat } from 'ol/proj'
import { boundingExtent } from 'ol/extent'

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

const props = defineProps<{
  vehicles?: Vehicle[]
  focusVehicleId?: string | null
}>()

const emit = defineEmits<{
  'vehicle-click': [vehicle: Vehicle]
}>()

const baseMapRef = ref()

const map = ref<Map | null>(null)

const vehicleSource =
    new VectorSource()

const vehicleLayer =
    new VectorLayer({
      source: vehicleSource,
    })

const popupVehicle =
    ref<Vehicle | null>(null)

let popupOverlay: any = null

function handleMapReady(payload: any) {

  map.value = payload.map

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
      (feature) => {

        const vehicle =
            feature.get('vehicle')

        if (!vehicle) return

        const geometry =
            feature.getGeometry()

        if (!(geometry instanceof Point)) {
          return
        }

        popupVehicle.value =
            vehicle

        popupOverlay?.setPosition(
            geometry.getCoordinates()
        )

        emit(
            'vehicle-click',
            vehicle
        )

        found = true
      }
  )

  if (!found) {
    closePopup()
  }
}

function renderVehicles() {

  vehicleSource.clear()

  const vehicles =
      props.vehicles || []

  vehicles.forEach((vehicle) => {

    if (
        vehicle.lat == null ||
        vehicle.lng == null
    ) {
      return
    }

    const feature =
        new Feature({

          geometry:
              new Point(
                  fromLonLat([
                    Number(vehicle.lng),
                    Number(vehicle.lat),
                  ])
              ),

          vehicle,
        })

    const isSelected =
        Boolean(
            props.focusVehicleId
        ) &&
        getVehicleKey(vehicle) ===
        props.focusVehicleId

    feature.setStyle(
        createVehicleStyle(
            vehicle,
            isSelected
        )
    )

    vehicleSource.addFeature(
        feature
    )
  })
}

function fitVehicles() {

  if (!map.value) return

  const coordinates =
      vehicleSource
          .getFeatures()
          .map((feature) =>
              feature
                  .getGeometry()
                  ?.getCoordinates()
          )
          .filter(
              (
                  item
              ): item is number[] =>
                  Array.isArray(item)
          )

  if (!coordinates.length) {
    return
  }

  map.value.getView().fit(
      boundingExtent(coordinates),
      {
        padding:
            [80, 80, 80, 80],

        duration: 400,

        maxZoom: 15,
      }
  )
}

function focusVehicle(
    vehicleId: string
) {

  if (!map.value) return

  const feature =
      vehicleSource
          .getFeatures()
          .find((feature) => {

            const vehicle =
                feature.get('vehicle')

            return (
                getVehicleKey(
                    vehicle
                ) === vehicleId
            )
          })

  if (!feature) return

  const geometry =
      feature.getGeometry()

  if (!(geometry instanceof Point)) {
    return
  }

  const coordinate =
      geometry.getCoordinates()

  const vehicle =
      feature.get('vehicle')

  popupVehicle.value =
      vehicle

  popupOverlay?.setPosition(
      coordinate
  )

  map.value.getView().animate({
    center: coordinate,
    zoom: 16,
    duration: 300,
  })
}

function closePopup() {

  popupVehicle.value =
      null

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

function getVehicleColor(
    status: VehicleStatus
): string {

  return {
    running: '#22c55e',
    start: '#eab308',
    acc_on: '#f97316',
    parking: '#64748b',
    no_gps: '#3b82f6',
    offline: '#ef4444',
  }[status] || '#64748b'
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

    case 'start':
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
    },
    { deep: true }
)

watch(
    () => props.focusVehicleId,
    async (vehicleId) => {

      await nextTick()

      if (!vehicleId) {
        return
      }

      focusVehicle(vehicleId)
    }
)
</script>

<style scoped>
.popup-title {
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
</style>