<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoiController extends Controller
{
    private function customerId(Request $request): int
    {
        $user = $request->attributes->get('auth_user');

        return (int) (
            $user->customer_customer_id
            ?? $user->customer_id
            ?? 0
        );
    }

    public function index(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');
        $customerId = $this->customerId($request);

        $rows = DB::connection($connection)->select("
            SELECT
                poi_id,
                poi_name,
                icon,
                st_X(g_poi) AS lng,
                st_Y(g_poi) AS lat,
                customer_customer_id
            FROM poi
            WHERE customer_customer_id = ?
            ORDER BY poi_id DESC
        ", [$customerId]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'poi_name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:255',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $connection = $request->attributes->get('gps_connection');
        $customerId = $this->customerId($request);

        DB::connection($connection)->insert("
            INSERT INTO poi (
                poi_name,
                icon,
                g_poi,
                customer_customer_id
            )
            VALUES (?, ?, ST_GeomFromText(?), ?)
        ", [
            $request->poi_name,
            $request->icon,
            "POINT({$request->lng} {$request->lat})",
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'POI created',
        ]);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'poi_name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:255',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $connection = $request->attributes->get('gps_connection');
        $customerId = $this->customerId($request);

        $exists = DB::connection($connection)->selectOne("
            SELECT poi_id
            FROM poi
            WHERE poi_id = ?
              AND customer_customer_id = ?
        ", [$id, $customerId]);

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'POI not found',
            ], 404);
        }

        DB::connection($connection)->update("
            UPDATE poi
            SET
                poi_name = ?,
                icon = ?,
                g_poi = ST_GeomFromText(?)
            WHERE poi_id = ?
              AND customer_customer_id = ?
        ", [
            $request->poi_name,
            $request->icon,
            "POINT({$request->lng} {$request->lat})",
            $id,
            $customerId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'POI updated',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $connection = $request->attributes->get('gps_connection');
        $customerId = $this->customerId($request);

        DB::connection($connection)->delete("
            DELETE FROM poi
            WHERE poi_id = ?
              AND customer_customer_id = ?
        ", [$id, $customerId]);

        return response()->json([
            'success' => true,
            'message' => 'POI deleted',
        ]);
    }
}
