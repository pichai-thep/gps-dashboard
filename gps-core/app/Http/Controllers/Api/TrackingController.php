<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    public function current(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'message' => 'Unsupported GPS server',
                'server_name' => $user->server_name ?? null,
            ], 400);
        }

        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $offset = ($page - 1) * $perPage;

        $groupId = (int) $request->query('group_id', -1);
        $keyword = $request->query('search');

        $sortBy = (string) $request->query('sort_by', 'plate_no');
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $allowedSort = [
            'plate_no' => 'plate_no',
            'gps_time' => 'date_sort',
            'time' => 'date_sort',
            'speed' => 'speed',
            'fuel_left' => 'fuel_left',
            'fuel' => 'fuel_left',
            'status' => 'status',
        ];

        $sortColumn = $allowedSort[$sortBy] ?? 'plate_no';

        $statusMap = [
            'running' => 1,
            'idle' => 2,
            'parking' => 3,
            'offline' => 4,
            'no_gps' => 5,
        ];

        $statusQuery = $request->query('status');
        $status = $statusQuery !== null && $statusQuery !== ''
            ? ($statusMap[$statusQuery] ?? -1)
            : -1;

        DB::purge($connection);
        DB::reconnect($connection);

        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare("
            CALL sp_current_track_kw5(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user->login,
            $groupId,
            $sortColumn,
            $sortDir,
            $keyword,
            0,
            $status,
            $offset,
            $perPage,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();

        $countStmt = $pdo->prepare("
            CALL sp_current_track_count(?, ?, ?, ?)
        ");

        $countStmt->execute([
            $user->login,
            $groupId,
            $keyword,
            0,
        ]);

        $countRows = $countStmt->fetchAll(\PDO::FETCH_OBJ);
        $countStmt->closeCursor();

        $total = 0;

        if (!empty($countRows)) {
            $total = (int) (
                $countRows[0]->total ??
                $countRows[0]->count ??
                $countRows[0]->total_count ??
                0
            );
        }

        $vehicles = collect($rows)->map(function ($row) {
            return [
                'imei' => $row->imei,
                'plate_no' => $row->plate_no,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'speed' => (float) $row->speed,
                'gps_time' => $row->date_sort,
                'received_time' => $row->received_date,
                'status' => $this->resolveStatus($row),
                'heading' => (int) ($row->heading ?? 0),
                'fuel_left' => (float) ($row->fuel_left ?? 0),
                'icon_path' => $row->icon_path ?? '',
                'icon' => $row->icon_path ?? 'bus',
                'driver_name' => $row->driver_name ?? null,
                'driver_phone' => $row->driver_phone ?? null,
                'acc_on' => $this->resolveAcc($row),
                'sequen_no' => isset($row->sequen_no) ? (int) $row->sequen_no : null,
                'dlt_synch' => $row->dlt_synch,
                'track1' => $row->track1 ?? null,
                'track3' => $row->track3 ?? null,
            ];
        })->values();

        return response()->json([
            'vehicles' => $vehicles,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
                'has_next_page' => $page * $perPage < $total,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'group_id' => $groupId,
                'status' => $statusQuery,
                'search' => $keyword,
            ],
        ]);
    }

    public function groups(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');
        $authUser = $request->attributes->get('auth_user');

        $customerId = (int) $request->query('customer_id');

        $gpsUser = DB::connection($connection)
            ->table('user')
            ->where('login', $authUser->login)
            ->first();

        if (!$gpsUser) {
            return response()->json([
                'message' => 'GPS user not found',
            ], 404);
        }

        $allowedCustomerIds = DB::connection($connection)
            ->table('customer_user')
            ->where('user_user_id', $gpsUser->user_id)
            ->pluck('customer_customer_id')
            ->toArray();

        if (!in_array($customerId, $allowedCustomerIds)) {
            return response()->json([
                'message' => 'Unauthorized customer_id',
            ], 403);
        }

        $groups = DB::connection($connection)
            ->table('customer_group')
            ->where('customer_id', $customerId)
            ->select([
                'customer_group_id as id',
                'customer_group_name as name',
            ])
            ->orderBy('customer_group_name')
            ->get();

        return response()->json([
            'groups' => $groups,
        ]);
    }

    private function resolveAcc($row): bool
    {
        $state = $row->state ?? null;

        return in_array(strtolower((string) $state), [
            '1',
            'on',
            'true',
            'y',
            'yes',
            'acc_on',
            'engine_on',
        ], true);
    }

    private function resolveStatus($row): string
    {
        $speed = (float) ($row->speed ?? 0);
        $gpsStatus = strtoupper((string) ($row->gps_status ?? ''));
        $isAccOn = $this->resolveAcc($row);

        if ($gpsStatus === 'V') {
            return 'no_gps';
        }

        $receivedTimeRaw = $row->received_date ?? null;

        if (!$receivedTimeRaw) {
            return 'no_gps';
        }

        try {
            $receivedTime = Carbon::parse($receivedTimeRaw, 'Asia/Bangkok');
            $now = now('Asia/Bangkok');

            if ($receivedTime->diffInMinutes($now) > 30) {
                return 'offline';
            }
        } catch (\Exception $e) {
            return 'offline';
        }

        if ($speed > 5) {
            return 'running';
        }

        if ($isAccOn) {
            return 'idle';
        }

        return 'parking';
    }
}
