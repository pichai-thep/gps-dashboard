import { getForbiddenZones } from '@/services/forbiddenZone'
import { getPois } from '@/services/poi'
import { getStations } from '@/services/station'

export type MapLocationType = 'poi' | 'station' | 'forbidden-zone'

export interface MapLocationOption {
    key: string
    name: string
    type: MapLocationType
    geometryType: 'point' | 'polygon'
    lat?: number | null
    lng?: number | null
    polygon_wkt?: string | null
}

export async function getMapLocations(): Promise<MapLocationOption[]> {
    const [poisResult, stationsResult, forbiddenZonesResult] =
        await Promise.allSettled([
            getPois(),
            getStations(),
            getForbiddenZones(),
        ])

    const pois = poisResult.status === 'fulfilled' ? poisResult.value : []
    const stations = stationsResult.status === 'fulfilled' ? stationsResult.value : []
    const forbiddenZones = forbiddenZonesResult.status === 'fulfilled'
        ? forbiddenZonesResult.value
        : []

    return [
        ...pois.map((poi) => ({
            key: `poi:${poi.poi_id}`,
            name: poi.poi_name,
            type: 'poi' as const,
            geometryType: 'point' as const,
            lat: poi.lat,
            lng: poi.lng,
        })),
        ...stations.map((station) => ({
            key: `station:${station.station_id}`,
            name: station.station_name,
            type: 'station' as const,
            geometryType: station.station_type === 'polygon'
                ? 'polygon' as const
                : 'point' as const,
            lat: station.lat,
            lng: station.lng,
            polygon_wkt: station.polygon_wkt,
        })),
        ...forbiddenZones.map((zone) => ({
            key: `forbidden-zone:${zone.id}`,
            name: zone.zone_name,
            type: 'forbidden-zone' as const,
            geometryType: 'polygon' as const,
            polygon_wkt: zone.polygon_wkt,
        })),
    ]
}
