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
        $groupId = (int) $request->query('group_id', -1);
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'message' => 'Unsupported GPS server',
                'server_name' => $user->server_name,
            ], 400);
        }

//        echo "user: $user->login\n";

        DB::purge($connection);
        DB::reconnect($connection);
        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare("
            CALL sp_current_track_kw5(?, ?, ?, ?, ?, ?, ?, ?, ?)
         ");

        $stmt->execute([
            $user->login,
            $groupId,
            'plate_no',
            'asc',
            null,
            0,
            -1,
            0,
            100,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $stmt->closeCursor();


//        return response()->json([
//            'count' => count($rows),
//            'locations' => $rows,
//        ]);

        return response()->json([
            'vehicles' => collect($rows)->map(function ($row) {
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
                    'driver_name' => $row->track1,
                    'driver_license_no' => $row->track3,
                    'acc_on' => $this->resolveAcc($row),
                    'sequen_no' => isset($row->sequen_no) ? (int) $row->sequen_no : null,
                    'icon' => $row->icon_path,
                ];
            })->values(),
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
            // ✅ บังคับ timezone ไทย
            $receivedTime = Carbon::parse($receivedTimeRaw, 'Asia/Bangkok');
            $now = now('Asia/Bangkok');

            $diffMinutes = $receivedTime->diffInMinutes($now);

            // 🔥 debug ดูค่าจริง
            // logger()->info("GPS TIME DIFF", [
            //     'received' => $receivedTime,
            //     'now' => $now,
            //     'diff' => $diffMinutes
            // ]);

            // ✅ offline ถ้าเกิน 30 นาที
            if ($diffMinutes > 30) {
                return 'offline';
            }

        } catch (\Exception $e) {
            return 'offline';
        }

        // 🚗 logic ปกติ
        if ($speed > 5) {
            return 'running';
        }

        if ($isAccOn) {
            return 'idle';
        }

        return 'parking';
    }

    public function groups(Request $request)
    {
        $connection = $request->attributes->get('gps_connection');
        $authUser = $request->attributes->get('auth_user');

        // 👉 รับจาก frontend
        $customerId = (int) $request->query('customer_id');

        // 🔥 ตรวจสิทธิ์ user ก่อน (สำคัญมาก)
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

        // ❌ ถ้าไม่มีสิทธิ์
        if (!in_array($customerId, $allowedCustomerIds)) {
            return response()->json([
                'message' => 'Unauthorized customer_id',
            ], 403);
        }

        // ✅ query group ตาม customer_id
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
}
