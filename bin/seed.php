<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\SeedData;

if (!in_array('--force', $argv ?? [], true)) {
    fwrite(STDERR, "This replaces routes, stops, reports, and demo accounts in " . env_value('DB_NAME') . ". Re-run with --force.\n");
    exit(1);
}
SeedData::run();
echo "Seeded demo data. Password for demo accounts: DemoPass123!\n";
