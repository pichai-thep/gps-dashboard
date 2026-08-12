<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class TemperatureChartController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 3, true);
        $sensor = (int) $request->query('sensor_no', 1);
        abort_unless(in_array($sensor, [1, 2], true), 422, 'Invalid temperature sensor');

        return $this->report($c, 'temperature-chart', 'sp_rpt_temperature_chart', [
            $c['imei'],
            $c['date_from'],
            $c['date_to'],
            $c['time_from'],
            $c['time_to'],
            $sensor,
        ]);
    }
}
