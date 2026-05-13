<?php

require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

if (isset($_GET['reset_login'])) {
    unset($_SESSION['pending_login_account_id']);
}

$error = '';
$username = '';
$roleChoices = [];
$pendingUser = null;

if (!empty($_SESSION['pending_login_account_id'])) {
    $pendingUser = find_account_by_id((int) $_SESSION['pending_login_account_id']);

    if ($pendingUser && $pendingUser['status'] === 'active') {
        $roleChoices = normalize_account_roles($pendingUser['roles'] ?? ($pendingUser['role'] ?? []));
        $username = $pendingUser['username'];
    } else {
        unset($_SESSION['pending_login_account_id']);
        $pendingUser = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($action === 'select_role') {
        $selectedRole = $_POST['role'] ?? '';

        if (!$pendingUser) {
            $error = 'Your login request expired. Sign in again.';
            $roleChoices = [];
        } elseif (complete_login($pendingUser, $selectedRole)) {
            redirect_to(role_home_path($selectedRole));
        } else {
            $error = 'Select a valid role for this account.';
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Enter your username and password.';
            $roleChoices = [];
        } else {
            $user = verify_account_credentials($username, $password);

            if (!$user) {
                $error = 'Invalid username or password.';
                $roleChoices = [];
                unset($_SESSION['pending_login_account_id']);
            } else {
                $roles = normalize_account_roles($user['roles'] ?? ($user['role'] ?? []));

                if (count($roles) > 1) {
                    $_SESSION['pending_login_account_id'] = (int) $user['id'];
                    $pendingUser = $user;
                    $roleChoices = $roles;
                } else {
                    $selectedRole = $roles[0] ?? account_primary_role($user['role'] ?? 'Administrator');

                    if (complete_login($user, $selectedRole)) {
                        redirect_to(role_home_path($selectedRole));
                    }

                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?php echo h($app['name']); ?></title>
    <link rel="icon" href="<?php echo h(app_url('assets/img/favicon.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo h(app_url('assets/img/apple-touch-icon.png')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/app.css')); ?>">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-panel" aria-label="Sign in">
            <div class="login-brand">
                <img class="login-brand-logo" src="<?php echo h(app_url('assets/img/logo.png')); ?>" alt="TJT Trucking">
                <span>TJT Trucking</span>
            </div>
            <h1>System Login</h1>
            <p class="login-subtitle">Sign in to continue to the system workspace.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?php echo h($error); ?></div>
            <?php endif; ?>

            <?php if (count($roleChoices) > 1): ?>
                <form method="post" action="<?php echo h(app_url('login.php')); ?>" class="login-form" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="select_role">

                    <label for="role">Open As</label>
                    <select id="role" name="role" autofocus>
                        <?php foreach ($roleChoices as $role): ?>
                            <option value="<?php echo h($role); ?>"><?php echo h($role); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Open Workspace</button>
                    <a class="btn btn-light" href="<?php echo h(app_url('login.php?reset_login=1')); ?>">Use Different Account</a>
                </form>
            <?php else: ?>
                <form method="post" action="<?php echo h(app_url('login.php')); ?>" class="login-form" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="login">

                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?php echo h($username); ?>" autocomplete="username" autofocus>

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password">

                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
            <?php endif; ?>

            <p class="demo-note">Default account is configured in your local .env file.</p>
        </section>
    </main>
</body>
</html>
