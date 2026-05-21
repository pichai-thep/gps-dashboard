<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapLayerController extends Controller
{
    private function customerId(Request $request): int
    {
        $gpsUserCustomer = $request->attributes->get('gpsUserCustomer');
        return (int) (
            $gpsUserCustomer->customer_id ?? 0
        );
    }

    private function dbConnection(Request $request){
        return $request->attributes->get('gps_connection');
    }

    public function pois(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                poi_id,
                poi_name,
                icon,
                ST_Y(g_poi) AS lat,
                ST_X(g_poi) AS lng
            FROM pois
            WHERE customer_customer_id = ?
            ORDER BY poi_name ASC
        ", [
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function stations(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                station_id,
                station_name,
                station_type,
                ST_Y(station_point) AS lat,
                ST_X(station_point) AS lng,
                radius,
                ST_AsText(station_polygon) AS polygon_wkt
            FROM stations
            WHERE customer_customer_id = ?
            ORDER BY station_name ASC
        ", [
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function forbiddenZones(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                id,
                zone_name,
                ST_AsText(polygon) AS polygon_wkt
            FROM forbidden_zones
            WHERE customer_id = ?
            ORDER BY zone_name ASC
        ", [
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }
}
