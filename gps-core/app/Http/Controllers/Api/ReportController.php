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

        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 10), 200);
        $offset = ($page - 1) * $perPage;

        $imeis = $request->query('imeis', []);

        if (!is_array($imeis)) {
            $imeis = [$imeis];
        }

        $imeis = array_values(array_filter($imeis, fn ($v) => trim((string) $v) !== ''));
        $imeiCsv = implode(',', $imeis);

        $summary = DB::connection($connection)->selectOne(
            "CALL sp_report_daily_summary_total(?, ?, ?)",
            [$dateFrom, $dateTo, $imeiCsv]
        );

        $rows = DB::connection($connection)->select(
            "CALL sp_report_daily_summary_rows(?, ?, ?, ?, ?)",
            [$dateFrom, $dateTo, $imeiCsv, $perPage, $offset]
        );

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) ($summary->total_rows ?? 0),
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
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 10), 200);
        $offset = ($page - 1) * $perPage;
        $imeis = $request->query('imeis', []);
        if (!is_array($imeis)) {
            $imeis = [$imeis];
        }

        $where = ['data_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];

        if ($status !== '') {
            $where[] = 'gps_status = ?';
            $params[] = $status;
        }

        if (!empty($imeis)) {
            $placeholders = implode(',', array_fill(0, count($imeis), '?'));
            $where[] = "s.imei IN ($placeholders)";
            $params = array_merge($params, $imeis);
        }
        $whereSql = implode(' AND ', $where);
        $summary = DB::connection($connection)->selectOne("
                SELECT
                    COUNT(*) AS total_rows,
                    COUNT(DISTINCT imei) AS total_vehicle,
                    COALESCE(SUM(duration_s), 0) AS duration_s
                FROM gps_status_sum s
                WHERE {$whereSql}
            ", $params);

//        dd($summary->raw_sql());

        $totalRow = DB::connection($connection)->selectOne("
        SELECT COUNT(*) AS total
        FROM gps_status_sum s
        WHERE {$whereSql}
    ", $params);

        $rows = DB::connection($connection)->select("
        SELECT
            s.id,
            s.imei,
            t.plate_no,
            s.data_date,
            s.gps_status,
            s.start_time,
            s.end_time,
            s.duration_s,
            s.updated_at
        FROM gps_status_sum s
        INNER JOIN tracker t ON s.imei = t.imei
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

        logger()->info('Report groupOptions', [
            'connection' => $dbConnection,
            'customer_id' => $customerId,
        ]);

        $rows = DB::connection($dbConnection)->select("
            SELECT
                customer_group_id AS group_id,
                customer_group_name as group_name
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
            SELECT cgt.imei, t.plate_no, cgt.customer_group_id AS group_id
            FROM customer_group_tracker cgt inner join tracker t on cgt.imei=t.imei
            {$whereSql}
            ORDER BY plate_no ASC
            LIMIT 5000
        ", $params);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function groups(Request $request)
    {
        $connection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        if (!$connection || !$customerId) {
            return response()->json([
                'success' => false,
                'data' => [],
            ], 400);
        }

        $rows = DB::connection($connection)->select("
        SELECT
            customer_group_id AS group_id,
            customer_group_name AS group_name
        FROM customer_group
        WHERE customer_customer_id = ?
        ORDER BY customer_group_name ASC
    ", [$customerId]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function vehicles(Request $request)
    {
        $connection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        if (!$connection || !$customerId) {
            return response()->json([
                'success' => false,
                'data' => [],
            ], 400);
        }

        $groupIds = $request->query('group_ids', []);

        if (is_string($groupIds)) {
            $groupIds = array_filter(explode(',', $groupIds));
        }

        $params = [$customerId];

        $where = [
            'v.customer_customer_id = ?'
        ];

        if (!empty($groupIds)) {
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $where[] = "v.customer_group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $whereSql = implode(' AND ', $where);

        $rows = DB::connection($connection)->select("
        SELECT
            v.box_imei AS imei,
            CONCAT(
                COALESCE(NULLIF(v.plate_no, ''), v.box_imei),
                ' / ',
                v.box_imei
            ) AS label,
            v.plate_no,
            v.customer_group_id AS group_id
        FROM vehicle v
        WHERE {$whereSql}
        ORDER BY v.plate_no ASC, v.box_imei ASC
    ", $params);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }
}
