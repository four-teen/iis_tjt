<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/text_encoding.php';

function employee_types()
{
    return [
        '0' => 'Regular Employee',
        '1' => 'Driver',
        '2' => 'Helper',
        '3' => 'Fleet Owner',
    ];
}

function employee_type_label($code)
{
    $types = employee_types();

    return $types[(string) $code] ?? 'Unclassified';
}

function customer_status_label($status)
{
    return (int) $status === 1 ? 'Active' : 'Inactive';
}

function clean_text($value)
{
    return trim(preg_replace('/\s+/', ' ', repair_legacy_text_encoding((string) $value)));
}

function list_customers($search = '', $status = '')
{
    $sql = 'SELECT * FROM tblcustomer';
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(soa LIKE :search_soa OR customername LIKE :search_name OR customeraddress LIKE :search_address)';
        $searchTerm = '%' . $search . '%';
        $params['search_soa'] = $searchTerm;
        $params['search_name'] = $searchTerm;
        $params['search_address'] = $searchTerm;
    }

    if ($status !== '' && in_array($status, ['0', '1'], true)) {
        $where[] = 'status = :status';
        $params['status'] = (int) $status;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY customername ASC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function customer_counts()
{
    $stmt = db()->query('
        SELECT
            COUNT(*) AS total_customers,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_customers,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS inactive_customers
        FROM tblcustomer
    ');
    $row = $stmt->fetch();

    return [
        'total' => (int) ($row['total_customers'] ?? 0),
        'active' => (int) ($row['active_customers'] ?? 0),
        'inactive' => (int) ($row['inactive_customers'] ?? 0),
    ];
}

function customer_route_totals_for_customers($customerIds)
{
    $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));

    if (!$customerIds) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
    $stmt = db()->prepare('
        SELECT customerid, COUNT(*) AS total_routes
        FROM tblcustomerinformation
        WHERE customerid IN (' . $placeholders . ')
        GROUP BY customerid
    ');
    $stmt->execute($customerIds);

    $totals = array_fill_keys($customerIds, 0);

    foreach ($stmt->fetchAll() as $row) {
        $totals[(int) $row['customerid']] = (int) $row['total_routes'];
    }

    return $totals;
}

function find_customer_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tblcustomer WHERE customerid = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function customer_soa_exists($soa, $excludeId = null)
{
    $sql = 'SELECT customerid FROM tblcustomer WHERE soa = :soa';
    $params = ['soa' => clean_text($soa)];

    if ($excludeId !== null) {
        $sql .= ' AND customerid <> :id';
        $params['id'] = (int) $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function validate_customer_data($data, $excludeId = null)
{
    $errors = [];
    $soa = strtoupper(clean_text($data['soa'] ?? ''));
    $name = strtoupper(clean_text($data['customername'] ?? ''));
    $address = strtoupper(clean_text($data['customeraddress'] ?? ''));
    $status = (string) ($data['status'] ?? '1');

    if ($soa === '') {
        $errors[] = 'SOA code is required.';
    } elseif (strlen($soa) > 7) {
        $errors[] = 'SOA code must be 7 characters or fewer.';
    } elseif (customer_soa_exists($soa, $excludeId)) {
        $errors[] = 'SOA code is already used.';
    }

    if ($name === '') {
        $errors[] = 'Customer name is required.';
    }

    if ($address === '') {
        $errors[] = 'Customer address is required.';
    }

    if (!in_array($status, ['0', '1'], true)) {
        $errors[] = 'Customer status is invalid.';
    }

    return $errors;
}

function create_customer($data)
{
    $stmt = db()->prepare('
        INSERT INTO tblcustomer (soa, customername, customeraddress, status)
        VALUES (:soa, :customername, :customeraddress, :status)
    ');

    $stmt->execute([
        'soa' => strtoupper(clean_text($data['soa'])),
        'customername' => strtoupper(clean_text($data['customername'])),
        'customeraddress' => strtoupper(clean_text($data['customeraddress'])),
        'status' => (int) $data['status'],
    ]);

    return (int) db()->lastInsertId();
}

function update_customer($id, $data)
{
    $stmt = db()->prepare('
        UPDATE tblcustomer
        SET soa = :soa,
            customername = :customername,
            customeraddress = :customeraddress,
            status = :status
        WHERE customerid = :id
    ');

    $stmt->execute([
        'soa' => strtoupper(clean_text($data['soa'])),
        'customername' => strtoupper(clean_text($data['customername'])),
        'customeraddress' => strtoupper(clean_text($data['customeraddress'])),
        'status' => (int) $data['status'],
        'id' => (int) $id,
    ]);
}

function delete_customer($id)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM tblcustomerinformation WHERE customerid = :id');
    $stmt->execute(['id' => (int) $id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('Customer has route/rate records and cannot be deleted yet.');
    }

    $stmt = db()->prepare('DELETE FROM tblcustomer WHERE customerid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function list_employees($search = '', $type = '')
{
    $sql = 'SELECT * FROM tblemployees';
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(firstname LIKE :search_firstname OR middlename LIKE :search_middlename OR lastname LIKE :search_lastname)';
        $searchTerm = '%' . $search . '%';
        $params['search_firstname'] = $searchTerm;
        $params['search_middlename'] = $searchTerm;
        $params['search_lastname'] = $searchTerm;
    }

    if ($type !== '' && array_key_exists($type, employee_types())) {
        $where[] = 'who_is = :type';
        $params['type'] = $type;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY lastname ASC, firstname ASC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function employee_counts()
{
    $stmt = db()->query('SELECT who_is, COUNT(*) AS total FROM tblemployees GROUP BY who_is');
    $counts = [
        'total' => 0,
        '0' => 0,
        '1' => 0,
        '2' => 0,
        '3' => 0,
    ];

    foreach ($stmt->fetchAll() as $row) {
        $code = (string) $row['who_is'];
        $total = (int) $row['total'];
        $counts[$code] = $total;
        $counts['total'] += $total;
    }

    return $counts;
}

function find_employee_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tblemployees WHERE employee_id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function validate_employee_data($data)
{
    $errors = [];
    $firstname = strtoupper(clean_text($data['firstname'] ?? ''));
    $lastname = strtoupper(clean_text($data['lastname'] ?? ''));
    $whoIs = (string) ($data['who_is'] ?? '');

    if ($firstname === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastname === '') {
        $errors[] = 'Last name is required.';
    }

    if (!array_key_exists($whoIs, employee_types())) {
        $errors[] = 'Employee classification is invalid.';
    }

    return $errors;
}

function create_employee($data)
{
    $stmt = db()->prepare('
        INSERT INTO tblemployees (firstname, middlename, lastname, who_is)
        VALUES (:firstname, :middlename, :lastname, :who_is)
    ');

    $stmt->execute([
        'firstname' => strtoupper(clean_text($data['firstname'])),
        'middlename' => strtoupper(clean_text($data['middlename'] ?? '')),
        'lastname' => strtoupper(clean_text($data['lastname'])),
        'who_is' => (string) $data['who_is'],
    ]);

    return (int) db()->lastInsertId();
}

function update_employee($id, $data)
{
    $stmt = db()->prepare('
        UPDATE tblemployees
        SET firstname = :firstname,
            middlename = :middlename,
            lastname = :lastname,
            who_is = :who_is
        WHERE employee_id = :id
    ');

    $stmt->execute([
        'firstname' => strtoupper(clean_text($data['firstname'])),
        'middlename' => strtoupper(clean_text($data['middlename'] ?? '')),
        'lastname' => strtoupper(clean_text($data['lastname'])),
        'who_is' => (string) $data['who_is'],
        'id' => (int) $id,
    ]);
}

function delete_employee($id)
{
    $stmt = db()->prepare('DELETE FROM tblemployees WHERE employee_id = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function setup_status_label($status)
{
    return (int) $status === 1 ? 'Active' : 'Inactive';
}

function list_locations($search = '', $status = '')
{
    $sql = 'SELECT * FROM tbllocation';
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'location LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    if ($status !== '' && in_array($status, ['0', '1'], true)) {
        $where[] = 'status = :status';
        $params['status'] = (int) $status;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY location ASC LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function location_counts()
{
    $row = db()->query('
        SELECT
            COUNT(*) AS total_locations,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_locations,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS inactive_locations
        FROM tbllocation
    ')->fetch();

    return [
        'total' => (int) ($row['total_locations'] ?? 0),
        'active' => (int) ($row['active_locations'] ?? 0),
        'inactive' => (int) ($row['inactive_locations'] ?? 0),
    ];
}

function find_location_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tbllocation WHERE locationid = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function validate_location_data($data)
{
    $errors = [];
    $location = clean_text($data['location'] ?? '');
    $status = (string) ($data['status'] ?? '1');

    if ($location === '') {
        $errors[] = 'Location is required.';
    }

    if (!in_array($status, ['0', '1'], true)) {
        $errors[] = 'Location status is invalid.';
    }

    return $errors;
}

function create_location($data)
{
    $stmt = db()->prepare('INSERT INTO tbllocation (location, status) VALUES (:location, :status)');
    $stmt->execute([
        'location' => ucwords(clean_text($data['location'])),
        'status' => (int) $data['status'],
    ]);

    return (int) db()->lastInsertId();
}

function update_location($id, $data)
{
    $stmt = db()->prepare('UPDATE tbllocation SET location = :location, status = :status WHERE locationid = :id');
    $stmt->execute([
        'location' => ucwords(clean_text($data['location'])),
        'status' => (int) $data['status'],
        'id' => (int) $id,
    ]);
}

function delete_location($id)
{
    $usage = location_usage_count($id);

    if ($usage > 0) {
        throw new RuntimeException('Location is used by ' . $usage . ' setup or route record' . ($usage === 1 ? '' : 's') . '. Remove or reassign those records before deleting it.');
    }

    $stmt = db()->prepare('DELETE FROM tbllocation WHERE locationid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function location_usage_count($id)
{
    $location = find_location_by_id($id);

    if (!$location) {
        return 0;
    }

    $total = 0;

    $stmt = db()->prepare('SELECT COUNT(*) FROM tblcustomerinformation WHERE origin = :location OR destination = :location');
    $stmt->execute(['location' => $location['location']]);
    $total += (int) $stmt->fetchColumn();

    $references = [
        ['tbltripdrops_perdrops', 'perdrops_locationid'],
        ['tbltripdrops_perkilo', 'perkilo_locationid'],
        ['tblmultiple_pickup', 'mpu_locationid'],
    ];

    foreach ($references as $reference) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM ' . $reference[0] . ' WHERE ' . $reference[1] . ' = :id');
        $stmt->execute(['id' => (int) $id]);
        $total += (int) $stmt->fetchColumn();
    }

    return $total;
}

function list_delivery_types()
{
    return db()->query('SELECT * FROM tbldeliverytype ORDER BY deliverytype ASC')->fetchAll();
}

function list_truck_types()
{
    return db()->query('SELECT * FROM tbltrucktype ORDER BY trucktype ASC')->fetchAll();
}

function find_delivery_type_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tbldeliverytype WHERE deliverytypeid = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function find_truck_type_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tbltrucktype WHERE trucktypeid = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function save_delivery_type($name, $id = null)
{
    $name = strtoupper(clean_text($name));

    if ($name === '') {
        throw new InvalidArgumentException('Delivery type is required.');
    }

    if ($id === null) {
        $stmt = db()->prepare('INSERT INTO tbldeliverytype (deliverytype) VALUES (:name)');
        $stmt->execute(['name' => $name]);

        return (int) db()->lastInsertId();
    }

    $stmt = db()->prepare('UPDATE tbldeliverytype SET deliverytype = :name WHERE deliverytypeid = :id');
    $stmt->execute(['name' => $name, 'id' => (int) $id]);

    return (int) $id;
}

function save_truck_type($name, $id = null)
{
    $name = strtoupper(clean_text($name));

    if ($name === '') {
        throw new InvalidArgumentException('Truck type is required.');
    }

    if ($id === null) {
        $stmt = db()->prepare('INSERT INTO tbltrucktype (trucktype) VALUES (:name)');
        $stmt->execute(['name' => $name]);

        return (int) db()->lastInsertId();
    }

    $stmt = db()->prepare('UPDATE tbltrucktype SET trucktype = :name WHERE trucktypeid = :id');
    $stmt->execute(['name' => $name, 'id' => (int) $id]);

    return (int) $id;
}

function delete_delivery_type($id)
{
    $usage = delivery_type_usage_count($id);

    if ($usage > 0) {
        throw new RuntimeException('Delivery type is used by ' . $usage . ' customer route/rate record' . ($usage === 1 ? '' : 's') . '. Reassign those routes before deleting it.');
    }

    $stmt = db()->prepare('DELETE FROM tbldeliverytype WHERE deliverytypeid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function delete_truck_type($id)
{
    $usage = truck_type_usage_count($id);

    if ($usage > 0) {
        throw new RuntimeException('Truck type is used by ' . $usage . ' customer route/rate record' . ($usage === 1 ? '' : 's') . '. Reassign those routes before deleting it.');
    }

    $stmt = db()->prepare('DELETE FROM tbltrucktype WHERE trucktypeid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function delivery_type_usage_count($id)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM tblcustomerinformation WHERE deliverytype = :id');
    $stmt->execute(['id' => (int) $id]);

    return (int) $stmt->fetchColumn();
}

function truck_type_usage_count($id)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM tblcustomerinformation WHERE trucktype = :id');
    $stmt->execute(['id' => (int) $id]);

    return (int) $stmt->fetchColumn();
}

function list_customer_routes($search = '', $customerId = '')
{
    $sql = '
        SELECT
            ci.*,
            c.soa,
            c.customername,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name
        FROM tblcustomerinformation ci
        LEFT JOIN tblcustomer c ON c.customerid = ci.customerid
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
    ';
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(c.customername LIKE :search_customer OR c.soa LIKE :search_soa OR ci.origin LIKE :search_origin OR ci.destination LIKE :search_destination)';
        $term = '%' . $search . '%';
        $params['search_customer'] = $term;
        $params['search_soa'] = $term;
        $params['search_origin'] = $term;
        $params['search_destination'] = $term;
    }

    if ($customerId !== '' && ctype_digit((string) $customerId)) {
        $where[] = 'ci.customerid = :customer_id';
        $params['customer_id'] = (int) $customerId;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY c.customername ASC, ci.origin ASC, ci.destination ASC LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function list_customer_routes_for_customer($customerId)
{
    $stmt = db()->prepare('
        SELECT
            ci.*,
            dt.deliverytype AS deliverytype_name,
            tt.trucktype AS trucktype_name
        FROM tblcustomerinformation ci
        LEFT JOIN tbldeliverytype dt ON dt.deliverytypeid = ci.deliverytype
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = ci.trucktype
        WHERE ci.customerid = :customer_id
        ORDER BY ci.destination ASC, ci.origin ASC, ci.customerinformationid ASC
    ');
    $stmt->execute(['customer_id' => (int) $customerId]);

    return $stmt->fetchAll();
}

function customer_route_counts($customerId)
{
    $stmt = db()->prepare('
        SELECT
            COUNT(*) AS total_routes,
            COUNT(DISTINCT origin) AS total_origins,
            COUNT(DISTINCT destination) AS total_destinations
        FROM tblcustomerinformation
        WHERE customerid = :customer_id
    ');
    $stmt->execute(['customer_id' => (int) $customerId]);
    $row = $stmt->fetch();

    return [
        'total' => (int) ($row['total_routes'] ?? 0),
        'origins' => (int) ($row['total_origins'] ?? 0),
        'destinations' => (int) ($row['total_destinations'] ?? 0),
    ];
}

function route_counts()
{
    $row = db()->query('
        SELECT
            COUNT(*) AS total_routes,
            SUM(CASE WHEN c.customerid IS NULL THEN 1 ELSE 0 END) AS unmatched_routes
        FROM tblcustomerinformation ci
        LEFT JOIN tblcustomer c ON c.customerid = ci.customerid
    ')->fetch();

    return [
        'total' => (int) ($row['total_routes'] ?? 0),
        'unmatched' => (int) ($row['unmatched_routes'] ?? 0),
        'delivery_types' => (int) db()->query('SELECT COUNT(*) FROM tbldeliverytype')->fetchColumn(),
        'truck_types' => (int) db()->query('SELECT COUNT(*) FROM tbltrucktype')->fetchColumn(),
    ];
}

function find_customer_route_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tblcustomerinformation WHERE customerinformationid = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function validate_customer_route_data($data)
{
    $errors = [];

    if (!find_customer_by_id((int) ($data['customerid'] ?? 0))) {
        $errors[] = 'Select a valid customer.';
    }

    if (clean_text($data['origin'] ?? '') === '') {
        $errors[] = 'Origin is required.';
    }

    if (clean_text($data['destination'] ?? '') === '') {
        $errors[] = 'Destination is required.';
    }

    if (!find_delivery_type_by_id((int) ($data['deliverytype'] ?? 0))) {
        $errors[] = 'Select a valid delivery type.';
    }

    if (!find_truck_type_by_id((int) ($data['trucktype'] ?? 0))) {
        $errors[] = 'Select a valid truck type.';
    }

    foreach (['driversrate', 'helpersrate', 'deliveryrate'] as $field) {
        if (!is_numeric($data[$field] ?? null) || (float) $data[$field] < 0) {
            $errors[] = ucfirst(str_replace('rate', ' rate', $field)) . ' must be zero or greater.';
        }
    }

    return $errors;
}

function save_customer_route($data, $id = null)
{
    $params = [
        'customerid' => (int) $data['customerid'],
        'origin' => strtoupper(clean_text($data['origin'])),
        'destination' => strtoupper(clean_text($data['destination'])),
        'driversrate' => (float) $data['driversrate'],
        'helpersrate' => (float) $data['helpersrate'],
        'deliveryrate' => (float) $data['deliveryrate'],
        'deliverytype' => (int) $data['deliverytype'],
        'trucktype' => (int) $data['trucktype'],
    ];

    if ($id === null) {
        $stmt = db()->prepare('
            INSERT INTO tblcustomerinformation
                (customerid, origin, destination, driversrate, helpersrate, deliveryrate, deliverytype, trucktype)
            VALUES
                (:customerid, :origin, :destination, :driversrate, :helpersrate, :deliveryrate, :deliverytype, :trucktype)
        ');
        $stmt->execute($params);

        return (int) db()->lastInsertId();
    }

    $params['id'] = (int) $id;
    $stmt = db()->prepare('
        UPDATE tblcustomerinformation
        SET customerid = :customerid,
            origin = :origin,
            destination = :destination,
            driversrate = :driversrate,
            helpersrate = :helpersrate,
            deliveryrate = :deliveryrate,
            deliverytype = :deliverytype,
            trucktype = :trucktype
        WHERE customerinformationid = :id
    ');
    $stmt->execute($params);

    return (int) $id;
}

function delete_customer_route($id)
{
    $usage = customer_route_usage_count($id);

    if ($usage > 0) {
        throw new RuntimeException('Route is used by ' . $usage . ' trip setup record' . ($usage === 1 ? '' : 's') . '. Remove or reassign those trips before deleting it.');
    }

    $stmt = db()->prepare('DELETE FROM tblcustomerinformation WHERE customerinformationid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function customer_route_usage_count($id)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM tbladditional_trips WHERE add_trip_customer_od = :id');
    $stmt->execute(['id' => (int) $id]);

    return (int) $stmt->fetchColumn();
}

function fleet_nullable_date($value)
{
    $value = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function fleet_nullable_int($value)
{
    $value = trim((string) $value);

    if ($value === '' || $value === '0') {
        return null;
    }

    return (int) $value;
}

function fleet_setup_rows($table)
{
    $allowed = [
        'tblmake' => 'makename',
        'tblvantype' => 'vantype',
        'tblbody' => 'body_name',
        'tblcolor' => 'color_name',
        'tblcolor_plate' => 'color_plate_desc',
        'tblltfrb_status' => 'ltfrb_status',
        'tbltrucktype' => 'trucktype',
    ];

    if (!isset($allowed[$table])) {
        return [];
    }

    return db()->query('SELECT * FROM ' . $table . ' ORDER BY ' . $allowed[$table] . ' ASC')->fetchAll();
}

function fleet_counts()
{
    $row = db()->query('
        SELECT
            COUNT(*) AS total_fleet,
            SUM(CASE WHEN validity IS NOT NULL AND validity < CURRENT_DATE THEN 1 ELSE 0 END) AS expired_lto,
            SUM(CASE WHEN UPPER(COALESCE(paremarks, "")) LIKE "%TEMPORARY%" THEN 1 ELSE 0 END) AS temporary_plates
        FROM tblfleet
    ')->fetch();

    return [
        'total' => (int) ($row['total_fleet'] ?? 0),
        'expired' => (int) ($row['expired_lto'] ?? 0),
        'temporary' => (int) ($row['temporary_plates'] ?? 0),
        'profiled' => (int) db()->query('SELECT COUNT(*) FROM tblfleet_info_1')->fetchColumn(),
    ];
}

function list_fleets()
{
    return db()->query('
        SELECT
            f.*,
            fi.fleet_info_1_id,
            tt.trucktype AS trucktype_name,
            mk.makename,
            vt.vantype,
            bd.body_name,
            cp.color_plate_desc,
            ls.ltfrb_status
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = fi.trucktype
        LEFT JOIN tblmake mk ON mk.makeid = fi.make
        LEFT JOIN tblvantype vt ON vt.vantypeid = fi.vantype
        LEFT JOIN tblbody bd ON bd.body_id = fi.body
        LEFT JOIN tblcolor_plate cp ON cp.color_plate_id = fi.platecolor
        LEFT JOIN tblltfrb_status ls ON ls.ltfrb_status_id = fi.ltfrbstatus
        ORDER BY f.platenumber ASC
        LIMIT 1000
    ')->fetchAll();
}

function find_fleet_by_id($id)
{
    $stmt = db()->prepare('
        SELECT
            f.*,
            fi.*,
            fi.vantype AS vantype_id,
            tt.trucktype AS trucktype_name,
            mk.makename,
            vt.vantype AS vantype_name,
            bd.body_name,
            cl.color_name,
            cp.color_plate_desc,
            ls.ltfrb_status
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        LEFT JOIN tbltrucktype tt ON tt.trucktypeid = fi.trucktype
        LEFT JOIN tblmake mk ON mk.makeid = fi.make
        LEFT JOIN tblvantype vt ON vt.vantypeid = fi.vantype
        LEFT JOIN tblbody bd ON bd.body_id = fi.body
        LEFT JOIN tblcolor cl ON cl.color_id = fi.color
        LEFT JOIN tblcolor_plate cp ON cp.color_plate_id = fi.platecolor
        LEFT JOIN tblltfrb_status ls ON ls.ltfrb_status_id = fi.ltfrbstatus
        WHERE f.fleetid = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function fleet_plate_exists($plateNumber, $excludeId = null)
{
    $sql = 'SELECT fleetid FROM tblfleet WHERE platenumber = :platenumber';
    $params = ['platenumber' => strtoupper(clean_text($plateNumber))];

    if ($excludeId !== null) {
        $sql .= ' AND fleetid <> :id';
        $params['id'] = (int) $excludeId;
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function validate_fleet_data($data, $excludeId = null)
{
    $errors = [];
    $plateNumber = strtoupper(clean_text($data['platenumber'] ?? ''));

    if ($plateNumber === '') {
        $errors[] = 'Plate number is required.';
    } elseif (strlen($plateNumber) > 20) {
        $errors[] = 'Plate number must be 20 characters or fewer.';
    } elseif (fleet_plate_exists($plateNumber, $excludeId)) {
        $errors[] = 'Plate number already exists.';
    }

    foreach (['validity', 'pavalidity', 'decisionvalidity'] as $field) {
        $value = trim((string) ($data[$field] ?? ''));

        if ($value !== '' && fleet_nullable_date($value) === null) {
            $errors[] = ucfirst($field) . ' must be a valid date.';
        }
    }

    return $errors;
}

function save_fleet($data, $id = null)
{
    $params = [
        'platenumber' => strtoupper(clean_text($data['platenumber'] ?? '')),
        'casenumber' => strtoupper(clean_text($data['casenumber'] ?? '')) ?: null,
        'validity' => fleet_nullable_date($data['validity'] ?? ''),
        'paremarks' => strtoupper(clean_text($data['paremarks'] ?? '')) ?: null,
        'pavalidity' => fleet_nullable_date($data['pavalidity'] ?? ''),
        'decisionremarks' => strtoupper(clean_text($data['decisionremarks'] ?? '')) ?: null,
        'decisionvalidity' => fleet_nullable_date($data['decisionvalidity'] ?? ''),
    ];

    if ($id === null) {
        $stmt = db()->prepare('
            INSERT INTO tblfleet (platenumber, casenumber, validity, paremarks, pavalidity, decisionremarks, decisionvalidity)
            VALUES (:platenumber, :casenumber, :validity, :paremarks, :pavalidity, :decisionremarks, :decisionvalidity)
        ');
        $stmt->execute($params);

        return (int) db()->lastInsertId();
    }

    $params['id'] = (int) $id;
    $stmt = db()->prepare('
        UPDATE tblfleet
        SET platenumber = :platenumber,
            casenumber = :casenumber,
            validity = :validity,
            paremarks = :paremarks,
            pavalidity = :pavalidity,
            decisionremarks = :decisionremarks,
            decisionvalidity = :decisionvalidity
        WHERE fleetid = :id
    ');
    $stmt->execute($params);

    return (int) $id;
}

function delete_fleet($id)
{
    $stmt = db()->prepare('DELETE FROM tblfleet WHERE fleetid = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function validate_fleet_profile_data($data)
{
    $errors = [];
    $lookupChecks = [
        'platecolor' => ['tblcolor_plate', 'color_plate_id', 'plate color'],
        'ltfrbstatus' => ['tblltfrb_status', 'ltfrb_status_id', 'LTFRB status'],
        'trucktype' => ['tbltrucktype', 'trucktypeid', 'truck type'],
        'vantype' => ['tblvantype', 'vantypeid', 'van type'],
        'make' => ['tblmake', 'makeid', 'make'],
        'body' => ['tblbody', 'body_id', 'body'],
        'color' => ['tblcolor', 'color_id', 'color'],
    ];

    foreach (['cpcvalidity'] as $field) {
        $value = trim((string) ($data[$field] ?? ''));

        if ($value !== '' && fleet_nullable_date($value) === null) {
            $errors[] = 'CPC validity must be a valid date.';
        }
    }

    foreach ($lookupChecks as $field => $meta) {
        $id = fleet_nullable_int($data[$field] ?? '');

        if ($id === null) {
            continue;
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM ' . $meta[0] . ' WHERE ' . $meta[1] . ' = :id');
        $stmt->execute(['id' => $id]);

        if ((int) $stmt->fetchColumn() === 0) {
            $errors[] = 'Select a valid ' . $meta[2] . '.';
        }
    }

    return $errors;
}

function save_fleet_profile($fleetId, $data)
{
    $params = [
        'fleetid' => (int) $fleetId,
        'cpc' => strtoupper(clean_text($data['cpc'] ?? '')) ?: null,
        'cpcvalidity' => fleet_nullable_date($data['cpcvalidity'] ?? ''),
        'platecolor' => fleet_nullable_int($data['platecolor'] ?? ''),
        'ltfrbstatus' => fleet_nullable_int($data['ltfrbstatus'] ?? ''),
        'trucktype' => fleet_nullable_int($data['trucktype'] ?? ''),
        'vantype' => fleet_nullable_int($data['vantype'] ?? ''),
        'make' => fleet_nullable_int($data['make'] ?? ''),
        'body' => fleet_nullable_int($data['body'] ?? ''),
        'color' => fleet_nullable_int($data['color'] ?? ''),
        'yearmodel' => clean_text($data['yearmodel'] ?? '') ?: null,
        'yearacquired' => clean_text($data['yearacquired'] ?? '') ?: null,
        'chassisnumber' => strtoupper(clean_text($data['chassisnumber'] ?? '')) ?: null,
        'enginenumber' => strtoupper(clean_text($data['enginenumber'] ?? '')) ?: null,
    ];

    $stmt = db()->prepare('
        INSERT INTO tblfleet_info_1
            (fleetid, cpc, cpcvalidity, platecolor, ltfrbstatus, trucktype, vantype, make, body, color, yearmodel, yearacquired, chassisnumber, enginenumber)
        VALUES
            (:fleetid, :cpc, :cpcvalidity, :platecolor, :ltfrbstatus, :trucktype, :vantype, :make, :body, :color, :yearmodel, :yearacquired, :chassisnumber, :enginenumber)
        ON DUPLICATE KEY UPDATE
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
    $stmt->execute($params);
}

function list_available_fleet_people($type)
{
    $stmt = db()->prepare('
        SELECT *
        FROM tblemployees
        WHERE who_is = :type
        ORDER BY lastname ASC, firstname ASC
    ');
    $stmt->execute(['type' => (string) $type]);

    return $stmt->fetchAll();
}

function list_fleet_assignments($fleetId)
{
    $stmt = db()->prepare('
        SELECT
            a.*,
            e.firstname,
            e.middlename,
            e.lastname,
            e.who_is
        FROM tblfleet_assigned_driver_helper a
        INNER JOIN tblemployees e ON e.employee_id = a.assigned_employeeid
        WHERE a.assigned_fleetid = :fleetid
        ORDER BY e.who_is ASC, e.lastname ASC, e.firstname ASC
    ');
    $stmt->execute(['fleetid' => (int) $fleetId]);

    return $stmt->fetchAll();
}

function find_fleet_assignment_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM tblfleet_assigned_driver_helper WHERE assigned_id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->fetch() ?: null;
}

function assign_fleet_person($fleetId, $employeeId)
{
    if (!find_fleet_by_id($fleetId)) {
        throw new InvalidArgumentException('Fleet record was not found.');
    }

    if (!find_employee_by_id($employeeId)) {
        throw new InvalidArgumentException('Employee record was not found.');
    }

    $stmt = db()->prepare('
        INSERT IGNORE INTO tblfleet_assigned_driver_helper (assigned_fleetid, assigned_employeeid)
        VALUES (:fleetid, :employeeid)
    ');
    $stmt->execute([
        'fleetid' => (int) $fleetId,
        'employeeid' => (int) $employeeId,
    ]);

    return $stmt->rowCount() > 0;
}

function remove_fleet_assignment($id)
{
    $stmt = db()->prepare('DELETE FROM tblfleet_assigned_driver_helper WHERE assigned_id = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}
