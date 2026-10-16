<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Reports\FuelAbnormalReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FuelAbnormalReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.vehicle_options_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $schema = Schema::connection('vehicle_options_test');
        foreach ([
            'user' => ['user_id', 'login'],
            'customer_user' => ['user_user_id', 'customer_customer_id'],
            'tracker' => ['imei', 'plate_no'],
            'customer_tracker' => ['tracker_imei', 'customer_customer_id'],
            'user_tracker' => ['tracker_imei', 'user_user_id'],
            'customer_group' => ['customer_group_id', 'customer_id'],
            'customer_group_tracker' => ['customer_group_id', 'imei'],
        ] as $table => $columns) {
            $schema->create($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->string($column);
                }
            });
        }
        $db = DB::connection('vehicle_options_test');
        $db->table('user')->insert(['user_id' => '1', 'login' => 'driver']);
        $db->table('customer_user')->insert(['user_user_id' => '1', 'customer_customer_id' => '10']);
        foreach (['allowed', 'unassigned', 'other-customer'] as $imei) {
            $db->table('tracker')->insert(['imei' => $imei, 'plate_no' => $imei]);
            $db->table('customer_tracker')->insert([
                'tracker_imei' => $imei,
                'customer_customer_id' => $imei === 'other-customer' ? '20' : '10',
            ]);
            if ($imei !== 'unassigned') {
                $db->table('user_tracker')->insert(['tracker_imei' => $imei, 'user_user_id' => '1']);
            }
        }
        $db->table('customer_group')->insert(['customer_group_id' => '5', 'customer_id' => '10']);
        $db->table('customer_group_tracker')->insert(['customer_group_id' => '5', 'imei' => 'allowed']);

    }

    protected function tearDown(): void
    {
        DB::purge('vehicle_options_test');
        parent::tearDown();
    }

    private function report(array $params = []): array
    {
        $request = Request::create('/', 'GET', array_replace([
            'date_from' => '2026-09-14', 'date_to' => '2026-09-14',
        ], $params));
        $request->attributes->add([
            'gps_connection' => 'vehicle_options_test',
            'auth_user' => (object) ['login' => 'driver'],
            'gpsUserCustomer' => (object) ['customer_id' => 10],
        ]);

        $controller = new class extends FuelAbnormalReportController
        {
            protected function report(array $context, string $report, string $procedure, array $arguments, ?callable $rowFilter = null)
            {
                $rows = [['imei' => 'allowed'], ['imei' => 'another-allowed']];
                $data = $rowFilter ? array_values(array_filter($rows, $rowFilter)) : $rows;

                return response()->json(compact('report', 'procedure', 'arguments', 'data'));
            }
        };

        return $controller($request)->getData(true);
    }

    public function test_dispatches_default_filters_in_procedure_parameter_order(): void
    {
        $result = $this->report();
        $this->assertSame('fuel-abnormal', $result['report']);
        $this->assertSame('sp_rpt_fuel_abnormal', $result['procedure']);
        $this->assertSame([-1, '', '2026-09-14', '2026-09-14', 'driver'], $result['arguments']);
    }

    public function test_passes_selected_group_vehicle_and_date_range(): void
    {
        $result = $this->report(['group_id' => 5, 'imei' => 'allowed', 'date_to' => '2026-09-30']);
        $this->assertSame([5, 'allowed', '2026-09-14', '2026-09-30', 'driver'], $result['arguments']);
    }

    public function test_rejects_unauthorized_filters_and_invalid_dates(): void
    {
        foreach ([
            [['customer_id' => 20], 403],
            [['imei' => 'other-customer'], 403],
            [['group_id' => 6], 403],
            [['date_to' => '2026-10-14'], 422],
            [['date_to' => '2026-09-13'], 422],
        ] as [$params, $status]) {
            try {
                $this->report($params);
                $this->fail('Expected rejection: '.json_encode($params));
            } catch (HttpException $exception) {
                $this->assertSame($status, $exception->getStatusCode());
            }
        }
    }
}
