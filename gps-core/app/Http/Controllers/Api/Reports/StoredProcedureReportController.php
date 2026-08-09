<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

abstract class StoredProcedureReportController extends Controller
{
    protected function requireCustomerFeature(array $context, string $column): void
    {
        $enabled = DB::connection($context['connection'])->table('customer')
            ->where('customer_id', $context['customer_id'])
            ->where($column, 1)
            ->exists();
        abort_unless($enabled, 403, 'Report feature is not enabled');
    }

    protected function context(Request $request, int $maxDays, bool $requiresVehicle = false): array
    {
        $connection = $request->attributes->get('gps_connection');
        $user = $request->attributes->get('auth_user');
        $gpsCustomer = $request->attributes->get('gpsUserCustomer');
        $customerId = (int) $request->query('customer_id', $gpsCustomer->customer_id ?? 0);

        abort_if(! $connection, 400, 'Unsupported GPS server');
        abort_if($customerId <= 0, 422, 'Missing customer_id');

        $customerAllowed = DB::connection($connection)
            ->table('customer_user as cu')
            ->join('user as u', 'u.user_id', '=', 'cu.user_user_id')
            ->where('u.login', $user->login ?? null)
            ->where('cu.customer_customer_id', $customerId)
            ->exists();
        abort_unless($customerAllowed, 403, 'Unauthorized customer_id');

        $dateFrom = (string) $request->query('date_from', now()->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo);

        if (! $startDate || ! $endDate || $startDate->format('Y-m-d') !== $dateFrom || $endDate->format('Y-m-d') !== $dateTo || $endDate < $startDate) {
            abort(422, 'Invalid report date range');
        }

        $inclusiveDays = (int) $startDate->diff($endDate)->days + 1;
        if ($maxDays > 0) {
            abort_if($inclusiveDays > $maxDays, 422, "Date range may not exceed {$maxDays} days");
        }

        $timeFrom = (string) $request->query('time_from', '00:00');
        $timeTo = (string) $request->query('time_to', '23:59');
        abort_unless(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeFrom), 422, 'Invalid start time');
        abort_unless(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeTo), 422, 'Invalid end time');
        abort_if($dateFrom === $dateTo && $timeTo < $timeFrom, 422, 'End time must not be before start time');

        $groupId = (int) $request->query('group_id', -1);
        if ($groupId > 0) {
            $groupAllowed = DB::connection($connection)->table('customer_group')
                ->where('customer_group_id', $groupId)->where('customer_id', $customerId)->exists();
            abort_unless($groupAllowed, 403, 'Unauthorized group_id');
        } else {
            $groupId = -1;
        }

        $imei = trim((string) $request->query('imei', ''));
        if ($imei !== '') {
            $vehicleAllowed = DB::connection($connection)->table('customer_tracker')
                ->where('customer_customer_id', $customerId)->where('tracker_imei', $imei)->exists();
            abort_unless($vehicleAllowed, 403, 'Unauthorized imei');
        }
        abort_if($requiresVehicle && $imei === '', 422, 'Vehicle is required');

        $criteria = $request->query('criteria', []);

        return [
            'connection' => $connection,
            'login' => (string) ($user->login ?? ''),
            'customer_id' => $customerId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'datetime_from' => "{$dateFrom} {$timeFrom}",
            'datetime_to' => "{$dateTo} {$timeTo}",
            'group_id' => $groupId,
            'imei' => $imei,
            'criteria' => is_array($criteria) ? $criteria : [],
            'max_days' => $maxDays,
        ];
    }

    protected function report(
        array $context,
        string $report,
        string $procedure,
        array $arguments,
        ?callable $rowFilter = null
    ) {
        $placeholders = implode(', ', array_fill(0, count($arguments), '?'));
        $statement = DB::connection($context['connection'])->getPdo()->prepare("CALL {$procedure}({$placeholders})");
        $statement->execute($arguments);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if ($rowFilter) {
            $rows = array_values(array_filter($rows, $rowFilter));
        }

        return response()->json([
            'success' => true,
            'report' => $report,
            'data' => $rows,
            'meta' => ['total_rows' => count($rows), 'max_range_days' => $context['max_days']],
        ]);
    }
}
