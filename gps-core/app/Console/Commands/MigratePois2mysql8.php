<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
test:
php artisan gps:migrate-pois-mysql8 --dry-run

real:
php artisan gps:migrate-pois-mysql8
*/

class MigratePois2mysql8 extends Command
{
    protected $signature = 'gps:migrate-pois-mysql8 {--dry-run}';

    protected $description = 'Migrate old poi to pois table';

    public function handle(): int
    {
        $dbConnection = 'gps5';
        $dryRun = $this->option('dry-run');

        $conn = DB::connection($dbConnection);

        $rows = $conn->table('poi')
            ->select([
                '*',
                DB::raw('ST_AsText(g_poi) AS g_poi_wkt'),
            ])
            ->get();

        $this->info("Found {$rows->count()} rows");

        foreach ($rows as $row) {
            try {
                $fixedPoint = $this->normalizePointWkt($row->g_poi_wkt);

                if ($dryRun) {
                    $this->line("ID {$row->poi_id}: {$row->poi_name} => {$fixedPoint}");
                    continue;
                }

                $quoted = $conn->getPdo()->quote($fixedPoint);

                $conn->table('pois')->insert([
                    'poi_id' => $row->poi_id,
                    'poi_name' => $row->poi_name,
                    'icon' => $row->icon,
                    'g_poi' => DB::raw(
                        "ST_GeomFromText({$quoted}, 4326, 'axis-order=long-lat')"
                    ),
                    'customer_customer_id' => $row->customer_customer_id,
                ]);

                $this->info("Migrated ID {$row->poi_id}");
            } catch (\Throwable $e) {
                $this->error("Failed ID {$row->poi_id}: " . $e->getMessage());
            }
        }

        $this->info('DONE');

        return self::SUCCESS;
    }

    private function normalizePointWkt(string $wkt): string
    {
        if (!preg_match('/POINT\s*\((.*)\)/i', trim($wkt), $matches)) {
            throw new \Exception("Invalid POINT WKT: {$wkt}");
        }

        $parts = preg_split('/\s+/', trim($matches[1]));

        if (count($parts) < 2) {
            throw new \Exception("Invalid POINT parts: {$wkt}");
        }

        $x = (float) $parts[0];
        $y = (float) $parts[1];

        // Thailand detect:
        // lat = 5-21, lon = 97-106
        // ถ้า x เป็น lat และ y เป็น lon แปลว่าสลับ
        if (($x >= 5 && $x <= 21) && ($y >= 97 && $y <= 106)) {
            [$x, $y] = [$y, $x];
        }

        return "POINT({$x} {$y})";
    }
}
