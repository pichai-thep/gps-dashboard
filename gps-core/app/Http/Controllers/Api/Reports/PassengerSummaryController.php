<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class PassengerSummaryController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 30);

        return $this->report($c, 'passenger-summary', 'sp_rpt_passenger_sum', [$c['group_id'], $c['customer_id'], $c['login'], $c['imei'], $c['date_from'], $c['date_to'], (int) ($c['criteria']['mm_chk'] ?? 0)]);
    }
}
