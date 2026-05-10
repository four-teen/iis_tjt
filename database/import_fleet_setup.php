<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
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

function fleet_pdo(array $config)
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

function fleet_quote_ident($identifier)
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function fleet_table_exists(PDO $pdo, $table)
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

function fleet_foreign_key_exists(PDO $pdo, $constraintName)
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

function fleet_add_fk(PDO $pdo, $table, $column, $parentTable, $parentColumn, $constraint, $deleteAction = 'RESTRICT')
{
    if (fleet_foreign_key_exists($pdo, $constraint)) {
        return 'exists';
    }

    $orphans = (int) $pdo->query(sprintf(
        'SELECT COUNT(*)
        FROM %s child
        LEFT JOIN %s parent ON child.%s = parent.%s
        WHERE child.%s IS NOT NULL
            AND parent.%s IS NULL',
        fleet_quote_ident($table),
        fleet_quote_ident($parentTable),
        fleet_quote_ident($column),
        fleet_quote_ident($parentColumn),
        fleet_quote_ident($column),
        fleet_quote_ident($parentColumn)
    ))->fetchColumn();

    if ($orphans > 0) {
        return 'orphans:' . $orphans;
    }

    $pdo->exec(sprintf(
        'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE CASCADE ON DELETE %s',
        fleet_quote_ident($table),
        fleet_quote_ident($constraint),
        fleet_quote_ident($column),
        fleet_quote_ident($parentTable),
        fleet_quote_ident($parentColumn),
        $deleteAction
    ));

    return 'applied';
}

function fleet_clean_text($value)
{
    return repair_legacy_text_encoding((string) $value);
}

function fleet_date_or_null($value)
{
    $value = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function fleet_int_or_null($value)
{
    $value = trim((string) $value);

    if ($value === '' || $value === '0') {
        return null;
    }

    return (int) $value;
}

function fleet_profile_score(array $row)
{
    $score = 0;
    $textColumns = ['cpc', 'cpcvalidity', 'yearmodel', 'yearacquired', 'chassisnumber', 'enginenumber'];
    $lookupColumns = ['platecolor', 'ltfrbstatus', 'trucktype', 'vantype', 'make', 'body', 'color'];

    foreach ($textColumns as $column) {
        if (trim((string) ($row[$column] ?? '')) !== '') {
            $score += 2;
        }
    }

    foreach ($lookupColumns as $column) {
        $value = (string) ($row[$column] ?? '');

        if ($value !== '' && $value !== '0') {
            $score += $value === '99' ? 1 : 3;
        }
    }

    return $score;
}

function fleet_create_tables(PDO $pdo)
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblmake (
            makeid INT(11) NOT NULL AUTO_INCREMENT,
            makename VARCHAR(25) NOT NULL,
            PRIMARY KEY (makeid),
            KEY idx_tblmake_makename (makename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblvantype (
            vantypeid INT(11) NOT NULL AUTO_INCREMENT,
            vantype VARCHAR(50) NOT NULL,
            PRIMARY KEY (vantypeid),
            KEY idx_tblvantype_vantype (vantype)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblbody (
            body_id INT(11) NOT NULL AUTO_INCREMENT,
            body_name VARCHAR(50) NOT NULL,
            PRIMARY KEY (body_id),
            KEY idx_tblbody_body_name (body_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblcolor (
            color_id INT(11) NOT NULL AUTO_INCREMENT,
            color_name VARCHAR(30) NOT NULL,
            PRIMARY KEY (color_id),
            KEY idx_tblcolor_color_name (color_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblcolor_plate (
            color_plate_id INT(11) NOT NULL AUTO_INCREMENT,
            color_plate_desc VARCHAR(70) NOT NULL,
            PRIMARY KEY (color_plate_id),
            KEY idx_tblcolor_plate_desc (color_plate_desc)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblltfrb_status (
            ltfrb_status_id INT(11) NOT NULL AUTO_INCREMENT,
            ltfrb_status VARCHAR(25) NOT NULL,
            PRIMARY KEY (ltfrb_status_id),
            KEY idx_tblltfrb_status (ltfrb_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblfleet (
            fleetid INT(11) NOT NULL AUTO_INCREMENT,
            platenumber VARCHAR(20) NOT NULL,
            casenumber VARCHAR(20) NULL,
            validity DATE NULL,
            paremarks VARCHAR(25) NULL,
            pavalidity DATE NULL,
            decisionremarks VARCHAR(30) NULL,
            decisionvalidity DATE NULL,
            PRIMARY KEY (fleetid),
            UNIQUE KEY uq_tblfleet_platenumber (platenumber),
            KEY idx_tblfleet_validity (validity),
            KEY idx_tblfleet_paremarks (paremarks)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblfleet_info_1 (
            fleet_info_1_id INT(11) NOT NULL AUTO_INCREMENT,
            fleetid INT(11) NOT NULL,
            cpc VARCHAR(30) NULL,
            cpcvalidity DATE NULL,
            platecolor INT(11) NULL,
            ltfrbstatus INT(11) NULL,
            trucktype INT(11) NULL,
            vantype INT(11) NULL,
            make INT(11) NULL,
            body INT(11) NULL,
            color INT(11) NULL,
            yearmodel VARCHAR(4) NULL,
            yearacquired VARCHAR(4) NULL,
            chassisnumber VARCHAR(20) NULL,
            enginenumber VARCHAR(20) NULL,
            PRIMARY KEY (fleet_info_1_id),
            UNIQUE KEY uq_tblfleet_info_1_fleet (fleetid),
            KEY idx_tblfleet_info_trucktype (trucktype),
            KEY idx_tblfleet_info_make (make),
            KEY idx_tblfleet_info_vantype (vantype),
            KEY idx_tblfleet_info_body (body)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tblfleet_assigned_driver_helper (
            assigned_id INT(11) NOT NULL AUTO_INCREMENT,
            assigned_fleetid INT(11) NOT NULL,
            assigned_employeeid INT(11) NOT NULL,
            PRIMARY KEY (assigned_id),
            UNIQUE KEY uq_tblfleet_assignment (assigned_fleetid, assigned_employeeid),
            KEY idx_tblfleet_assignment_employee (assigned_employeeid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
}

function fleet_copy_lookup(PDO $source, PDO $target, $table, array $columns)
{
    $insert = $target->prepare(sprintf(
        'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        fleet_quote_ident($table),
        implode(', ', array_map('fleet_quote_ident', $columns)),
        implode(', ', array_map(function ($column) {
            return ':' . $column;
        }, $columns)),
        implode(', ', array_map(function ($column) {
            return fleet_quote_ident($column) . ' = VALUES(' . fleet_quote_ident($column) . ')';
        }, array_slice($columns, 1)))
    ));

    $count = 0;

    foreach ($source->query('SELECT * FROM ' . fleet_quote_ident($table)) as $row) {
        $params = [];

        foreach ($columns as $column) {
            $params[$column] = is_string($row[$column]) ? fleet_clean_text($row[$column]) : $row[$column];
        }

        $insert->execute($params);
        $count++;
    }

    return $count;
}

function fleet_copy_fleets(PDO $source, PDO $target)
{
    $insert = $target->prepare('
        INSERT INTO tblfleet
            (fleetid, platenumber, casenumber, validity, paremarks, pavalidity, decisionremarks, decisionvalidity)
        VALUES
            (:fleetid, :platenumber, :casenumber, :validity, :paremarks, :pavalidity, :decisionremarks, :decisionvalidity)
        ON DUPLICATE KEY UPDATE
            platenumber = VALUES(platenumber),
            casenumber = VALUES(casenumber),
            validity = VALUES(validity),
            paremarks = VALUES(paremarks),
            pavalidity = VALUES(pavalidity),
            decisionremarks = VALUES(decisionremarks),
            decisionvalidity = VALUES(decisionvalidity)
    ');
    $count = 0;

    foreach ($source->query('SELECT * FROM tblfleet') as $row) {
        $insert->execute([
            'fleetid' => (int) $row['fleetid'],
            'platenumber' => strtoupper(trim(fleet_clean_text($row['platenumber']))),
            'casenumber' => trim(fleet_clean_text($row['casenumber'])) ?: null,
            'validity' => fleet_date_or_null($row['validity']),
            'paremarks' => trim(fleet_clean_text($row['paremarks'])) ?: null,
            'pavalidity' => fleet_date_or_null($row['pavalidity']),
            'decisionremarks' => trim(fleet_clean_text($row['decisionremarks'])) ?: null,
            'decisionvalidity' => fleet_date_or_null($row['decisionvalidity']),
        ]);
        $count++;
    }

    return $count;
}

function fleet_copy_profiles(PDO $source, PDO $target)
{
    $profiles = [];

    foreach ($source->query('SELECT * FROM tblfleet_info_1 ORDER BY fleetid ASC, fleet_info_1_id ASC') as $row) {
        $fleetId = (int) $row['fleetid'];

        if (!isset($profiles[$fleetId]) || fleet_profile_score($row) > fleet_profile_score($profiles[$fleetId])) {
            $profiles[$fleetId] = $row;
        }
    }

    $insert = $target->prepare('
        INSERT INTO tblfleet_info_1
            (fleet_info_1_id, fleetid, cpc, cpcvalidity, platecolor, ltfrbstatus, trucktype, vantype, make, body, color, yearmodel, yearacquired, chassisnumber, enginenumber)
        VALUES
            (:fleet_info_1_id, :fleetid, :cpc, :cpcvalidity, :platecolor, :ltfrbstatus, :trucktype, :vantype, :make, :body, :color, :yearmodel, :yearacquired, :chassisnumber, :enginenumber)
        ON DUPLICATE KEY UPDATE
            fleetid = VALUES(fleetid),
            cpc = VALUES(cpc),
            cpcvalidity = VALUES(cpcvalidity),
            platecolor = VALUES(platecolor),
            ltfrbstatus = VALUES(ltfrbstatus),
            trucktype = VALUES(trucktype),
            vantype = VALUES(vantype),
            make = VALUES(make),
            body = VALUES(body),
            color = VALUES(color),
            yearmodel = VALUES(yearmodel),
            yearacquired = VALUES(yearacquired),
            chassisnumber = VALUES(chassisnumber),
            enginenumber = VALUES(enginenumber)
    ');
    $count = 0;

    foreach ($profiles as $row) {
        $insert->execute([
            'fleet_info_1_id' => (int) $row['fleet_info_1_id'],
            'fleetid' => (int) $row['fleetid'],
            'cpc' => trim(fleet_clean_text($row['cpc'])) ?: null,
            'cpcvalidity' => fleet_date_or_null($row['cpcvalidity']),
            'platecolor' => fleet_int_or_null($row['platecolor']),
            'ltfrbstatus' => fleet_int_or_null($row['ltfrbstatus']),
            'trucktype' => fleet_int_or_null($row['trucktype']),
            'vantype' => fleet_int_or_null($row['vantype']),
            'make' => fleet_int_or_null($row['make']),
            'body' => fleet_int_or_null($row['body']),
            'color' => fleet_int_or_null($row['color']),
            'yearmodel' => trim(fleet_clean_text($row['yearmodel'])) ?: null,
            'yearacquired' => trim(fleet_clean_text($row['yearacquired'])) ?: null,
            'chassisnumber' => trim(fleet_clean_text($row['chassisnumber'])) ?: null,
            'enginenumber' => trim(fleet_clean_text($row['enginenumber'])) ?: null,
        ]);
        $count++;
    }

    return $count;
}

function fleet_copy_assignments(PDO $source, PDO $target)
{
    $insert = $target->prepare('
        INSERT INTO tblfleet_assigned_driver_helper (assigned_id, assigned_fleetid, assigned_employeeid)
        VALUES (:assigned_id, :assigned_fleetid, :assigned_employeeid)
        ON DUPLICATE KEY UPDATE
            assigned_fleetid = VALUES(assigned_fleetid),
            assigned_employeeid = VALUES(assigned_employeeid)
    ');
    $count = 0;
    $skipped = 0;

    foreach ($source->query('SELECT * FROM tblfleet_assigned_driver_helper') as $row) {
        $fleetId = (int) $row['assigned_fleetid'];
        $employeeId = (int) $row['assigned_employeeid'];

        $fleetExists = (int) $target->query('SELECT COUNT(*) FROM tblfleet WHERE fleetid = ' . $fleetId)->fetchColumn() > 0;
        $employeeExists = (int) $target->query('SELECT COUNT(*) FROM tblemployees WHERE employee_id = ' . $employeeId)->fetchColumn() > 0;

        if (!$fleetExists || !$employeeExists) {
            $skipped++;
            continue;
        }

        $insert->execute([
            'assigned_id' => (int) $row['assigned_id'],
            'assigned_fleetid' => $fleetId,
            'assigned_employeeid' => $employeeId,
        ]);
        $count++;
    }

    return [$count, $skipped];
}

$source = fleet_pdo($sourceConfig);
$target = fleet_pdo($targetConfig);

echo 'Importing fleet setup...' . PHP_EOL;
fleet_create_tables($target);

$target->beginTransaction();

try {
    $lookups = [
        'tblmake' => ['makeid', 'makename'],
        'tblvantype' => ['vantypeid', 'vantype'],
        'tblbody' => ['body_id', 'body_name'],
        'tblcolor' => ['color_id', 'color_name'],
        'tblcolor_plate' => ['color_plate_id', 'color_plate_desc'],
        'tblltfrb_status' => ['ltfrb_status_id', 'ltfrb_status'],
    ];

    foreach ($lookups as $table => $columns) {
        $count = fleet_copy_lookup($source, $target, $table, $columns);
        echo '[copied] ' . $table . ' rows=' . $count . PHP_EOL;
    }

    echo '[copied] tblfleet rows=' . fleet_copy_fleets($source, $target) . PHP_EOL;
    echo '[copied] tblfleet_info_1 rows=' . fleet_copy_profiles($source, $target) . PHP_EOL;
    [$assignments, $skippedAssignments] = fleet_copy_assignments($source, $target);
    echo '[copied] tblfleet_assigned_driver_helper rows=' . $assignments . ' skipped=' . $skippedAssignments . PHP_EOL;

    $target->commit();
} catch (Throwable $error) {
    if ($target->inTransaction()) {
        $target->rollBack();
    }

    throw $error;
}

$foreignKeys = [
    ['tblfleet_info_1', 'fleetid', 'tblfleet', 'fleetid', 'fk_tblfleet_info_1_fleet', 'CASCADE'],
    ['tblfleet_info_1', 'platecolor', 'tblcolor_plate', 'color_plate_id', 'fk_tblfleet_info_1_platecolor', 'RESTRICT'],
    ['tblfleet_info_1', 'ltfrbstatus', 'tblltfrb_status', 'ltfrb_status_id', 'fk_tblfleet_info_1_ltfrbstatus', 'RESTRICT'],
    ['tblfleet_info_1', 'trucktype', 'tbltrucktype', 'trucktypeid', 'fk_tblfleet_info_1_trucktype', 'RESTRICT'],
    ['tblfleet_info_1', 'vantype', 'tblvantype', 'vantypeid', 'fk_tblfleet_info_1_vantype', 'RESTRICT'],
    ['tblfleet_info_1', 'make', 'tblmake', 'makeid', 'fk_tblfleet_info_1_make', 'RESTRICT'],
    ['tblfleet_info_1', 'body', 'tblbody', 'body_id', 'fk_tblfleet_info_1_body', 'RESTRICT'],
    ['tblfleet_info_1', 'color', 'tblcolor', 'color_id', 'fk_tblfleet_info_1_color', 'RESTRICT'],
    ['tblfleet_assigned_driver_helper', 'assigned_fleetid', 'tblfleet', 'fleetid', 'fk_tblfleet_assignment_fleet', 'CASCADE'],
    ['tblfleet_assigned_driver_helper', 'assigned_employeeid', 'tblemployees', 'employee_id', 'fk_tblfleet_assignment_employee', 'RESTRICT'],
];

foreach ($foreignKeys as $fk) {
    $status = fleet_add_fk($target, $fk[0], $fk[1], $fk[2], $fk[3], $fk[4], $fk[5]);
    echo '[fk ' . $status . '] ' . $fk[0] . '.' . $fk[1] . ' -> ' . $fk[2] . '.' . $fk[3] . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
