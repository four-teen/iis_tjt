<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/text_encoding.php';

$targetConfig = $app['database'];
$sourceConfig = [
    'host' => env_value('LEGACY_DB_HOST', '127.0.0.1'),
    'port' => (int) env_value('LEGACY_DB_PORT', 3306),
    'database' => env_value('LEGACY_DB_DATABASE', 'nhts32trz_iis_tjtmovers_db'),
    'username' => env_value('LEGACY_DB_USERNAME', 'root'),
    'password' => env_value('LEGACY_DB_PASSWORD', 'vertrigo'),
    'charset' => env_value('LEGACY_DB_CHARSET', 'utf8mb4'),
];

$tables = [
    'tblcustomer',
    'tblcustomerinformation',
    'tblcustomerinformation_new_rates',
    'tbllocation',
    'tbldeliverytype',
    'tbltrucktype',
    'tbltripdrops_perdrops',
    'tbltripdrops_perkilo',
    'tblmultiple_pickup',
    'tbladditional_trips',
];

function migration_pdo(array $config)
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function quote_ident($identifier)
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function table_exists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
    ');
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function foreign_key_exists(PDO $pdo, $constraintName)
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = "FOREIGN KEY"
            AND CONSTRAINT_NAME = :constraint_name
    ');
    $stmt->execute(['constraint_name' => $constraintName]);

    return (int) $stmt->fetchColumn() > 0;
}

function recreate_statement(PDO $source, $table)
{
    $row = $source->query('SHOW CREATE TABLE ' . quote_ident($table))->fetch();

    return preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $row['Create Table']);
}

function copy_table_data(PDO $source, PDO $target, $table)
{
    $columns = array_map(function ($row) {
        return $row['Field'];
    }, $source->query('SHOW COLUMNS FROM ' . quote_ident($table))->fetchAll());

    $insert = $target->prepare(sprintf(
        'REPLACE INTO %s (%s) VALUES (%s)',
        quote_ident($table),
        implode(', ', array_map('quote_ident', $columns)),
        implode(', ', array_map(function ($column) {
            return ':' . $column;
        }, $columns))
    ));

    $copied = 0;
    $target->beginTransaction();

    try {
        foreach ($source->query('SELECT * FROM ' . quote_ident($table)) as $row) {
            foreach ($row as $column => $value) {
                if (is_string($value)) {
                    $row[$column] = repair_legacy_text_encoding($value);
                }
            }

            if ($table === 'tblcustomerinformation' || $table === 'tblcustomerinformation_new_rates') {
                $row['deliverytype'] = legacy_delivery_type_id($row['deliverytype']);
                $row['trucktype'] = legacy_truck_type_id($row['trucktype']);
            }

            $params = [];

            foreach ($columns as $column) {
                $params[$column] = $row[$column];
            }

            $insert->execute($params);
            $copied++;

            if ($copied % 500 === 0) {
                $target->commit();
                $target->beginTransaction();
            }
        }

        $target->commit();
    } catch (Throwable $error) {
        if ($target->inTransaction()) {
            $target->rollBack();
        }

        throw $error;
    }

    return $copied;
}

function convert_table_to_utf8mb4(PDO $pdo, $table)
{
    $pdo->exec(sprintf(
        'ALTER TABLE %s CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        quote_ident($table)
    ));
}

function legacy_delivery_type_id($value)
{
    $value = trim((string) $value);
    $map = [
        '' => null,
        'BACK LOAD' => 3,
        'FULL LOAD' => 6,
    ];

    return array_key_exists($value, $map) ? $map[$value] : (int) $value;
}

function legacy_truck_type_id($value)
{
    $value = trim((string) $value);
    $map = [
        '' => null,
        '10W WING VAN' => 10,
    ];

    return array_key_exists($value, $map) ? $map[$value] : (int) $value;
}

function drop_foreign_key_if_exists(PDO $pdo, $table, $constraint)
{
    if (!foreign_key_exists($pdo, $constraint)) {
        return;
    }

    $pdo->exec(sprintf(
        'ALTER TABLE %s DROP FOREIGN KEY %s',
        quote_ident($table),
        quote_ident($constraint)
    ));
}

function add_foreign_key_if_clean(PDO $pdo, $table, $column, $parentTable, $parentColumn, $constraint)
{
    if (foreign_key_exists($pdo, $constraint)) {
        return 'exists';
    }

    $orphans = (int) $pdo->query(sprintf(
        'SELECT COUNT(*)
        FROM %s child
        LEFT JOIN %s parent ON child.%s = parent.%s
        WHERE child.%s IS NOT NULL
            AND parent.%s IS NULL',
        quote_ident($table),
        quote_ident($parentTable),
        quote_ident($column),
        quote_ident($parentColumn),
        quote_ident($column),
        quote_ident($parentColumn)
    ))->fetchColumn();

    if ($orphans > 0) {
        return 'orphans:' . $orphans;
    }

    $pdo->exec(sprintf(
        'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE CASCADE ON DELETE RESTRICT',
        quote_ident($table),
        quote_ident($constraint),
        quote_ident($column),
        quote_ident($parentTable),
        quote_ident($parentColumn)
    ));

    return 'applied';
}

function normalize_route_rate_columns(PDO $pdo)
{
    $pdo->exec('ALTER TABLE tblcustomerinformation MODIFY deliverytype INT(11) NOT NULL, MODIFY trucktype INT(11) NOT NULL');

    $pdo->exec('ALTER TABLE tblcustomerinformation_new_rates MODIFY deliverytype INT(11) NULL, MODIFY trucktype INT(11) NULL');
    $pdo->exec('UPDATE tblcustomerinformation_new_rates SET deliverytype = NULL WHERE deliverytype = 0');
    $pdo->exec('UPDATE tblcustomerinformation_new_rates SET trucktype = NULL WHERE trucktype = 0');
}

function prepare_new_rates_table(PDO $pdo)
{
    $pdo->exec("UPDATE tblcustomerinformation_new_rates SET deliverytype = '0' WHERE CAST(deliverytype AS CHAR) = ''");
    $pdo->exec("UPDATE tblcustomerinformation_new_rates SET deliverytype = '6' WHERE CAST(deliverytype AS CHAR) = 'FULL LOAD'");
    $pdo->exec("UPDATE tblcustomerinformation_new_rates SET trucktype = '0' WHERE CAST(trucktype AS CHAR) = ''");
    $pdo->exec("UPDATE tblcustomerinformation_new_rates SET trucktype = '10' WHERE CAST(trucktype AS CHAR) = '10W WING VAN'");
    $pdo->exec('ALTER TABLE tblcustomerinformation_new_rates MODIFY deliverytype INT(11) NULL, MODIFY trucktype INT(11) NULL');
    $pdo->exec('UPDATE tblcustomerinformation_new_rates SET deliverytype = NULL WHERE deliverytype = 0');
    $pdo->exec('UPDATE tblcustomerinformation_new_rates SET trucktype = NULL WHERE trucktype = 0');
}

$source = migration_pdo($sourceConfig);
$target = migration_pdo($targetConfig);

echo 'Importing customer, route, and rate setup tables...' . PHP_EOL;
$target->exec('SET FOREIGN_KEY_CHECKS = 0');

try {
    foreach ($tables as $table) {
        if (!table_exists($target, $table)) {
            $target->exec(recreate_statement($source, $table));
            echo '[created] ' . $table . PHP_EOL;
        }

        if ($table === 'tblcustomerinformation_new_rates') {
            prepare_new_rates_table($target);
        }

        $rows = copy_table_data($source, $target, $table);
        echo '[copied] ' . $table . ' rows=' . $rows . PHP_EOL;
    }
} finally {
    $target->exec('SET FOREIGN_KEY_CHECKS = 1');
}

drop_foreign_key_if_exists($target, 'tblmultiple_pickup', 'fk_tblmultiple_pickup_mpu_fleetid');
drop_foreign_key_if_exists($target, 'tbladditional_trips', 'fk_tbladditional_trips_add_trip_fleetid');
normalize_route_rate_columns($target);

foreach ($tables as $table) {
    convert_table_to_utf8mb4($target, $table);
    echo '[converted utf8mb4] ' . $table . PHP_EOL;
}

$foreignKeys = [
    ['tblcustomerinformation', 'deliverytype', 'tbldeliverytype', 'deliverytypeid', 'fk_tblcustomerinformation_deliverytype'],
    ['tblcustomerinformation', 'trucktype', 'tbltrucktype', 'trucktypeid', 'fk_tblcustomerinformation_trucktype'],
    ['tblcustomerinformation_new_rates', 'deliverytype', 'tbldeliverytype', 'deliverytypeid', 'fk_tblcustomerinformation_new_rates_deliverytype'],
    ['tblcustomerinformation_new_rates', 'trucktype', 'tbltrucktype', 'trucktypeid', 'fk_tblcustomerinformation_new_rates_trucktype'],
    ['tbltripdrops_perdrops', 'perdrops_locationid', 'tbllocation', 'locationid', 'fk_tbltripdrops_perdrops_perdrops_locationid'],
    ['tbltripdrops_perkilo', 'perkilo_locationid', 'tbllocation', 'locationid', 'fk_tbltripdrops_perkilo_perkilo_locationid'],
    ['tblmultiple_pickup', 'mpu_locationid', 'tbllocation', 'locationid', 'fk_tblmultiple_pickup_mpu_locationid'],
    ['tbladditional_trips', 'add_trip_customer_od', 'tblcustomerinformation', 'customerinformationid', 'fk_tbladditional_trips_add_trip_customer_od'],
];

foreach ($foreignKeys as $fk) {
    $status = add_foreign_key_if_clean($target, $fk[0], $fk[1], $fk[2], $fk[3], $fk[4]);
    echo '[fk ' . $status . '] ' . $fk[0] . '.' . $fk[1] . ' -> ' . $fk[2] . '.' . $fk[3] . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
