<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
test:
php artisan gps:migrate-stations-mysql8 --dry-run

real:
php artisan gps:migrate-stations-mysql8
*/

class MigrateStation2mysql8 extends Command
{
    protected $signature = 'gps:migrate-stations-mysql8 {--dry-run}';
    protected $description = 'Migrate old station polygons to stations table';

    public function handle(): int
    {
        $dbConnection = 'gps21';
        $dryRun = $this->option('dry-run');
        $conn = DB::connection($dbConnection);

        $rows = $conn->table('station')
            ->select([
                '*',
                DB::raw('ST_AsText(station_polygon) AS polygon_wkt'),
                DB::raw('ST_AsText(station_point) AS point_wkt'),
            ])
            ->get();

        $this->info("Found {$rows->count()} rows");

        $conn->table('stations')->truncate();
        foreach ($rows as $row) {
            try {
                $fixedPolygon = null;
                $fixedPoint = null;

                if ($row->polygon_wkt) {
                    $fixedPolygon = $this->normalizePolygonWkt(
                        $row->polygon_wkt
                    );
                }

                if ($row->point_wkt) {
                    $fixedPoint = $this->normalizePointWkt(
                        $row->point_wkt
                    );
                }

                if ($dryRun) {
                    $this->line("
                                ID: {$row->station_id}
                                NAME: {$row->station_name}

                                POINT:
                                {$fixedPoint}

                                POLYGON:
                                {$fixedPolygon}
                                ");
                    continue;
                }

                $insert = [
                    'station_id' => $row->station_id,
                    'station_name' => $row->station_name,
                    'radius' => $row->radius,
                    'station_type' => $row->station_type,
                    'customer_customer_id' => $row->customer_customer_id,
                    'created_date' => $row->created_date,
                    'modified_date' => $row->modified_date,
                ];

                if ($fixedPoint) {
                    $quoted = $conn->getPdo()->quote($fixedPoint);
                    $insert['station_point'] = DB::raw("
                        ST_GeomFromText({$quoted}, 0)
                    ");
                }

                if ($fixedPolygon) {
                    $quoted = $conn->getPdo()->quote($fixedPolygon);
                    $insert['station_polygon'] = DB::raw("
                        ST_GeomFromText({$quoted}, 0)
                    ");
                }

                $conn->table('stations')->insert($insert);
                $this->info("Migrated ID {$row->station_id} {$fixedPoint} {$fixedPolygon}");

            } catch (\Throwable $e) {
                $this->error("Failed ID {$row->station_id} {$e->getMessage()}");
            }
        }
        $this->info('DONE');
        return self::SUCCESS;
    }

    private function normalizePointWkt(string $wkt): string
    {
        if (!preg_match('/POINT\s*\((.*)\)/i', trim($wkt), $matches)) {
            throw new \Exception("Invalid POINT WKT");
        }

        $parts = preg_split('/\s+/', trim($matches[1]));

        $x = (float)$parts[0];
        $y = (float)$parts[1];

        // lat/lon swapped
        if (($x >= 5 && $x <= 21) &&
            ($y >= 97 && $y <= 106)) {

            [$x, $y] = [$y, $x];
        }

        return "POINT({$x} {$y})";
    }

    private function normalizePolygonWkt(string $wkt): string
    {
        if (!preg_match('/POLYGON\s*\(\((.*)\)\)/i', trim($wkt), $matches)) {
            throw new \Exception("Invalid POLYGON WKT");
        }

        $pairs = explode(',', $matches[1]);

        $points = [];

        foreach ($pairs as $pair) {

            $parts = preg_split('/\s+/', trim($pair));

            if (count($parts) < 2) {
                continue;
            }

            $x = (float)$parts[0];
            $y = (float)$parts[1];

            // detect swapped lat/lon
            if (($x >= 5 && $x <= 21) &&
                ($y >= 97 && $y <= 106)) {

                [$x, $y] = [$y, $x];
            }

            $points[] = [$x, $y];
        }

        if (count($points) < 3) {
            throw new \Exception("Polygon < 3 points");
        }

        // remove duplicate consecutive points
        $clean = [];

        foreach ($points as $point) {

            if (empty($clean) ||
                $clean[count($clean) - 1] !== $point) {

                $clean[] = $point;
            }
        }

        // close polygon
        if ($clean[0] !== $clean[count($clean) - 1]) {
            $clean[] = $clean[0];
        }

        $coords = array_map(function ($p) {
            return "{$p[0]} {$p[1]}";
        }, $clean);

        return 'POLYGON((' . implode(',', $coords) . '))';
    }
}
