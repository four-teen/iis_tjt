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
            'label' => 'Accounts',
            'icon' => 'shield',
            'url' => app_url('administrator/accounts.php'),
            'required_role' => 'Administrator',
        ],
    ],
    'Master Data' => [
        [
            'key' => 'employees',
            'label' => 'Employees & Crews',
            'icon' => 'users',
            'url' => app_url('administrator/employees.php'),
            'required_role' => 'Administrator',
        ],
        [
            'key' => 'customers',
            'label' => 'Customers',
            'icon' => 'building',
            'url' => app_url('administrator/customers.php'),
            'required_role' => 'Administrator',
        ],
    ],
    'Customer Service' => [
        [
            'key' => 'customer_service',
            'label' => 'Bookings',
            'icon' => 'calendar',
            'url' => app_url('administrator/customer_service.php'),
            'required_roles' => ['Administrator', 'Customer Service'],
        ],
    ],
    'Operations Setup' => [
        [
            'key' => 'locations',
            'label' => 'Locations',
            'icon' => 'map',
            'url' => app_url('administrator/locations.php'),
            'required_role' => 'Administrator',
        ],
        [
            'key' => 'delivery_types',
            'label' => 'Delivery Types',
            'icon' => 'clipboard',
            'url' => app_url('administrator/delivery_types.php'),
            'required_role' => 'Administrator',
        ],
        [
            'key' => 'truck_types',
            'label' => 'Truck Types',
            'icon' => 'truck',
            'url' => app_url('administrator/truck_types.php'),
            'required_role' => 'Administrator',
        ],
        [
            'key' => 'fleet',
            'label' => 'Fleet',
            'icon' => 'truck',
            'url' => app_url('administrator/fleet.php'),
            'required_role' => 'Administrator',
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
    <link rel="icon" href="<?php echo h(app_url('assets/img/favicon.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo h(app_url('assets/img/apple-touch-icon.png')); ?>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.datatables.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/app.css')); ?>">
</head>
<body class="admin-page">
    <div class="admin-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <img class="brand-logo" src="<?php echo h(app_url('assets/img/logo.png')); ?>" alt="TJT Trucking">
                <span>TJT Trucking</span>
            </div>

            <nav class="sidebar-nav" aria-label="Workspace navigation">
                <?php foreach ($navGroups as $groupLabel => $items): ?>
                    <?php
                    $visibleItems = array_values(array_filter($items, function ($item) use ($user) {
                        if (!empty($item['required_roles'])) {
                            return in_array($user['role'] ?? '', $item['required_roles'], true);
                        }

                        return empty($item['required_role']) || (($user['role'] ?? '') === $item['required_role']);
                    }));
                    ?>
                    <?php if (!$visibleItems): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <div class="sidebar-section">
                        <p class="sidebar-heading"><?php echo h($groupLabel); ?></p>
                        <?php foreach ($visibleItems as $item): ?>
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
                    <p class="eyebrow"><?php echo h($user['role'] ?? 'Workspace'); ?></p>
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
