<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class EventReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 7);

        return $this->report($c, 'event', 'sp_rpt_event', [$c['group_id'], $c['login'], $c['imei'], (string) ($c['criteria']['event_type'] ?? ''), $c['date_from'], $c['date_to']]);
    }
}
