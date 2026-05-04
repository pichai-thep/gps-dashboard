<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    private function buildAuthContext($authUser, string $gpsConnection): array
    {
        $dbHost = config("database.connections.$gpsConnection.host");
        $dbPort = config("database.connections.$gpsConnection.port");

        $gpsUser = DB::connection($gpsConnection)
            ->table('user')
            ->where('login', $authUser->login)
            ->first();

        if (!$gpsUser) {
            abort(404, 'GPS user not found');
        }

        $customers = DB::connection($gpsConnection)
            ->table('customer_user as cu')
            ->join('customer as c', 'c.customer_id', '=', 'cu.customer_customer_id')
            ->where('cu.user_user_id', $gpsUser->user_id)
            ->select('c.*')
            ->get();

        if ($customers->isEmpty()) {
            abort(404, 'Customer not found');
        }

        $customer = $customers->first();

        $roles = DB::connection('auth_db')
            ->table('group_role_user as gru')
            ->join('group_role as gr', 'gr.id', '=', 'gru.group_role_id')
            ->where('gru.users_id', $authUser->id)
            ->pluck('gr.group_role_name')
            ->values();

        $dbHost = config("database.connections.$gpsConnection.host");

        return [
            'user' => [
                'id' => $authUser->id,
                'gps_user_id' => $gpsUser->user_id,
                'username' => $authUser->login,
                'email' => $gpsUser->email ?? $authUser->email ?? null,
                'server_name' => $authUser->server_name,
                'gps_connection' => $gpsConnection,
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'roles' => $roles,
            ],

            'customer' => [
                'id' => $customer->customer_id,
                'name' => $customer->customer_name,
                'map_api' => $customer->map_api,
            ],

            'customers' => $customers->map(fn ($item) => [
                'id' => $item->customer_id,
                'name' => $item->customer_name,
                'map_api' => $item->map_api,
            ])->values(),

            'features' => [
                'station' => (bool) $customer->station_show,
                'poi' => (bool) $customer->poi_show,
                'zone' => (bool) $customer->zone_show,
                'overSpeedReport' => (bool) $customer->over_speed_report,
                'summaryReport' => (bool) $customer->summary_report,
                'canbus' => (bool) $customer->enable_canbus,
                'engineCut' => (bool) $customer->enable_engine_cut,
                'fuel' => (bool) $customer->enable_fuel_chk,
                'battery' => (bool) $customer->enable_batt_mont,
                'passenger' => (bool) $customer->enable_passenger,
                'geocoding' => (bool) $customer->enable_geocoding,
                'attendance' => (bool) $customer->enable_attendance,
                'fare' => (bool) $customer->enable_fare_cal,
                'temperature' => (bool) $customer->show_temp,
            ],

            'config' => [
                'fuelUnit' => $customer->fuel_unit_as,
                'mapApi' => $customer->map_api,
                'mapApi_key' => $customer->map_api_key,
                'showInfoWindow' => (bool) $customer->show_infowindow,
            ],
        ];
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('login', $request->username)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        if (!$this->checkPassword($request->password, $user->pwd)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        return response()->json([
            'token' => 'dev-token-' . $user->id,
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $gpsConnection = $request->attributes->get('gps_connection');
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$gpsConnection) {
            return response()->json([
                'message' => 'GPS connection not resolved',
            ], 500);
        }

        return response()->json(
            $this->buildAuthContext($authUser, $gpsConnection)
        );
    }

    public function logout()
    {
        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    private function checkPassword($input, $stored)
    {
        // 1. plain text
        if ($input === $stored) {
            return true;
        }

        // 2. md5
        if (md5($input) === $stored) {
            return true;
        }

        return false;
    }
}
