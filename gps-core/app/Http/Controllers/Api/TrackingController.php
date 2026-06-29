<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    private const PROCEDURE_CHUNK_SIZE = 100;

    private const DEFAULT_STATUS_COUNTS = [
        'run' => 0,
        'idle' => 0,
        'park' => 0,
        'no_gps' => 0,
        'offline' => 0,
    ];

    public function current(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');
        $gpsUserCustomer = $request->attributes->get('gpsUserCustomer');

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
        $dltSynch = $this->resolveDltSynchFilter($request);
        $statusQuery = $request->query('status');

        $sortBy = (string) $request->query('sort_by', 'plate_no');
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $sortColumn = $this->resolveSortColumn($sortBy);

        DB::purge($connection);
        DB::reconnect($connection);

        $pdo = DB::connection($connection)->getPdo();

        if($gpsUserCustomer->enable_passenger){
            $rows = $this->fetchAllCurrentRows_passenger(
                $pdo,
                $user->login,
                $groupId,
                $sortColumn,
                $sortDir,
                $keyword,
                $dltSynch,
                -1
            );

        }else{
            $rows = $this->fetchAllCurrentRows(
                $pdo,
                $user->login,
                $groupId,
                $sortColumn,
                $sortDir,
                $keyword,
                $dltSynch,
                -1
            );

        }

        $allVehicles = collect($rows)
            ->map(fn ($row) => $this->transformVehicle($row))
            ->values();

        $statusCounts = self::DEFAULT_STATUS_COUNTS;

        foreach ($allVehicles as $vehicle) {
            $status = $vehicle['status'];

            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            }
        }

        $noDriverCardCount = $allVehicles
            ->filter(fn ($vehicle) => $this->isNoDriverCard($vehicle))
            ->count();

        $dltSynchCount = $allVehicles
            ->filter(fn ($vehicle) => $this->isDltSynched($vehicle))
            ->count();

        $filteredVehicles = $allVehicles;

        if ($statusQuery !== null && $statusQuery !== '') {
            $filteredVehicles = $allVehicles
                ->filter(fn ($vehicle) => $vehicle['status'] === $statusQuery)
                ->values();
        }

        $noDriverCard = (int) $request->query('no_driver_card', 0);

        if ($noDriverCard === 1) {
            $filteredVehicles = $filteredVehicles
                ->filter(fn ($vehicle) => $this->isNoDriverCard($vehicle))
                ->values();
        }

        $dltSynch = (int) $request->query('dlt_synch', 0);

        if ($dltSynch === 1) {
            $filteredVehicles = $filteredVehicles
                ->filter(fn ($vehicle) => $this->isDltSynched($vehicle))
                ->values();
        }

        $total = $filteredVehicles->count();

        $vehicles = $filteredVehicles
            ->slice($offset, $perPage)
            ->values();

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
                'status_counts' => $statusCounts,
                'no_driver_card_count' => $noDriverCardCount,
                'dlt_synch_count' => $dltSynchCount,
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

    private function fetchCurrentRows(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status,
        int $offset,
        int $perPage
    ): array {
        return $this->fetchProcedureRows($pdo, 'sp_webapi_current_track', [
            $login,
            $groupId,
            $sortColumn,
            $sortDir,
            $keyword,
            $dltSynch,
            $status,
            $offset,
            $perPage,
        ]);
    }

    private function fetchAllCurrentRows(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status
    ): array {
        $rows = [];
        $offset = 0;

        do {
            $result = $this->fetchCurrentRowsChunked(
                $pdo,
                $login,
                $groupId,
                $sortColumn,
                $sortDir,
                $keyword,
                $dltSynch,
                $status,
                $offset,
                self::PROCEDURE_CHUNK_SIZE
            );
            $chunk = $result['rows'];

            $rows = array_merge($rows, $chunk);
            $offset += self::PROCEDURE_CHUNK_SIZE;
        } while (count($chunk) === self::PROCEDURE_CHUNK_SIZE || $result['failed']);

        return $rows;
    }

    private function fetchCurrentRows_passenger(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status,
        int $offset,
        int $perPage
    ): array {
        return $this->fetchProcedureRows($pdo, 'sp_api_current_track_passenger', [
            $login,
            $groupId,
            $sortColumn,
            $sortDir,
            $keyword,
            $dltSynch,
            $status,
            $offset,
            $perPage,
        ]);
    }

    private function fetchAllCurrentRows_passenger(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status
    ): array {
        $rows = [];
        $offset = 0;

        do {
            $result = $this->fetchCurrentRowsPassengerChunked(
                $pdo,
                $login,
                $groupId,
                $sortColumn,
                $sortDir,
                $keyword,
                $dltSynch,
                $status,
                $offset,
                self::PROCEDURE_CHUNK_SIZE
            );
            $chunk = $result['rows'];

            $rows = array_merge($rows, $chunk);
            $offset += self::PROCEDURE_CHUNK_SIZE;
        } while (count($chunk) === self::PROCEDURE_CHUNK_SIZE || $result['failed']);

        return $rows;
    }

    private function fetchCurrentRowsChunked(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status,
        int $offset,
        int $perPage
    ): array {
        try {
            return [
                'rows' => $this->fetchCurrentRows(
                    $pdo,
                    $login,
                    $groupId,
                    $sortColumn,
                    $sortDir,
                    $keyword,
                    $dltSynch,
                    $status,
                    $offset,
                    $perPage
                ),
                'failed' => false,
            ];
        } catch (\PDOException $exception) {
            return $this->splitCurrentRowsChunk(
                $exception,
                fn (int $nextOffset, int $nextSize) => $this->fetchCurrentRowsChunked(
                    $pdo,
                    $login,
                    $groupId,
                    $sortColumn,
                    $sortDir,
                    $keyword,
                    $dltSynch,
                    $status,
                    $nextOffset,
                    $nextSize
                ),
                'sp_webapi_current_track',
                $offset,
                $perPage
            );
        }
    }

    private function fetchCurrentRowsPassengerChunked(
        \PDO $pdo,
        string $login,
        int $groupId,
        string $sortColumn,
        string $sortDir,
        ?string $keyword,
        ?int $dltSynch,
        int $status,
        int $offset,
        int $perPage
    ): array {
        try {
            return [
                'rows' => $this->fetchCurrentRows_passenger(
                    $pdo,
                    $login,
                    $groupId,
                    $sortColumn,
                    $sortDir,
                    $keyword,
                    $dltSynch,
                    $status,
                    $offset,
                    $perPage
                ),
                'failed' => false,
            ];
        } catch (\PDOException $exception) {
            return $this->splitCurrentRowsChunk(
                $exception,
                fn (int $nextOffset, int $nextSize) => $this->fetchCurrentRowsPassengerChunked(
                    $pdo,
                    $login,
                    $groupId,
                    $sortColumn,
                    $sortDir,
                    $keyword,
                    $dltSynch,
                    $status,
                    $nextOffset,
                    $nextSize
                ),
                'sp_api_current_track_passenger',
                $offset,
                $perPage
            );
        }
    }

    private function splitCurrentRowsChunk(
        \PDOException $exception,
        callable $fetchChunk,
        string $procedure,
        int $offset,
        int $perPage
    ): array {
        if (!$this->isUnknownTableAliasError($exception, 'g')) {
            throw $exception;
        }

        if ($perPage <= 1) {
            logger()->warning('Tracking stored procedure skipped bad row', [
                'procedure' => $procedure,
                'failed_offset' => $offset,
                'error' => $exception->getMessage(),
            ]);

            return [
                'rows' => [],
                'failed' => true,
            ];
        }

        $leftSize = intdiv($perPage, 2);
        $rightSize = $perPage - $leftSize;
        $left = $fetchChunk($offset, $leftSize);
        $right = $fetchChunk($offset + $leftSize, $rightSize);

        return [
            'rows' => array_merge($left['rows'], $right['rows']),
            'failed' => $left['failed'] || $right['failed'],
        ];
    }

    private function fetchProcedureRows(\PDO $pdo, string $procedure, array $params): array
    {
        $stmt = $pdo->prepare("
            CALL {$procedure}(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $this->logProcedureParams($procedure, $params);

        try {
            $stmt->execute($params);
        } catch (\PDOException $exception) {
            $stmt->closeCursor();

            if (!$this->isUnknownTableAliasError($exception, 'g') || ($params[2] ?? null) === 'plate_no') {
                throw $exception;
            }

            $params[2] = 'plate_no';
            $this->logProcedureParams($procedure, $params, 'retry');

            $stmt = $pdo->prepare("
                CALL {$procedure}(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute($params);
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();

        return $rows;
    }

    private function logProcedureParams(string $procedure, array $params, string $attempt = 'initial'): void
    {
        logger()->info('Tracking stored procedure params', [
            'procedure' => $procedure,
            'attempt' => $attempt,
            'params' => [
                '_login' => $params[0] ?? null,
                '_customer_group_id' => $params[1] ?? null,
                '_sortby' => $params[2] ?? null,
                '_direction' => $params[3] ?? null,
                '_keyword' => $params[4] ?? null,
                '_is_dltSynch' => $params[5] ?? null,
                '_status' => $params[6] ?? null,
                '_offSet' => $params[7] ?? null,
                '_size' => $params[8] ?? null,
            ],
        ]);
    }

    private function isUnknownTableAliasError(\PDOException $exception, string $alias): bool
    {
        return str_contains($exception->getMessage(), "Unknown table '{$alias}'");
    }

    private function transformVehicle($row): array
    {
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
            'fuel_left' => $row->fuel_left,
            'temperature' => $row->temperature,
            'icon_path' => $row->icon_path ?? '',
            'icon' => $row->icon_path ?? 'bus',
            'driver_name' => $row->driver_name ?? null,
            'driver_phone' => $row->driver_phone ?? null,
            'driver_status' => $this->resolveDriverStatus($row),
            'acc_state' => $this->resolveAcc($row),
            'sequen_no' => isset($row->sequen_no) ? (int) $row->sequen_no : null,
            'dlt_synch' => $row->dlt_synch,
            'track1' => $row->track1 ?? null,
            'track3' => $row->track3 ?? null,
            'address' => $row->address ?? null,
            'num_sats' => $row->num_sats ?? null,
            'passenger_num' => $row->passenger_num ?? null,
        ];
    }

    private function resolveSortColumn(string $sortBy): string
    {
        $sortBy = strtolower(trim($sortBy));
        $sortBy = str_contains($sortBy, '.')
            ? substr($sortBy, strrpos($sortBy, '.') + 1)
            : $sortBy;

        $allowedSort = [
            'plate_no' => 'plate_no',
            'data_date' => 'date_sort',
            'gps_time' => 'date_sort',
            'time' => 'date_sort',
            'speed' => 'speed',
            'fuel_left' => 'fuel_left',
            'fuel' => 'fuel_left',
            'status' => 'ic_status',
            'ic_status' => 'ic_status',
        ];

        return $allowedSort[$sortBy] ?? 'plate_no';
    }

    private function resolveDltSynchFilter(Request $request): ?int
    {
        $value = $request->query('dlt_synch', $request->query('dltSynch'));

        return (int) $value === 1 ? 1 : null;
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

    private function isNoDriverCard(array $vehicle): bool
    {
        return (int) ($vehicle['dlt_synch'] ?? 0) === 1
            && (float) ($vehicle['speed'] ?? 0) > 5
            && trim((string) ($vehicle['track3'] ?? '')) === '';
    }

    private function isDltSynched(array $vehicle): bool
    {
        return (int) ($vehicle['dlt_synch'] ?? 0) === 1;
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

        return 'idle';
    }
}
