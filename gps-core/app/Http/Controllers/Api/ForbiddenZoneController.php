<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForbiddenZoneController extends Controller
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
                id,
                zone_name,
                ST_AsText(polygon) AS polygon_wkt,
                customer_id
            FROM forbidden_zones
            WHERE customer_id = ?
            ORDER BY id DESC
        ", [
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $data = $request->validate([
            'zone_name' => 'required|string|max:45',
            'polygon' => 'required|array|min:3',
            'polygon.*.lat' => 'required|numeric',
            'polygon.*.lng' => 'required|numeric',
        ]);

        $wkt = $this->makePolygonWkt($data['polygon']);

        DB::connection($dbConnection)->insert("
            INSERT INTO forbidden_zone
                (zone_name, polygon, customer_id)
            VALUES
                (?, ST_GeomFromText(?), ?)
        ", [
            $data['zone_name'],
            $wkt,
            $customerId,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $data = $request->validate([
            'zone_name' => 'required|string|max:45',
            'polygon' => 'required|array|min:3',
            'polygon.*.lat' => 'required|numeric',
            'polygon.*.lng' => 'required|numeric',
        ]);

        $wkt = $this->makePolygonWkt($data['polygon']);

        DB::connection($dbConnection)->update("
            UPDATE forbidden_zone
            SET
                zone_name = ?,
                polygon = ST_GeomFromText(?)
            WHERE id = ?
              AND customer_id = ?
        ", [
            $data['zone_name'],
            $wkt,
            $id,
            $customerId,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        DB::connection($dbConnection)->delete("
            DELETE FROM forbidden_zone
            WHERE id = ?
              AND customer_id = ?
        ", [
            $id,
            $customerId,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    private function makePolygonWkt(array $points): string
    {
        $coords = array_map(function ($p) {
            return $p['lng'] . ' ' . $p['lat'];
        }, $points);

        if ($coords[0] !== end($coords)) {
            $coords[] = $coords[0];
        }

        return 'POLYGON((' . implode(',', $coords) . '))';
    }
}
