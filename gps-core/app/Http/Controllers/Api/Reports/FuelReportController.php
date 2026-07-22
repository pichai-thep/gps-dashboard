<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class FuelReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 3, true);

        return $this->report($c, 'fuel', 'sp_rpt_fuel2', [$c['imei'], $c['date_from'], $c['date_to'], $c['time_from'], $c['time_to']]);
    }
}
