<template>
  <div class="base-map-shell">
    <div ref="mapEl" class="base-map"></div>

    <div class="map-provider-label">
      {{ providerLabel }}
    </div>

    <div class="map-actions">
      <button type="button" title="Zoom in" @click.stop="zoomIn">
        +
      </button>

      <button type="button" title="Zoom out" @click.stop="zoomOut">
        −
      </button>

      <button type="button" title="Fit map" @click.stop="emitFit">
        <i class="pi pi-map-marker"></i>
      </button>
    </div>

    <slot
        :map="map"
        :base-layer="baseLayer"
        :popup-overlay="popupOverlay"
    />

    <div ref="popupEl" class="map-popup">
      <slot name="popup" />
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
  shallowRef,
  watch,
} from 'vue'

import Map from 'ol/Map'
import View from 'ol/View'
import TileLayer from 'ol/layer/Tile'
import XYZ from 'ol/source/XYZ'
import Overlay from 'ol/Overlay'
import { fromLonLat } from 'ol/proj'
import { defaults as defaultControls } from 'ol/control'

import { useAuthStore } from '@/stores/auth'

import {
  DEFAULT_MAP_PROVIDER,
  mapProviders,
  type MapLayerType,
  type MapProviderKey,
} from '@/config/mapProviders'

const props = withDefaults(
    defineProps<{
      provider?: MapProviderKey | null
      layer?: MapLayerType
      center?: [number, number]
      zoom?: number
    }>(),
    {
      provider: null,
      layer: 'default',
      center: () => [100.5018, 13.7563],
      zoom: 6,
    }
)

const emit = defineEmits<{
  ready: [
    payload: {
      map: Map
      baseLayer: TileLayer<XYZ>
      popupOverlay: Overlay
    }
  ]
  fit: []
}>()

const auth = useAuthStore()

const mapEl = ref<HTMLDivElement | null>(null)
const popupEl = ref<HTMLDivElement | null>(null)

const map = shallowRef<Map | null>(null)
const baseLayer = shallowRef<TileLayer<XYZ> | null>(null)
const popupOverlay = shallowRef<Overlay | null>(null)

const selectedProvider = computed<MapProviderKey>(() => {
  if (props.provider) {
    return props.provider
  }

  return resolveMapProvider(auth.config?.mapApi)
})

const providerLabel = computed(() => {
  return `${selectedProvider.value.toUpperCase()} • ${props.layer}`
})

onMounted(async () => {
  await nextTick()

  if (!mapEl.value || !popupEl.value) {
    return
  }

  const tileLayer = createBaseLayer(
      selectedProvider.value,
      props.layer
  )

  const overlay = new Overlay({
    element: popupEl.value,
    positioning: 'bottom-center',
    offset: [0, -20],
  })

  const olMap = new Map({
    target: mapEl.value,

    controls: defaultControls({
      zoom: false,
      rotate: false,
      attribution: false,
    }),

    layers: [
      tileLayer,
    ],

    overlays: [
      overlay,
    ],

    view: new View({
      center: fromLonLat(props.center),
      zoom: props.zoom,
    }),
  })

  map.value = olMap
  baseLayer.value = tileLayer
  popupOverlay.value = overlay

  emit('ready', {
    map: olMap,
    baseLayer: tileLayer,
    popupOverlay: overlay,
  })
})

watch(
    () => [
      selectedProvider.value,
      props.layer,
      auth.config?.mapApi_key,
    ],
    () => {
      if (!baseLayer.value) {
        return
      }

      const source = createTileSource(
          selectedProvider.value,
          props.layer
      )

      baseLayer.value.setSource(source)
      source.refresh()
    }
)

function resolveMapProvider(value?: string | null): MapProviderKey {
  const mapApi = String(value || '').toLowerCase()

  if (!mapApi) return 'osm'

  if (mapApi === 'google' || mapApi === 'googlemap') {
    return 'google'
  }

  if (mapApi === 'longdo') {
    return 'longdo'
  }

  if (mapApi === 'osm' || mapApi === 'openstreetmap') {
    return 'osm'
  }

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

function zoomIn() {
  if (!map.value) return

  const view = map.value.getView()
  const zoom = view.getZoom() ?? props.zoom

  view.animate({
    zoom: zoom + 1,
    duration: 200,
  })
}

function zoomOut() {
  if (!map.value) return

  const view = map.value.getView()
  const zoom = view.getZoom() ?? props.zoom

  view.animate({
    zoom: zoom - 1,
    duration: 200,
  })
}

function emitFit() {
  emit('fit')
}

function setPopupPosition(coordinate?: number[]) {
  popupOverlay.value?.setPosition(coordinate)
}

function getMap() {
  return map.value
}

function getPopupOverlay() {
  return popupOverlay.value
}

defineExpose({
  getMap,
  getPopupOverlay,
  setPopupPosition,
})

onBeforeUnmount(() => {
  if (map.value) {
    map.value.setTarget(undefined)
    map.value = null
  }
})
</script>

<style scoped>
.base-map-shell {
  width: 100%;
  height: 100%;
  flex: 1;
  position: relative;
  border-radius: 18px;
  overflow: hidden;
}

.base-map {
  width: 100%;
  height: 100%;
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

.map-actions button:hover {
  background: #22c55e;
  color: #052e16;
}

.map-popup {
  min-width: 250px;
  padding: 12px;

  border-radius: 12px;

  background: rgba(15, 23, 42, 0.95);
  color: #ffffff;

  font-size: 12px;
}
</style>