<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:191',
            'platform' => 'nullable|in:android,ios',
            'app_version' => 'nullable|string|max:50',
        ]);

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('login', $request->username)
            ->where('active', 1)
            ->first();

        if (!$user) {
            $msg = "Mobile Login unauthorized user:$request->username not found";
            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        if (!$this->checkPassword($request->password, $user->pwd)) {
            $msg = "Mobile Login unauthorized user:$request->username, Invalid password";
            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        $plainToken = Str::random(80);

        DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->insert([
                'user_id' => $user->id,
                'login' => $user->login,
                'server_name' => $user->server_name,
                'token_hash' => hash('sha256', $plainToken),
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'app_version' => $request->app_version,
                'expires_at' => now()->addDays(90),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $msg = 'Mobile Login success, user: ' . $user->login;
        logger()->info($msg);

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'expires_at' => now()->addDays(90)->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
            ],
        ]);
    }

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
                'stationInOutSummaryReport' => (bool) $customer->summary_station_inout,
                'canbus' => (bool) $customer->enable_canbus,
                'engineCut' => (bool) $customer->enable_engine_cut,
                'fuel' => (bool) $customer->enable_fuel_chk,
                'battery' => (bool) $customer->enable_batt_mont,
                'passenger' => (bool) $customer->enable_passenger,
                'geocoding' => (bool) $customer->enable_geocoding,
                'attendance' => (bool) $customer->enable_attendance,
                'fare' => (bool) $customer->enable_fare_cal,
                'temperature' => (bool) $customer->show_temp,
                'input1' => (bool) $customer->show_input1,
                'input2' => (bool) $customer->show_input2,
            ],

            'config' => [
                'fuelUnit' => $customer->fuel_unit_as,
                'mapApi' => $customer->map_api,
                'mapApi_key' => $customer->map_api_key,
                'showInfoWindow' => (bool) $customer->show_infowindow,
            ],
        ];
    }

//    public function me(Request $request)
//    {
//        $user = $request->attributes->get('mobile_user');
//
//        return response()->json([
//            'success' => true,
//            'user' => [
//                'id' => $user->id,
//                'username' => $user->login,
//                'server_name' => $user->server_name,
//            ],
//        ]);
//    }

    public function me(Request $request)
    {
        $gpsConnection = $request->attributes->get('gps_connection');
//        $authUser = $request->attributes->get('auth_user');
        $authUser = $request->attributes->get('mobile_user');

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

    public function logout(Request $request)
    {
        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $msg = "Mobile logout unauthorized";
            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        $tokenHash = hash('sha256', trim($matches[1]));

        DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->where('token_hash', $tokenHash)
            ->delete();

        $msg = "Mobile logout success";
        logger()->info($msg);

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

//    private function checkPassword(string $plainPassword, string $storedPassword): bool
//    {
//        if (password_verify($plainPassword, $storedPassword)) {
//            return true;
//        }
//
//        return md5($plainPassword) === $storedPassword;
//    }

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
