<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
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

    public function dailySummary(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $keyword = trim((string) $request->query('keyword', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 10), 200);
        $offset = ($page - 1) * $perPage;

        $where = [
            'data_date BETWEEN ? AND ?'
        ];

        $params = [$dateFrom, $dateTo];

        if ($keyword !== '') {
            $where[] = 'imei LIKE ?';
            $params[] = "%{$keyword}%";
        }

        $whereSql = implode(' AND ', $where);

        $summary = DB::connection($connection)->selectOne("
            SELECT
                COUNT(*) AS total_rows,
                COUNT(DISTINCT imei) AS total_vehicle,
                COALESCE(SUM(run_time_s), 0) AS run_time_s,
                COALESCE(SUM(idle_time_s), 0) AS idle_time_s,
                COALESCE(SUM(park_time_s), 0) AS park_time_s,
                COALESCE(SUM(distance_m), 0) AS distance_m
            FROM gps_data_sum
            WHERE {$whereSql}
        ", $params);

        $totalRow = DB::connection($connection)->selectOne("
            SELECT COUNT(*) AS total
            FROM gps_data_sum
            WHERE {$whereSql}
        ", $params);

        $rows = DB::connection($connection)->select("
            SELECT
                imei,
                data_date,
                run_time_s,
                idle_time_s,
                park_time_s,
                distance_m,
                updated_at
            FROM gps_data_sum
            WHERE {$whereSql}
            ORDER BY data_date DESC, imei ASC
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) $totalRow->total,
            ],
        ]);
    }

    public function statusSummary(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $imei = trim((string) $request->query('imei', ''));
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 10), 200);
        $offset = ($page - 1) * $perPage;

        $where = ['data_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];

        if ($imei !== '') {
            $where[] = 'imei LIKE ?';
            $params[] = "%{$imei}%";
        }

        if ($status !== '') {
            $where[] = 'gps_status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $summary = DB::connection($connection)->selectOne("
        SELECT
            COUNT(*) AS total_rows,
            COUNT(DISTINCT imei) AS total_vehicle,
            COALESCE(SUM(duration_s), 0) AS duration_s
        FROM gps_status_sum
        WHERE {$whereSql}
    ", $params);

        $totalRow = DB::connection($connection)->selectOne("
        SELECT COUNT(*) AS total
        FROM gps_status_sum
        WHERE {$whereSql}
    ", $params);

        $rows = DB::connection($connection)->select("
        SELECT
            id,
            imei,
            data_date,
            gps_status,
            start_time,
            end_time,
            duration_s,
            updated_at
        FROM gps_status_sum
        WHERE {$whereSql}
        ORDER BY start_time DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $params);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) $totalRow->total,
            ],
        ]);
    }

    public function stationSummary(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $imei = trim((string) $request->query('imei', ''));
        $stationId = trim((string) $request->query('station_id', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 10), 200);
        $offset = ($page - 1) * $perPage;

        $where = ['gss.data_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];

        if ($imei !== '') {
            $where[] = 'gss.imei LIKE ?';
            $params[] = "%{$imei}%";
        }

        if ($stationId !== '') {
            $where[] = 'gss.station_id = ?';
            $params[] = $stationId;
        }

        $whereSql = implode(' AND ', $where);

        $summary = DB::connection($connection)->selectOne("
        SELECT
            COUNT(*) AS total_rows,
            COUNT(DISTINCT gss.imei) AS total_vehicle,
            COUNT(DISTINCT gss.station_id) AS total_station,
            COALESCE(SUM(gss.duration_s), 0) AS duration_s
        FROM gps_station_sum gss
        WHERE {$whereSql}
    ", $params);

        $totalRow = DB::connection($connection)->selectOne("
        SELECT COUNT(*) AS total
        FROM gps_station_sum gss
        WHERE {$whereSql}
    ", $params);

        $rows = DB::connection($connection)->select("
        SELECT
            gss.id,
            gss.imei,
            gss.data_date,
            gss.station_id,
            s.station_name,
            gss.start_time,
            gss.end_time,
            gss.duration_s,
            gss.updated_at
        FROM gps_station_sum gss
        LEFT JOIN station s ON s.station_id = gss.station_id
        WHERE {$whereSql}
        ORDER BY gss.start_time DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $params);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) $totalRow->total,
            ],
        ]);
    }

    public function groupOptions(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                customer_group_id AS group_id,
                customer_group_name
            FROM customer_group
            WHERE customer_id = ?
            ORDER BY customer_group_name ASC"
            , [$customerId]
        );

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function vehicleOptions(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        $groupIds = $request->query('group_ids', []);

        if (!is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        $where = [];
        $params = [];

        if (!empty($groupIds)) {
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $where[] = "customer_group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = DB::connection($dbConnection)->select("
        SELECT
            imei,
            COALESCE(NULLIF(plate_no, ''), imei) AS label,
            customer_group_id AS group_id
        FROM tracker
        {$whereSql}
        ORDER BY plate_no ASC
        LIMIT 5000
    ", $params);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }
}
