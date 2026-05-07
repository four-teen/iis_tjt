<?php

$user = current_user();
$pageTitle = $pageTitle ?? 'Administrator';
$activeNav = $activeNav ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle); ?> | <?php echo h($app['name']); ?></title>
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/app.css')); ?>">
</head>
<body class="admin-page">
    <div class="admin-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span class="brand-mark small">TJ</span>
                <span><?php echo h($app['name']); ?></span>
            </div>

            <nav class="sidebar-nav" aria-label="Administrator navigation">
                <a class="<?php echo $activeNav === 'dashboard' ? 'active' : ''; ?>" href="<?php echo h(app_url('administrator/index.php')); ?>">
                    <span class="nav-icon">D</span>
                    Dashboard
                </a>
                <a class="<?php echo $activeNav === 'accounts' ? 'active' : ''; ?>" href="<?php echo h(app_url('administrator/accounts.php')); ?>">
                    <span class="nav-icon">A</span>
                    Accounts
                </a>
                <a href="#">
                    <span class="nav-icon">R</span>
                    Reports
                </a>
                <a href="#">
                    <span class="nav-icon">S</span>
                    Settings
                </a>
            </nav>
        </aside>

        <div class="content-shell">
            <header class="topbar">
                <button type="button" class="icon-button" id="menuToggle" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div>
                    <p class="eyebrow">Administrator</p>
                    <h1><?php echo h($pageTitle); ?></h1>
                </div>

                <div class="user-menu">
                    <div class="avatar"><?php echo h(strtoupper(substr($user['name'] ?? 'A', 0, 1))); ?></div>
                    <div>
                        <strong><?php echo h($user['name'] ?? 'Administrator'); ?></strong>
                        <span><?php echo h($user['role'] ?? 'User'); ?></span>
                    </div>
                    <a class="btn btn-light" href="<?php echo h(app_url('logout.php')); ?>">Logout</a>
                </div>
            </header>

            <main class="page-content">
