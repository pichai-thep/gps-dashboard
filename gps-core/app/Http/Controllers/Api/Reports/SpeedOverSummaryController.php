<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class SpeedOverSummaryController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 0);

        return $this->report($c, 'speed-over-summary', 'sp_rpt_speed_over_sum', [$c['group_id'], $c['login'], (string) ($c['criteria']['over_type'] ?? ''), $c['imei'], $c['date_from'], $c['date_to']]);
    }
}
