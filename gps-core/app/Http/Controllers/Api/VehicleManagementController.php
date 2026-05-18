<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleManagementController extends Controller
{
    private function conn(Request $request): string
    {
        return $request->attributes->get('gps_connection');
    }

    private function customerId(Request $request): int
    {
        $gpsUserCustomer = $request->attributes->get('gpsUserCustomer');

        if (!$gpsUserCustomer || empty($gpsUserCustomer->customer_id)) {
            abort(403, 'Missing customer_id');
        }

        return (int) $gpsUserCustomer->customer_id;
    }

    public function index(Request $request)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $keyword = trim($request->query('keyword', ''));
        $groupId = $request->query('group_id');

        $query = DB::connection($conn)
            ->table('customer_tracker as ct')
            ->join('tracker as t', 't.imei', '=', 'ct.tracker_imei')
            ->leftJoin('customer_group_tracker as cgt', function ($join) use ($customerId) {
                $join->on('ct.tracker_imei', '=', 'cgt.imei')
                    ->whereExists(function ($q) use ($customerId) {
                        $q->select(DB::raw(1))
                            ->from('customer_group as z')
                            ->whereColumn('z.customer_group_id', 'cgt.customer_group_id')
                            ->where('z.customer_id', $customerId);
                    });
            })
            ->leftJoin('customer_group as cg', 'cg.customer_group_id', '=', 'cgt.customer_group_id')
            ->where('ct.customer_customer_id', $customerId)
            ->select([
                't.imei',
                't.plate_no',
                't.sequen_no',
                DB::raw("
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT cg.customer_group_name
                            ORDER BY cg.customer_group_name
                            SEPARATOR ', '
                        ),
                        ''
                    ) as group_names
                "),
            ])
            ->groupBy('t.imei', 't.plate_no', 't.sequen_no');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('t.imei', 'like', "%{$keyword}%")
                    ->orWhere('t.plate_no', 'like', "%{$keyword}%")
                    ->orWhere('t.driver_name', 'like', "%{$keyword}%");
            });
        }

        if ($groupId) {
            $query->whereExists(function ($q) use ($groupId, $customerId) {
                $q->select(DB::raw(1))
                    ->from('customer_group_tracker as x')
                    ->join('customer_group as g', 'g.customer_group_id', '=', 'x.customer_group_id')
                    ->whereColumn('x.imei', 't.imei')
                    ->where('x.customer_group_id', $groupId)
                    ->where('g.customer_id', $customerId);
            });
        }

        $rows = $query
            ->orderBy('t.plate_no')
            ->limit(500)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, string $imei)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $row = DB::connection($conn)
            ->table('customer_tracker as ct')
            ->join('tracker as t', 't.imei', '=', 'ct.tracker_imei')
            ->leftJoin('tracker_mileage as tm', 'tm.imei', '=', 't.imei')
            ->select([
                't.*',
                'tm.mileage as current_mileage',
            ])
            ->where('ct.customer_customer_id', $customerId)
            ->where('t.imei', $imei)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function updateVehicle(Request $request, string $imei)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);
        $user = $request->attributes->get('auth_user');

        $allowed = DB::connection($conn)
            ->table('customer_tracker')
            ->where('customer_customer_id', $customerId)
            ->where('tracker_imei', $imei)
            ->exists();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found or permission denied',
            ], 404);
        }

        $data = $request->validate([
            'plate_no' => ['nullable', 'string', 'max:50'],
            'sequen_no' => ['nullable', 'numeric'],

            'driver_id' => ['nullable', 'string', 'max:20'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30'],

            'speed_limited' => ['nullable', 'integer'],
            'icon_path' => ['nullable', 'string', 'max:255'],

            'fuel_min_vol' => ['nullable', 'numeric'],
            'fuel_max_vol' => ['nullable', 'numeric'],
            'input_fuel_reverse' => ['nullable', 'boolean'],

            'fuel_kmpl' => ['nullable', 'numeric'],
            'fuel_lph' => ['nullable', 'numeric'],
            'fuel_tank_size' => ['nullable', 'integer'],
            'fuel_price' => ['nullable', 'numeric'],

            'fuel_mont' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:100'],
        ]);

        $data['changed_date'] = now();
        $data['changed_by'] = $user->login ?? null;

        DB::connection($conn)
            ->table('tracker')
            ->where('imei', $imei)
            ->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated',
        ]);
    }

    public function updateMileage(Request $request, string $imei)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);
        $user = $request->attributes->get('auth_user');

        $allowed = DB::connection($conn)
            ->table('customer_tracker')
            ->where('customer_customer_id', $customerId)
            ->where('tracker_imei', $imei)
            ->exists();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found or permission denied',
            ], 404);
        }

        $data = $request->validate([
            'current_mileage' => ['required', 'integer', 'min:0'],
        ]);

        DB::connection($conn)
            ->table('tracker_mileage')
            ->updateOrInsert(
                [
                    'imei' => $imei,
                ],
                [
                    'mileage' => $data['current_mileage'],
                    'updated_date' => now(),
                    'updated_by' => $user->login ?? null,
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Mileage updated',
        ]);
    }

    public function updateUrRate(Request $request, string $imei)
    {
        return response()->json([
            'success' => true,
            'message' => 'UR-rate updated',
        ]);
    }

    public function groups(Request $request)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $rows = DB::connection($conn)
            ->table('customer_group')
            ->select([
                'customer_group_id',
                'customer_group_name',
            ])
            ->where('customer_id', $customerId)
            ->orderBy('customer_group_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function createGroup(Request $request)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $data = $request->validate([
            'customer_group_name' => ['required', 'string', 'max:100'],
        ]);

        $id = DB::connection($conn)
            ->table('customer_group')
            ->insertGetId([
                'customer_id' => $customerId,
                'customer_group_name' => $data['customer_group_name'],
            ]);

        return response()->json([
            'success' => true,
            'customer_group_id' => $id,
            'message' => 'Group created',
        ]);
    }

    public function deleteGroup(Request $request, int $id)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $deleted = DB::connection($conn)
            ->table('customer_group')
            ->where('customer_group_id', $id)
            ->where('customer_id', $customerId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found or permission denied',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Group deleted',
        ]);
    }

    public function moveToGroup(Request $request)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $data = $request->validate([
            'imeis' => ['required', 'array', 'min:1'],
            'imeis.*' => ['required', 'string', 'max:20'],
            'customer_group_id' => ['required', 'integer'],
        ]);

        $groupExists = DB::connection($conn)
            ->table('customer_group')
            ->where('customer_group_id', $data['customer_group_id'])
            ->where('customer_id', $customerId)
            ->exists();

        if (!$groupExists) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid group',
            ], 403);
        }

        $allowedImeis = DB::connection($conn)
            ->table('customer_tracker')
            ->where('customer_customer_id', $customerId)
            ->whereIn('tracker_imei', $data['imeis'])
            ->pluck('tracker_imei')
            ->toArray();

        if (count($allowedImeis) !== count($data['imeis'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some vehicles are not allowed',
            ], 403);
        }

        foreach ($allowedImeis as $imei) {
            DB::connection($conn)
                ->table('customer_group_tracker')
                ->where('imei', $imei)
                ->delete();

            DB::connection($conn)
                ->table('customer_group_tracker')
                ->insert([
                    'imei' => $imei,
                    'customer_group_id' => $data['customer_group_id'],
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicles moved to group',
        ]);
    }

    public function removeVehiclesFromGroup(Request $request)
    {
        $conn = $this->conn($request);
        $customerId = $this->customerId($request);

        $data = $request->validate([
            'group_id' => ['required', 'integer'],
            'imeis' => ['required', 'array', 'min:1'],
            'imeis.*' => ['required', 'string'],
        ]);

        DB::connection($conn)
            ->table('customer_group_tracker')
            ->where('customer_group_id', $data['group_id'])
            ->whereIn('imei', $data['imeis'])
            ->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
