<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/accounts.php';

function find_user_by_username($username)
{
    return find_account_by_username($username);
}

function attempt_login($username, $password)
{
    $user = find_user_by_username(trim($username));

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    update_account_last_login($user['id']);
    log_account_activity($user['id'], $user['id'], 'login', 'Account signed in.');

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];

    return true;
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

function redirect_if_logged_in()
{
    if (is_logged_in()) {
        redirect_to('administrator/index.php');
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
