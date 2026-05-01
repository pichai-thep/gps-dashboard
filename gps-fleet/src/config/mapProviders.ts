export type MapProviderKey =
    | 'osm'
    | 'googleRoadmap'
    | 'googleSatellite'
    | 'googleHybrid'

export type MapProvider = {
    label: string
    url: string
    attributions: string
}

export const DEFAULT_MAP_PROVIDER: MapProviderKey = 'osm'

export const mapProviders: Record<MapProviderKey, MapProvider> = {
    osm: {
        label: 'OpenStreetMap',
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attributions: '© OpenStreetMap contributors',
    },
    googleRoadmap: {
        label: 'Google Roadmap',
        url: 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
        attributions: '© Google',
    },
    googleSatellite: {
        label: 'Google Satellite',
        url: 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
        attributions: '© Google',
    },
    googleHybrid: {
        label: 'Google Hybrid',
        url: 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
        attributions: '© Google',
    },
}

const STORAGE_KEY = 'gps_fleet_map_provider'

export function getSavedMapProvider(): MapProviderKey {
    const saved = localStorage.getItem(STORAGE_KEY) as MapProviderKey | null

    if (saved && mapProviders[saved]) {
        return saved
    }

    return DEFAULT_MAP_PROVIDER
}

export function saveMapProvider(provider: MapProviderKey): void {
    localStorage.setItem(STORAGE_KEY, provider)
}