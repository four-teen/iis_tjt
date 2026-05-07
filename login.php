<?php

require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (attempt_login($username, $password)) {
        redirect_to('administrator/index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?php echo h($app['name']); ?></title>
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/app.css')); ?>">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-panel" aria-label="Sign in">
            <div class="brand-mark brand-mark-wide">TJT Trucking</div>
            <h1>Administrator Login</h1>
            <p class="login-subtitle">Sign in to continue to the admin workspace.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo h(app_url('login.php')); ?>" class="login-form" novalidate>
                <?php echo csrf_field(); ?>

                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="<?php echo h($username); ?>" autocomplete="username" autofocus>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password">

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>

            <p class="demo-note">Default account is configured in your local .env file.</p>
        </section>
    </main>
</body>
</html>
