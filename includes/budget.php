<?php

require_once __DIR__ . '/coordinator.php';

function ensure_budget_schema()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    ensure_coordinator_schema();

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblbudget_od (
            od_budget_id INT(11) NOT NULL AUTO_INCREMENT,
            od_customerinformationid INT(11) NOT NULL,
            od_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            od_budget_status TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (od_budget_id),
            UNIQUE KEY uq_tblbudget_od_route (od_customerinformationid),
            KEY idx_tblbudget_od_status (od_budget_status),
            CONSTRAINT fk_tblbudget_od_route
                FOREIGN KEY (od_customerinformationid) REFERENCES tblcustomerinformation (customerinformationid)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tblbudget_od_created_by
                FOREIGN KEY (created_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_tblbudget_od_updated_by
                FOREIGN KEY (updated_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tbldispatch_budget (
            dis_budget_id INT(11) NOT NULL AUTO_INCREMENT,
            dis_budget_referenceid BIGINT NOT NULL,
            dis_budget_acc_name INT(11) NOT NULL,
            dis_budget_platenum INT(11) NOT NULL,
            dis_budget_od INT(11) NOT NULL,
            dis_budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            dis_budget_dated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            remarks VARCHAR(100) NOT NULL DEFAULT 'Regular',
            created_by BIGINT UNSIGNED NULL,
            voided_at DATETIME NULL,
            voided_by BIGINT UNSIGNED NULL,
            void_reason VARCHAR(160) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (dis_budget_id),
            KEY idx_tbldispatch_budget_reference (dis_budget_referenceid),
            KEY idx_tbldispatch_budget_employee (dis_budget_acc_name),
            KEY idx_tbldispatch_budget_route (dis_budget_od),
            KEY idx_tbldispatch_budget_voided (voided_at),
            CONSTRAINT fk_tbldispatch_budget_employee
                FOREIGN KEY (dis_budget_acc_name) REFERENCES tblemployees (employee_id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tbldispatch_budget_fleet
                FOREIGN KEY (dis_budget_platenum) REFERENCES tblfleet (fleetid)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tbldispatch_budget_route
                FOREIGN KEY (dis_budget_od) REFERENCES tblcustomerinformation (customerinformationid)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tbldispatch_budget_created_by
                FOREIGN KEY (created_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_tbldispatch_budget_voided_by
                FOREIGN KEY (voided_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblowner_budget (
            owner_budget_id INT(11) NOT NULL AUTO_INCREMENT,
            owners_id INT(11) NOT NULL,
            date_released DATE NOT NULL,
            budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            deleted CHAR(1) NOT NULL DEFAULT 'N',
            created_by BIGINT UNSIGNED NULL,
            deleted_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (owner_budget_id),
            KEY idx_tblowner_budget_owner (owners_id),
            KEY idx_tblowner_budget_deleted (deleted),
            CONSTRAINT fk_tblowner_budget_owner
                FOREIGN KEY (owners_id) REFERENCES tblemployees (employee_id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tblowner_budget_created_by
                FOREIGN KEY (created_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_tblowner_budget_deleted_by
                FOREIGN KEY (deleted_by) REFERENCES accounts (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    budget_ensure_legacy_columns();

    $ensured = true;
}

function budget_identifier($identifier)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }

    return '`' . $identifier . '`';
}

function budget_column_exists($table, $column)
{
    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
    ');
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function budget_index_exists($table, $index)
{
    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
    ');
    $stmt->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function budget_ensure_column($table, $column, $definition)
{
    if (!budget_column_exists($table, $column)) {
        db()->exec('ALTER TABLE ' . budget_identifier($table) . ' ADD COLUMN ' . $definition);
    }
}

function budget_ensure_index($table, $index, $sql)
{
    if (budget_index_exists($table, $index)) {
        return;
    }

    try {
        db()->exec($sql);
    } catch (Throwable $error) {
        // Legacy imports may contain duplicate route budget rows. The module still
        // works through select-then-update saves even when a uniqueness upgrade fails.
    }
}

function budget_ensure_legacy_columns()
{
    budget_ensure_column('tblbudget_od', 'created_by', 'created_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tblbudget_od', 'updated_by', 'updated_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tblbudget_od', 'created_at', 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    budget_ensure_column('tblbudget_od', 'updated_at', 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    budget_ensure_index('tblbudget_od', 'uq_tblbudget_od_route', 'ALTER TABLE tblbudget_od ADD UNIQUE KEY uq_tblbudget_od_route (od_customerinformationid)');

    budget_ensure_column('tbldispatch_budget', 'remarks', "remarks VARCHAR(100) NOT NULL DEFAULT 'Regular'");
    budget_ensure_column('tbldispatch_budget', 'created_by', 'created_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tbldispatch_budget', 'voided_at', 'voided_at DATETIME NULL');
    budget_ensure_column('tbldispatch_budget', 'voided_by', 'voided_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tbldispatch_budget', 'void_reason', 'void_reason VARCHAR(160) NULL');
    budget_ensure_column('tbldispatch_budget', 'created_at', 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

    budget_ensure_column('tblowner_budget', 'deleted', "deleted CHAR(1) NOT NULL DEFAULT 'N'");
    budget_ensure_column('tblowner_budget', 'created_by', 'created_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tblowner_budget', 'deleted_by', 'deleted_by BIGINT UNSIGNED NULL');
    budget_ensure_column('tblowner_budget', 'deleted_at', 'deleted_at DATETIME NULL');
    budget_ensure_column('tblowner_budget', 'created_at', 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
}

function budget_money($value)
{
    return number_format((float) $value, 2);
}

function budget_number_value($value)
{
    return number_format((float) $value, 2, '.', '');
}

function budget_parse_amount($value)
{
    $value = str_replace(',', '', trim((string) $value));

    if ($value === '' || !is_numeric($value)) {
        throw new InvalidArgumentException('Amount must be numeric.');
    }

    $amount = round((float) $value, 2);

    if ($amount < 0) {
        throw new InvalidArgumentException('Amount cannot be negative.');
    }

    return $amount;
}

function budget_employee_name(array $employee)
{
    $name = trim(($employee['lastname'] ?? '') . ', ' . ($employee['firstname'] ?? ''));

    return $name !== ',' ? clean_text($name) : 'Unnamed employee';
}

function budget_customer_label(array $row)
{
    $soa = clean_text($row['soa'] ?? '');
    $name = clean_text($row['customername'] ?? ($row['customer_label'] ?? ''));

    return $soa !== '' ? '[' . $soa . '] ' . $name : ($name ?: 'Unknown customer');
}

function budget_route_label(array $row)
{
    return clean_text($row['origin'] ?? 'Unknown origin') . ' to ' . clean_text($row['destination'] ?? 'Unknown destination');
}

function budget_counts()
{
    ensure_budget_schema();

    $routeRow = db()->query('
        SELECT
            COUNT(ci.customerinformationid) AS total_routes,
            SUM(CASE WHEN b.od_budget_id IS NOT NULL AND b.od_budget > 0 THEN 1 ELSE 0 END) AS budgeted_routes,
            SUM(CASE WHEN b.od_budget_id IS NULL OR b.od_budget <= 0 THEN 1 ELSE 0 END) AS missing_routes,
            COALESCE(SUM(CASE WHEN b.od_budget_id IS NOT NULL THEN b.od_budget ELSE 0 END), 0) AS total_route_budget
        FROM tblcustomerinformation ci
        LEFT JOIN tblbudget_od b ON b.od_customerinformationid = ci.customerinformationid
    ')->fetch();

    $dispatchRow = db()->query('
        SELECT
            COUNT(d.dispatched_id) AS active_dispatches,
            SUM(CASE WHEN COALESCE(r.release_count, 0) > 0 THEN 1 ELSE 0 END) AS dispatches_with_budget,
            SUM(CASE WHEN COALESCE(r.release_count, 0) = 0 THEN 1 ELSE 0 END) AS dispatches_without_budget,
            COALESCE(SUM(COALESCE(r.total_released, 0)), 0) AS total_released
        FROM tbldispatched d
        LEFT JOIN (
            SELECT
                dis_budget_referenceid,
                COUNT(*) AS release_count,
                SUM(dis_budget_amount) AS total_released
            FROM tbldispatch_budget
            WHERE voided_at IS NULL
            GROUP BY dis_budget_referenceid
        ) r ON r.dis_budget_referenceid = d.dis_referenceid
    ')->fetch();

    $ownerRow = db()->query("
        SELECT
            COUNT(*) AS owner_release_count,
            COALESCE(SUM(CASE WHEN deleted = 'N' THEN budget_amount ELSE 0 END), 0) AS owner_total
        FROM tblowner_budget
    ")->fetch();

    return [
        'total_routes' => (int) ($routeRow['total_routes'] ?? 0),
        'budgeted_routes' => (int) ($routeRow['budgeted_routes'] ?? 0),
        'missing_routes' => (int) ($routeRow['missing_routes'] ?? 0),
        'total_route_budget' => (float) ($routeRow['total_route_budget'] ?? 0),
        'active_dispatches' => (int) ($dispatchRow['active_dispatches'] ?? 0),
        'dispatches_with_budget' => (int) ($dispatchRow['dispatches_with_budget'] ?? 0),
        'dispatches_without_budget' => (int) ($dispatchRow['dispatches_without_budget'] ?? 0),
        'total_released' => (float) ($dispatchRow['total_released'] ?? 0),
        'owner_release_count' => (int) ($ownerRow['owner_release_count'] ?? 0),
        'owner_total' => (float) ($ownerRow['owner_total'] ?? 0),
    ];
}

function list_budget_routes($search = '', $customerId = '', $budgetFilter = '')
{
    ensure_budget_schema();

    $sql = '
        SELECT
            ci.*,
            c.soa,
            c.customername,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            b.od_budget_id,
            b.od_budget,
            b.updated_at AS budget_updated_at
        FROM tblcustomerinformation ci
        LEFT JOIN tblcustomer c ON c.customerid = ci.customerid
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblbudget_od b ON b.od_customerinformationid = ci.customerinformationid
    ';
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(c.soa LIKE :search_soa OR c.customername LIKE :search_customer OR ci.origin LIKE :search_origin OR ci.destination LIKE :search_destination)';
        $term = '%' . $search . '%';
        $params['search_soa'] = $term;
        $params['search_customer'] = $term;
        $params['search_origin'] = $term;
        $params['search_destination'] = $term;
    }

    if ($customerId !== '' && ctype_digit((string) $customerId)) {
        $where[] = 'ci.customerid = :customer_id';
        $params['customer_id'] = (int) $customerId;
    }

    if ($budgetFilter === 'missing') {
        $where[] = '(b.od_budget_id IS NULL OR b.od_budget <= 0)';
    } elseif ($budgetFilter === 'set') {
        $where[] = 'b.od_budget > 0';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY c.customername ASC, ci.origin ASC, ci.destination ASC LIMIT 700';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function save_route_budget($routeId, $amount, $accountId = null)
{
    ensure_budget_schema();

    $route = find_customer_route_by_id((int) $routeId);

    if (!$route) {
        throw new InvalidArgumentException('Route record was not found.');
    }

    $amount = budget_parse_amount($amount);

    $stmt = db()->prepare('SELECT od_budget_id FROM tblbudget_od WHERE od_customerinformationid = :route_id ORDER BY od_budget_id ASC LIMIT 1');
    $stmt->execute(['route_id' => (int) $routeId]);
    $existingId = (int) $stmt->fetchColumn();

    if ($existingId > 0) {
        $update = db()->prepare('
            UPDATE tblbudget_od
            SET od_budget = :amount,
                od_budget_status = 1,
                updated_by = :account_id
            WHERE od_budget_id = :id
        ');
        $update->execute([
            'amount' => $amount,
            'account_id' => $accountId ? (int) $accountId : null,
            'id' => $existingId,
        ]);

        return $existingId;
    }

    $insert = db()->prepare('
        INSERT INTO tblbudget_od
            (od_customerinformationid, od_budget, od_budget_status, created_by, updated_by)
        VALUES
            (:route_id, :amount, 1, :created_by, :updated_by)
    ');
    $insert->execute([
        'route_id' => (int) $routeId,
        'amount' => $amount,
        'created_by' => $accountId ? (int) $accountId : null,
        'updated_by' => $accountId ? (int) $accountId : null,
    ]);

    return (int) db()->lastInsertId();
}

function budget_dispatch_base_query()
{
    $driverSelect = coordinator_crew_count_select('tbldispatch_driver', 'dispatch_reference_id', 'dispatch_driver', 'driver');
    $helperSelect = coordinator_crew_count_select('tbldispatch_helper', 'dispatch_reference_id', 'dispatch_helper', 'helper');

    return "
        SELECT
            d.*,
            c.soa,
            c.customername,
            ci.origin,
            ci.destination,
            ci.deliveryrate,
            ci.driversrate,
            ci.helpersrate,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            f.platenumber,
            f.paremarks,
            b.od_budget_id,
            b.od_budget,
            COALESCE(releases.release_count, 0) AS release_count,
            COALESCE(releases.total_released, 0) AS total_released,
            releases.last_released_at,
            COALESCE(drivers.driver_count, 0) AS driver_count,
            COALESCE(drivers.driver_names, '') AS driver_names,
            COALESCE(helpers.helper_count, 0) AS helper_count,
            COALESCE(helpers.helper_names, '') AS helper_names
        FROM tbldispatched d
        LEFT JOIN tblcustomer c ON c.customerid = d.dis_customer_name_id
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = d.dis_ods
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = d.dis_plaka
        LEFT JOIN tblbudget_od b ON b.od_customerinformationid = d.dis_ods
        LEFT JOIN (
            SELECT
                dis_budget_referenceid,
                COUNT(*) AS release_count,
                SUM(dis_budget_amount) AS total_released,
                MAX(dis_budget_dated) AS last_released_at
            FROM tbldispatch_budget
            WHERE voided_at IS NULL
            GROUP BY dis_budget_referenceid
        ) releases ON releases.dis_budget_referenceid = d.dis_referenceid
        LEFT JOIN ({$driverSelect}) drivers ON drivers.reference_id = d.dis_referenceid
        LEFT JOIN ({$helperSelect}) helpers ON helpers.reference_id = d.dis_referenceid
    ";
}

function list_budget_dispatches($search = '', $status = 'all')
{
    ensure_budget_schema();

    $sql = budget_dispatch_base_query();
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(d.dis_referenceid LIKE :search_reference OR c.soa LIKE :search_soa OR c.customername LIKE :search_customer OR ci.origin LIKE :search_origin OR ci.destination LIKE :search_destination OR f.platenumber LIKE :search_plate)';
        $term = '%' . $search . '%';
        $params['search_reference'] = $term;
        $params['search_soa'] = $term;
        $params['search_customer'] = $term;
        $params['search_origin'] = $term;
        $params['search_destination'] = $term;
        $params['search_plate'] = $term;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY d.dis_dispatched_date DESC, d.dispatched_id DESC LIMIT 600';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if ($status === 'all') {
        return $rows;
    }

    return array_values(array_filter($rows, function ($row) use ($status) {
        $routeBudget = (float) ($row['od_budget'] ?? 0);
        $releaseCount = (int) ($row['release_count'] ?? 0);

        if ($status === 'needs_route_budget') {
            return $routeBudget <= 0;
        }

        if ($status === 'needs_release') {
            return $routeBudget > 0 && $releaseCount === 0;
        }

        if ($status === 'released') {
            return $releaseCount > 0;
        }

        return true;
    }));
}

function find_budget_dispatch($reference)
{
    ensure_budget_schema();

    $sql = budget_dispatch_base_query() . ' WHERE d.dis_referenceid = :reference LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute(['reference' => (int) $reference]);

    return $stmt->fetch() ?: null;
}

function list_dispatch_budget_releases($reference)
{
    ensure_budget_schema();

    $stmt = db()->prepare('
        SELECT
            b.*,
            e.firstname,
            e.middlename,
            e.lastname,
            e.who_is,
            creator.full_name AS created_by_name,
            voider.full_name AS voided_by_name
        FROM tbldispatch_budget b
        LEFT JOIN tblemployees e ON e.employee_id = b.dis_budget_acc_name
        LEFT JOIN accounts creator ON creator.id = b.created_by
        LEFT JOIN accounts voider ON voider.id = b.voided_by
        WHERE b.dis_budget_referenceid = :reference
        ORDER BY b.dis_budget_id DESC
    ');
    $stmt->execute(['reference' => (int) $reference]);

    return $stmt->fetchAll();
}

function dispatch_budget_recipients($reference)
{
    ensure_budget_schema();

    $stmt = db()->prepare("
        SELECT e.employee_id, e.firstname, e.middlename, e.lastname, e.who_is, 'Driver' AS crew_role
        FROM tbldispatch_driver d
        INNER JOIN tblemployees e ON e.employee_id = d.dispatch_driver
        WHERE d.dispatch_reference_id = :driver_reference
        UNION ALL
        SELECT e.employee_id, e.firstname, e.middlename, e.lastname, e.who_is, 'Helper' AS crew_role
        FROM tbldispatch_helper h
        INNER JOIN tblemployees e ON e.employee_id = h.dispatch_helper
        WHERE h.dispatch_reference_id = :helper_reference
        ORDER BY crew_role ASC, lastname ASC, firstname ASC
    ");
    $stmt->execute([
        'driver_reference' => (int) $reference,
        'helper_reference' => (int) $reference,
    ]);

    return $stmt->fetchAll();
}

function budget_is_dispatch_recipient($reference, $employeeId)
{
    foreach (dispatch_budget_recipients($reference) as $recipient) {
        if ((int) $recipient['employee_id'] === (int) $employeeId) {
            return true;
        }
    }

    return false;
}

function release_dispatch_budget($reference, $employeeId, $amount, $remarks, $accountId = null)
{
    ensure_budget_schema();

    $dispatch = find_budget_dispatch($reference);

    if (!$dispatch) {
        throw new InvalidArgumentException('Dispatched trip was not found.');
    }

    if ((float) ($dispatch['od_budget'] ?? 0) <= 0) {
        throw new InvalidArgumentException('Set the route budget before releasing trip budget.');
    }

    if (!budget_is_dispatch_recipient($reference, $employeeId)) {
        throw new InvalidArgumentException('Budget recipient must be an assigned driver or helper on this dispatch.');
    }

    $amount = budget_parse_amount($amount);

    if ($amount <= 0) {
        throw new InvalidArgumentException('Released amount must be greater than zero.');
    }

    $remarks = clean_text($remarks);

    if ($remarks === '') {
        $remarks = ((int) ($dispatch['release_count'] ?? 0) === 0) ? 'Initial route budget' : 'Additional budget';
    }

    $remarks = substr($remarks, 0, 100);

    $stmt = db()->prepare('
        INSERT INTO tbldispatch_budget
            (dis_budget_referenceid, dis_budget_acc_name, dis_budget_platenum, dis_budget_od, dis_budget_amount, dis_budget_dated, remarks, created_by)
        VALUES
            (:reference, :employee, :plate, :route, :amount, CURRENT_TIMESTAMP, :remarks, :created_by)
    ');
    $stmt->execute([
        'reference' => (int) $reference,
        'employee' => (int) $employeeId,
        'plate' => (int) $dispatch['dis_plaka'],
        'route' => (int) $dispatch['dis_ods'],
        'amount' => $amount,
        'remarks' => $remarks,
        'created_by' => $accountId ? (int) $accountId : null,
    ]);

    return (int) db()->lastInsertId();
}

function void_dispatch_budget_release($releaseId, $reference, $reason, $accountId = null)
{
    ensure_budget_schema();

    $reason = clean_text($reason);

    if ($reason === '') {
        $reason = 'Voided by budget account';
    }

    $stmt = db()->prepare('
        UPDATE tbldispatch_budget
        SET voided_at = CURRENT_TIMESTAMP,
            voided_by = :voided_by,
            void_reason = :void_reason
        WHERE dis_budget_id = :release_id
            AND dis_budget_referenceid = :reference
            AND voided_at IS NULL
    ');
    $stmt->execute([
        'voided_by' => $accountId ? (int) $accountId : null,
        'void_reason' => substr($reason, 0, 160),
        'release_id' => (int) $releaseId,
        'reference' => (int) $reference,
    ]);

    return $stmt->rowCount() > 0;
}

function list_owner_budget_summaries($search = '')
{
    ensure_budget_schema();

    $sql = "
        SELECT
            e.*,
            COALESCE(SUM(CASE WHEN b.deleted = 'N' THEN b.budget_amount ELSE 0 END), 0) AS total_budget,
            COUNT(CASE WHEN b.deleted = 'N' THEN 1 END) AS release_count,
            MAX(CASE WHEN b.deleted = 'N' THEN b.date_released ELSE NULL END) AS last_release_date
        FROM tblemployees e
        LEFT JOIN tblowner_budget b ON b.owners_id = e.employee_id
        WHERE e.who_is = '3'
    ";
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (e.firstname LIKE :search_firstname OR e.lastname LIKE :search_lastname)';
        $params['search_firstname'] = '%' . $search . '%';
        $params['search_lastname'] = '%' . $search . '%';
    }

    $sql .= ' GROUP BY e.employee_id ORDER BY e.lastname ASC, e.firstname ASC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function list_owner_budget_rows($ownerId)
{
    ensure_budget_schema();

    $stmt = db()->prepare('
        SELECT
            b.*,
            creator.full_name AS created_by_name,
            deleter.full_name AS deleted_by_name
        FROM tblowner_budget b
        LEFT JOIN accounts creator ON creator.id = b.created_by
        LEFT JOIN accounts deleter ON deleter.id = b.deleted_by
        WHERE b.owners_id = :owner_id
        ORDER BY b.date_released DESC, b.owner_budget_id DESC
    ');
    $stmt->execute(['owner_id' => (int) $ownerId]);

    return $stmt->fetchAll();
}

function save_owner_budget($ownerId, $dateReleased, $amount, $accountId = null)
{
    ensure_budget_schema();

    $owner = find_employee_by_id((int) $ownerId);

    if (!$owner || (string) ($owner['who_is'] ?? '') !== '3') {
        throw new InvalidArgumentException('Select a valid fleet owner.');
    }

    $dateReleased = trim((string) $dateReleased);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReleased)) {
        throw new InvalidArgumentException('Release date is invalid.');
    }

    $amount = budget_parse_amount($amount);

    if ($amount <= 0) {
        throw new InvalidArgumentException('Owner budget amount must be greater than zero.');
    }

    $stmt = db()->prepare('
        INSERT INTO tblowner_budget
            (owners_id, date_released, budget_amount, deleted, created_by)
        VALUES
            (:owner_id, :date_released, :amount, "N", :created_by)
    ');
    $stmt->execute([
        'owner_id' => (int) $ownerId,
        'date_released' => $dateReleased,
        'amount' => $amount,
        'created_by' => $accountId ? (int) $accountId : null,
    ]);

    return (int) db()->lastInsertId();
}

function void_owner_budget($ownerBudgetId, $ownerId, $accountId = null)
{
    ensure_budget_schema();

    $stmt = db()->prepare("
        UPDATE tblowner_budget
        SET deleted = 'Y',
            deleted_by = :deleted_by,
            deleted_at = CURRENT_TIMESTAMP
        WHERE owner_budget_id = :owner_budget_id
            AND owners_id = :owner_id
            AND deleted = 'N'
    ");
    $stmt->execute([
        'deleted_by' => $accountId ? (int) $accountId : null,
        'owner_budget_id' => (int) $ownerBudgetId,
        'owner_id' => (int) $ownerId,
    ]);

    return $stmt->rowCount() > 0;
}
