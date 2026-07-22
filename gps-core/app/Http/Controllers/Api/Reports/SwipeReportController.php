<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class SwipeReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 7);

        return $this->report($c, 'swipe', 'sp_rpt_swipe_data', [$c['group_id'], $c['login'], $c['imei'], (string) ($c['criteria']['swipe_type'] ?? ''), $c['datetime_from'], $c['datetime_to']]);
    }
}
