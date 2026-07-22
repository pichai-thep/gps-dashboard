<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class SpeedOverController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 7);

        return $this->report($c, 'speed-over', 'sp_rpt_speed_over_time', [$c['group_id'], (string) ($c['criteria']['over_type'] ?? ''), $c['login'], $c['imei'], $c['datetime_from'], $c['datetime_to']]);
    }
}
