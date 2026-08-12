<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class TemperatureReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 3, true);
        $status = strtolower(trim((string) ($c['criteria']['temp_status'] ?? 'all')));
        abort_unless(in_array($status, ['all', 'green', 'yellow', 'red'], true), 422, 'Invalid temperature status');

        $statement = DB::connection($c['connection'])
            ->getPdo()
            ->prepare('CALL sp_rpt_temperature(?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $c['customer_id'],
            $c['imei'],
            $c['date_from'],
            $c['date_to'],
            $c['time_from'],
            $c['time_to'],
            $status,
        ]);

        $config = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $rows = [];
        if ($statement->nextRowset()) {
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $statement->closeCursor();
        $numberOrNull = static fn (string $key): ?float => isset($config[$key])
            ? (float) $config[$key]
            : null;

        return response()->json([
            'success' => true,
            'report' => 'temperature',
            'config' => [
                'sensor_a' => [
                    'min' => $numberOrNull('sensor_a_min'),
                    'max' => $numberOrNull('sensor_a_max'),
                    'average' => $numberOrNull('sensor_a_average'),
                ],
                'sensor_b' => [
                    'min' => $numberOrNull('sensor_b_min'),
                    'max' => $numberOrNull('sensor_b_max'),
                    'average' => $numberOrNull('sensor_b_average'),
                ],
            ],
            'data' => $rows,
            'meta' => ['total_rows' => count($rows), 'max_range_days' => $c['max_days']],
        ]);
    }
}
