<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class ReportController extends Controller
{
    private function customerId(Request $request): int
    {
        $customerId = (int) $request->query('customer_id', 0);
        $gpsUserCustomer = $request->attributes->get('gpsUserCustomer');

        if ($customerId <= 0) {
            $customerId = (int) ($gpsUserCustomer->customer_id ?? 0);
        }

        if ($customerId <= 0) {
            abort(422, 'Missing customer_id');
        }

        $connection = $this->dbConnection($request);
        $authUser = $request->attributes->get('auth_user');

        $isAllowed = DB::connection($connection)
            ->table('customer_user as cu')
            ->join('user as u', 'u.user_id', '=', 'cu.user_user_id')
            ->where('u.login', $authUser->login ?? null)
            ->where('cu.customer_customer_id', $customerId)
            ->exists();

        if (! $isAllowed) {
            abort(403, 'Unauthorized customer_id');
        }

        return $customerId;
    }

    private function dbConnection(Request $request)
    {
        return $request->attributes->get('gps_connection');
    }

    public function groupOptions(Request $request)
    {
        $dbConnection = $this->dbConnection($request);
        $customerId = $this->customerId($request);

        //        logger()->info('Report groupOptions', [
        //            'connection' => $dbConnection,
        //            'customer_id' => $customerId,
        //        ]);

        $rows = DB::connection($dbConnection)->select('
            SELECT
                customer_group_id AS group_id,
                customer_group_name as group_name
            FROM customer_group
            WHERE customer_id = ?
            ORDER BY customer_group_name ASC', [$customerId]
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

        if (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        $groupIds = array_values(array_filter(
            array_map('intval', $groupIds),
            fn (int $groupId) => $groupId > 0
        ));

        $query = DB::connection($dbConnection)
            ->table('customer_tracker as ct')
            ->join('tracker as t', 't.imei', '=', 'ct.tracker_imei')
            ->where('ct.customer_customer_id', $customerId)
            ->select([
                't.imei',
                DB::raw("COALESCE(NULLIF(TRIM(t.plate_no), ''), t.imei) as plate_no"),
            ])
            ->distinct();

        if (! empty($groupIds)) {
            $query->whereExists(function ($groupQuery) use ($groupIds, $customerId) {
                $groupQuery->select(DB::raw(1))
                    ->from('customer_group_tracker as cgt')
                    ->join('customer_group as cg', 'cg.customer_group_id', '=', 'cgt.customer_group_id')
                    ->whereColumn('cgt.imei', 'ct.tracker_imei')
                    ->where('cg.customer_id', $customerId)
                    ->whereIn('cgt.customer_group_id', $groupIds);
            });
        }

        $rows = $query
            ->orderBy('plate_no')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function stationOptions(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');
        $customerId = $this->customerId($request);

        $rows = DB::connection($connection)
            ->table('stations')
            ->where('customer_customer_id', $customerId)
            ->select([
                'station_id',
                'station_name',
            ])
            ->orderBy('station_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function dailySummary(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        if (! $connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $sortField = $request->query('sort_by', 'data_date');
        $sortOrder = $request->query('sort_order', 'desc');
        $isExport = filter_var($request->query('export', false), FILTER_VALIDATE_BOOLEAN);

        $page = max((int) $request->query('page', 1), 1);

        if ($isExport) {
            $perPage = min(max((int) $request->query('per_page', 100000), 1), 100000);
        } else {
            $perPage = min(max((int) $request->query('per_page', 50), 10), 500);
        }

        $imeis = $request->query('imeis', []);

        if (! is_array($imeis)) {
            $imeis = [$imeis];
        }

        $imeis = array_values(array_filter($imeis, function ($v) {
            return trim((string) $v) !== '';
        }));

        $imeiCsv = implode(',', $imeis);

        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare('CALL sp_report_daily_summary(?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user->login,
            $dateFrom,
            $dateTo,
            $imeiCsv,
            $page,
            $perPage,
            $sortField,
            $sortOrder,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $paginationRaw = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        $totalRows = (int) ($summary['total_rows'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($totalRows / $perPage) : 0;

        return response()->json([
            'success' => true,
            'summary' => [
                'total_rows' => $totalRows,
                'total_vehicle' => (int) ($summary['total_vehicle'] ?? 0),
                'run_time_s' => (int) ($summary['run_time_s'] ?? 0),
                'idle_time_s' => (int) ($summary['idle_time_s'] ?? 0),
                'park_time_s' => (int) ($summary['park_time_s'] ?? 0),
                'distance_m' => (float) ($summary['distance_m'] ?? 0),
                //                'ur_rate_avg' => $summary['ur_rate_avg'] !== null
                //                    ? (float) $summary['ur_rate_avg']
                //                    : null,
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'offset' => (int) ($paginationRaw['offset'] ?? (($page - 1) * $perPage)),
            ],
            'data' => $rows,
        ]);
    }

    public function statusSummary(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        if (! $connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $sortField = $request->query('sort_by', 'data_date');
        $sortOrder = $request->query('sort_order', 'desc');

        $status = $request->query('status', '');

        $isExport = filter_var($request->query('export', false), FILTER_VALIDATE_BOOLEAN);

        $page = max((int) $request->query('page', 1), 1);

        if ($isExport) {
            $perPage = min(max((int) $request->query('per_page', 100000), 1), 100000);
        } else {
            $perPage = min(max((int) $request->query('per_page', 50), 10), 500);
        }

        $imeis = $request->query('imeis', []);

        if (! is_array($imeis)) {
            $imeis = [$imeis];
        }

        $imeis = array_values(array_filter($imeis, function ($v) {
            return trim((string) $v) !== '';
        }));

        $imeiCsv = implode(',', $imeis);

        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare('CALL sp_report_status_summary(?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user->login,
            $dateFrom,
            $dateTo,
            $status,
            $imeiCsv,
            $page,
            $perPage,
            $sortField,
            $sortOrder,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $paginationRaw = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        $totalRows = (int) ($summary['total_rows'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($totalRows / $perPage) : 0;

        return response()->json([
            'success' => true,
            'summary' => [
                'total_rows' => $totalRows,
                'total_vehicle' => (int) ($summary['total_vehicle'] ?? 0),
                'duration_s' => (int) ($summary['duration_s'] ?? 0),
            ],
            'pagination' => [
                'current_page' => (int) ($paginationRaw['current_page'] ?? $page),
                'per_page' => (int) ($paginationRaw['per_page'] ?? $perPage),
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'offset' => (int) ($paginationRaw['offset'] ?? (($page - 1) * $perPage)),
            ],
            'data' => $rows,
        ]);
    }

    public function stationSummary(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        if (! $connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $sortField = $request->query('sort_by', 'data_date');
        $sortOrder = $request->query('sort_order', 'desc');

        $stationId = (int) $request->query('station_id', 0);

        $isExport = filter_var(
            $request->query('export', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $page = max((int) $request->query('page', 1), 1);

        if ($isExport) {
            $perPage = min(max((int) $request->query('per_page', 100000), 1), 100000);
        } else {
            $perPage = min(max((int) $request->query('per_page', 50), 10), 500);
        }

        $imeis = $request->query('imeis', []);

        if (! is_array($imeis)) {
            $imeis = [$imeis];
        }

        $imeis = array_values(array_filter($imeis, function ($v) {
            return trim((string) $v) !== '';
        }));

        $imeiCsv = implode(',', $imeis);

        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare('CALL sp_report_station_summary(?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user->login,
            $dateFrom,
            $dateTo,
            $stationId,
            $imeiCsv,
            $page,
            $perPage,
            $sortField,
            $sortOrder,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $paginationRaw = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt->nextRowset();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        $totalRows = (int) ($paginationRaw['total_rows'] ?? ($summary['total_rows'] ?? 0));
        $totalPages = (int) ($paginationRaw['total_pages'] ?? ($perPage > 0 ? ceil($totalRows / $perPage) : 0));

        return response()->json([
            'success' => true,
            'summary' => [
                'total_rows' => $totalRows,
                'total_vehicle' => (int) ($summary['total_vehicle'] ?? 0),
                'total_station' => (int) ($summary['total_station'] ?? 0),
                'duration_s' => (int) ($summary['duration_s'] ?? 0),
            ],
            'pagination' => [
                'current_page' => (int) ($paginationRaw['current_page'] ?? $page),
                'per_page' => (int) ($paginationRaw['per_page'] ?? $perPage),
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'offset' => (int) ($paginationRaw['offset'] ?? (($page - 1) * $perPage)),
            ],
            'data' => $rows,
        ]);
    }
}
