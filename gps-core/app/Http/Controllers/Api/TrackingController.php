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

        $connection = $this->resolveGpsConnection($user->server_name);

        if (!$connection) {
            return response()->json([
                'message' => 'Unsupported GPS server',
                'server_name' => $user->server_name,
            ], 400);
        }

//        echo "user: $user->login\n";

        $rows = DB::connection($connection)->select("
        CALL sp_current_track_kw5(?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
            $user->login,   // _login
            -1,              // _customer_group_id
            'plate_no',     // _sortby
            'asc',         // _direction
            null,             // _keyword
            0,            // _is_dltSynch
            -1,             // _status (all)
            0,              // offset
            100             // limit
        ]);



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

        // ใช้ received_time / received_date เป็นตัวเช็ค offline
        $receivedTimeRaw = $row->received_date ?? null;

        $isAccOn = $this->resolveAcc($row);

        if ($gpsStatus === 'V') {
            return 'no_gps';
        }

        if ($receivedTimeRaw) {
            try {
                $receivedTime = Carbon::parse($receivedTimeRaw);

                if ($receivedTime->greaterThan(now()->addMinutes(10))) {
                    return 'offline';
                }

                if ($receivedTime->lessThan(now()->subMinutes(10))) {
                    return 'offline';
                }
            } catch (\Exception $e) {
                return 'offline';
            }
        }

        if ($speed > 5) {
            return 'running';
        }

        if ($isAccOn) {
            return 'idle';
        }

        return 'parking';
    }

    private function resolveGpsConnection(?string $serverName): ?string
    {
        return match (strtolower(trim((string) $serverName))) {
            'server5', 'gps5' => 'gps5',
            'server10', 'gps10' => 'gps10',
            'server13', 'gps13' => 'gps13',
            'server14', 'gps14' => 'gps14',
            'server16', 'gps16' => 'gps16',
            'server19', 'gps19' => 'gps19',
            'server20', 'gps20' => 'gps20',
            default => null,
        };
    }

}
