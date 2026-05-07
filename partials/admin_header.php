<?php

require_once APP_ROOT . '/includes/icons.php';

$user = current_user();
$pageTitle = $pageTitle ?? 'Administrator';
$activeNav = $activeNav ?? 'dashboard';
$navGroups = [
    'Control Center' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'url' => app_url('administrator/index.php'),
        ],
    ],
    'Administration' => [
        [
            'key' => 'accounts',
            'label' => 'Account Management',
            'icon' => 'shield',
            'url' => app_url('administrator/accounts.php'),
        ],
    ],
    'Master Data' => [
        [
            'key' => 'employees',
            'label' => 'Employees & Crews',
            'icon' => 'users',
            'url' => app_url('administrator/employees.php'),
        ],
        [
            'key' => 'customers',
            'label' => 'Customer Management',
            'icon' => 'building',
            'url' => app_url('administrator/customers.php'),
        ],
    ],
    'Operations Setup' => [
        [
            'key' => 'locations',
            'label' => 'Locations',
            'icon' => 'map',
            'url' => '#',
            'disabled' => true,
        ],
        [
            'key' => 'rates',
            'label' => 'Rates & Delivery Types',
            'icon' => 'clipboard',
            'url' => '#',
            'disabled' => true,
        ],
        [
            'key' => 'fleet',
            'label' => 'Fleet',
            'icon' => 'truck',
            'url' => '#',
            'disabled' => true,
        ],
    ],
    'Monitoring' => [
        [
            'key' => 'reports',
            'label' => 'Reports',
            'icon' => 'chart',
            'url' => '#',
            'disabled' => true,
        ],
        [
            'key' => 'settings',
            'label' => 'System Settings',
            'icon' => 'settings',
            'url' => '#',
            'disabled' => true,
        ],
    ],
];
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
                <span class="brand-mark small">TJT</span>
                <span>TJT Trucking</span>
            </div>

            <nav class="sidebar-nav" aria-label="Administrator navigation">
                <?php foreach ($navGroups as $groupLabel => $items): ?>
                    <div class="sidebar-section">
                        <p class="sidebar-heading"><?php echo h($groupLabel); ?></p>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $isActive = $activeNav === $item['key'];
                            $isDisabled = !empty($item['disabled']);
                            $className = trim(($isActive ? 'active ' : '') . ($isDisabled ? 'disabled' : ''));
                            ?>
                            <?php if ($isDisabled): ?>
                                <span class="nav-link <?php echo h($className); ?>" aria-disabled="true">
                                    <span class="nav-icon"><?php echo icon($item['icon']); ?></span>
                                    <span><?php echo h($item['label']); ?></span>
                                </span>
                            <?php else: ?>
                                <a class="<?php echo h($className); ?>" href="<?php echo h($item['url']); ?>">
                                    <span class="nav-icon"><?php echo icon($item['icon']); ?></span>
                                    <span><?php echo h($item['label']); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
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
