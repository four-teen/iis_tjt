<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accounts.php';

require_login();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$counts = account_counts();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="welcome-panel">
    <div>
        <p class="eyebrow">IIS-TJT Movers</p>
        <h2>Welcome, <?php echo h(current_user()['name']); ?></h2>
        <p>The new system foundation now uses database-backed administrator accounts, protected sessions, CSRF checks, and environment-based configuration.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo h(app_url('administrator/accounts.php')); ?>">Manage Accounts</a>
</section>

<section class="stats-grid" aria-label="System overview">
    <article class="stat-card">
        <span class="stat-label">Active Users</span>
        <strong><?php echo h($counts['active']); ?></strong>
        <p>Accounts allowed to sign in.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Total Accounts</span>
        <strong><?php echo h($counts['total']); ?></strong>
        <p>Administrator-managed account records.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Status</span>
        <strong>Ready</strong>
        <p>Database and admin account module are in place.</p>
    </article>
</section>

<section class="panel-grid">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Administrator</p>
                <h3>Core Menu</h3>
            </div>
        </div>

        <div class="module-list">
            <a href="<?php echo h(app_url('administrator/accounts.php')); ?>">
                <strong>Account Management</strong>
                <span>Create, update, activate, deactivate, and reset administrator-managed accounts.</span>
            </a>
            <a href="#">
                <strong>Reports</strong>
                <span>Next foundation for management, finance, and operations reports.</span>
            </a>
            <a href="#">
                <strong>Settings</strong>
                <span>Store app-level preferences and system options.</span>
            </a>
        </div>
    </article>

    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Next Work</p>
                <h3>Build Checklist</h3>
            </div>
        </div>

        <ul class="check-list">
            <li>Define exact permissions per department role.</li>
            <li>Add customer, fleet, driver, and route master data.</li>
            <li>Create trip lifecycle status tables.</li>
            <li>Add audit views for important account actions.</li>
        </ul>
    </article>
</section>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
