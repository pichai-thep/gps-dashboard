// src/config/mapProviders.ts

export type MapLayerType = 'default' | 'satellite' | 'hybrid'

export const DEFAULT_MAP_PROVIDER = 'longdo'

export type MapProviderKey = 'longdo' | 'osm' | 'google'

type MapProvider = {
    label: string
    url: string | ((key?: string | null, layer?: MapLayerType) => string)
    attributions: string
}

/**
 * 🔥 Map Providers
 * - longdo รองรับ layer
 * - osm fallback ไม่มี layer จริง
 */
export const mapProviders: Record<MapProviderKey, MapProvider> = {
    longdo: {
        label: 'Longdo Map',
        url: (key?: string | null) => {
            return `https://ms.longdo.com/mmmap/tile.php?zoom={z}&x={x}&y={y}&key=${key || ''}`
        },
        attributions: '© Longdo Map',
    },

    google: {
        label: 'Google Map',
        url: (_key?: string | null, layer: MapLayerType = 'default') => {
            const lyrs = {
                default: 'm',
                road: 'm',
                satellite: 's',
                hybrid: 'y',
            }[layer]

            return `https://mt1.google.com/vt/lyrs=${lyrs}&x={x}&y={y}&z={z}`
        },
        attributions: '© Google',
    },

    osm: {
        label: 'OpenStreetMap',

        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',

        attributions: '© OpenStreetMap contributors',
    },
}

//
// 🔧 Helpers
//

export function getSavedMapProvider(): MapProviderKey {
    const saved = localStorage.getItem('map_provider') as MapProviderKey | null
    return saved && mapProviders[saved] ? saved : DEFAULT_MAP_PROVIDER
}

export function saveMapProvider(provider: MapProviderKey) {
    localStorage.setItem('map_provider', provider)
}