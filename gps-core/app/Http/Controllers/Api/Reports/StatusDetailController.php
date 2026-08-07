<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class StatusDetailController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 7, true);
        $status = strtolower(trim((string) ($c['criteria']['status'] ?? '')));

        // The stored procedure classifies an engine-on, stationary vehicle as
        // "idle". Keep "start" as a backwards-compatible API alias, but pass
        // the value used by sp_rpt_status_details when filtering.
        if ($status === 'start') {
            $status = 'idle';
        }

        if ($status === '' || $status === 'all') {
            $status = null;
        }

        $duration = max(0, (int) ($c['criteria']['duration'] ?? 0));

        return $this->report($c, 'status-detail', 'sp_rpt_status_details', [
            $c['imei'],
            $c['customer_id'],
            $c['datetime_from'],
            $c['datetime_to'],
            $status,
            $duration,
        ], static function (array $row) use ($duration): bool {
            return $duration === 0
                || (int) ($row['duration_mm'] ?? $row['state_minute'] ?? 0) >= $duration;
        });
    }
}
