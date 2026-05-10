<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/text_encoding.php';

$options = getopt('', ['dry-run', 'skip-convert']);
$dryRun = array_key_exists('dry-run', $options);
$skipConvert = array_key_exists('skip-convert', $options);

function repair_quote_ident($identifier)
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function repair_text_columns(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND CHARACTER_SET_NAME IS NOT NULL
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ");

    $columns = [];

    foreach ($stmt as $row) {
        $columns[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }

    return $columns;
}

function repair_primary_keys(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ");

    $keys = [];

    foreach ($stmt as $row) {
        $keys[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }

    return $keys;
}

function repair_tables_needing_utf8mb4(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT DISTINCT t.TABLE_NAME
        FROM information_schema.TABLES t
        LEFT JOIN information_schema.COLUMNS c
            ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
            AND c.TABLE_NAME = t.TABLE_NAME
            AND c.CHARACTER_SET_NAME IS NOT NULL
        WHERE t.TABLE_SCHEMA = DATABASE()
            AND t.TABLE_TYPE = 'BASE TABLE'
            AND (
                t.TABLE_COLLATION NOT LIKE 'utf8mb4%'
                OR c.CHARACTER_SET_NAME <> 'utf8mb4'
            )
        ORDER BY t.TABLE_NAME
    ");

    return array_column($stmt->fetchAll(), 'TABLE_NAME');
}

function repair_collect_changes(PDO $pdo, array $columnsByTable, array $primaryKeys)
{
    $changes = [];
    $skipped = [];

    foreach ($columnsByTable as $table => $columns) {
        $pkColumns = $primaryKeys[$table] ?? [];

        if (count($pkColumns) !== 1) {
            $skipped[] = $table;
            continue;
        }

        $pk = $pkColumns[0];
        $selectColumns = array_unique(array_merge([$pk], $columns));
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', array_map('repair_quote_ident', $selectColumns)),
            repair_quote_ident($table)
        );

        foreach ($pdo->query($sql) as $row) {
            $rowChanges = [];

            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || !is_string($row[$column])) {
                    continue;
                }

                $fixed = repair_legacy_text_encoding($row[$column]);

                if ($fixed !== $row[$column]) {
                    $rowChanges[$column] = [
                        'before' => $row[$column],
                        'after' => $fixed,
                    ];
                }
            }

            if ($rowChanges) {
                $changes[$table][] = [
                    'primary_key' => $pk,
                    'primary_value' => $row[$pk],
                    'columns' => $rowChanges,
                ];
            }
        }
    }

    return [$changes, $skipped];
}

function repair_apply_changes(PDO $pdo, array $changes)
{
    $rowsUpdated = 0;
    $cellsUpdated = 0;

    $pdo->beginTransaction();

    try {
        foreach ($changes as $table => $rows) {
            foreach ($rows as $row) {
                $columns = array_keys($row['columns']);
                $assignments = [];
                $params = [
                    'primary_value' => $row['primary_value'],
                ];

                foreach ($columns as $column) {
                    $placeholder = 'col_' . count($params);
                    $assignments[] = repair_quote_ident($column) . ' = :' . $placeholder;
                    $params[$placeholder] = $row['columns'][$column]['after'];
                    $cellsUpdated++;
                }

                $sql = sprintf(
                    'UPDATE %s SET %s WHERE %s = :primary_value',
                    repair_quote_ident($table),
                    implode(', ', $assignments),
                    repair_quote_ident($row['primary_key'])
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rowsUpdated++;
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return [$rowsUpdated, $cellsUpdated];
}

function repair_write_backup(array $changes)
{
    if (!$changes) {
        return null;
    }

    $directory = __DIR__ . '/../storage';

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $path = $directory . '/encoding-repair-' . date('Ymd-His') . '.json';
    file_put_contents($path, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $path;
}

function repair_convert_tables(PDO $pdo, array $tables)
{
    $converted = [];

    foreach ($tables as $table) {
        $pdo->exec(sprintf(
            'ALTER TABLE %s CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            repair_quote_ident($table)
        ));
        $converted[] = $table;
    }

    return $converted;
}

$pdo = db();
$columnsByTable = repair_text_columns($pdo);
$primaryKeys = repair_primary_keys($pdo);
[$changes, $skipped] = repair_collect_changes($pdo, $columnsByTable, $primaryKeys);
$tablesToConvert = $skipConvert ? [] : repair_tables_needing_utf8mb4($pdo);

$rowCount = array_sum(array_map('count', $changes));
$cellCount = 0;

foreach ($changes as $rows) {
    foreach ($rows as $row) {
        $cellCount += count($row['columns']);
    }
}

echo ($dryRun ? 'Dry run' : 'Applying repair') . PHP_EOL;
echo 'Rows with legacy text issues: ' . $rowCount . PHP_EOL;
echo 'Text cells to fix: ' . $cellCount . PHP_EOL;
echo 'Tables to convert: ' . count($tablesToConvert) . PHP_EOL;

if ($skipped) {
    echo 'Skipped tables without single-column primary key: ' . implode(', ', $skipped) . PHP_EOL;
}

if ($dryRun) {
    foreach ($changes as $table => $rows) {
        echo '[would fix] ' . $table . ' rows=' . count($rows) . PHP_EOL;
    }

    foreach ($tablesToConvert as $table) {
        echo '[would convert] ' . $table . PHP_EOL;
    }

    exit(0);
}

$backupPath = repair_write_backup($changes);
[$rowsUpdated, $cellsUpdated] = repair_apply_changes($pdo, $changes);
$converted = repair_convert_tables($pdo, $tablesToConvert);

if ($backupPath !== null) {
    echo 'Backup: ' . $backupPath . PHP_EOL;
}

echo 'Rows updated: ' . $rowsUpdated . PHP_EOL;
echo 'Text cells fixed: ' . $cellsUpdated . PHP_EOL;

foreach ($converted as $table) {
    echo '[converted] ' . $table . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
