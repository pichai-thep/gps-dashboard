<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class FuelReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 3, true);
        $status = strtolower(trim((string) ($c['criteria']['status'] ?? '')));
        abort_unless(in_array($status, ['', 'park', 'idle', 'run'], true), 422, 'Invalid vehicle status');

        return $this->report(
            $c,
            'fuel',
            'sp_rpt_fuel2',
            [$c['imei'], $c['date_from'], $c['date_to'], $c['time_from'], $c['time_to']],
            function (array $row) use ($status): bool {
                if ($status === '') {
                    return true;
                }

                $normalizedRow = array_change_key_case($row, CASE_LOWER);
                $raw = strtolower(trim((string) ($normalizedRow['vehicle_status'] ?? $normalizedRow['status'] ?? $normalizedRow['state'] ?? '')));
                $speed = (float) ($normalizedRow['speed'] ?? 0);

                if ($speed > 0 || str_contains($raw, 'run') || str_contains($raw, 'moving') || str_contains($raw, 'วิ่ง')) {
                    $rowStatus = 'run';
                } elseif (
                    $raw === '1'
                    || str_contains($raw, 'idle')
                    || str_contains($raw, 'start')
                    || str_contains($raw, 'on')
                    || str_contains($raw, 'true')
                    || str_contains($raw, 'ติดเครื่อง')
                ) {
                    $rowStatus = 'idle';
                } else {
                    $rowStatus = 'park';
                }

                return $rowStatus === $status;
            }
        );
    }
}
