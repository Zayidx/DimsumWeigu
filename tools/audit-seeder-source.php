<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() === 'sqlite') {
    $connection->statement('PRAGMA query_only = ON');
} elseif ($connection->getDriverName() === 'mysql') {
    $connection->statement('SET TRANSACTION READ ONLY');
} else {
    throw new RuntimeException('Unsupported source driver.');
}
$connection->beginTransaction();
try {
    $schema = $connection->getSchemaBuilder();
    $tables = [];
    foreach ($schema->getTables($connection->getDriverName() === 'mysql' ? $connection->getDatabaseName() : null) as $table) {
        $name = $table['name'];
        $tables[$name] = [
            'count' => $connection->table($name)->count(),
            'columns' => array_map(fn ($column) => ['name' => $column['name'], 'nullable' => $column['nullable'], 'type' => $column['type_name']], $schema->getColumns($name)),
            'foreign_keys' => $schema->getForeignKeys($name),
            'indexes' => $schema->getIndexes($name),
        ];
    }
    file_put_contents(__DIR__.'/../storage/app/seeder-source-audit.json', json_encode(['driver' => $connection->getDriverName(), 'tables' => $tables], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode(['driver' => $connection->getDriverName(), 'tables' => array_map(fn ($table) => $table['count'], $tables)]);
} finally {
    $connection->rollBack();
}
