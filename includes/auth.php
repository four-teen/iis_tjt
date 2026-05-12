<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/accounts.php';

function find_user_by_username($username)
{
    return find_account_by_username($username);
}

function verify_account_credentials($username, $password)
{
    $user = find_user_by_username(trim($username));

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    return $user;
}

function complete_login($user, $role)
{
    $roles = normalize_account_roles($user['roles'] ?? ($user['role'] ?? []));
    $role = trim((string) $role);

    if (!in_array($role, $roles, true)) {
        return false;
    }

    session_regenerate_id(true);
    update_account_last_login($user['id']);
    log_account_activity($user['id'], $user['id'], 'login', 'Account signed in as ' . $role . '.');

    unset($_SESSION['pending_login_account_id']);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $role,
        'roles' => $roles,
    ];

    return true;
}

function attempt_login($username, $password, $role = null)
{
    $user = verify_account_credentials($username, $password);

    if (!$user) {
        return false;
    }

    $roles = normalize_account_roles($user['roles'] ?? ($user['role'] ?? []));
    $selectedRole = $role ?: account_primary_role($roles);

    return complete_login($user, $selectedRole);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in()
{
    return current_user() !== null;
}

function require_login()
{
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function require_role($role)
{
    require_login();

    $user = current_user();

    if (($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function require_any_role($roles)
{
    require_login();

    $roles = is_array($roles) ? $roles : [$roles];
    $user = current_user();

    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function user_has_role($role)
{
    $user = current_user();

    return in_array($role, $user['roles'] ?? [], true);
}

function user_has_any_role($roles)
{
    $roles = is_array($roles) ? $roles : [$roles];

    foreach ($roles as $role) {
        if (user_has_role($role)) {
            return true;
        }
    }

    return false;
}

function role_home_path($role)
{
    if ($role === 'Customer Service') {
        return 'administrator/customer_service.php';
    }

    if ($role === 'Coordinator') {
        return 'administrator/coordinator.php';
    }

    return 'administrator/index.php';
}

function redirect_if_logged_in()
{
    if (is_logged_in()) {
        redirect_to(role_home_path(current_user()['role'] ?? 'Administrator'));
    }
}

function logout_user()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
