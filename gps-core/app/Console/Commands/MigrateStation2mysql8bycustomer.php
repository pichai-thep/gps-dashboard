<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
test:
php artisan gps:migrate-stations-mysql8-by-customer "CUSTOMER NAME" --dry-run

real:
php artisan gps:migrate-stations-mysql8-by-customer "CUSTOMER NAME"
*/
class MigrateStation2mysql8bycustomer extends Command
{
    protected $signature = 'gps:migrate-stations-mysql8-by-customer
                            {customer_name : Exact customer name}
                            {--dry-run : Preview without changing data}';

    protected $description = 'Migrate station polygons to MySQL 8 for a customer name';

    public function handle(): int
    {
        $conn = DB::connection('gps21');
        $customerName = $this->argument('customer_name');
        $dryRun = (bool) $this->option('dry-run');

        $customers = $conn->table('customer')
            ->where('customer_name', $customerName)
            ->get(['customer_id', 'customer_name']);

        if ($customers->isEmpty()) {
            $this->error("Customer not found: {$customerName}");
            return self::FAILURE;
        }

        if ($customers->count() > 1) {
            $this->error("More than one customer found with name: {$customerName}");
            return self::FAILURE;
        }

        $customer = $customers->first();
        $customerId = $customer->customer_id;

        $rows = $conn->table('station')
            ->where('customer_customer_id', $customerId)
            ->select([
                '*',
                DB::raw('ST_AsText(station_polygon) AS polygon_wkt'),
                DB::raw('ST_AsText(station_point) AS point_wkt'),
            ])
            ->get();

        $this->info("Customer: {$customer->customer_name} (ID: {$customerId})");
        $this->info("Found {$rows->count()} rows");

        if (!$dryRun) {
            $conn->table('stations')
                ->where('customer_customer_id', $customerId)
                ->delete();
        }

        $migrated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $fixedPolygon = $row->polygon_wkt
                    ? $this->normalizePolygonWkt($row->polygon_wkt)
                    : null;
                $fixedPoint = $row->point_wkt
                    ? $this->normalizePointWkt($row->point_wkt)
                    : null;

                if ($dryRun) {
                    $this->line("ID: {$row->station_id} | {$row->station_name}");
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
                    $quotedPoint = $conn->getPdo()->quote($fixedPoint);
                    $insert['station_point'] = DB::raw(
                        "ST_GeomFromText({$quotedPoint}, 0)"
                    );
                }

                if ($fixedPolygon) {
                    $quotedPolygon = $conn->getPdo()->quote($fixedPolygon);
                    $insert['station_polygon'] = DB::raw(
                        "ST_GeomFromText({$quotedPolygon}, 0)"
                    );
                }

                $conn->table('stations')->insert($insert);
                $migrated++;
                $this->info("Migrated ID {$row->station_id}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed ID {$row->station_id}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info('DRY RUN DONE - no data was changed');
            return self::SUCCESS;
        }

        $this->info("DONE - migrated: {$migrated}, failed: {$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function normalizePointWkt(string $wkt): string
    {
        if (!preg_match('/POINT\s*\((.*)\)/i', trim($wkt), $matches)) {
            throw new \Exception('Invalid POINT WKT');
        }

        $parts = preg_split('/\s+/', trim($matches[1]));
        if (count($parts) < 2) {
            throw new \Exception('Invalid POINT coordinates');
        }

        $x = (float) $parts[0];
        $y = (float) $parts[1];

        if ($x >= 5 && $x <= 21 && $y >= 97 && $y <= 106) {
            [$x, $y] = [$y, $x];
        }

        return "POINT({$x} {$y})";
    }

    private function normalizePolygonWkt(string $wkt): string
    {
        if (!preg_match('/POLYGON\s*\(\((.*)\)\)/i', trim($wkt), $matches)) {
            throw new \Exception('Invalid POLYGON WKT');
        }

        $points = [];
        foreach (explode(',', $matches[1]) as $pair) {
            $parts = preg_split('/\s+/', trim($pair));
            if (count($parts) < 2) {
                continue;
            }

            $x = (float) $parts[0];
            $y = (float) $parts[1];

            if ($x >= 5 && $x <= 21 && $y >= 97 && $y <= 106) {
                [$x, $y] = [$y, $x];
            }

            $points[] = [$x, $y];
        }

        if (count($points) < 3) {
            throw new \Exception('Polygon < 3 points');
        }

        $clean = [];
        foreach ($points as $point) {
            if (empty($clean) || $clean[count($clean) - 1] !== $point) {
                $clean[] = $point;
            }
        }

        if (count($clean) < 3) {
            throw new \Exception('Polygon < 3 unique points');
        }

        if ($clean[0] !== $clean[count($clean) - 1]) {
            $clean[] = $clean[0];
        }

        $coords = array_map(function (array $point): string {
            return "{$point[0]} {$point[1]}";
        }, $clean);

        return 'POLYGON((' . implode(',', $coords) . '))';
    }
}
