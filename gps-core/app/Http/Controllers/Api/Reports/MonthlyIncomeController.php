<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class MonthlyIncomeController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 0);
        $this->requireCustomerFeature($c, 'summary_report');
        $this->requireCustomerFeature($c, 'enable_fare_cal');
        $date = new \DateTimeImmutable($c['date_from']);

        return $this->report($c, 'monthly-income', 'sp_rpt_monthly_income', [
            $c['group_id'], $c['login'], $c['imei'], (int) $date->format('n'), (int) $date->format('Y'),
        ]);
    }
}
