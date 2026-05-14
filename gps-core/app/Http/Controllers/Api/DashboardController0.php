<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController0 extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported GPS server',
            ], 400);
        }

        $rows = DB::connection($connection)->select("
            CALL sp_current_track_kw5(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $user->login,
            -1,
            'plate_no',
            'asc',
            '',
            0,
            -1,
            0,
            10000,
        ]);

        $total = count($rows);

        $running = 0;
        $idle = 0;
        $parking = 0;
        $offline = 0;
        $noGps = 0;

        foreach ($rows as $row) {
            $status = $this->resolveStatus($row);

            match ($status) {
                'running' => $running++,
                'idle' => $idle++,
                'parking' => $parking++,
                'offline' => $offline++,
                'no_gps' => $noGps++,
                default => null,
            };
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'running' => $running,
                'idle' => $idle,
                'parking' => $parking,
                'offline' => $offline,
                'no_gps' => $noGps,
                'updated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    private function resolveStatus(object $row): string
    {
        $gpsStatus = $row->gps_status ?? null;
        $gpsTime = $row->gps_time ?? null;
        $speed = (float) ($row->speed ?? 0);
        $acc = (int) ($row->acc ?? $row->acc_status ?? 0);

        if ($gpsStatus === 'V') {
            return 'no_gps';
        }

        if ($gpsTime && now()->diffInMinutes($gpsTime) > 10) {
            return 'offline';
        }

        if ($speed > 5) {
            return 'running';
        }

        if ($acc === 1) {
            return 'idle';
        }

        return 'parking';
    }
}
