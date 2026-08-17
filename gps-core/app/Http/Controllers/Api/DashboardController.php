<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const DEFAULT_STATUS_COUNTS = [
        'run' => 0,
        'idle' => 0,
        'acc_on' => 0,
        'park' => 0,
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

//        $stmt = $pdo->prepare("
//            CALL sp_current_track_kw5   (?, ?, ?, ?, ?, ?, ?, ?, ?)
//        ");

        $stmt = $pdo->prepare("
            CALL sp_webapi_dashboard(?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $dltSynchTotal = 0;
        $driverCardVehicles = [];

        foreach ($rows as $row) {
            $status = $this->resolveStatus($row);

            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            }

            $stationCount = (int) ($row->station_count ?? 0);

            if ($stationCount > 0) {
                $inStation++;
            } else {
                $outStation++;
            }

            $driverStatus = $this->resolveDriverStatus($row);
            $isDltSynched = (int) ($row->dlt_synch ?? 0) === 1;
            $isAccOn = $this->resolveAcc($row);
            $hasDriverCard = trim((string) ($row->track3 ?? '')) !== '';
            $hasDriverCardData =
                trim((string) ($row->track1 ?? '')) !== '' ||
                trim((string) ($row->track3 ?? '')) !== '';

            if ($isDltSynched) {
                $dltSynchTotal++;
            }

            if ($hasDriverCardData && $status === 'run') {
                $driverCardVehicles[] = [
                    'imei' => $row->imei ?? null,
                    'plate_no' => $row->plate_no ?? null,
                    'driver_name' => $row->driver_name ?? null,
                    'license_name' => $this->formatDriverName($row->track1 ?? null),
                    'license_no' => $row->track3 ?? null,
                    'speed' => isset($row->speed) ? (float) $row->speed : 0,
                    'status' => $status,
                ];
            }

            if (
                $isDltSynched &&
                $isAccOn &&
                !$hasDriverCard
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

                'run' => $statusCounts['run'],
                'idle' => $statusCounts['idle'],
                'acc_on' => $statusCounts['acc_on'],
                'park' => $statusCounts['park'],
                'no_gps' => $statusCounts['no_gps'],
                'offline' => $statusCounts['offline'],

                'in_station' => $inStation,
                'out_station' => $outStation,

                'driving_without_card' => $drivingWithoutCard,
                'card_inserted' => $cardInserted,
                'dlt_synch_total' => $dltSynchTotal,
                'driver_card_vehicles' => $driverCardVehicles,

                'updated_at' => now('Asia/Bangkok')->toDateTimeString(),
            ],
        ]);
    }

    private function cleanDriverText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['^', '%'], ' ', (string) $value)));
    }

    private function formatDriverName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $parts = array_map(
            fn ($part) => $this->cleanDriverText($part),
            explode('$', $value)
        );

        $lastname = $parts[0] ?? '';
        $firstname = $parts[1] ?? '';
        $prefix = $parts[2] ?? '';

        $name = trim(implode(' ', array_filter([
            $prefix,
            $firstname,
            $lastname,
        ])));

        return $name !== '' ? $name : null;
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
        $track3 = trim((string) ($row->track3 ?? ''));

        if ($dltSynch !== 1) {
            return 'hide';
        }

        if ($track3 !== '') {
            return 'ok';
        }

        return $this->resolveAcc($row) ? 'missing' : 'hide';
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
            return 'park';
        }

        if ($speed > 0) {
            return 'run';
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
