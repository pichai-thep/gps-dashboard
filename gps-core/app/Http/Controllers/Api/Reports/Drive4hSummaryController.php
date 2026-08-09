<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class Drive4hSummaryController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 0);

        return $this->report($c, 'drive4h-summary', 'sp_rpt_drive4h_sum', [$c['group_id'], $c['login'], $c['imei'], $c['date_from'], $c['date_to'], (int) ($c['criteria']['mm_chk'] ?? 0)]);
    }
}
