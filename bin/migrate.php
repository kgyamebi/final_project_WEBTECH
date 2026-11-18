<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$fresh = in_array('--fresh', $argv ?? [], true);
$pdo = db();
if ($fresh) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "Dropped existing tables in " . env_value('DB_NAME') . PHP_EOL;
}
run_sql_script($pdo, (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
echo "Schema ready in " . env_value('DB_NAME') . PHP_EOL;
