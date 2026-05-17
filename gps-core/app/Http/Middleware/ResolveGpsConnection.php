<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveGpsConnection
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || !str_starts_with($token, 'dev-token-')) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $userId = (int) str_replace('dev-token-', '', $token);

        $authUser = DB::connection('auth_db')
            ->table('users')
            ->where('id', $userId)
            ->where('active', 1)
            ->first();


        if (!$authUser) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        $gpsConnection = $this->resolveGpsConnection($authUser->server_name ?? null);

        if (!$gpsConnection) {
            return response()->json([
                'message' => 'Invalid GPS server',
                'server_name' => $authUser->server_name ?? null,
            ], 422);
        }

        if (!config("database.connections.$gpsConnection")) {
            return response()->json([
                'message' => 'GPS database connection is not configured',
                'gps_connection' => $gpsConnection,
            ], 500);
        }

//        dd($gpsConnection);

//        dd($userId);

        $gpsUserCustomer = DB::connection($gpsConnection)->table('user')
            ->leftJoin('customer_user', 'user.user_id', '=', 'customer_user.user_user_id')
            ->leftJoin('customer', 'customer_user.customer_customer_id', '=', 'customer.customer_id')
            ->select('user.*', 'customer.*')
            ->where('user.login', $authUser->login)
            ->first();
        ;

//        dd($gpsUserCustomer);

//        logger()->info('GPS CONNECTION RESOLVED', [
//            'server_name' => $authUser->server_name ?? null,
//            'gps_connection' => $gpsConnection,
//            'db_host' => config("database.connections.$gpsConnection.host"),
//            'db_port' => config("database.connections.$gpsConnection.port"),
//        ]);

        $request->attributes->set('gps_connection', $gpsConnection);
        $request->attributes->set('auth_user', $authUser);
        $request->attributes->set('gpsUserCustomer', $gpsUserCustomer);

        return $next($request);
    }

    private function resolveGpsConnection(?string $serverName): ?string
    {
        return match (strtolower(trim((string) $serverName))) {
            'server5', 'gps5' => 'gps5',
            'server10', 'gps10' => 'gps10',
            'server13', 'gps13' => 'gps13',
            'server14', 'gps14' => 'gps14',
            'server16', 'gps16' => 'gps16',
            'server19', 'gps19' => 'gps19',
            'server20', 'gps20' => 'gps20',
            'server21', 'gps21' => 'gps21',
            default => null,
        };
    }
}
