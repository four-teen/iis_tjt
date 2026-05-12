<?php

require_once __DIR__ . '/master_data.php';

function booking_type_options()
{
    return [
        'per_trip' => [
            'code' => 0,
            'label' => 'Per Trip',
            'detail' => 'Standard booking against non-lock-in delivery routes.',
        ],
        'lock_in' => [
            'code' => 9,
            'label' => 'Lock In',
            'detail' => 'Reserved booking for lock-in delivery type routes.',
        ],
    ];
}

function booking_type_code($type)
{
    $options = booking_type_options();

    return $options[$type]['code'] ?? $options['per_trip']['code'];
}

function booking_type_label($value)
{
    $code = (int) $value;

    return $code === 9 ? 'Lock In' : 'Per Trip';
}

function booking_type_key_from_code($value)
{
    return (int) $value === 9 ? 'lock_in' : 'per_trip';
}

function booking_table_exists($table)
{
    static $cache = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
    ');
    $stmt->execute(['table' => $table]);

    $cache[$table] = (int) $stmt->fetchColumn() > 0;

    return $cache[$table];
}

function booking_route_matches_type($route, $type)
{
    $deliveryType = (int) ($route['deliverytype'] ?? 0);

    if ($type === 'lock_in') {
        return $deliveryType === 9;
    }

    return $deliveryType !== 9;
}

function booking_status_badge($pickupDate, $deliveryDate)
{
    $pickup = strtotime((string) $pickupDate);
    $delivery = strtotime((string) $deliveryDate);
    $now = time();

    if ($delivery && $delivery < $now) {
        return ['class' => 'badge-danger', 'label' => 'Past Delivery'];
    }

    if ($pickup && $pickup < $now) {
        return ['class' => 'badge-warning', 'label' => 'Pickup Due'];
    }

    if ($pickup && date('Y-m-d', $pickup) === date('Y-m-d')) {
        return ['class' => 'badge-info', 'label' => 'Pickup Today'];
    }

    return ['class' => 'badge-success', 'label' => 'Scheduled'];
}

function booking_lapsed_label($pickupDate)
{
    $pickup = strtotime((string) $pickupDate);

    if (!$pickup) {
        return ['tone' => 'muted', 'label' => 'No pickup date'];
    }

    $today = new DateTimeImmutable('today');
    $pickupDay = new DateTimeImmutable(date('Y-m-d', $pickup));
    $days = (int) $today->diff($pickupDay)->format('%r%a');

    if ($days < 0) {
        return ['tone' => 'danger', 'label' => abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' delayed'];
    }

    if ($days === 0) {
        return ['tone' => 'warning', 'label' => 'Due today'];
    }

    return ['tone' => 'success', 'label' => $days . ' day' . ($days === 1 ? '' : 's') . ' ahead'];
}

function booking_ordinal_label($number)
{
    $number = (int) $number;
    $lastTwo = $number % 100;

    if ($lastTwo >= 11 && $lastTwo <= 13) {
        return $number . 'th';
    }

    switch ($number % 10) {
        case 1:
            return $number . 'st';
        case 2:
            return $number . 'nd';
        case 3:
            return $number . 'rd';
        default:
            return $number . 'th';
    }
}

function apply_plate_schedule_labels(array $bookings)
{
    $groups = [];

    foreach ($bookings as $index => $booking) {
        $fleetId = (int) ($booking['reservedplate'] ?? 0);

        if ($fleetId <= 0) {
            continue;
        }

        $groups[$fleetId][] = $index;
    }

    foreach ($groups as $indexes) {
        usort($indexes, function ($left, $right) use ($bookings) {
            $leftBooking = $bookings[$left];
            $rightBooking = $bookings[$right];
            $leftKey = [
                strtotime((string) ($leftBooking['pickupdate'] ?? '')) ?: PHP_INT_MAX,
                strtotime((string) ($leftBooking['deliverydate'] ?? '')) ?: PHP_INT_MAX,
                strtotime((string) ($leftBooking['bookingdate'] ?? '')) ?: PHP_INT_MAX,
                (int) ($leftBooking['bookingidauto'] ?? 0),
            ];
            $rightKey = [
                strtotime((string) ($rightBooking['pickupdate'] ?? '')) ?: PHP_INT_MAX,
                strtotime((string) ($rightBooking['deliverydate'] ?? '')) ?: PHP_INT_MAX,
                strtotime((string) ($rightBooking['bookingdate'] ?? '')) ?: PHP_INT_MAX,
                (int) ($rightBooking['bookingidauto'] ?? 0),
            ];

            return $leftKey <=> $rightKey;
        });

        $total = count($indexes);

        foreach ($indexes as $position => $bookingIndex) {
            $bookings[$bookingIndex]['plate_schedule_position'] = $position + 1;
            $bookings[$bookingIndex]['plate_schedule_total'] = $total;
            $bookings[$bookingIndex]['plate_schedule_label'] = booking_ordinal_label($position + 1) . ' scheduled';
        }
    }

    return $bookings;
}

function booking_datetime_value($value)
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function booking_datetime_text($value)
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M d, Y @ h:i A', $timestamp) : 'Not set';
}

function normalize_booking_datetime($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $timestamp = strtotime($value);

    return $timestamp ? date('Y-m-d H:i', $timestamp) : null;
}

function generate_booking_reference($preferredReference = null)
{
    $reference = (int) $preferredReference;

    if ($reference < 1000000000) {
        $reference = time();
    }

    while (booking_reference_exists($reference)) {
        $reference++;
    }

    return $reference;
}

function booking_reference_exists($reference)
{
    ensure_customer_service_schema();

    $stmt = db()->prepare('SELECT COUNT(*) FROM tblbooking WHERE bookingid = :reference');
    $stmt->execute(['reference' => (int) $reference]);

    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM tblbooking_canceled WHERE bookingid = :reference');
    $stmt->execute(['reference' => (int) $reference]);

    return (int) $stmt->fetchColumn() > 0;
}

function ensure_customer_service_schema()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblbooking (
            bookingidauto INT(11) NOT NULL AUTO_INCREMENT,
            bookingid BIGINT NOT NULL,
            bookingdate DATETIME NOT NULL,
            customername INT(11) NOT NULL,
            companyrepresentative VARCHAR(80) NOT NULL,
            origindestination INT(11) NOT NULL,
            pickupdate DATETIME NOT NULL,
            deliverydate DATETIME NOT NULL,
            deliverytype INT(11) NOT NULL,
            reservedplate INT(11) NOT NULL,
            PRIMARY KEY (bookingidauto),
            KEY idx_tblbooking_reference (bookingid),
            KEY idx_tblbooking_customer (customername),
            KEY idx_tblbooking_route (origindestination),
            KEY idx_tblbooking_pickup (pickupdate),
            KEY idx_tblbooking_reservedplate (reservedplate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblbooking_canceled (
            bookingidauto INT(11) NOT NULL,
            bookingid BIGINT NOT NULL,
            bookingdate DATETIME NOT NULL,
            customername INT(11) NOT NULL,
            companyrepresentative VARCHAR(80) NOT NULL,
            origindestination INT(11) NOT NULL,
            pickupdate DATETIME NOT NULL,
            deliverydate DATETIME NOT NULL,
            deliverytype INT(11) NOT NULL,
            reservedplate INT(11) NOT NULL,
            PRIMARY KEY (bookingidauto),
            KEY idx_tblbooking_canceled_reference (bookingid),
            KEY idx_tblbooking_canceled_customer (customername),
            KEY idx_tblbooking_canceled_pickup (pickupdate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS tblshipment_number (
            shipment_number_id INT(11) NOT NULL AUTO_INCREMENT,
            sn_reference_id BIGINT NOT NULL,
            shipmentnumber VARCHAR(60) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (shipment_number_id),
            UNIQUE KEY uq_tblshipment_number_reference (sn_reference_id),
            KEY idx_tblshipment_number_value (shipmentnumber)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ensured = true;
}

function booking_counts()
{
    ensure_customer_service_schema();

    $dispatchJoin = '';
    $dispatchWhere = '';

    if (booking_table_exists('tbldispatched')) {
        $dispatchJoin = 'LEFT JOIN tbldispatched d ON d.dis_referenceid = b.bookingid';
        $dispatchWhere = 'WHERE d.dispatched_id IS NULL';
    }

    $row = db()->query('
        SELECT
            COUNT(*) AS active_bookings,
            SUM(CASE WHEN b.pickupdate < NOW() THEN 1 ELSE 0 END) AS pickup_due,
            SUM(CASE WHEN DATE(b.pickupdate) = CURRENT_DATE THEN 1 ELSE 0 END) AS pickup_today
        FROM tblbooking b
        ' . $dispatchJoin . '
        ' . $dispatchWhere . '
    ')->fetch();
    $dispatched = 0;

    if (booking_table_exists('tbldispatched')) {
        $dispatched = (int) db()->query('SELECT COUNT(*) FROM tbldispatched')->fetchColumn();
    }

    return [
        'active' => (int) ($row['active_bookings'] ?? 0),
        'pickup_due' => (int) ($row['pickup_due'] ?? 0),
        'pickup_today' => (int) ($row['pickup_today'] ?? 0),
        'canceled' => (int) db()->query('SELECT COUNT(*) FROM tblbooking_canceled')->fetchColumn(),
        'dispatched' => $dispatched,
    ];
}

function list_booking_routes()
{
    $stmt = db()->query('
        SELECT
            ci.*,
            c.soa,
            c.customername,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name
        FROM tblcustomerinformation ci
        INNER JOIN tblcustomer c ON c.customerid = ci.customerid
        INNER JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        INNER JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        WHERE c.status = 1
        ORDER BY c.soa ASC, c.customername ASC, dt.deliverytype ASC, ci.origin ASC, ci.destination ASC
        LIMIT 1000
    ');

    return $stmt->fetchAll();
}

function list_booking_customers()
{
    return list_customers('', '1');
}

function list_booking_fleet_options()
{
    ensure_customer_service_schema();

    $dispatchJoin = '';
    $dispatchSelect = '0 AS dispatch_count';
    $activeBookingDispatchJoin = '';
    $activeBookingWhere = '';

    if (booking_table_exists('tbldispatched')) {
        $dispatchSelect = 'COALESCE(active_dispatches.dispatch_count, 0) AS dispatch_count';
        $dispatchJoin = '
            LEFT JOIN (
                SELECT CAST(dis_plaka AS UNSIGNED) AS fleetid, COUNT(*) AS dispatch_count
                FROM tbldispatched
                GROUP BY CAST(dis_plaka AS UNSIGNED)
            ) active_dispatches ON active_dispatches.fleetid = f.fleetid
        ';
        $activeBookingDispatchJoin = 'LEFT JOIN tbldispatched d ON d.dis_referenceid = b.bookingid';
        $activeBookingWhere = 'WHERE d.dispatched_id IS NULL';
    }

    return db()->query('
        SELECT
            f.fleetid,
            f.platenumber,
            f.paremarks,
            f.validity,
            fi.trucktype,
            tt.trucktype AS trucktype_name,
            COALESCE(active_bookings.total_active, 0) AS active_booking_count,
            active_bookings.next_pickup,
            active_bookings.last_delivery,
            ' . $dispatchSelect . '
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = fi.trucktype
        LEFT JOIN (
            SELECT
                CAST(b.reservedplate AS UNSIGNED) AS fleetid,
                COUNT(*) AS total_active,
                MIN(b.pickupdate) AS next_pickup,
                MAX(b.deliverydate) AS last_delivery
            FROM tblbooking b
            ' . $activeBookingDispatchJoin . '
            ' . $activeBookingWhere . '
            GROUP BY CAST(b.reservedplate AS UNSIGNED)
        ) active_bookings ON active_bookings.fleetid = f.fleetid
        ' . $dispatchJoin . '
        ORDER BY f.platenumber ASC
        LIMIT 1000
    ')->fetchAll();
}

function find_booking_route_by_id($routeId)
{
    $stmt = db()->prepare('
        SELECT
            ci.*,
            c.soa,
            c.customername,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name
        FROM tblcustomerinformation ci
        INNER JOIN tblcustomer c ON c.customerid = ci.customerid
        INNER JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        INNER JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        WHERE ci.customerinformationid = :route_id
        LIMIT 1
    ');
    $stmt->execute(['route_id' => (int) $routeId]);

    return $stmt->fetch() ?: null;
}

function find_booking_by_id($bookingId)
{
    ensure_customer_service_schema();

    $stmt = db()->prepare('SELECT * FROM tblbooking WHERE bookingidauto = :booking_id LIMIT 1');
    $stmt->execute(['booking_id' => (int) $bookingId]);

    return $stmt->fetch() ?: null;
}

function fleet_booking_conflict_count($fleetId, $pickupDate, $deliveryDate, $excludeBookingId = null)
{
    ensure_customer_service_schema();

    $sql = '
        SELECT COUNT(*)
        FROM tblbooking
        WHERE CAST(reservedplate AS UNSIGNED) = :fleet_id
            AND NOT (deliverydate <= :pickup_date OR pickupdate >= :delivery_date)
    ';
    $params = [
        'fleet_id' => (int) $fleetId,
        'pickup_date' => $pickupDate,
        'delivery_date' => $deliveryDate,
    ];

    if ($excludeBookingId !== null) {
        $sql .= ' AND bookingidauto <> :booking_id';
        $params['booking_id'] = (int) $excludeBookingId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function fleet_dispatch_conflict_count($fleetId)
{
    if (!booking_table_exists('tbldispatched')) {
        return 0;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM tbldispatched WHERE CAST(dis_plaka AS UNSIGNED) = :fleet_id');
    $stmt->execute(['fleet_id' => (int) $fleetId]);

    return (int) $stmt->fetchColumn();
}

function validate_booking_data($data, $excludeBookingId = null)
{
    $errors = [];
    $type = $data['booking_type'] ?? 'per_trip';
    $customerId = (int) ($data['customer_id'] ?? 0);
    $routeId = (int) ($data['route_id'] ?? 0);
    $fleetId = (int) ($data['reserved_plate'] ?? 0);
    $bookingDate = normalize_booking_datetime($data['booking_date'] ?? '');
    $pickupDate = normalize_booking_datetime($data['pickup_date'] ?? '');
    $deliveryDate = normalize_booking_datetime($data['delivery_date'] ?? '');
    $shipmentNumber = clean_text($data['shipment_number'] ?? '');
    $representative = clean_text($data['representative'] ?? '');
    $route = $routeId > 0 ? find_booking_route_by_id($routeId) : null;

    if (!array_key_exists($type, booking_type_options())) {
        $errors[] = 'Select a valid booking type.';
    }

    $customer = $customerId > 0 ? find_customer_by_id($customerId) : null;

    if (!$customer || (int) ($customer['status'] ?? 0) !== 1) {
        $errors[] = 'Select an active customer.';
    }

    if (!$route) {
        $errors[] = 'Select a valid customer route.';
    } elseif ((int) $route['customerid'] !== $customerId) {
        $errors[] = 'Selected route does not belong to the selected customer.';
    } elseif (!booking_route_matches_type($route, $type)) {
        $errors[] = 'Selected route does not match the booking type.';
    }

    if ($bookingDate === null) {
        $errors[] = 'Booking date is required.';
    }

    if ($pickupDate === null) {
        $errors[] = 'Pick-up date is required.';
    }

    if ($deliveryDate === null) {
        $errors[] = 'Delivery date is required.';
    }

    if ($pickupDate !== null && $deliveryDate !== null && strtotime($deliveryDate) < strtotime($pickupDate)) {
        $errors[] = 'Delivery date must be after the pick-up date.';
    }

    if ($shipmentNumber === '') {
        $errors[] = 'Shipment number is required.';
    } elseif (strlen($shipmentNumber) > 60) {
        $errors[] = 'Shipment number must be 60 characters or fewer.';
    }

    if (strlen($representative) > 80) {
        $errors[] = 'Representative must be 80 characters or fewer.';
    }

    if (!find_fleet_by_id($fleetId)) {
        $errors[] = 'Select a valid reserved plate number.';
    } elseif (fleet_dispatch_conflict_count($fleetId) > 0) {
        $errors[] = 'Selected plate is already marked as dispatched.';
    }

    return $errors;
}

function create_booking($data)
{
    ensure_customer_service_schema();

    $reference = generate_booking_reference($data['booking_reference'] ?? null);
    $bookingDate = normalize_booking_datetime($data['booking_date']);
    $pickupDate = normalize_booking_datetime($data['pickup_date']);
    $deliveryDate = normalize_booking_datetime($data['delivery_date']);

    db()->beginTransaction();

    try {
        $stmt = db()->prepare('
            INSERT INTO tblbooking
                (bookingid, bookingdate, customername, companyrepresentative, origindestination, pickupdate, deliverydate, deliverytype, reservedplate)
            VALUES
                (:bookingid, :bookingdate, :customername, :companyrepresentative, :origindestination, :pickupdate, :deliverydate, :deliverytype, :reservedplate)
        ');
        $stmt->execute([
            'bookingid' => $reference,
            'bookingdate' => $bookingDate,
            'customername' => (int) $data['customer_id'],
            'companyrepresentative' => strtoupper(clean_text($data['representative'] ?? '')),
            'origindestination' => (int) $data['route_id'],
            'pickupdate' => $pickupDate,
            'deliverydate' => $deliveryDate,
            'deliverytype' => booking_type_code($data['booking_type'] ?? 'per_trip'),
            'reservedplate' => (int) $data['reserved_plate'],
        ]);

        $bookingId = (int) db()->lastInsertId();

        $shipment = db()->prepare('
            INSERT INTO tblshipment_number (sn_reference_id, shipmentnumber)
            VALUES (:reference_id, :shipment_number)
            ON DUPLICATE KEY UPDATE shipmentnumber = VALUES(shipmentnumber)
        ');
        $shipment->execute([
            'reference_id' => $reference,
            'shipment_number' => strtoupper(clean_text($data['shipment_number'] ?? '')),
        ]);

        db()->commit();

        return $bookingId;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function cancel_booking($bookingId)
{
    ensure_customer_service_schema();

    if (!find_booking_by_id($bookingId)) {
        return false;
    }

    db()->beginTransaction();

    try {
        $copy = db()->prepare('
            INSERT IGNORE INTO tblbooking_canceled
                (bookingidauto, bookingid, bookingdate, customername, companyrepresentative, origindestination, pickupdate, deliverydate, deliverytype, reservedplate)
            SELECT bookingidauto, bookingid, bookingdate, customername, companyrepresentative, origindestination, pickupdate, deliverydate, deliverytype, reservedplate
            FROM tblbooking
            WHERE bookingidauto = :booking_id
        ');
        $copy->execute(['booking_id' => (int) $bookingId]);

        $delete = db()->prepare('DELETE FROM tblbooking WHERE bookingidauto = :booking_id');
        $delete->execute(['booking_id' => (int) $bookingId]);

        db()->commit();

        return $delete->rowCount() > 0;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function list_active_bookings()
{
    ensure_customer_service_schema();

    $dispatchJoin = '';
    $dispatchWhere = '';

    if (booking_table_exists('tbldispatched')) {
        $dispatchJoin = 'LEFT JOIN tbldispatched dispatched_booking ON dispatched_booking.dis_referenceid = b.bookingid';
        $dispatchWhere = 'WHERE dispatched_booking.dispatched_id IS NULL';
    }

    return db()->query('
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
            sn.shipmentnumber
        FROM tblbooking b
        ' . $dispatchJoin . '
        LEFT JOIN tblcustomer c ON c.customerid = CAST(b.customername AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(b.origindestination AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(b.reservedplate AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = b.bookingid
        ' . $dispatchWhere . '
        ORDER BY b.bookingidauto DESC
        LIMIT 500
    ')->fetchAll();
}

function list_recent_canceled_bookings($limit = 8)
{
    ensure_customer_service_schema();

    $limit = max(1, min(50, (int) $limit));

    return db()->query('
        SELECT
            b.*,
            c.soa,
            c.customername AS customer_label,
            ci.origin,
            ci.destination,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name,
            f.platenumber,
            sn.shipmentnumber
        FROM tblbooking_canceled b
        LEFT JOIN tblcustomer c ON c.customerid = CAST(b.customername AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(b.origindestination AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(b.reservedplate AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = b.bookingid
        ORDER BY b.bookingidauto DESC
        LIMIT ' . $limit . '
    ')->fetchAll();
}

function booking_customer_type_availability($routes)
{
    $availability = [];

    foreach ($routes as $route) {
        $customerId = (int) ($route['customerid'] ?? 0);

        if ($customerId <= 0) {
            continue;
        }

        $type = booking_type_key_from_code($route['deliverytype'] ?? 0);
        $availability[$customerId][$type] = true;
    }

    $tokens = [];

    foreach ($availability as $customerId => $types) {
        $tokens[$customerId] = implode(' ', array_keys($types));
    }

    return $tokens;
}

function booking_setup_warnings()
{
    $warnings = [];
    $activeCustomers = (int) db()->query('SELECT COUNT(*) FROM tblcustomer WHERE status = 1')->fetchColumn();
    $routes = (int) db()->query('SELECT COUNT(*) FROM tblcustomerinformation')->fetchColumn();
    $fleet = (int) db()->query('SELECT COUNT(*) FROM tblfleet')->fetchColumn();
    $zeroDeliveryRates = (int) db()->query('SELECT COUNT(*) FROM tblcustomerinformation WHERE deliveryrate <= 0')->fetchColumn();
    $unprofiledFleet = (int) db()->query('
        SELECT COUNT(*)
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        WHERE fi.fleetid IS NULL OR fi.trucktype IS NULL
    ')->fetchColumn();

    if ($activeCustomers === 0) {
        $warnings[] = ['tone' => 'danger', 'label' => 'No active customers', 'count' => 0];
    }

    if ($routes === 0) {
        $warnings[] = ['tone' => 'danger', 'label' => 'No customer routes', 'count' => 0];
    }

    if ($fleet === 0) {
        $warnings[] = ['tone' => 'danger', 'label' => 'No fleet records', 'count' => 0];
    }

    if ($zeroDeliveryRates > 0) {
        $warnings[] = ['tone' => 'warning', 'label' => 'Routes with zero delivery rate', 'count' => $zeroDeliveryRates];
    }

    if ($unprofiledFleet > 0) {
        $warnings[] = ['tone' => 'warning', 'label' => 'Fleet records missing truck profile', 'count' => $unprofiledFleet];
    }

    return $warnings;
}

function booking_crew_counts()
{
    $statusJoin = '';
    $statusWhere = '';

    if (booking_table_exists('tblemployees_status')) {
        $statusJoin = ' INNER JOIN tblemployees_status s ON s.status_employee_id = e.employee_id';
        $statusWhere = ' AND s.status_code = 1';
    }

    $stmt = db()->query('
        SELECT
            SUM(CASE WHEN e.who_is = "1" THEN 1 ELSE 0 END) AS drivers,
            SUM(CASE WHEN e.who_is = "2" THEN 1 ELSE 0 END) AS helpers
        FROM tblemployees e
        ' . $statusJoin . '
        WHERE e.who_is IN ("1", "2")' . $statusWhere . '
    ');
    $row = $stmt->fetch();
    $dispatched = 0;

    if (booking_table_exists('tbldispatch_driver')) {
        $dispatched += (int) db()->query('SELECT COUNT(DISTINCT dispatch_driver) FROM tbldispatch_driver')->fetchColumn();
    }

    if (booking_table_exists('tbldispatch_helper')) {
        $dispatched += (int) db()->query('SELECT COUNT(DISTINCT dispatch_helper) FROM tbldispatch_helper')->fetchColumn();
    }

    $drivers = (int) ($row['drivers'] ?? 0);
    $helpers = (int) ($row['helpers'] ?? 0);
    $active = $drivers + $helpers;

    return [
        'drivers' => $drivers,
        'helpers' => $helpers,
        'active' => $active,
        'dispatched' => min($active, $dispatched),
        'available' => max(0, $active - $dispatched),
    ];
}

function list_booking_crew_status($limit = 12)
{
    $limit = max(1, min(100, (int) $limit));
    $profileJoin = '';
    $profileSelect = "'No Contact' AS phone";
    $statusJoin = '';
    $statusWhere = '';
    $dispatchJoin = '';
    $dispatchSelect = "'Available' AS dispatch_status";

    if (booking_table_exists('tblprofile')) {
        $profileSelect = "COALESCE(p.phone, 'No Contact') AS phone";
        $profileJoin = ' LEFT JOIN tblprofile p ON p.profile_employee_id = e.employee_id';
    }

    if (booking_table_exists('tblemployees_status')) {
        $statusJoin = ' INNER JOIN tblemployees_status s ON s.status_employee_id = e.employee_id';
        $statusWhere = ' AND s.status_code = 1';
    }

    if (booking_table_exists('tbldispatch_driver') || booking_table_exists('tbldispatch_helper')) {
        $driverColumn = 'NULL';
        $helperColumn = 'NULL';
        $driverJoin = booking_table_exists('tbldispatch_driver')
            ? ' LEFT JOIN (SELECT DISTINCT dispatch_driver FROM tbldispatch_driver) dd ON dd.dispatch_driver = e.employee_id'
            : '';
        $helperJoin = booking_table_exists('tbldispatch_helper')
            ? ' LEFT JOIN (SELECT DISTINCT dispatch_helper FROM tbldispatch_helper) dh ON dh.dispatch_helper = e.employee_id'
            : '';
        $driverColumn = booking_table_exists('tbldispatch_driver') ? 'dd.dispatch_driver' : 'NULL';
        $helperColumn = booking_table_exists('tbldispatch_helper') ? 'dh.dispatch_helper' : 'NULL';
        $dispatchJoin = $driverJoin . $helperJoin;
        $dispatchSelect = "
            CASE
                WHEN {$driverColumn} IS NOT NULL THEN 'Dispatched as Driver'
                WHEN {$helperColumn} IS NOT NULL THEN 'Dispatched as Helper'
                ELSE 'Available'
            END AS dispatch_status
        ";
    }

    return db()->query('
        SELECT
            e.employee_id,
            e.firstname,
            e.middlename,
            e.lastname,
            e.who_is,
            ' . $profileSelect . ',
            ' . $dispatchSelect . '
        FROM tblemployees e
        ' . $profileJoin . '
        ' . $statusJoin . '
        ' . $dispatchJoin . '
        WHERE e.who_is IN ("1", "2")' . $statusWhere . '
        ORDER BY e.who_is ASC, e.lastname ASC, e.firstname ASC
        LIMIT ' . $limit . '
    ')->fetchAll();
}
