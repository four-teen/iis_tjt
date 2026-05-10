<?php

require_once __DIR__ . '/database.php';

function account_roles()
{
    return [
        'Administrator',
        'Manager',
        'Finance',
        'Operations',
        'Human Resource',
        'Billing',
        'Budget',
        'Coordinator',
        'Customer Service',
        'Fleet Management',
        'Inventory',
    ];
}

function account_statuses()
{
    return ['active', 'inactive'];
}

function ensure_account_role_assignments_schema()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS account_role_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_account_role_assignments_account_role (account_id, role),
            KEY idx_account_role_assignments_role (role),
            CONSTRAINT fk_account_role_assignments_account
                FOREIGN KEY (account_id) REFERENCES accounts (id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->exec("
        INSERT IGNORE INTO account_role_assignments (account_id, role)
        SELECT id, role
        FROM accounts
        WHERE role IS NOT NULL AND role <> ''
    ");

    $ensured = true;
}

function normalize_username($username)
{
    return strtolower(trim((string) $username));
}

function normalize_account_roles($roles)
{
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    $allowed = account_roles();
    $normalized = [];

    foreach ($roles as $role) {
        $role = trim((string) $role);

        if ($role !== '' && in_array($role, $allowed, true) && !in_array($role, $normalized, true)) {
            $normalized[] = $role;
        }
    }

    return $normalized;
}

function account_primary_role($roles)
{
    $roles = normalize_account_roles($roles);

    return $roles[0] ?? 'Administrator';
}

function parse_roles_csv($rolesCsv)
{
    if (!is_string($rolesCsv) || trim($rolesCsv) === '') {
        return [];
    }

    return normalize_account_roles(explode('||', $rolesCsv));
}

function account_roles_by_id($accountId)
{
    ensure_account_role_assignments_schema();

    $stmt = db()->prepare('
        SELECT role
        FROM account_role_assignments
        WHERE account_id = :account_id
        ORDER BY role ASC
    ');
    $stmt->execute(['account_id' => (int) $accountId]);

    return normalize_account_roles(array_column($stmt->fetchAll(), 'role'));
}

function hydrate_account_roles($account)
{
    if (!$account) {
        return null;
    }

    $roles = parse_roles_csv($account['roles_csv'] ?? '');

    if (!$roles && isset($account['id'])) {
        $roles = account_roles_by_id($account['id']);
    }

    if (!$roles && !empty($account['role'])) {
        $roles = normalize_account_roles($account['role']);
    }

    $account['roles'] = $roles;
    $account['role'] = account_primary_role($roles ?: ($account['role'] ?? 'Administrator'));

    return $account;
}

function find_account_by_username($username)
{
    ensure_account_role_assignments_schema();

    $stmt = db()->prepare('SELECT * FROM accounts WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => normalize_username($username)]);

    return hydrate_account_roles($stmt->fetch() ?: null);
}

function find_account_by_id($id)
{
    ensure_account_role_assignments_schema();

    $stmt = db()->prepare('SELECT * FROM accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $id]);

    return hydrate_account_roles($stmt->fetch() ?: null);
}

function list_accounts($search = '', $role = '', $status = '')
{
    ensure_account_role_assignments_schema();

    $sql = "
        SELECT accounts.*, role_summary.roles_csv
        FROM accounts
        LEFT JOIN (
            SELECT account_id, GROUP_CONCAT(role ORDER BY role ASC SEPARATOR '||') AS roles_csv
            FROM account_role_assignments
            GROUP BY account_id
        ) role_summary ON role_summary.account_id = accounts.id
    ";
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(full_name LIKE :search_name OR username LIKE :search_username OR email LIKE :search_email)';
        $searchTerm = '%' . trim($search) . '%';
        $params['search_name'] = $searchTerm;
        $params['search_username'] = $searchTerm;
        $params['search_email'] = $searchTerm;
    }

    if ($role !== '' && in_array($role, account_roles(), true)) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM account_role_assignments role_filter
            WHERE role_filter.account_id = accounts.id
                AND role_filter.role = :role
        )';
        $params['role'] = $role;
    }

    if ($status !== '' && in_array($status, account_statuses(), true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY accounts.role ASC, accounts.full_name ASC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map('hydrate_account_roles', $stmt->fetchAll());
}

function account_counts()
{
    $stmt = db()->query("
        SELECT
            COUNT(*) AS total_accounts,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_accounts,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_accounts
        FROM accounts
    ");

    $counts = $stmt->fetch();

    return [
        'total' => (int) ($counts['total_accounts'] ?? 0),
        'active' => (int) ($counts['active_accounts'] ?? 0),
        'inactive' => (int) ($counts['inactive_accounts'] ?? 0),
    ];
}

function username_exists($username, $excludeId = null)
{
    $sql = 'SELECT id FROM accounts WHERE username = :username';
    $params = ['username' => normalize_username($username)];

    if ($excludeId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = (int) $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function email_exists($email, $excludeId = null)
{
    $email = trim((string) $email);

    if ($email === '') {
        return false;
    }

    $sql = 'SELECT id FROM accounts WHERE email = :email';
    $params = ['email' => $email];

    if ($excludeId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = (int) $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function validate_account_data($data, $mode = 'create', $excludeId = null)
{
    $errors = [];
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $username = normalize_username($data['username'] ?? '');
    $email = trim((string) ($data['email'] ?? ''));
    $rawRoles = $data['roles'] ?? ($data['role'] ?? []);
    $roleValues = is_array($rawRoles) ? $rawRoles : [$rawRoles];
    $selectedRoleCount = count(array_unique(array_filter(array_map('strval', $roleValues), 'strlen')));
    $roles = normalize_account_roles($roleValues);
    $status = trim((string) ($data['status'] ?? 'active'));
    $password = (string) ($data['password'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters using letters, numbers, dot, underscore, or dash.';
    } elseif (username_exists($username, $excludeId)) {
        $errors[] = 'Username is already used.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is invalid.';
    } elseif (email_exists($email, $excludeId)) {
        $errors[] = 'Email address is already used.';
    }

    if (!$roles) {
        $errors[] = 'Select at least one role.';
    } elseif ($selectedRoleCount !== count($roles)) {
        $errors[] = 'One or more selected roles are invalid.';
    }

    if (!in_array($status, account_statuses(), true)) {
        $errors[] = 'Selected status is invalid.';
    }

    if ($mode === 'create' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    return $errors;
}

function create_account($data, $createdBy = null)
{
    ensure_account_role_assignments_schema();

    $roles = normalize_account_roles($data['roles'] ?? ($data['role'] ?? []));
    $primaryRole = account_primary_role($roles);

    $stmt = db()->prepare('
        INSERT INTO accounts
            (full_name, username, email, role, status, password_hash, must_change_password, created_by, updated_by)
        VALUES
            (:full_name, :username, :email, :role, :status, :password_hash, :must_change_password, :created_by, :updated_by)
    ');

    $stmt->execute([
        'full_name' => trim($data['full_name']),
        'username' => normalize_username($data['username']),
        'email' => trim($data['email'] ?? '') ?: null,
        'role' => $primaryRole,
        'status' => trim($data['status'] ?? 'active'),
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
        'created_by' => $createdBy,
        'updated_by' => $createdBy,
    ]);

    $accountId = (int) db()->lastInsertId();
    sync_account_roles($accountId, $roles);
    log_account_activity($createdBy, $accountId, 'created', 'Account created.');

    return $accountId;
}

function update_account($id, $data, $updatedBy = null)
{
    ensure_account_role_assignments_schema();

    $roles = normalize_account_roles($data['roles'] ?? ($data['role'] ?? []));
    $primaryRole = account_primary_role($roles);

    $stmt = db()->prepare('
        UPDATE accounts
        SET full_name = :full_name,
            username = :username,
            email = :email,
            role = :role,
            status = :status,
            updated_by = :updated_by
        WHERE id = :id
    ');

    $stmt->execute([
        'full_name' => trim($data['full_name']),
        'username' => normalize_username($data['username']),
        'email' => trim($data['email'] ?? '') ?: null,
        'role' => $primaryRole,
        'status' => trim($data['status']),
        'updated_by' => $updatedBy,
        'id' => (int) $id,
    ]);

    sync_account_roles($id, $roles);
    log_account_activity($updatedBy, $id, 'updated', 'Account details updated.');
}

function sync_account_roles($accountId, $roles)
{
    ensure_account_role_assignments_schema();

    $roles = normalize_account_roles($roles);

    if (!$roles) {
        $roles = ['Administrator'];
    }

    $delete = db()->prepare('DELETE FROM account_role_assignments WHERE account_id = :account_id');
    $delete->execute(['account_id' => (int) $accountId]);

    $insert = db()->prepare('
        INSERT INTO account_role_assignments (account_id, role)
        VALUES (:account_id, :role)
    ');

    foreach ($roles as $role) {
        $insert->execute([
            'account_id' => (int) $accountId,
            'role' => $role,
        ]);
    }
}

function update_account_password($id, $password, $updatedBy = null)
{
    $stmt = db()->prepare('
        UPDATE accounts
        SET password_hash = :password_hash,
            must_change_password = 1,
            updated_by = :updated_by
        WHERE id = :id
    ');

    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated_by' => $updatedBy,
        'id' => (int) $id,
    ]);

    log_account_activity($updatedBy, $id, 'password_reset', 'Password reset by administrator.');
}

function update_account_status($id, $status, $updatedBy = null)
{
    if (!in_array($status, account_statuses(), true)) {
        throw new InvalidArgumentException('Invalid account status.');
    }

    $stmt = db()->prepare('UPDATE accounts SET status = :status, updated_by = :updated_by WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'updated_by' => $updatedBy,
        'id' => (int) $id,
    ]);

    log_account_activity($updatedBy, $id, 'status_changed', 'Account status changed to ' . $status . '.');
}

function delete_account($id, $deletedBy = null)
{
    $account = find_account_by_id($id);

    if (!$account) {
        return false;
    }

    log_account_activity($deletedBy, $id, 'deleted', 'Account deleted by administrator.');

    $stmt = db()->prepare('DELETE FROM accounts WHERE id = :id');
    $stmt->execute(['id' => (int) $id]);

    return $stmt->rowCount() > 0;
}

function update_account_last_login($id)
{
    $stmt = db()->prepare('UPDATE accounts SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute(['id' => (int) $id]);
}

function log_account_activity($actorId, $accountId, $action, $description)
{
    $stmt = db()->prepare('
        INSERT INTO account_activity_logs (actor_account_id, account_id, action, description, ip_address)
        VALUES (:actor_account_id, :account_id, :action, :description, :ip_address)
    ');

    $stmt->execute([
        'actor_account_id' => $actorId,
        'account_id' => $accountId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
