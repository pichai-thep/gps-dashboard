<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class SpeedReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 3, true);
        $offset = max(0, (int) $request->query('offset', 0));
        $size = min(200, max(1, (int) $request->query('size', 50)));
        $speed = min(160, max(0, (int) ($c['criteria']['speed'] ?? 0)));

        $statement = DB::connection($c['connection'])
            ->getPdo()
            ->prepare('CALL sp_rpt_speed(?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $c['imei'],
            $c['datetime_from'],
            $c['datetime_to'],
            $speed,
            $offset,
            $size,
        ]);

        $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $statement->nextRowset();
        $pagination = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $statement->nextRowset();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return response()->json([
            'success' => true,
            'report' => 'speed',
            'summary' => [
                'total_rows' => (int) ($summary['total_rows'] ?? 0),
                'average_speed' => (float) ($summary['average_speed'] ?? 0),
                'max_speed' => (int) ($summary['max_speed'] ?? 0),
                'speed_over_rows' => (int) ($summary['speed_over_rows'] ?? 0),
                'speed_limited' => (int) ($summary['speed_limited'] ?? 0),
            ],
            'pagination' => [
                'current_page' => (int) ($pagination['current_page'] ?? 1),
                'per_page' => (int) ($pagination['per_page'] ?? $size),
                'offset' => (int) ($pagination['offset'] ?? $offset),
                'total_rows' => (int) ($pagination['total_rows'] ?? 0),
                'total_pages' => (int) ($pagination['total_pages'] ?? 0),
            ],
            'data' => $rows,
            'meta' => [
                'total_rows' => (int) ($pagination['total_rows'] ?? 0),
                'max_range_days' => $c['max_days'],
                'offset' => (int) ($pagination['offset'] ?? $offset),
                'size' => (int) ($pagination['per_page'] ?? $size),
            ],
        ]);
    }
}
