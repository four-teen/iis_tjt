<?php

require_once __DIR__ . '/database.php';

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
    return trim(preg_replace('/\s+/', ' ', (string) $value));
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
