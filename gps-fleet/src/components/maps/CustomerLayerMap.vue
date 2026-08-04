<script setup lang="ts">
import {
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'

import type OlMap from 'ol/Map'
import Feature from 'ol/Feature'
import Overlay from 'ol/Overlay'

import Point from 'ol/geom/Point'
import Polygon from 'ol/geom/Polygon'
import CircleGeom from 'ol/geom/Circle'

import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

import {
  Style,
  Fill,
  Stroke,
  Circle as CircleStyle,
  Icon,
} from 'ol/style'

import {fromLonLat} from 'ol/proj'
import {
  getCustomerPois,
  getCustomerStations,
  getCustomerForbiddenZones,
} from '@/services/mapLayer'
import { useI18n } from '@/i18n'

import {poiIconRegistry} from '@/constants/poiIcons'
const { t } = useI18n()

type OlMapLike = Pick<
    OlMap,
    | 'addLayer'
    | 'removeLayer'
    | 'addOverlay'
    | 'removeOverlay'
    | 'on'
    | 'un'
    | 'forEachFeatureAtPixel'
>

const props = withDefaults(
    defineProps<{
      map: OlMapLike

      showPois?: boolean
      showStations?: boolean
      showForbiddenZones?: boolean
    }>(),
    {
      showPois: false,
      showStations: false,
      showForbiddenZones: false,
    },
)

const panelVisible = ref(false)
const poiVisible = ref(props.showPois)
const stationVisible = ref(props.showStations)
const forbiddenVisible = ref(
    props.showForbiddenZones,
)
const poiLoaded = ref(false)
const stationLoaded = ref(false)
const forbiddenLoaded = ref(false)

const poiLoading = ref(false)
const stationLoading = ref(false)
const forbiddenLoading = ref(false)
const hoverTooltipEl = ref<HTMLDivElement | null>(null)
const hoverName = ref('')

let hoverOverlay: Overlay | null = null

// =========================
// POI Layer
// =========================

const poiLayer = new VectorLayer({
  source: new VectorSource(),

  style: (feature) => {
    const icon = feature.get('icon')

    const iconPath =
        poiIconRegistry[
            icon as keyof typeof poiIconRegistry
            ]?.mapIcon ||
        '/poi-icons/default.svg'

    return [
      // BG
      new Style({
        image: new CircleStyle({
          radius: 18,

          fill: new Fill({
            color: '#ffffff',
          }),

          stroke: new Stroke({
            color: '#10b981',
            width: 2,
          }),
        }),
      }),

      // SVG
      new Style({
        image: new Icon({
          src: iconPath,
          scale: 0.7,
          anchor: [0.5, 0.5],
        }),
      }),
    ]
  },
})

poiLayer.setZIndex(30)

// =========================
// Station Layer
// =========================

const stationLayer = new VectorLayer({
  source: new VectorSource(),

  style: new Style({
    stroke: new Stroke({
      color: '#2563eb',
      width: 2,
    }),

    fill: new Fill({
      color: 'rgba(37,99,235,0.12)',
    }),

    image: new CircleStyle({
      radius: 6,

      fill: new Fill({
        color: '#2563eb',
      }),

      stroke: new Stroke({
        color: '#ffffff',
        width: 2,
      }),
    }),
  }),
})

stationLayer.setZIndex(20)

// =========================
// Forbidden Zone Layer
// =========================

const forbiddenZoneLayer =
    new VectorLayer({
      source: new VectorSource(),

      style: new Style({
        stroke: new Stroke({
          color: '#ef4444',
          width: 2,
        }),

        fill: new Fill({
          color: 'rgba(239,68,68,0.14)',
        }),
      }),
    })

forbiddenZoneLayer.setZIndex(10)

onMounted(() => {
  props.map.addLayer(poiLayer)
  props.map.addLayer(stationLayer)
  props.map.addLayer(forbiddenZoneLayer)

  if (hoverTooltipEl.value) {
    hoverOverlay = new Overlay({
      element: hoverTooltipEl.value,
      positioning: 'bottom-center',
      offset: [0, -12],
      stopEvent: false,
      insertFirst: false,
    })

    props.map.addOverlay(hoverOverlay)
    props.map.on('singleclick', handleMapClick)
  }

  poiLayer.setVisible(false)
  stationLayer.setVisible(false)
  forbiddenZoneLayer.setVisible(false)

  restoreLayerState()
})

onBeforeUnmount(() => {
  props.map.un('singleclick', handleMapClick)

  if (hoverOverlay) {
    props.map.removeOverlay(hoverOverlay)
    hoverOverlay = null
  }

  props.map.removeLayer(poiLayer)
  props.map.removeLayer(stationLayer)
  props.map.removeLayer(forbiddenZoneLayer)
})

function handleMapClick(event: any) {
  if (!hoverOverlay) {
    hideHoverTooltip()
    return
  }

  const feature = props.map.forEachFeatureAtPixel(
      event.pixel,
      (candidate) =>
          candidate.get('overlayName')
              ? candidate
              : undefined,
      {
        hitTolerance: 8,
      },
  )

  const name = String(feature?.get('overlayName') ?? '').trim()

  if (!name) {
    hideHoverTooltip()
    return
  }

  hoverName.value = name
  hoverOverlay.setPosition(event.coordinate)
}

function hideHoverTooltip() {
  hoverName.value = ''
  hoverOverlay?.setPosition(undefined)
}

// =========================
// Watch
// =========================
watch(poiVisible, async (v) => {
  if (v) {
    await ensurePoisLoaded()
  }

  toggleLayer(poiLayer, v)
  localStorage.setItem('map.layer.poi', String(v))
})

watch(stationVisible, async (v) => {
  if (v) {
    await ensureStationsLoaded()
  }

  toggleLayer(stationLayer, v)
  localStorage.setItem('map.layer.station', String(v))
})

watch(forbiddenVisible, async (v) => {
  if (v) {
    await ensureForbiddenZonesLoaded()
  }

  toggleLayer(forbiddenZoneLayer, v)
  localStorage.setItem('map.layer.forbidden', String(v))
})


async function ensurePoisLoaded() {
  if (poiLoaded.value || poiLoading.value) return

  poiLoading.value = true

  try {
    const rows = await getCustomerPois()
    renderPois(rows)
    poiLoaded.value = true
  } finally {
    poiLoading.value = false
  }
}

async function ensureStationsLoaded() {
  if (stationLoaded.value || stationLoading.value) return

  stationLoading.value = true

  try {
    const rows = await getCustomerStations()
    renderStations(rows)
    stationLoaded.value = true
  } finally {
    stationLoading.value = false
  }
}

async function ensureForbiddenZonesLoaded() {
  if (forbiddenLoaded.value || forbiddenLoading.value) return

  forbiddenLoading.value = true

  try {
    const rows = await getCustomerForbiddenZones()
    renderForbiddenZones(rows)
    forbiddenLoaded.value = true
  } finally {
    forbiddenLoading.value = false
  }
}

// =========================
// Restore localStorage
// =========================

function restoreLayerState() {
  const poi =
      localStorage.getItem(
          'map.layer.poi',
      )

  const station =
      localStorage.getItem(
          'map.layer.station',
      )

  const forbidden =
      localStorage.getItem(
          'map.layer.forbidden',
      )

  if (poi !== null) {
    poiVisible.value =
        poi === 'true'
  }

  if (station !== null) {
    stationVisible.value =
        station === 'true'
  }

  if (forbidden !== null) {
    forbiddenVisible.value =
        forbidden === 'true'
  }
}

// =========================
// Ensure layers
// =========================

function toggleLayer(
    layer: VectorLayer<any>,
    visible: boolean,
) {
  hideHoverTooltip()
  layer.setVisible(visible)

}

// =========================
// Load API
// =========================


// =========================
// Render POI
// =========================

function renderPois(rows: any[]) {
  const source =
      poiLayer.getSource()

  source?.clear()

  rows.forEach((row) => {
    if (
        !row.lat ||
        !row.lng
    ) {
      return
    }

    const feature =
        new Feature({
          geometry: new Point(
              fromLonLat([
                Number(row.lng),
                Number(row.lat),
              ]),
          ),
        })

    feature.set(
        'icon',
        row.icon,
    )

    feature.set(
        'overlayName',
        row.poi_name ?? row.name ?? '',
    )

    source?.addFeature(feature)
  })
}

// =========================
// Render Station
// =========================

function renderStations(
    rows: any[],
) {
  const source =
      stationLayer.getSource()

  source?.clear()

  rows.forEach((row) => {
    const stationType = String(row.station_type ?? 'circle')
        .trim()
        .toLowerCase()
    const lat = Number(row.lat)
    const lng = Number(row.lng)

    // =====================
    // Circle
    // =====================

    if (
        stationType === 'circle' &&
        Number.isFinite(lat) &&
        Number.isFinite(lng)
    ) {
      const center =
          fromLonLat([
            lng,
            lat,
          ])

      const parsedRadius = Number(row.radius)
      const radius = Number.isFinite(parsedRadius) && parsedRadius > 0
          ? parsedRadius
          : 300

      const circleFeature = new Feature(
          new CircleGeom(
              center,
              radius,
          ),
      )

      const centerFeature = new Feature(
          new Point(center),
      )

      const stationName = row.station_name ?? row.name ?? ''

      circleFeature.set(
          'overlayName',
          stationName,
      )

      centerFeature.set(
          'overlayName',
          stationName,
      )

      source?.addFeatures([
        circleFeature,
        centerFeature,
      ])
    }

    // =====================
    // Polygon
    // =====================

    if (
        stationType === 'polygon' &&
        row.polygon_wkt
    ) {
      const coords =
          parsePolygonWkt(
              row.polygon_wkt,
          )

      if (!coords.length) {
        return
      }

      const feature = new Feature(
          new Polygon([
            coords.map((p) =>
                fromLonLat([
                  p.lng,
                  p.lat,
                ]),
            ),
          ]),
      )

      feature.set(
          'overlayName',
          row.station_name ?? row.name ?? '',
      )

      source?.addFeature(feature)
    }
  })
}

// =========================
// Render Forbidden Zone
// =========================

function renderForbiddenZones(
    rows: any[],
) {
  const source =
      forbiddenZoneLayer.getSource()

  source?.clear()

  rows.forEach((row) => {
    if (!row.polygon_wkt) {
      return
    }

    const coords =
        parsePolygonWkt(
            row.polygon_wkt,
        )

    if (!coords.length) {
      return
    }

    const feature = new Feature(
        new Polygon([
          coords.map((p) =>
              fromLonLat([
                p.lng,
                p.lat,
              ]),
          ),
        ]),
    )

    feature.set(
        'overlayName',
        row.zone_name ?? row.name ?? '',
    )

    source?.addFeature(feature)
  })
}

// =========================
// Parse WKT
// =========================

function parsePolygonWkt(
    wkt?: string | null,
) {
  if (!wkt) {
    return []
  }

  const text = wkt
      .replace(
          'POLYGON((',
          '',
      )
      .replace('))', '')

  return text
      .split(',')
      .map((pair) => {
        const [lng, lat] =
            pair
                .trim()
                .split(' ')
                .map(Number)

        return {
          lng,
          lat,
        }
      })
      .filter(
          (p) =>
              Number.isFinite(
                  p.lng,
              ) &&
              Number.isFinite(
                  p.lat,
              ),
      )
}
</script>

<template>
  <div class="customer-layer-control">
    <div
        ref="hoverTooltipEl"
        class="map-feature-tooltip"
        :class="{ visible: hoverName }"
    >
      {{ hoverName }}
    </div>

    <button
        type="button"
        class="layer-toggle-button"
        :class="{ active: panelVisible }"
        :title="t('customerLayerTitle')"
        @click.stop="panelVisible = !panelVisible"
    >
      <i class="pi pi-clone"></i>
    </button>

    <div
        v-if="panelVisible"
        class="customer-layer-panel"
    >
      <label>
        <input v-model="poiVisible" type="checkbox"/>
        <span>{{ t('poisLayer') }}</span>
      </label>

      <label>
        <input v-model="stationVisible" type="checkbox"/>
        <span>{{ t('stationsLayer') }}</span>
      </label>

      <label>
        <input v-model="forbiddenVisible" type="checkbox"/>
        <span>{{ t('forbiddenZonesLayer') }}</span>
      </label>
    </div>
  </div>
</template>

<style scoped>

.customer-layer-control {
  position: relative;
  z-index: 49;
}

.map-feature-tooltip {
  max-width: 240px;
  padding: 6px 10px;
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 8px;

  background: rgba(15, 23, 42, 0.94);
  color: #ffffff;

  font-size: 12px;
  font-weight: 700;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;

  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.3);
  backdrop-filter: blur(10px);
  opacity: 0;
  pointer-events: none;
  transform: translateY(2px);
  transition: opacity 120ms ease, transform 120ms ease;
}

.map-feature-tooltip.visible {
  opacity: 1;
  transform: translateY(0);
}

.layer-toggle-button {
  width: 36px;
  height: 36px;

  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;

  background: rgba(15, 23, 42, 0.9);
  color: #ffffff;

  cursor: pointer;
  backdrop-filter: blur(12px);
}

.layer-toggle-button:hover,
.layer-toggle-button.active {
  background: #22c55e;
  color: #052e16;
}

.customer-layer-panel {
  position: absolute;
  top: 48px;
  right: 0;

  min-width: 190px;
  padding: 12px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.customer-layer-panel label {
  display: flex;
  align-items: center;
  gap: 8px;

  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
}

.customer-layer-panel input {
  accent-color: #22c55e;
}
</style>
