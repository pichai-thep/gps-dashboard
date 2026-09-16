<?php

namespace App\Http\Controllers\Api\Reports;

use Illuminate\Http\Request;

class RiskEventReportController extends StoredProcedureReportController
{
    public function __invoke(Request $request)
    {
        $c = $this->context($request, 7);
        $riskType = $c['criteria']['risk_type'] ?? '';
        abort_unless(in_array($riskType, ['', 'NEAR', 'ENTER', 'INSIDE', 'EXIT'], true), 422, 'Invalid risk type');
        $riskObject = $c['criteria']['risk_obj'] ?? '';
        abort_unless(in_array($riskObject, ['', 'AREA', 'ROUTE'], true), 422, 'Invalid risk object');

        return $this->report($c, 'risk-event', 'sp_rpt_risk_event', [
            $c['group_id'],
            $c['login'],
            $riskObject,
            $riskType,
            $c['datetime_from'].':00',
            $c['datetime_to'].':59',
        ], $c['imei'] !== ''
            ? static fn (array $row): bool => (string) ($row['imei'] ?? '') === $c['imei']
            : null);
    }
}
