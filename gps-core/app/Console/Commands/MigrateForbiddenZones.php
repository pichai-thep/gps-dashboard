<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
test:
php artisan gps:migrate-forbidden-zones --dry-run
php artisan gps:migrate-forbidden-zones "CUSTOMER NAME" --dry-run

real:
php artisan gps:migrate-forbidden-zones
php artisan gps:migrate-forbidden-zones "CUSTOMER NAME"
*/

class MigrateForbiddenZones extends Command
{
    protected $signature = 'gps:migrate-forbidden-zones
                            {customer_name? : Exact customer name}
                            {--server= : GPS server, e.g. gps5, server5, or 5}
                            {--dry-run : Preview without changing data}';

    protected $description = 'Migrate forbidden_zone to forbidden_zones';

    public function handle(): int
    {
        $dbConnection = $this->resolveServer('gps5');
        if ($dbConnection === null) {
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        $conn = DB::connection($dbConnection);
        $customerName = $this->argument('customer_name');
        $customerId = null;

        if ($customerName !== null) {
            $customers = $conn->table('customer')
                ->where('customer_name', $customerName)
                ->get(['customer_id']);

            if ($customers->isEmpty()) {
                $this->error("Customer not found: {$customerName}");
                return self::FAILURE;
            }

            if ($customers->count() > 1) {
                $this->error("More than one customer found with name: {$customerName}");
                return self::FAILURE;
            }

            $customerId = $customers->first()->customer_id;
            $this->info("Customer: {$customerName} (ID: {$customerId})");
        }

        $query = $conn->table('forbidden_zone')
            ->select([
                '*',
                DB::raw('ST_AsText(polygon) AS polygon_wkt'),
            ]);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        $rows = $query->get();

        $this->info("Found {$rows->count()} rows");

        if (!$dryRun) {
            if ($customerId === null) {
                $conn->table('forbidden_zones')->truncate();
            } else {
                $conn->table('forbidden_zones')
                    ->where('customer_id', $customerId)
                    ->delete();
            }
        }
        foreach ($rows as $row) {

            try {

                $fixedPolygon = $this->normalizePolygonWkt(
                    $row->polygon_wkt
                );

                if ($dryRun) {

                    $this->line("
                        ID: {$row->id}
                        ZONE: {$row->zone_name}
                        {$fixedPolygon}
                        ");
                    continue;
                }

                $quoted = $conn->getPdo()->quote($fixedPolygon);

                $conn->table('forbidden_zones')->insert([
                    'id' => $row->id,
                    'zone_name' => $row->zone_name,

                    'polygon' => DB::raw("
                        ST_GeomFromText({$quoted},0)
                    "),

                    'customer_id' => $row->customer_id,
                    'login' => $row->login,
                    'created_at' => $row->created_at,
                ]);

                $this->info("Migrated ID {$row->id}");

            } catch (\Throwable $e) {

                $this->error("
Failed ID {$row->id}
{$e->getMessage()}
");
            }
        }

        $this->info('DONE');

        return self::SUCCESS;
    }

    private function resolveServer(string $default): ?string
    {
        $server = $this->option('server');
        if ($server === null || $server === '') {
            return $default;
        }

        $connection = strtolower(trim((string) $server));
        $connection = preg_replace('/^server/', 'gps', $connection);
        if (ctype_digit($connection)) {
            $connection = 'gps' . $connection;
        }

        if (!preg_match('/^gps\d+$/', $connection) ||
            !array_key_exists($connection, config('database.connections', []))) {
            $this->error("Invalid GPS server: {$server}");
            return null;
        }

        $this->info("Server: {$connection}");
        return $connection;
    }

    private function normalizePolygonWkt(string $wkt): string
    {
        if (!preg_match('/POLYGON\s*\(\((.*)\)\)/i', trim($wkt), $matches)) {
            throw new \Exception("Invalid POLYGON WKT: {$wkt}");
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

            /*
             Thailand detect

             lat = 5-21
             lon = 97-106

             ถ้า x เป็น lat
             และ y เป็น lon
             แปลว่าสลับ
            */

            if (($x >= 5 && $x <= 21) &&
                ($y >= 97 && $y <= 106)) {

                [$x, $y] = [$y, $x];
            }

            // store as lon lat
            $points[] = [$x, $y];
        }

        if (count($points) < 3) {
            throw new \Exception("Polygon has less than 3 points");
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
