<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
test:
php artisan gps:migrate-pois --dry-run
php artisan gps:migrate-pois "CUSTOMER NAME" --dry-run

real:
php artisan gps:migrate-pois
php artisan gps:migrate-pois "CUSTOMER NAME"
*/

class MigratePois extends Command
{
    protected $signature = 'gps:migrate-pois
                            {customer_name? : Exact customer name}
                            {--server= : GPS server, e.g. gps5, server5, or 5}
                            {--dry-run : Preview without changing data}';

    protected $description = 'Migrate old poi to pois table';

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

        $query = $conn->table('poi')
            ->select([
                '*',
                DB::raw('ST_AsText(g_poi) AS g_poi_wkt'),
            ]);

        if ($customerId !== null) {
            $query->where('customer_customer_id', $customerId);
        }

        $rows = $query->get();

        $this->info("Found {$rows->count()} rows");

        if (!$dryRun) {
            if ($customerId === null) {
                $conn->table('pois')->truncate();
            } else {
                $conn->table('pois')
                    ->where('customer_customer_id', $customerId)
                    ->delete();
            }
        }
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
                        "ST_GeomFromText({$quoted},0)"
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
