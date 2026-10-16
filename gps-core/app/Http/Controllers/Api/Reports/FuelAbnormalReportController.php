<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class FuelAbnormalReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 30);

        return $this->report($c, 'fuel-abnormal', 'sp_rpt_fuel_abnormal', [
            $c['group_id'], $c['imei'], $c['date_from'], $c['date_to'], $c['login'],
        ]);
    }
}
