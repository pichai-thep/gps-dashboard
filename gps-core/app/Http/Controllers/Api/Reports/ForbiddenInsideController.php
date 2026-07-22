<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class ForbiddenInsideController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 30);

        return $this->report($c, 'forbidden-inside', 'sp_rpt_forbidden_inside', [$c['group_id'], $c['login'], $c['imei'], $c['date_from'], $c['date_to']]);
    }
}
