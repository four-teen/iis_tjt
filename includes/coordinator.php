<?php

require_once __DIR__ . '/customer_service.php';

function ensure_coordinator_schema()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    ensure_customer_service_schema();

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblcoord_dispatch_preparations (
            prep_id INT(11) NOT NULL AUTO_INCREMENT,
            prep_referenceid BIGINT NOT NULL,
            prep_coordinator BIGINT UNSIGNED NULL,
            prep_customer_name_id INT(11) NOT NULL,
            prep_ods INT(11) NOT NULL,
            prep_plaka INT(11) NOT NULL,
            prep_dispatched_date DATETIME NOT NULL,
            prep_status VARCHAR(20) NOT NULL DEFAULT 'prepared',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (prep_id),
            UNIQUE KEY uq_tblcoord_dispatch_preparations_reference (prep_referenceid),
            KEY idx_tblcoord_dispatch_preparations_status (prep_status),
            KEY idx_tblcoord_dispatch_preparations_plate (prep_plaka),
            KEY idx_tblcoord_dispatch_preparations_dispatch_date (prep_dispatched_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tbldispatched (
            dispatched_id INT(11) NOT NULL AUTO_INCREMENT,
            dis_referenceid BIGINT NOT NULL,
            dis_coordinator BIGINT UNSIGNED NULL,
            dis_customer_name_id INT(11) NOT NULL,
            dis_ods INT(11) NOT NULL,
            dis_plaka INT(11) NOT NULL,
            dis_dispatched_date DATETIME NOT NULL,
            dis_approved TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (dispatched_id),
            UNIQUE KEY uq_tbldispatched_reference (dis_referenceid),
            KEY idx_tbldispatched_plate (dis_plaka),
            KEY idx_tbldispatched_dispatch_date (dis_dispatched_date),
            KEY idx_tbldispatched_customer (dis_customer_name_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tbldispatch_driver (
            dispatch_driver_auto_id INT(11) NOT NULL AUTO_INCREMENT,
            dispatch_reference_id BIGINT NOT NULL,
            dispatch_driver INT(11) NOT NULL,
            dispatch_date DATETIME NULL,
            dispatch_customer INT(11) NOT NULL,
            coordinator BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (dispatch_driver_auto_id),
            UNIQUE KEY uq_tbldispatch_driver_reference_driver (dispatch_reference_id, dispatch_driver),
            KEY idx_tbldispatch_driver_driver (dispatch_driver),
            KEY idx_tbldispatch_driver_reference (dispatch_reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tbldispatch_helper (
            dispatch_helper_auto_id INT(11) NOT NULL AUTO_INCREMENT,
            dispatch_reference_id BIGINT NOT NULL,
            dispatch_helper INT(11) NOT NULL,
            dispatch_date DATETIME NULL,
            dispatch_customer INT(11) NOT NULL,
            coordinator BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (dispatch_helper_auto_id),
            UNIQUE KEY uq_tbldispatch_helper_reference_helper (dispatch_reference_id, dispatch_helper),
            KEY idx_tbldispatch_helper_helper (dispatch_helper),
            KEY idx_tbldispatch_helper_reference (dispatch_reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ensured = true;
}

function coordinator_employee_name(array $employee)
{
    $parts = array_filter([
        clean_text($employee['lastname'] ?? ''),
        clean_text($employee['firstname'] ?? ''),
    ]);

    if (!$parts) {
        return 'Unnamed employee';
    }

    return implode(', ', $parts);
}

function coordinator_money_text($value)
{
    return number_format((float) $value, 2);
}

function coordinator_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function coordinator_checked($value, array $current)
{
    return in_array((int) $value, array_map('intval', $current), true) ? ' checked' : '';
}

function coordinator_csv_ids($value)
{
    if (trim((string) $value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
}

function coordinator_customer_text(array $row)
{
    $soa = clean_text($row['soa'] ?? '');
    $customer = clean_text($row['customer_label'] ?? '');

    if ($soa !== '') {
        return '[' . $soa . '] ' . $customer;
    }

    return $customer ?: 'Unknown customer';
}

function coordinator_route_text($origin, $destination)
{
    $origin = clean_text($origin ?: 'Unknown origin');
    $destination = clean_text($destination ?: 'Unknown destination');

    return $origin . ' to ' . $destination;
}

function coordinator_booking_route_text(array $booking)
{
    $origin = ($booking['prep_origin'] ?? '') ?: ($booking['origin'] ?? '');
    $destination = ($booking['prep_destination'] ?? '') ?: ($booking['destination'] ?? '');

    return coordinator_route_text($origin, $destination);
}

function coordinator_booking_plate_text(array $booking)
{
    return clean_text(($booking['prep_platenumber'] ?? '') ?: ($booking['platenumber'] ?? '') ?: 'No plate selected');
}

function coordinator_route_detail_text(array $booking)
{
    $truckType = ($booking['prep_trucktype_name'] ?? '') ?: ($booking['trucktype_name'] ?? '') ?: 'Truck type pending';
    $deliveryType = ($booking['prep_deliverytype_name'] ?? '') ?: ($booking['deliverytype_name'] ?? '') ?: 'Delivery type pending';
    $rate = array_key_exists('prep_deliveryrate', $booking) && $booking['prep_deliveryrate'] !== null
        ? $booking['prep_deliveryrate']
        : ($booking['deliveryrate'] ?? 0);

    return clean_text($truckType) . ' / ' . clean_text($deliveryType) . ' / DD: ' . coordinator_money_text($rate);
}

function coordinator_readiness(array $booking)
{
    $prepared = !empty($booking['prep_id']);
    $hasCrew = (int) ($booking['driver_count'] ?? 0) > 0 && (int) ($booking['helper_count'] ?? 0) > 0;

    if ($prepared && $hasCrew) {
        return ['class' => 'badge-success', 'label' => 'Ready to dispatch', 'tone' => 'ready'];
    }

    if ($prepared) {
        return ['class' => 'badge-warning', 'label' => 'Needs crew', 'tone' => 'prepared'];
    }

    return ['class' => 'badge-info', 'label' => 'Needs confirmation', 'tone' => 'pending'];
}

function coordinator_route_option_label(array $route)
{
    return '[' . clean_text($route['origin'] ?? '') . '] to [' . clean_text($route['destination'] ?? '') . '] / '
        . clean_text($route['trucktype_name'] ?? 'Truck type pending') . ' / '
        . clean_text($route['deliverytype_name'] ?? 'Delivery type pending') . ' / DD: '
        . coordinator_money_text($route['deliveryrate'] ?? 0);
}

function coordinator_person_option_label(array $person)
{
    $name = coordinator_employee_name($person);
    $active = (int) ($person['active_dispatch_count'] ?? 0);

    if ($active <= 0) {
        return $name . ' - Available';
    }

    return $name . ' - Already assigned on ' . $active . ' dispatch record' . ($active === 1 ? '' : 's');
}

function coordinator_plate_option_label(array $fleet)
{
    $activeBookings = (int) ($fleet['active_booking_count'] ?? 0);
    $activeDispatches = (int) ($fleet['dispatch_count'] ?? 0);
    $remarks = clean_text($fleet['paremarks'] ?? '') ?: 'No plate remarks';

    if ($activeDispatches > 0) {
        $status = 'Active dispatched';
    } elseif ($activeBookings > 0) {
        $status = 'Scheduled - reuse allowed (' . $activeBookings . ')';
    } else {
        $status = 'Available';
    }

    return clean_text($fleet['platenumber'] ?? 'No plate') . ' / ' . $remarks . ' / ' . $status;
}

function coordinator_counts()
{
    ensure_coordinator_schema();

    $pending = db()->query("
        SELECT COUNT(*)
        FROM tblbooking b
        LEFT JOIN tblcoord_dispatch_preparations p
            ON p.prep_referenceid = b.bookingid
            AND p.prep_status = 'prepared'
        LEFT JOIN tbldispatched d ON d.dis_referenceid = b.bookingid
        WHERE d.dispatched_id IS NULL
            AND p.prep_id IS NULL
    ")->fetchColumn();

    $prepared = db()->query("
        SELECT COUNT(*)
        FROM tblcoord_dispatch_preparations p
        INNER JOIN tblbooking b ON b.bookingid = p.prep_referenceid
        LEFT JOIN tbldispatched d ON d.dis_referenceid = p.prep_referenceid
        WHERE p.prep_status = 'prepared'
            AND d.dispatched_id IS NULL
    ")->fetchColumn();

    $dispatched = db()->query('SELECT COUNT(*) FROM tbldispatched')->fetchColumn();

    return [
        'pending' => (int) $pending,
        'prepared' => (int) $prepared,
        'dispatched' => (int) $dispatched,
    ];
}

function coordinator_crew_count_select($table, $referenceColumn, $employeeColumn, $alias)
{
    return "
        SELECT
            {$referenceColumn} AS reference_id,
            MIN({$employeeColumn}) AS {$alias}_primary_id,
            COUNT(*) AS {$alias}_count,
            GROUP_CONCAT({$employeeColumn} ORDER BY {$employeeColumn} ASC SEPARATOR ',') AS {$alias}_ids,
            GROUP_CONCAT(CONCAT(e.lastname, ', ', e.firstname) ORDER BY e.lastname ASC, e.firstname ASC SEPARATOR '; ') AS {$alias}_names
        FROM {$table} crew
        LEFT JOIN tblemployees e ON e.employee_id = crew.{$employeeColumn}
        GROUP BY {$referenceColumn}
    ";
}

function list_coordinator_bookings()
{
    ensure_coordinator_schema();

    $driverSelect = coordinator_crew_count_select('tbldispatch_driver', 'dispatch_reference_id', 'dispatch_driver', 'driver');
    $helperSelect = coordinator_crew_count_select('tbldispatch_helper', 'dispatch_reference_id', 'dispatch_helper', 'helper');

    return db()->query("
        SELECT
            b.*,
            c.soa,
            c.customername AS customer_label,
            ci.origin,
            ci.destination,
            ci.deliveryrate,
            ci.driversrate,
            ci.helpersrate,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            f.platenumber,
            f.paremarks,
            sn.shipmentnumber,
            p.prep_id,
            p.prep_referenceid,
            p.prep_coordinator,
            p.prep_ods,
            p.prep_plaka,
            p.prep_dispatched_date,
            p.prep_status,
            pci.origin AS prep_origin,
            pci.destination AS prep_destination,
            pci.deliveryrate AS prep_deliveryrate,
            pci.driversrate AS prep_driversrate,
            pci.helpersrate AS prep_helpersrate,
            pdt.deliverytype AS prep_deliverytype_name,
            ptt.trucktype AS prep_trucktype_name,
            pf.platenumber AS prep_platenumber,
            pf.paremarks AS prep_paremarks,
            COALESCE(drivers.driver_primary_id, 0) AS driver_primary_id,
            COALESCE(drivers.driver_count, 0) AS driver_count,
            COALESCE(drivers.driver_ids, '') AS driver_ids,
            COALESCE(drivers.driver_names, '') AS driver_names,
            COALESCE(helpers.helper_primary_id, 0) AS helper_primary_id,
            COALESCE(helpers.helper_count, 0) AS helper_count,
            COALESCE(helpers.helper_ids, '') AS helper_ids,
            COALESCE(helpers.helper_names, '') AS helper_names
        FROM tblbooking b
        LEFT JOIN tbldispatched d ON d.dis_referenceid = b.bookingid
        LEFT JOIN tblcoord_dispatch_preparations p
            ON p.prep_referenceid = b.bookingid
            AND p.prep_status = 'prepared'
        LEFT JOIN tblcustomer c ON c.customerid = CAST(b.customername AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(b.origindestination AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(b.reservedplate AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = b.bookingid
        LEFT JOIN tblcustomerinformation pci ON pci.customerinformationid = CAST(p.prep_ods AS UNSIGNED)
        LEFT JOIN tbldeliverytype pdt ON pdt.deliverytypeid = pci.deliverytype
        LEFT JOIN tbltrucktype ptt ON ptt.trucktypeid = pci.trucktype
        LEFT JOIN tblfleet pf ON pf.fleetid = CAST(p.prep_plaka AS UNSIGNED)
        LEFT JOIN ({$driverSelect}) drivers ON drivers.reference_id = b.bookingid
        LEFT JOIN ({$helperSelect}) helpers ON helpers.reference_id = b.bookingid
        WHERE d.dispatched_id IS NULL
        ORDER BY b.pickupdate ASC, b.bookingidauto DESC
        LIMIT 500
    ")->fetchAll();
}

function find_coordinator_booking_detail($reference)
{
    ensure_coordinator_schema();

    $driverSelect = coordinator_crew_count_select('tbldispatch_driver', 'dispatch_reference_id', 'dispatch_driver', 'driver');
    $helperSelect = coordinator_crew_count_select('tbldispatch_helper', 'dispatch_reference_id', 'dispatch_helper', 'helper');

    $stmt = db()->prepare("
        SELECT
            b.*,
            c.soa,
            c.customername AS customer_label,
            ci.origin,
            ci.destination,
            ci.deliveryrate,
            ci.driversrate,
            ci.helpersrate,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            f.platenumber,
            f.paremarks,
            sn.shipmentnumber,
            p.prep_id,
            p.prep_referenceid,
            p.prep_coordinator,
            p.prep_ods,
            p.prep_plaka,
            p.prep_dispatched_date,
            p.prep_status,
            pci.origin AS prep_origin,
            pci.destination AS prep_destination,
            pci.deliveryrate AS prep_deliveryrate,
            pci.driversrate AS prep_driversrate,
            pci.helpersrate AS prep_helpersrate,
            pdt.deliverytype AS prep_deliverytype_name,
            ptt.trucktype AS prep_trucktype_name,
            pf.platenumber AS prep_platenumber,
            pf.paremarks AS prep_paremarks,
            COALESCE(drivers.driver_primary_id, 0) AS driver_primary_id,
            COALESCE(drivers.driver_count, 0) AS driver_count,
            COALESCE(drivers.driver_ids, '') AS driver_ids,
            COALESCE(drivers.driver_names, '') AS driver_names,
            COALESCE(helpers.helper_primary_id, 0) AS helper_primary_id,
            COALESCE(helpers.helper_count, 0) AS helper_count,
            COALESCE(helpers.helper_ids, '') AS helper_ids,
            COALESCE(helpers.helper_names, '') AS helper_names
        FROM tblbooking b
        LEFT JOIN tbldispatched d ON d.dis_referenceid = b.bookingid
        LEFT JOIN tblcoord_dispatch_preparations p
            ON p.prep_referenceid = b.bookingid
            AND p.prep_status = 'prepared'
        LEFT JOIN tblcustomer c ON c.customerid = CAST(b.customername AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(b.origindestination AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(b.reservedplate AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = b.bookingid
        LEFT JOIN tblcustomerinformation pci ON pci.customerinformationid = CAST(p.prep_ods AS UNSIGNED)
        LEFT JOIN tbldeliverytype pdt ON pdt.deliverytypeid = pci.deliverytype
        LEFT JOIN tbltrucktype ptt ON ptt.trucktypeid = pci.trucktype
        LEFT JOIN tblfleet pf ON pf.fleetid = CAST(p.prep_plaka AS UNSIGNED)
        LEFT JOIN ({$driverSelect}) drivers ON drivers.reference_id = b.bookingid
        LEFT JOIN ({$helperSelect}) helpers ON helpers.reference_id = b.bookingid
        WHERE b.bookingid = :reference
            AND d.dispatched_id IS NULL
        LIMIT 1
    ");
    $stmt->execute(['reference' => (int) $reference]);

    return $stmt->fetch() ?: null;
}

function list_coordinator_dispatches($limit = 120)
{
    ensure_coordinator_schema();

    $limit = max(1, min(500, (int) $limit));
    $driverSelect = coordinator_crew_count_select('tbldispatch_driver', 'dispatch_reference_id', 'dispatch_driver', 'driver');
    $helperSelect = coordinator_crew_count_select('tbldispatch_helper', 'dispatch_reference_id', 'dispatch_helper', 'helper');

    return db()->query("
        SELECT
            d.*,
            b.bookingdate,
            b.pickupdate,
            b.deliverydate,
            b.deliverytype,
            b.companyrepresentative,
            c.soa,
            c.customername AS customer_label,
            ci.origin,
            ci.destination,
            ci.deliveryrate,
            ci.driversrate,
            ci.helpersrate,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            f.platenumber,
            f.paremarks,
            sn.shipmentnumber,
            COALESCE(drivers.driver_primary_id, 0) AS driver_primary_id,
            COALESCE(drivers.driver_count, 0) AS driver_count,
            COALESCE(drivers.driver_ids, '') AS driver_ids,
            COALESCE(drivers.driver_names, '') AS driver_names,
            COALESCE(helpers.helper_primary_id, 0) AS helper_primary_id,
            COALESCE(helpers.helper_count, 0) AS helper_count,
            COALESCE(helpers.helper_ids, '') AS helper_ids,
            COALESCE(helpers.helper_names, '') AS helper_names
        FROM tbldispatched d
        LEFT JOIN tblbooking b ON b.bookingid = d.dis_referenceid
        LEFT JOIN tblcustomer c ON c.customerid = CAST(d.dis_customer_name_id AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(d.dis_ods AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(d.dis_plaka AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = d.dis_referenceid
        LEFT JOIN ({$driverSelect}) drivers ON drivers.reference_id = d.dis_referenceid
        LEFT JOIN ({$helperSelect}) helpers ON helpers.reference_id = d.dis_referenceid
        ORDER BY d.dis_dispatched_date DESC, d.dispatched_id DESC
        LIMIT {$limit}
    ")->fetchAll();
}

function list_dispatch_people($type)
{
    ensure_coordinator_schema();

    $type = (string) $type;
    $table = $type === '2' ? 'tbldispatch_helper' : 'tbldispatch_driver';
    $employeeColumn = $type === '2' ? 'dispatch_helper' : 'dispatch_driver';

    $stmt = db()->prepare("
        SELECT
            e.*,
            COALESCE(active_assignments.active_count, 0) AS active_dispatch_count,
            active_assignments.next_dispatch
        FROM tblemployees e
        LEFT JOIN (
            SELECT
                {$employeeColumn} AS employee_id,
                COUNT(DISTINCT dispatch_reference_id) AS active_count,
                MIN(dispatch_date) AS next_dispatch
            FROM {$table}
            GROUP BY {$employeeColumn}
        ) active_assignments ON active_assignments.employee_id = e.employee_id
        WHERE e.who_is = :type
        ORDER BY e.lastname ASC, e.firstname ASC
        LIMIT 500
    ");
    $stmt->execute(['type' => $type]);

    return $stmt->fetchAll();
}

function find_coordinator_booking_by_reference($reference)
{
    ensure_coordinator_schema();

    $stmt = db()->prepare('SELECT * FROM tblbooking WHERE bookingid = :reference LIMIT 1');
    $stmt->execute(['reference' => (int) $reference]);

    return $stmt->fetch() ?: null;
}

function dispatch_preparation_by_reference($reference)
{
    ensure_coordinator_schema();

    $stmt = db()->prepare("
        SELECT *
        FROM tblcoord_dispatch_preparations
        WHERE prep_referenceid = :reference
            AND prep_status = 'prepared'
        LIMIT 1
    ");
    $stmt->execute(['reference' => (int) $reference]);

    return $stmt->fetch() ?: null;
}

function coordinator_is_dispatched($reference)
{
    ensure_coordinator_schema();

    $stmt = db()->prepare('SELECT COUNT(*) FROM tbldispatched WHERE dis_referenceid = :reference');
    $stmt->execute(['reference' => (int) $reference]);

    return (int) $stmt->fetchColumn() > 0;
}

function coordinator_active_dispatch_plate_count($fleetId, $excludeReference = null)
{
    ensure_coordinator_schema();

    $sql = 'SELECT COUNT(*) FROM tbldispatched WHERE CAST(dis_plaka AS UNSIGNED) = :fleet_id';
    $params = ['fleet_id' => (int) $fleetId];

    if ($excludeReference !== null) {
        $sql .= ' AND dis_referenceid <> :reference';
        $params['reference'] = (int) $excludeReference;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function coordinator_normalized_dispatch_data(array $data)
{
    $driverSource = $data['driver_ids'] ?? $data['driver_id'] ?? [];
    $helperSource = $data['helper_ids'] ?? $data['helper_id'] ?? [];

    if (!is_array($driverSource)) {
        $driverSource = [$driverSource];
    }

    if (!is_array($helperSource)) {
        $helperSource = [$helperSource];
    }

    $driverIds = [];
    $helperIds = [];

    foreach ($driverSource as $driverId) {
        $driverId = (int) $driverId;

        if ($driverId > 0) {
            $driverIds[$driverId] = $driverId;
        }
    }

    foreach ($helperSource as $helperId) {
        $helperId = (int) $helperId;

        if ($helperId > 0) {
            $helperIds[$helperId] = $helperId;
        }
    }

    return [
        'reference' => (int) ($data['reference'] ?? $data['booking_reference'] ?? 0),
        'dispatch_date' => normalize_booking_datetime($data['dispatch_date'] ?? ''),
        'route_id' => (int) ($data['route_id'] ?? 0),
        'plate_id' => (int) ($data['plate_id'] ?? 0),
        'driver_id' => (int) reset($driverIds),
        'driver_ids' => array_values($driverIds),
        'helper_ids' => array_values($helperIds),
    ];
}

function validate_dispatch_preparation(array $data, $requireCrew = true)
{
    ensure_coordinator_schema();

    $data = coordinator_normalized_dispatch_data($data);
    $errors = [];
    $booking = $data['reference'] > 0 ? find_coordinator_booking_by_reference($data['reference']) : null;
    $route = $data['route_id'] > 0 ? find_booking_route_by_id($data['route_id']) : null;
    $fleet = $data['plate_id'] > 0 ? find_fleet_by_id($data['plate_id']) : null;

    if (!$booking) {
        $errors[] = 'Booking reference was not found.';
    } elseif (coordinator_is_dispatched($data['reference'])) {
        $errors[] = 'This booking has already been dispatched.';
    }

    if ($data['dispatch_date'] === null) {
        $errors[] = 'Dispatch date is required.';
    }

    if (!$route) {
        $errors[] = 'Select a valid route for dispatch.';
    } elseif ($booking && (int) $route['customerid'] !== (int) $booking['customername']) {
        $errors[] = 'Selected route does not belong to the booking customer.';
    }

    if (!$fleet) {
        $errors[] = 'Select a valid plate number.';
    } elseif (coordinator_active_dispatch_plate_count($data['plate_id'], $data['reference']) > 0) {
        $errors[] = 'Selected plate is already dispatched on an active trip.';
    }

    if ($requireCrew && count($data['driver_ids']) === 0) {
        $errors[] = 'Select at least one driver.';
    }

    foreach ($data['driver_ids'] as $driverId) {
        $driver = find_employee_by_id($driverId);

        if (!$driver || (string) ($driver['who_is'] ?? '') !== '1') {
            $errors[] = 'Selected driver is invalid.';
            break;
        }
    }

    if ($requireCrew && count($data['helper_ids']) === 0) {
        $errors[] = 'Select at least one helper.';
    }

    foreach ($data['helper_ids'] as $helperId) {
        $helper = find_employee_by_id($helperId);

        if (!$helper || (string) ($helper['who_is'] ?? '') !== '2') {
            $errors[] = 'Selected helper is invalid.';
            break;
        }
    }

    return $errors;
}

function save_dispatch_preparation(array $data, $coordinatorId, $requireCrew = false)
{
    ensure_coordinator_schema();

    $normalized = coordinator_normalized_dispatch_data($data);
    $errors = validate_dispatch_preparation($normalized, $requireCrew);

    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $booking = find_coordinator_booking_by_reference($normalized['reference']);

    db()->beginTransaction();

    try {
        $prep = db()->prepare("
            INSERT INTO tblcoord_dispatch_preparations
                (prep_referenceid, prep_coordinator, prep_customer_name_id, prep_ods, prep_plaka, prep_dispatched_date, prep_status)
            VALUES
                (:reference, :coordinator, :customer, :route, :plate, :dispatch_date, 'prepared')
            ON DUPLICATE KEY UPDATE
                prep_coordinator = VALUES(prep_coordinator),
                prep_customer_name_id = VALUES(prep_customer_name_id),
                prep_ods = VALUES(prep_ods),
                prep_plaka = VALUES(prep_plaka),
                prep_dispatched_date = VALUES(prep_dispatched_date),
                prep_status = 'prepared'
        ");
        $prep->execute([
            'reference' => $normalized['reference'],
            'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            'customer' => (int) $booking['customername'],
            'route' => $normalized['route_id'],
            'plate' => $normalized['plate_id'],
            'dispatch_date' => $normalized['dispatch_date'],
        ]);

        $deleteDrivers = db()->prepare('DELETE FROM tbldispatch_driver WHERE dispatch_reference_id = :reference');
        $deleteDrivers->execute(['reference' => $normalized['reference']]);

        $driver = db()->prepare('
            INSERT INTO tbldispatch_driver
                (dispatch_reference_id, dispatch_driver, dispatch_date, dispatch_customer, coordinator)
            VALUES
                (:reference, :driver, :dispatch_date, :customer, :coordinator)
        ');

        foreach ($normalized['driver_ids'] as $driverId) {
            $driver->execute([
                'reference' => $normalized['reference'],
                'driver' => $driverId,
                'dispatch_date' => $normalized['dispatch_date'],
                'customer' => (int) $booking['customername'],
                'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            ]);
        }

        $deleteHelpers = db()->prepare('DELETE FROM tbldispatch_helper WHERE dispatch_reference_id = :reference');
        $deleteHelpers->execute(['reference' => $normalized['reference']]);

        $helper = db()->prepare('
            INSERT INTO tbldispatch_helper
                (dispatch_reference_id, dispatch_helper, dispatch_date, dispatch_customer, coordinator)
            VALUES
                (:reference, :helper, :dispatch_date, :customer, :coordinator)
        ');

        foreach ($normalized['helper_ids'] as $helperId) {
            $helper->execute([
                'reference' => $normalized['reference'],
                'helper' => $helperId,
                'dispatch_date' => $normalized['dispatch_date'],
                'customer' => (int) $booking['customername'],
                'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            ]);
        }

        db()->commit();

        return true;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function coordinator_first_crew_id($table, $referenceColumn, $employeeColumn, $reference)
{
    $stmt = db()->prepare("
        SELECT {$employeeColumn}
        FROM {$table}
        WHERE {$referenceColumn} = :reference
        ORDER BY {$employeeColumn} ASC
        LIMIT 1
    ");
    $stmt->execute(['reference' => (int) $reference]);

    return (int) $stmt->fetchColumn();
}

function coordinator_crew_ids($table, $referenceColumn, $employeeColumn, $reference)
{
    $stmt = db()->prepare("
        SELECT {$employeeColumn}
        FROM {$table}
        WHERE {$referenceColumn} = :reference
        ORDER BY {$employeeColumn} ASC
    ");
    $stmt->execute(['reference' => (int) $reference]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function dispatch_prepared_booking($reference, $coordinatorId)
{
    ensure_coordinator_schema();

    $reference = (int) $reference;
    $preparation = dispatch_preparation_by_reference($reference);

    if (!$preparation) {
        throw new InvalidArgumentException('Prepare the dispatch details before dispatching this booking.');
    }

    $driverIds = coordinator_crew_ids('tbldispatch_driver', 'dispatch_reference_id', 'dispatch_driver', $reference);
    $helperIds = coordinator_crew_ids('tbldispatch_helper', 'dispatch_reference_id', 'dispatch_helper', $reference);
    $data = [
        'reference' => $reference,
        'dispatch_date' => $preparation['prep_dispatched_date'],
        'route_id' => $preparation['prep_ods'],
        'plate_id' => $preparation['prep_plaka'],
        'driver_ids' => $driverIds,
        'helper_ids' => $helperIds,
    ];
    $errors = validate_dispatch_preparation($data, true);

    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    db()->beginTransaction();

    try {
        $insert = db()->prepare('
            INSERT INTO tbldispatched
                (dis_referenceid, dis_coordinator, dis_customer_name_id, dis_ods, dis_plaka, dis_dispatched_date, dis_approved)
            VALUES
                (:reference, :coordinator, :customer, :route, :plate, :dispatch_date, 1)
        ');
        $insert->execute([
            'reference' => $reference,
            'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            'customer' => (int) $preparation['prep_customer_name_id'],
            'route' => (int) $preparation['prep_ods'],
            'plate' => (int) $preparation['prep_plaka'],
            'dispatch_date' => $preparation['prep_dispatched_date'],
        ]);

        $updatePrep = db()->prepare("
            UPDATE tblcoord_dispatch_preparations
            SET prep_status = 'dispatched',
                prep_coordinator = :coordinator
            WHERE prep_referenceid = :reference
        ");
        $updatePrep->execute([
            'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            'reference' => $reference,
        ]);

        $updateDrivers = db()->prepare('
            UPDATE tbldispatch_driver
            SET dispatch_date = :dispatch_date,
                coordinator = :coordinator
            WHERE dispatch_reference_id = :reference
        ');
        $updateDrivers->execute([
            'dispatch_date' => $preparation['prep_dispatched_date'],
            'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            'reference' => $reference,
        ]);

        $updateHelpers = db()->prepare('
            UPDATE tbldispatch_helper
            SET dispatch_date = :dispatch_date,
                coordinator = :coordinator
            WHERE dispatch_reference_id = :reference
        ');
        $updateHelpers->execute([
            'dispatch_date' => $preparation['prep_dispatched_date'],
            'coordinator' => $coordinatorId ? (int) $coordinatorId : null,
            'reference' => $reference,
        ]);

        db()->commit();

        return true;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function save_and_dispatch_booking(array $data, $coordinatorId)
{
    save_dispatch_preparation($data, $coordinatorId, true);

    return dispatch_prepared_booking((int) ($data['reference'] ?? $data['booking_reference'] ?? 0), $coordinatorId);
}

function cancel_dispatched_booking($reference, $coordinatorId = null)
{
    ensure_coordinator_schema();

    $reference = (int) $reference;

    if ($reference <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM tbldispatched WHERE dis_referenceid = :reference LIMIT 1');
    $stmt->execute(['reference' => $reference]);
    $dispatch = $stmt->fetch();

    if (!$dispatch) {
        return false;
    }

    db()->beginTransaction();

    try {
        $prep = db()->prepare("
            INSERT INTO tblcoord_dispatch_preparations
                (prep_referenceid, prep_coordinator, prep_customer_name_id, prep_ods, prep_plaka, prep_dispatched_date, prep_status)
            VALUES
                (:reference, :coordinator, :customer, :route, :plate, :dispatch_date, 'prepared')
            ON DUPLICATE KEY UPDATE
                prep_coordinator = VALUES(prep_coordinator),
                prep_customer_name_id = VALUES(prep_customer_name_id),
                prep_ods = VALUES(prep_ods),
                prep_plaka = VALUES(prep_plaka),
                prep_dispatched_date = VALUES(prep_dispatched_date),
                prep_status = 'prepared'
        ");
        $prep->execute([
            'reference' => $reference,
            'coordinator' => $coordinatorId ? (int) $coordinatorId : ($dispatch['dis_coordinator'] ?: null),
            'customer' => (int) $dispatch['dis_customer_name_id'],
            'route' => (int) $dispatch['dis_ods'],
            'plate' => (int) $dispatch['dis_plaka'],
            'dispatch_date' => $dispatch['dis_dispatched_date'],
        ]);

        $delete = db()->prepare('DELETE FROM tbldispatched WHERE dis_referenceid = :reference');
        $delete->execute(['reference' => $reference]);

        db()->commit();

        return $delete->rowCount() > 0;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}
