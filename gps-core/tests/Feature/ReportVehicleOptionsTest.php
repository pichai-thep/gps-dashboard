<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportVehicleOptionsTest extends TestCase
{
    public function test_options_only_include_assigned_customer_vehicles_and_respect_groups(): void
    {
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

        foreach ([[[], ['allowed']], [[5], ['allowed']], [[6], []]] as [$groups, $expected]) {
            $request = Request::create('/', 'GET', ['group_ids' => $groups]);
            $request->attributes->add([
                'gps_connection' => 'vehicle_options_test',
                'auth_user' => (object) ['login' => 'driver'],
                'gpsUserCustomer' => (object) ['customer_id' => 10],
            ]);
            $response = (new ReportController)->vehicleOptions($request);
            $this->assertSame($expected, array_column($response->getData(true)['data'], 'imei'));
        }
        DB::purge('vehicle_options_test');
    }
}
