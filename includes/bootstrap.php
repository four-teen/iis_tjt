<?php

define('APP_ROOT', dirname(__DIR__));

function load_env_file($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_value($key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

load_env_file(APP_ROOT . '/.env');

$app = require APP_ROOT . '/config/app.php';

date_default_timezone_set($app['timezone'] ?? 'Asia/Manila');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionPath = APP_ROOT . '/storage/sessions';

    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }

    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    session_start();
}

function app_starts_with($haystack, $needle)
{
    return $needle === '' || strpos($haystack, $needle) === 0;
}

function app_base_url()
{
    global $app;

    if (!empty($app['base_url'])) {
        return rtrim($app['base_url'], '/');
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? APP_ROOT);
    $appRoot = realpath(APP_ROOT);

    if ($documentRoot && $appRoot) {
        $documentRoot = str_replace('\\', '/', $documentRoot);
        $appRoot = str_replace('\\', '/', $appRoot);

        if (app_starts_with($appRoot, $documentRoot)) {
            return rtrim(str_replace('\\', '/', substr($appRoot, strlen($documentRoot))), '/');
        }
    }

    return '';
}

function app_url($path = '')
{
    $base = app_base_url();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function redirect_to($path)
{
    header('Location: ' . app_url($path));
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function secure_token($length = 32)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }

    return sha1(uniqid((string) mt_rand(), true));
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = secure_token();
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function token_equals($known, $given)
{
    if (function_exists('hash_equals')) {
        return hash_equals((string) $known, (string) $given);
    }

    return (string) $known === (string) $given;
}

function verify_csrf($token)
{
    return isset($_SESSION['csrf_token']) && token_equals($_SESSION['csrf_token'], $token);
}

function flash($type, $message)
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_messages()
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}
