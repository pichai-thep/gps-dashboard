<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileAlarmSettingController extends Controller
{
    private array $fields = [
        'speed_over_alert',
        'input1_on_alert',
        'input1_off_alert',
        'input2_on_alert',
        'input2_off_alert',
        'engine_on_alert',
        'engine_off_alert',
        'power_on_alert',
        'power_off_alert',
        'station_in_alert',
        'station_out_alert',
        'fixzone_out_alert',
        'restrictzone_in_alert',
        'restrictzone_out_alert',
        'gps_antenna_connect_alert',
        'gps_antenna_disconnect_alert',
        'abnormal_gps_alert',
        'speed_over_device_alert',
        'speed_over_cloud_alert',
        'abnormal_fuel_alert',
        'hash_accelerate_alert',
        'hash_break_alert',
        'drive4h_alert',
    ];

    public function index(Request $request)
    {
        $mobileUser = $request->attributes->get('mobile_user');
        $connection = $request->attributes->get('gps_connection');

        $user = DB::connection($connection)
            ->table('user')
            ->where('login', $mobileUser->login)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User setting not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSetting($user),
        ]);
    }

    public function update(Request $request)
    {
        $mobileUser = $request->attributes->get('mobile_user');
        $connection = $request->attributes->get('gps_connection');

        $rules = [];

        foreach ($this->fields as $field) {
            $rules[$field] = 'sometimes|boolean';
        }

        $validated = $request->validate($rules);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'No setting changed',
            ], 422);
        }

        $data = [];

        foreach ($this->fields as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field] ? 1 : 0;
            }
        }

        $data['changed_date'] = now();
        $data['changed_by'] = $mobileUser->login;

        $updated = DB::connection($connection)
            ->table('user')
            ->where('login', $mobileUser->login)
            ->update($data);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'User setting not found or not changed',
            ], 404);
        }

        $user = DB::connection($connection)
            ->table('user')
            ->where('login', $mobileUser->login)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $this->formatSetting($user),
        ]);
    }

    private function formatSetting($user): array
    {
        $data = [];

        foreach ($this->fields as $field) {
            $data[$field] = (bool) ($user->{$field} ?? false);
        }

        return $data;
    }
}
