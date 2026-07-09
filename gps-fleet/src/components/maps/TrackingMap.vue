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
          :title="t('followVehicle')"
          :class="{ active: followVehicle }"
          @click.stop="toggleFollowVehicle"
      >
        <i class="pi pi-send"></i>
      </button>

      <button
          type="button"
          :title="t('showPopup')"
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
      <div v-if="popupVehicle">

        <div class="popup-title">{{ popupVehicle.plate_no }}</div>

        <div class="popup-row">
          <span>{{ t('imei') }}</span>
          <strong>{{ popupVehicle.imei }}</strong>
        </div>

        <div class="popup-row">
          <span>{{ t('status') }}</span>
          <strong>{{ popupVehicle.status }}</strong>
        </div>

        <div class="popup-row">
          <span>{{ t('speed') }}</span>
          <strong>
            {{ popupVehicle.speed ?? 0 }}
            km/h
          </strong>
        </div>

        <div class="popup-row" v-if="popupVehicle.fuel_left">
          <span>{{ t('fuelLeft') }}</span>
          <strong>{{ popupVehicle.fuel_left ?? '' }} %</strong>
        </div>

        <div class="popup-row" v-if="showInput1">
          <span>{{ t('input1') }}</span>
          <strong>{{ formatInputState(popupVehicle.input1) }}</strong>
        </div>

        <div class="popup-row" v-if="showInput2">
          <span>{{ t('input2') }}</span>
          <strong>{{ formatInputState(popupVehicle.input2) }}</strong>
        </div>

        <div class="popup-row">
          <span>{{ t('gpsTime') }}</span>
          <strong>
            {{ popupVehicle.gps_time ?? '-' }}
          </strong>
        </div>
        <div class="popup-row">
          <span>{{ t('updatedTime') }}</span>
          <strong>
            {{ popupVehicle.received_time ?? '-' }}
          </strong>
        </div>

        <div class="popup-row">
          <span>{{ t('latLon') }}</span>
          <strong>{{ popupVehicle.lat }}, {{ popupVehicle.lng }}</strong>
        </div>

        <div class="popup-row" v-if="popupVehicle.driver_name">
          <span>{{ t('driverName') }}</span>
          <strong>
            {{ popupVehicle.driver_name }}
          </strong>
        </div>

        <div class="popup-row" v-if="popupVehicle.driver_phone">
          <span>{{ t('driverPhone') }}</span>
          <strong>
            {{ popupVehicle.driver_phone }}
          </strong>
        </div>

        <div class="popup-row" v-if="popupVehicle.track3">
          <span>{{ t('licenseNo') }}</span>
          <strong>{{popupVehicle.track3 ?? '-' }}</strong>
        </div>

        <div class="popup-row" v-if="popupVehicle.track1">
          <span>{{ t('licenseName') }}</span>
          <strong>{{ formatDriverName(popupVehicle?.track1) }}</strong>
        </div>

        <div class="popup-row">
          <span>{{ t('address') }}</span>

          <button
              v-if="!selectedAddress"
              type="button"
              class="address-link"
              :disabled="addressLoading"
              @click.stop="loadSelectedAddress"
          >
            {{ addressLoading ? t('loading') : t('showAddress') }}
          </button>
        </div>

        <div v-if="selectedAddress" class="popup-address">
          {{ selectedAddress }}
        </div>

        <Button
            v-if="showEngineCutCommand"
            type="button"
            class="command-button"
            icon="pi pi-bolt"
            :label="t('sendCommand')"
            size="small"
            severity="warning"
            @click.stop="openCommandDialog"
        />

      </div>
    </template>

  </BaseMap>

  <Dialog
      v-model:visible="commandDialogVisible"
      modal
      :header="t('sendCommand')"
      :style="{ width: 'min(92vw, 440px)' }"
  >
    <div v-if="commandVehicle" class="command-dialog">
      <div class="command-intro">
        {{ t('commandTargetIntro') }}
      </div>

      <div class="command-target">
        <div class="command-target-row">
          <span>{{ t('plate') }}</span>
          <strong>{{ commandVehicle.plate_no || '-' }}</strong>
        </div>

        <div class="command-target-row">
          <span>{{ t('imei') }}</span>
          <strong>{{ commandVehicle.imei || '-' }}</strong>
        </div>
      </div>

      <div class="field">
        <label for="engine-command">{{ t('command') }}</label>

        <Dropdown
            id="engine-command"
            v-model="selectedCommand"
            :options="engineCommandOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
        />
      </div>

      <div class="field">
        <label for="engine-command-pwd">{{ t('confirmPassword') }}</label>

        <Password
            id="engine-command-pwd"
            v-model="commandPassword"
            :feedback="false"
            toggleMask
            class="w-full"
            :inputProps="{ autocomplete: 'current-password' }"
            @keydown.enter="sendCommand"
        />
      </div>

      <Message
          v-if="commandResult"
          :severity="commandResultSeverity"
          :closable="false"
      >
        {{ commandResult }}
      </Message>
    </div>

    <template #footer>
      <Button
          :label="t('cancel')"
          severity="secondary"
          outlined
          :disabled="commandSending"
          @click="commandDialogVisible = false"
      />

      <Button
          :label="t('sendCommand')"
          icon="pi pi-send"
          severity="warning"
          :loading="commandSending"
          :disabled="!canSendCommand"
          @click="sendCommand"
      />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import {
  computed,
  nextTick,
  ref,
  watch,
} from 'vue'

import BaseMap from './BaseMap.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Message from 'primevue/message'
import Password from 'primevue/password'

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
import { useI18n } from '@/i18n'
import {
  sendEngineCutCommand,
  type EngineCutCommand,
} from '@/services/tracking'

const auth = useAuthStore()
const { t } = useI18n()

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
const showInput1 = computed(() => Boolean(auth.features?.input1))
const showInput2 = computed(() => Boolean(auth.features?.input2))
const showEngineCutCommand = computed(() => Boolean(auth.features?.engineCut))
let selectedFeatureKey: string | null = null

const commandDialogVisible = ref(false)
const commandVehicle = ref<Vehicle | null>(null)
const selectedCommand = ref<EngineCutCommand>('engine-cut')
const commandPassword = ref('')
const commandSending = ref(false)
const commandResult = ref('')
const commandSucceeded = ref(false)

const engineCommandOptions = computed(() => [
  {
    label: t('engineCut'),
    value: 'engine-cut',
  },
  {
    label: t('engineCutCancel'),
    value: 'engine-cut-cancel',
  },
])

const canSendCommand = computed(() => {
  return Boolean(
      commandVehicle.value?.imei &&
      commandPassword.value.trim() &&
      !commandSending.value
  )
})

const commandResultSeverity = computed(() => {
  return commandSucceeded.value ? 'success' : 'error'
})

function isInputOn(value?: string | number | boolean | null): boolean {
  if (value === true) return true

  const normalized = String(value ?? '').trim().toLowerCase()

  return ['1', 'true', 'on'].includes(normalized)
}

function formatInputState(value?: string | number | boolean | null): string {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return isInputOn(value) ? 'ON' : 'OFF'
}

function openCommandDialog() {
  if (!popupVehicle.value) return

  commandVehicle.value = popupVehicle.value
  selectedCommand.value = 'engine-cut'
  commandPassword.value = ''
  commandResult.value = ''
  commandSucceeded.value = false
  commandDialogVisible.value = true
}

async function sendCommand() {
  if (!canSendCommand.value || !commandVehicle.value?.imei) return

  try {
    commandSending.value = true
    commandResult.value = ''
    commandSucceeded.value = false

    const response = await sendEngineCutCommand(
        selectedCommand.value,
        {
          imei: commandVehicle.value.imei,
          pwd: commandPassword.value.trim(),
        }
    )

    commandSucceeded.value = Number(response.code) === 1
    commandResult.value =
        response.message ||
        (
            commandSucceeded.value
                ? t('commandSuccess')
                : t('commandFailed')
        )

    if (response.ref_id) {
      commandResult.value += ` (${t('referenceId')}: ${response.ref_id})`
    }
  } catch (e: any) {
    commandSucceeded.value = false
    commandResult.value =
        e?.response?.data?.message ||
        e?.message ||
        t('commandFailed')
  } finally {
    commandSending.value = false
  }
}

async function loadSelectedAddress() {
  if (!popupVehicle.value) return

  const lat = Number(popupVehicle.value.lat)
  const lon = Number(popupVehicle.value.lng)

  if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
    selectedAddress.value = t('noAddressCoordinates')
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
    selectedAddress.value = t('mapApiKeyMissing')
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
    selectedAddress.value = t('loadingAddressFailed')
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

        if (showPopup.value) {
          selectedAddress.value = null
          addressLoading.value = false
          popupVehicle.value = vehicle
          popupOverlay?.setPosition(coordinate)
        }

        clickedFromMap.value = true
        emit('vehicle-click', vehicle)

        // if (showPopup.value && clickedFromMap.value) {
        //   selectedAddress.value = null
        //   addressLoading.value = false
        //   popupVehicle.value = vehicle
        //   popupOverlay?.setPosition(coordinate)
        // } else {
        //   closePopup()
        // }
        //
        // clickedFromMap.value = true
        // emit('vehicle-click', vehicle)

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

  if (showPopup.value) {
    selectedAddress.value = null
    addressLoading.value = false
    popupVehicle.value = vehicle
    popupOverlay?.setPosition(coordinate)
  }

  if (!animate) return

  const view = map.value.getView()
  const currentZoom = view.getZoom() ?? 16
  const targetZoom = currentZoom < 15 ? 16 : currentZoom

  view.cancelAnimations()

  view.animate({
    center: coordinate,
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

    case 'run':
      return `/cars/${carType}/${getDirectionName(vehicle.heading)}.png`

    case 'idle':
      return `/cars/${carType}/start.png`

    case 'park':
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

      const fromMap = clickedFromMap.value
      focusVehicle(
          vehicleId,
          !fromMap
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

.command-button {
  width: 100%;
  margin-top: 12px;
  justify-content: center;
}

.command-dialog {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.command-intro {
  color: #475569;
  line-height: 1.45;
}

.command-target {
  display: grid;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.command-target-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.command-target-row span {
  color: #64748b;
}

.command-target-row strong {
  color: #0f172a;
  text-align: right;
  overflow-wrap: anywhere;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  font-weight: 700;
  color: #334155;
}

.w-full {
  width: 100%;
}

</style>
