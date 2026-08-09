<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class MonthlyDistanceController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 0);
        $this->requireCustomerFeature($c, 'summary_report');
        $date = new \DateTimeImmutable($c['date_from']);

        return $this->report($c, 'monthly-distance', 'sp_rpt_monthly_dist', [
            $c['customer_id'], $c['login'], $c['imei'], (int) $date->format('n'), (int) $date->format('Y'),
        ]);
    }
}
