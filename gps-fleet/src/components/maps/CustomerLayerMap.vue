<script setup lang="ts">
import {
  onMounted,
  ref,
  watch,
} from 'vue'

import type OlMap from 'ol/Map'
import Feature from 'ol/Feature'

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
    'getLayers' | 'addLayer' | 'removeLayer'
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

  poiLayer.setVisible(false)
  stationLayer.setVisible(false)
  forbiddenZoneLayer.setVisible(false)

  restoreLayerState()
})

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
    // =====================
    // Circle
    // =====================

    if (
        row.station_type ===
        'circle' &&
        row.lat &&
        row.lng
    ) {
      const center =
          fromLonLat([
            Number(row.lng),
            Number(row.lat),
          ])

      const radius =
          Number(
              row.radius || 300,
          )

      source?.addFeature(
          new Feature(
              new CircleGeom(
                  center,
                  radius,
              ),
          ),
      )
    }

    // =====================
    // Polygon
    // =====================

    if (
        row.station_type ===
        'polygon' &&
        row.polygon_wkt
    ) {
      const coords =
          parsePolygonWkt(
              row.polygon_wkt,
          )

      if (!coords.length) {
        return
      }

      source?.addFeature(
          new Feature(
              new Polygon([
                coords.map((p) =>
                    fromLonLat([
                      p.lng,
                      p.lat,
                    ]),
                ),
              ]),
          ),
      )
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

    source?.addFeature(
        new Feature(
            new Polygon([
              coords.map((p) =>
                  fromLonLat([
                    p.lng,
                    p.lat,
                  ]),
              ),
            ]),
        ),
    )
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
