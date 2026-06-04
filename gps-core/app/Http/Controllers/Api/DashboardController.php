<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const DEFAULT_STATUS_COUNTS = [
        'running' => 0,
        'idle' => 0,
        'acc_on' => 0,
        'parking' => 0,
        'no_gps' => 0,
        'offline' => 0,
    ];

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

        DB::purge($connection);
        DB::reconnect($connection);

        $pdo = DB::connection($connection)->getPdo();

        $stmt = $pdo->prepare("
            CALL sp_current_track_kw5(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user->login,
            -1,
            'plate_no',
            'asc',
            null,
            null,
            -1,
            0,
            100000,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();

        $statusCounts = self::DEFAULT_STATUS_COUNTS;

        $inStation = 0;
        $outStation = 0;
        $drivingWithoutCard = 0;
        $cardInserted = 0;

        foreach ($rows as $row) {
            $status = $this->resolveStatus($row);

            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            }

            // TODO: ถ้ามี field station จริง ค่อยเปลี่ยน logic ตรงนี้
            $outStation++;

            $driverStatus = $this->resolveDriverStatus($row);

            if (
                (int) ($row->dlt_synch ?? 0) === 1 &&
                (float) ($row->speed ?? 0) > 0 &&
                $driverStatus === 'missing'
            ) {
                $drivingWithoutCard++;
            }

            if ($driverStatus === 'ok') {
                $cardInserted++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($rows),

                'running' => $statusCounts['running'],
                'idle' => $statusCounts['idle'],
                'acc_on' => $statusCounts['acc_on'],
                'parking' => $statusCounts['parking'],
                'no_gps' => $statusCounts['no_gps'],
                'offline' => $statusCounts['offline'],

                'in_station' => $inStation,
                'out_station' => $outStation,

                'driving_without_card' => $drivingWithoutCard,
                'card_inserted' => $cardInserted,

                'updated_at' => now('Asia/Bangkok')->toDateTimeString(),
            ],
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

    private function resolveDriverStatus($row): string
    {
        $dltSynch = (int) ($row->dlt_synch ?? 0);
        $track1 = trim((string) ($row->track1 ?? ''));
        $track3 = trim((string) ($row->track3 ?? ''));

        if ($dltSynch === 0) {
            return 'hide';
        }

        if ($track1 !== '' && $track3 === '') {
            return 'no_license';
        }

        if ($track3 !== '') {
            return 'ok';
        }

        return 'missing';
    }

    private function resolveStatus($row): string
    {
        $speed = (float) ($row->speed ?? 0);
        $gpsStatus = strtoupper((string) ($row->gps_status ?? ''));
        $isAccOn = $this->resolveAcc($row);
        $engineVolt = (float) ($row->engine_volt ?? 0);
        $extPower = (float) ($row->ext_power ?? 0);

        $receivedTimeRaw = $row->received_date ?? null;

        try {
            $receivedTime = Carbon::parse($receivedTimeRaw, 'Asia/Bangkok');
            $now = now('Asia/Bangkok');

            if ($receivedTime->diffInMinutes($now) > 30) {
                return 'offline';
            }
        } catch (\Exception $e) {
            return 'offline';
        }

        if ($gpsStatus === 'V') {
            return 'no_gps';
        }

        if (!$isAccOn) {
            return 'parking';
        }

        if ($speed > 0) {
            return 'running';
        }

        if ($extPower > $engineVolt) {
            return 'idle';
        }

        return 'acc_on';
    }

    private function fetchCurrentRows(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        int $status,
        int $offset,
        int $perPage
    ): array {
        $stmt = $pdo->prepare("
            CALL sp_webapi_current_track(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $login,
            $groupId,
            $sortColumn,
            $sortDir,
            $keyword,
            null,
            $status,
            $offset,
            $perPage,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();

        return $rows;
    }
}
