<template>
  <BaseMap
      ref="baseMapRef"
      @ready="handleMapReady"
      @fit="fitHistory"
  >
    <template #popup>
      <div v-if="popupData">
        <div class="popup-title">
          {{ popupData.plate_no }}
        </div>

        <div class="popup-row">
          <span>Status</span>
          <strong>{{ popupData.status }}</strong>
        </div>

        <div class="popup-row">
          <span>Speed</span>
          <strong>{{ popupData.speed }} km/h</strong>
        </div>

        <div class="popup-row">
          <span>GPS Time</span>
          <strong>{{ popupData.gps_time }}</strong>
        </div>

        <div class="popup-row">
          <span>Heading</span>
          <strong>{{ popupData.heading }}</strong>
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
import LineString from 'ol/geom/LineString'

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

type HistoryPoint = {
  lat?: number | string
  lng?: number | string

  latitude?: number | string
  longitude?: number | string

  speed?: number | string

  degree?: number | string
  heading?: number | string
  direction?: number | string
  angle?: number | string
  course?: number | string

  state?: number | string
  gps_status?: string

  gps_time?: string
  data_date?: string

  plate_no?: string
}

const props = defineProps<{
  historyPoints?: HistoryPoint[]
  focusHistoryIndex?: number | null
}>()

const baseMapRef = ref()

const map = ref<Map | null>(null)

const historySource =
    new VectorSource()

const historyLayer =
    new VectorLayer({
      source: historySource,
    })

const popupData =
    ref<any>(null)

let popupOverlay: any = null

function handleMapReady(payload: any) {

  map.value = payload.map

  popupOverlay =
      payload.popupOverlay

  map.value.addLayer(
      historyLayer
  )

  map.value.on(
      'singleclick',
      handleMapClick
  )

  renderHistory()
}

function handleMapClick(event: any) {

  if (!map.value) return

  let found = false

  map.value.forEachFeatureAtPixel(
      event.pixel,
      (feature) => {

        const point =
            feature.get('history')

        if (!point) return

        const geometry =
            feature.getGeometry()

        if (!(geometry instanceof Point)) {
          return
        }

        showPopup(
            point,
            geometry.getCoordinates()
        )

        found = true
      }
  )

  if (!found) {
    closePopup()
  }
}

function renderHistory() {

  historySource.clear()

  closePopup()

  const points =
      props.historyPoints || []

  if (!points.length) {
    return
  }

  const coordinates: number[][] = []

  const validPoints: HistoryPoint[] = []

  points.forEach((point) => {

    const lat =
        Number(
            point.lat ??
            point.latitude
        )

    const lng =
        Number(
            point.lng ??
            point.longitude
        )

    if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng)
    ) {
      return
    }

    const coordinate =
        fromLonLat([lng, lat])

    coordinates.push(coordinate)

    validPoints.push(point)
  })

  if (!coordinates.length) {
    return
  }

  const routeFeature =
      new Feature({
        geometry:
            new LineString(
                coordinates
            ),
      })

  routeFeature.setStyle(
      new Style({
        stroke: new Stroke({
          color: '#ef4444',
          width: 4,
        }),
      })
  )

  historySource.addFeature(
      routeFeature
  )

  validPoints.forEach(
      (point, index) => {

        const coordinate =
            coordinates[index]

        const feature =
            new Feature({
              geometry:
                  new Point(
                      coordinate
                  ),

              history: point,

              historyIndex: index,
            })

        feature.setStyle(
            createHistoryStyle(
                point,
                index,
                validPoints.length
            )
        )

        historySource.addFeature(
            feature
        )
      }
  )

  fitHistory()
}

function fitHistory() {

  if (!map.value) return

  const coordinates =
      historySource
          .getFeatures()
          .map((feature) => {

            const geometry =
                feature.getGeometry()

            if (
                geometry instanceof Point
            ) {
              return geometry.getCoordinates()
            }

            return null
          })
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
            [70, 70, 70, 70],

        duration: 400,

        maxZoom: 16,
      }
  )
}

function getHeading(
    point: HistoryPoint
): number {

  const heading =
      Number(
          point.degree ??
          point.heading ??
          point.direction ??
          point.angle ??
          point.course ??
          0
      )

  return Number.isFinite(heading)
      ? heading
      : 0
}

function resolveStatus(
    point: HistoryPoint
) {

  const state =
      Number(point.state ?? 0)

  const speed =
      Number(point.speed ?? 0)

  const gpsStatus =
      String(
          point.gps_status ?? ''
      ).toUpperCase()

  if (gpsStatus === 'V') {
    return {
      label: 'No GPS',
      color: '#3b82f6',
    }
  }

  if (
      state === 1 &&
      speed > 0
  ) {
    return {
      label: 'Running',
      color: '#22c55e',
    }
  }

  if (
      state === 1 &&
      speed <= 0
  ) {
    return {
      label: 'Start',
      color: '#eab308',
    }
  }

  return {
    label: 'Parking',
    color: '#ef4444',
  }
}

function createArrowSvg(
    color: string
): string {

  const svg = `
    <svg
      width="42"
      height="42"
      viewBox="0 0 42 42"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle
        cx="21"
        cy="21"
        r="16"
        fill="${color}"
        stroke="#111827"
        stroke-width="3"
      />

      <path
        d="M21 6 L31 29 L21 23 L11 29 Z"
        fill="#ffffff"
      />
    </svg>
  `

  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
}

function createHistoryStyle(
    point: HistoryPoint,
    index: number,
    total: number,
): Style {

  const status =
      resolveStatus(point)

  const isStart =
      index === 0

  const isEnd =
      index === total - 1

  return new Style({

    image: new Icon({

      src:
          createArrowSvg(
              status.color
          ),

      scale:
          isStart || isEnd
              ? 0.62
              : 0.55,

      anchor: [0.5, 0.5],

      rotation:
          (
              getHeading(point) *
              Math.PI
          ) / 180,
    }),

    text:
        isStart || isEnd
            ? new Text({

              text:
                  isStart
                      ? 'START'
                      : 'END',

              offsetY: -24,

              font:
                  '700 11px system-ui',

              fill:
                  new Fill({
                    color: '#ffffff',
                  }),

              stroke:
                  new Stroke({
                    color: '#111827',
                    width: 4,
                  }),
            })
            : undefined,
  })
}

function showPopup(
    point: HistoryPoint,
    coordinate: number[],
) {

  popupData.value = {

    plate_no:
        point.plate_no ??
        'History',

    status:
    resolveStatus(point)
        .label,

    speed:
        Number(
            point.speed ?? 0
        ),

    gps_time:
        point.gps_time ??
        point.data_date ??
        '-',

    heading:
        getHeading(point),
  }

  popupOverlay?.setPosition(
      coordinate
  )
}

function closePopup() {

  popupData.value = null

  popupOverlay?.setPosition(
      undefined
  )
}

function focusHistoryPoint(
    index: number
) {

  if (!map.value) return

  const feature =
      historySource
          .getFeatures()
          .find(
              (item) =>
                  item.get(
                      'historyIndex'
                  ) === index
          )

  if (!feature) return

  const geometry =
      feature.getGeometry()

  if (!(geometry instanceof Point)) {
    return
  }

  const coordinate =
      geometry.getCoordinates()

  const point =
      feature.get('history')

  showPopup(
      point,
      coordinate
  )

  map.value.getView().animate({
    center: coordinate,
    zoom: 17,
    duration: 300,
  })
}

watch(
    () => props.historyPoints,
    async () => {

      await nextTick()

      renderHistory()
    },
    { deep: true }
)

watch(
    () => props.focusHistoryIndex,
    async (index) => {

      await nextTick()

      if (
          index === null ||
          index === undefined
      ) {
        return
      }

      focusHistoryPoint(index)
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