<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'imei' => 'required|string',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);

        $dbConnection = $request->attributes->get('gps_connection');

        $start = Carbon::parse($request->start_date . ' ' . $request->start_time . ':00');
        $end = Carbon::parse($request->end_date . ' ' . $request->end_time . ':59');

        if ($start->greaterThanOrEqualTo($end)) {
            return response()->json([
                'success' => false,
                'message' => 'Start time must be before end time',
            ], 422);
        }

        if ($start->diffInDays($end) > 7) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 7 days',
            ], 422);
        }



        if (!$dbConnection) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection not found',
            ], 400);
        }

        $pdo = DB::connection($dbConnection)->getPdo();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

//        $stmt = $pdo->prepare('CALL sp_history_track_all7(?, ?, ?, ?, ?)');
        $stmt = $pdo->prepare('CALL sp_webapi_history(?, ?, ?, ?, ?)');

        $imei = $request->imei;
        $sdate = $request->start_date;
        $edate = $request->end_date;
        $stime = $request->start_time;
        $etime = $request->end_time;

        $stmt->bindParam(1, $imei, PDO::PARAM_STR);
        $stmt->bindParam(2, $sdate, PDO::PARAM_STR);
        $stmt->bindParam(3, $edate, PDO::PARAM_STR);
        $stmt->bindParam(4, $stime, PDO::PARAM_STR);
        $stmt->bindParam(5, $etime, PDO::PARAM_STR);

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return response()->json([
            'success' => true,
            'total' => count($rows),
            'data' => $rows,
        ]);
    }

    public function export(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Export not implemented yet',
        ], 501);
    }
}
