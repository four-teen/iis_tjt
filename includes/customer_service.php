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

function generate_booking_reference()
{
    $reference = time();

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

    $row = db()->query('
        SELECT
            COUNT(*) AS active_bookings,
            SUM(CASE WHEN pickupdate < NOW() THEN 1 ELSE 0 END) AS pickup_due,
            SUM(CASE WHEN DATE(pickupdate) = CURRENT_DATE THEN 1 ELSE 0 END) AS pickup_today
        FROM tblbooking
    ')->fetch();

    return [
        'active' => (int) ($row['active_bookings'] ?? 0),
        'pickup_due' => (int) ($row['pickup_due'] ?? 0),
        'pickup_today' => (int) ($row['pickup_today'] ?? 0),
        'canceled' => (int) db()->query('SELECT COUNT(*) FROM tblbooking_canceled')->fetchColumn(),
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

    return db()->query('
        SELECT
            f.fleetid,
            f.platenumber,
            f.paremarks,
            f.validity,
            fi.trucktype,
            tt.trucktype AS trucktype_name,
            COALESCE(active_bookings.total_active, 0) AS active_booking_count
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = fi.trucktype
        LEFT JOIN (
            SELECT CAST(reservedplate AS UNSIGNED) AS fleetid, COUNT(*) AS total_active
            FROM tblbooking
            GROUP BY CAST(reservedplate AS UNSIGNED)
        ) active_bookings ON active_bookings.fleetid = f.fleetid
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
    } elseif ($pickupDate !== null && $deliveryDate !== null && fleet_booking_conflict_count($fleetId, $pickupDate, $deliveryDate, $excludeBookingId) > 0) {
        $errors[] = 'Selected plate is already reserved for an overlapping booking schedule.';
    }

    return $errors;
}

function create_booking($data)
{
    ensure_customer_service_schema();

    $reference = generate_booking_reference();
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
        LEFT JOIN tblcustomer c ON c.customerid = CAST(b.customername AS UNSIGNED)
        LEFT JOIN tblcustomerinformation ci ON ci.customerinformationid = CAST(b.origindestination AS UNSIGNED)
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        LEFT JOIN tblfleet f ON f.fleetid = CAST(b.reservedplate AS UNSIGNED)
        LEFT JOIN tblshipment_number sn ON sn.sn_reference_id = b.bookingid
        ORDER BY b.bookingidauto DESC
        LIMIT 500
    ')->fetchAll();
}

function booking_crew_counts()
{
    $stmt = db()->query('
        SELECT
            SUM(CASE WHEN who_is = "1" THEN 1 ELSE 0 END) AS drivers,
            SUM(CASE WHEN who_is = "2" THEN 1 ELSE 0 END) AS helpers
        FROM tblemployees
        WHERE who_is IN ("1", "2")
    ');
    $row = $stmt->fetch();

    return [
        'drivers' => (int) ($row['drivers'] ?? 0),
        'helpers' => (int) ($row['helpers'] ?? 0),
    ];
}
