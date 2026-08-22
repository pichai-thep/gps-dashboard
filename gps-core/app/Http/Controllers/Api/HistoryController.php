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
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:5000',
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

        if ($start->diffInDays($end) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 31 days',
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
        $stmt = $pdo->prepare('CALL sp_webapi_history(?, ?, ?, ?, ?, ?, ?)');
        $imei = $request->imei;
        $sdate = $request->start_date;
        $edate = $request->end_date;
        $stime = $request->start_time;
        $etime = $request->end_time;
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 1000);

        $stmt->bindParam(1, $imei, PDO::PARAM_STR);
        $stmt->bindParam(2, $sdate, PDO::PARAM_STR);
        $stmt->bindParam(3, $edate, PDO::PARAM_STR);
        $stmt->bindParam(4, $stime, PDO::PARAM_STR);
        $stmt->bindParam(5, $etime, PDO::PARAM_STR);
        $stmt->bindParam(6, $page, PDO::PARAM_INT);
        $stmt->bindParam(7, $perPage, PDO::PARAM_INT);

        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pagination = [];
        if ($stmt->nextRowset()) {
            $pagination = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $rows = [];
        if ($stmt->nextRowset()) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt->closeCursor();

        $dltSynch = (int) ($summary['dlt_synch'] ?? $summary['v_dlt_synch'] ?? 0);
        $dltCardReader = (int) ($summary['dlt_card_reader'] ?? $summary['v_dlt_card_reader'] ?? 0);
        $rows = array_map(static function (array $row) use ($dltSynch, $dltCardReader): array {
            $row['dlt_synch'] = (int) ($row['dlt_synch'] ?? $dltSynch);
            $row['dlt_card_reader'] = (int) ($row['dlt_card_reader'] ?? $dltCardReader);
            $row['ext_power_status'] = array_key_exists('ext_power_status', $row)
                && $row['ext_power_status'] !== null
                    ? (int) $row['ext_power_status']
                    : null;
            $temperature = $row['temperature'] ?? null;
            $row['temperature'] = $temperature !== null
                && $temperature !== ''
                && is_numeric($temperature)
                    ? number_format((float) $temperature, 1, '.', '')
                    : null;
            return $row;
        }, $rows);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'pagination' => $pagination,
            'data' => $rows,
        ]);
    }

    public function export(Request $request)
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

        if ($start->diffInDays($end) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 31 days',
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
        $stmt = $pdo->prepare('CALL sp_webapi_history_csv(?, ?, ?, ?, ?)');
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

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rows = [];
        if ($stmt->nextRowset()) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt->closeCursor();

        $csvRows = [];

// UTF-8 BOM ให้ Excel เปิดภาษาไทยได้
        $csv = "\xEF\xBB\xBF";

// Summary
        $csvRows[] = ['Summary'];
        $csvRows[] = ['Run-time', $summary['run_time'] ?? '00:00:00'];
        $csvRows[] = ['Idle-time', $summary['idle_time'] ?? '00:00:00'];
        $csvRows[] = ['Park-time', $summary['park_time'] ?? '00:00:00'];
        $csvRows[] = ['Distance (km)', $summary['distance_km'] ?? 0];
        $csvRows[] = [];

// Header
        $csvRows[] = [
            'No',
            'GPS Time',
            'Plate No',
            'Speed',
            'Status',
            'num_sats',
            'Latitude',
            'Longitude',
            'Fuel_left',
            'Temperature',
        ];

        foreach ($rows as $index => $row) {
            $csvRows[] = [
                $index + 1,
                $row['data_date'] ?? $row['gps_time'] ?? '',
                $row['plate_no'] ?? '',
                $row['speed'] ?? 0,
                $row['car_status'] ?? '',
                $row['num_sats'] ?? 0,
                $row['lat'] ?? '',
                $row['lng'] ?? '',
                $row['fuel_left'] ?? '',
                $row['temperature'] ?? '',
            ];
        }

        $handle = fopen('php://temp', 'r+');

        foreach ($csvRows as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv .= stream_get_contents($handle);
        fclose($handle);

        $filename = sprintf(
            'history-%s-%s-%s.csv',
            $imei,
            $sdate,
            $edate
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
