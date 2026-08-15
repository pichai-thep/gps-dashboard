<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StationController extends Controller
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

    public function index(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                station_id,
                station_name,
                st_X(station_point) AS lng,
                st_Y(station_point) AS lat,
                radius,
                station_type,
                ST_AsText(station_polygon) AS polygon_wkt,
                customer_customer_id,
                created_date AS created_at,
                modified_date AS modified_at
            FROM stations
            WHERE customer_customer_id = ?
            ORDER BY station_id DESC
        ", [$customerId]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'station_name' => 'required|string|max:100',
            'station_type' => 'required|string|in:circle,polygon',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'radius' => 'nullable|numeric|min:0',
            'polygon' => 'nullable|array',
        ]);

        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        if ($request->station_type === 'circle') {
            $request->validate([
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'radius' => 'required|numeric|min:1',
            ]);

            DB::connection($dbConnection)->insert("
                INSERT INTO stations (
                    station_name,
                    station_point,
                    radius,
                    station_type,
                    station_polygon,
                    customer_customer_id,
                    created_date,
                    modified_date
                )
                VALUES (?, ST_GeomFromText(?,0), ?, ?, NULL, ?, NOW(), NOW())
            ", [
                $request->station_name,
                "POINT({$request->lng} {$request->lat})",
                $request->radius,
                'circle',
                $customerId,
            ]);
        }

        if ($request->station_type === 'polygon') {
            $request->validate([
                'polygon' => 'required|array|min:3',
                'polygon.*.lat' => 'required|numeric',
                'polygon.*.lng' => 'required|numeric',
            ]);

            $wkt = $this->polygonToWkt($request->polygon);

            DB::connection($dbConnection)->insert("
                INSERT INTO stations (
                    station_name,
                    station_point,
                    radius,
                    station_type,
                    station_polygon,
                    customer_customer_id,
                    created_date,
                    modified_date
                )
                VALUES (?, NULL, NULL, ?, ST_GeomFromText(?,0), ?, NOW(), NOW())
            ", [
                $request->station_name,
                'polygon',
                $wkt,
                $customerId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Station created',
        ]);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'station_name' => 'required|string|max:100',
            'station_type' => 'required|string|in:circle,polygon',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'radius' => 'nullable|numeric|min:0',
            'polygon' => 'nullable|array',
        ]);

        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $exists = DB::connection($dbConnection)->selectOne("
            SELECT station_id
            FROM stations
            WHERE station_id = ?
              AND customer_customer_id = ?
        ", [$id, $customerId]);

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Station not found',
            ], 404);
        }

        if ($request->station_type === 'circle') {
            $request->validate([
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'radius' => 'required|numeric|min:1',
            ]);

            DB::connection($dbConnection)->update("
                UPDATE stations
                SET
                    station_name = ?,
                    station_point = ST_GeomFromText(?,0),
                    radius = ?,
                    station_type = ?,
                    station_polygon = NULL,
                    modified_date = NOW()
                WHERE station_id = ?
                  AND customer_customer_id = ?
            ", [
                $request->station_name,
                "POINT({$request->lng} {$request->lat})",
                $request->radius,
                'circle',
                $id,
                $customerId,
            ]);
        }

        if ($request->station_type === 'polygon') {
            $request->validate([
                'polygon' => 'required|array|min:3',
                'polygon.*.lat' => 'required|numeric',
                'polygon.*.lng' => 'required|numeric',
            ]);

            $wkt = $this->polygonToWkt($request->polygon);

            DB::connection($dbConnection)->update("
                UPDATE stations
                SET
                    station_name = ?,
                    station_point = NULL,
                    radius = NULL,
                    station_type = ?,
                    station_polygon = ST_GeomFromText(?,0),
                    modified_date = NOW()
                WHERE station_id = ?
                  AND customer_customer_id = ?
            ", [
                $request->station_name,
                'polygon',
                $wkt,
                $id,
                $customerId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Station updated',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        DB::connection($dbConnection)->delete("
            DELETE FROM stations
            WHERE station_id = ?
              AND customer_customer_id = ?
        ", [$id, $customerId]);

        return response()->json([
            'success' => true,
            'message' => 'Station deleted',
        ]);
    }

    private function polygonToWkt(array $polygon): string
    {
        $points = collect($polygon)
            ->map(fn ($p) => "{$p['lng']} {$p['lat']}")
            ->values();

        if ($points->first() !== $points->last()) {
            $points->push($points->first());
        }

        return "POLYGON((" . $points->implode(',') . "))";
    }
}
