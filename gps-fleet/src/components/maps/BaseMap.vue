<template>
  <div class="base-map-shell"
      :class="{ fullscreen: isFullscreen }"
  >
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

      <button
          type="button"
          title="Fullscreen"
          @click.stop="toggleFullscreen"
      >
        <i
            class="pi"
            :class="
              isFullscreen
                ? 'pi-window-minimize'
                : 'pi-window-maximize'
            "
        ></i>
      </button>

    </div>

    <div class="map-controls">
      <slot name="map-controls" />
    </div>

    <div v-if="isGoogleMap" class="map-layer-switcher">
      <button
          type="button"
          :class="{ active: currentLayer === 'default' }"
          @click.stop="currentLayer = 'default'"
      >
        Map
      </button>

      <button
          type="button"
          :class="{ active: currentLayer === 'satellite' }"
          @click.stop="currentLayer = 'satellite'"
      >
        Satellite
      </button>

      <button
          type="button"
          :class="{ active: currentLayer === 'hybrid' }"
          @click.stop="currentLayer = 'hybrid'"
      >
        Hybrid
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
import Overlay from 'ol/Overlay'
import TileLayer from 'ol/layer/Tile'
import XYZ from 'ol/source/XYZ'
import { defaults as defaultControls } from 'ol/control'
import { fromLonLat } from 'ol/proj'

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

const currentLayer = ref<MapLayerType>(props.layer)
const isFullscreen = ref(false)


const isGoogleMap = computed(() => {
  return selectedProvider.value === 'google'
})

const selectedProvider = computed<MapProviderKey>(() => {
  if (props.provider) {
    return props.provider
  }

  return resolveMapProvider(auth.config?.mapApi)
})

const providerLabel = computed(() => {
  return `${selectedProvider.value.toUpperCase()} • ${currentLayer.value}`
})

onMounted(async () => {
  await nextTick()

  window.addEventListener('keydown', onKeydown)
  if (!mapEl.value || !popupEl.value) {
    return
  }

  const tileLayer = createBaseLayer(
      selectedProvider.value,
      currentLayer.value
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

    layers: [tileLayer],
    overlays: [overlay],

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
      currentLayer.value,
      auth.config?.mapApi_key,
    ],
    () => {
      if (!baseLayer.value) {
        return
      }

      const source = createTileSource(
          selectedProvider.value,
          currentLayer.value
      )

      baseLayer.value.setSource(source)
      source.refresh()
    }
)

onBeforeUnmount(() => {
  map.value?.setTarget(undefined)
  map.value = null
  window.removeEventListener('keydown', onKeydown)
})

function toggleFullscreen() {
  isFullscreen.value = !isFullscreen.value

  setTimeout(() => {
    map.value?.updateSize()
  }, 300)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    isFullscreen.value = false
  }
}


function resolveMapProvider(value?: string | null): MapProviderKey {
  const mapApi = String(value || '').toLowerCase()

  if (!mapApi) {
    return 'osm'
  }

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
  const view = map.value?.getView()

  if (!view) {
    return
  }

  const zoom = view.getZoom() ?? props.zoom

  view.animate({
    zoom: zoom + 1,
    duration: 200,
  })
}

function zoomOut() {
  const view = map.value?.getView()

  if (!view) {
    return
  }

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
</script>

<style scoped>
.base-map-shell {
  position: relative;
  flex: 1;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 18px;
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

  padding: 4px 8px;
  border-radius: 6px;

  background: rgba(15, 23, 42, 0.7);
  color: #cbd5f5;

  font-size: 11px;

  pointer-events: none;
  backdrop-filter: blur(6px);
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

.map-actions button,
.map-controls :slotted(button) {
  width: 36px;
  height: 36px;

  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;

  background: rgba(15, 23, 42, 0.9);
  color: #ffffff;

  cursor: pointer;
  backdrop-filter: blur(12px);
}

.map-actions button {
  font-size: 18px;
  font-weight: 800;
}

.map-actions button:hover {
  background: #22c55e;
  color: #052e16;
}

.map-controls {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 40;

  display: flex;
  flex-direction: column;
  gap: 8px;
}

.map-controls :slotted(button:hover) {
  background: #2563eb;
}

.map-controls :slotted(button.active) {
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

.map-layer-switcher {
  position: absolute;
  top: 16px;
  left: 64px;
  z-index: 35;

  display: flex;
  gap: 6px;

  padding: 4px;
  border-radius: 12px;

  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(12px);
}

.map-layer-switcher button {
  height: 32px;
  padding: 0 10px;

  border: 0;
  border-radius: 8px;

  background: transparent;
  color: #ffffff;

  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.map-layer-switcher button:hover {
  background: rgba(255, 255, 255, 0.12);
}

.map-layer-switcher button.active {
  background: #22c55e;
  color: #052e16;
}

.base-map-shell.fullscreen {
  position: fixed;
  inset: 0;
  z-index: 9999;

  width: 100vw;
  height: 100vh;

  border-radius: 0;

  background: #020817;
}

.base-map-shell.fullscreen .base-map {
  width: 100%;
  height: 100%;
}

</style>