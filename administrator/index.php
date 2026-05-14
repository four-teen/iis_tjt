<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accounts.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

function dashboard_scalar($sql)
{
    return (int) db()->query($sql)->fetchColumn();
}

function readiness_item($label, $value, $detail, $isReady, $url = '', $isWarning = false)
{
    return [
        'label' => $label,
        'value' => $value,
        'detail' => $detail,
        'tone' => $isReady ? 'success' : ($isWarning ? 'warning' : 'danger'),
        'ready' => $isReady,
        'url' => $url,
    ];
}

function readiness_percent($items)
{
    if (!$items) {
        return 0;
    }

    $ready = 0;

    foreach ($items as $item) {
        if (!empty($item['ready'])) {
            $ready++;
        }
    }

    return (int) round(($ready / count($items)) * 100);
}

function readiness_status_label($tone)
{
    if ($tone === 'success') {
        return 'Ready';
    }

    if ($tone === 'warning') {
        return 'Review';
    }

    return 'Blocker';
}

$accountCounts = account_counts();
$customerCounts = customer_counts();
$employeeCounts = employee_counts();
$locationCounts = location_counts();
$routeCounts = route_counts();
$fleetCounts = fleet_counts();

$readinessChecks = [
    'active_customers_without_routes' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblcustomer c
        WHERE c.status = 1
            AND NOT EXISTS (
                SELECT 1
                FROM tblcustomerinformation ci
                WHERE ci.customerid = c.customerid
            )
    '),
    'routes_with_bad_customer' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblcustomerinformation ci
        LEFT JOIN tblcustomer c ON c.customerid = ci.customerid
        WHERE c.customerid IS NULL OR c.status <> 1
    '),
    'routes_with_zero_deliveryrate' => dashboard_scalar('SELECT COUNT(*) FROM tblcustomerinformation WHERE deliveryrate <= 0'),
    'routes_with_zero_driverrate' => dashboard_scalar('SELECT COUNT(*) FROM tblcustomerinformation WHERE driversrate <= 0'),
    'routes_with_zero_helperrate' => dashboard_scalar('SELECT COUNT(*) FROM tblcustomerinformation WHERE helpersrate <= 0'),
    'route_origins_not_locations' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblcustomerinformation ci
        LEFT JOIN tbllocation l ON UPPER(l.location) = UPPER(ci.origin) AND l.status = 1
        WHERE l.locationid IS NULL
    '),
    'route_destinations_not_locations' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblcustomerinformation ci
        LEFT JOIN tbllocation l ON UPPER(l.location) = UPPER(ci.destination) AND l.status = 1
        WHERE l.locationid IS NULL
    '),
    'fleet_without_profile' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        WHERE fi.fleetid IS NULL
    '),
    'fleet_missing_specs' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        WHERE COALESCE(fi.trucktype, 99) = 99
            OR COALESCE(fi.vantype, 99) = 99
            OR COALESCE(fi.make, 99) = 99
            OR COALESCE(fi.body, 99) = 99
            OR COALESCE(fi.color, 99) = 99
            OR COALESCE(fi.platecolor, 99) = 99
            OR COALESCE(fi.ltfrbstatus, 99) = 99
    '),
    'fleet_missing_cpc' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet f
        LEFT JOIN tblfleet_info_1 fi ON fi.fleetid = f.fleetid
        WHERE fi.cpc IS NULL OR fi.cpc = "" OR fi.cpcvalidity IS NULL
    '),
    'fleet_expired_registration' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet
        WHERE validity IS NOT NULL AND validity < CURDATE()
    '),
    'fleet_without_driver' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet f
        WHERE NOT EXISTS (
            SELECT 1
            FROM tblfleet_assigned_driver_helper a
            INNER JOIN tblemployees e ON e.employee_id = a.assigned_employeeid
            WHERE a.assigned_fleetid = f.fleetid AND e.who_is = "1"
        )
    '),
    'fleet_without_helper' => dashboard_scalar('
        SELECT COUNT(*)
        FROM tblfleet f
        WHERE NOT EXISTS (
            SELECT 1
            FROM tblfleet_assigned_driver_helper a
            INNER JOIN tblemployees e ON e.employee_id = a.assigned_employeeid
            WHERE a.assigned_fleetid = f.fleetid AND e.who_is = "2"
        )
    '),
];

$foundationItems = [
    readiness_item('Active Users', $accountCounts['active'] . ' / ' . $accountCounts['total'], 'Accounts that can access the system.', $accountCounts['active'] > 0, 'Administrator/accounts.php'),
    readiness_item('Active Customers', $customerCounts['active'] . ' / ' . $customerCounts['total'], 'Customers available for booking.', $customerCounts['active'] > 0, 'Administrator/customers.php'),
    readiness_item('Active Locations', $locationCounts['active'] . ' / ' . $locationCounts['total'], 'Origins, destinations, pickups, and drops.', $locationCounts['active'] > 0, 'Administrator/locations.php'),
    readiness_item('Delivery Types', $routeCounts['delivery_types'], 'Delivery categories for rate and booking forms.', $routeCounts['delivery_types'] > 0, 'Administrator/delivery_types.php'),
    readiness_item('Truck Types', $routeCounts['truck_types'], 'Truck classifications for route matching.', $routeCounts['truck_types'] > 0, 'Administrator/truck_types.php'),
    readiness_item('Drivers / Helpers', $employeeCounts['1'] . ' / ' . $employeeCounts['2'], 'Crew pool available for dispatch assignment.', $employeeCounts['1'] > 0 && $employeeCounts['2'] > 0, 'Administrator/employees.php'),
    readiness_item('Fleet Units', $fleetCounts['total'], 'Registered trucks available for booking dispatch.', $fleetCounts['total'] > 0, 'Administrator/fleet.php'),
    readiness_item('Customer Routes', $routeCounts['total'], 'Route and rate records available to quote bookings.', $routeCounts['total'] > 0, 'Administrator/customers.php'),
];

$routeQualityItems = [
    readiness_item('Customers Without Routes', $readinessChecks['active_customers_without_routes'], 'Every active customer should have at least one route/rate setup.', $readinessChecks['active_customers_without_routes'] === 0, 'Administrator/customers.php'),
    readiness_item('Routes With Missing Customer', $readinessChecks['routes_with_bad_customer'], 'Route/rate rows must map to an active customer.', $readinessChecks['routes_with_bad_customer'] === 0, 'Administrator/customers.php'),
    readiness_item('Zero Delivery Rates', $readinessChecks['routes_with_zero_deliveryrate'], 'Delivery rates must be encoded before booking billing can work.', $readinessChecks['routes_with_zero_deliveryrate'] === 0, 'Administrator/customers.php'),
    readiness_item('Zero Driver Rates', $readinessChecks['routes_with_zero_driverrate'], 'Driver rates are needed for payroll/liquidation readiness.', $readinessChecks['routes_with_zero_driverrate'] === 0, 'Administrator/customers.php'),
    readiness_item('Zero Helper Rates', $readinessChecks['routes_with_zero_helperrate'], 'Helper rates are needed for payroll/liquidation readiness.', $readinessChecks['routes_with_zero_helperrate'] === 0, 'Administrator/customers.php'),
    readiness_item('Origins Not In Locations', $readinessChecks['route_origins_not_locations'], 'Route origins should match active location records.', $readinessChecks['route_origins_not_locations'] === 0, 'Administrator/locations.php'),
    readiness_item('Destinations Not In Locations', $readinessChecks['route_destinations_not_locations'], 'Route destinations should match active location records.', $readinessChecks['route_destinations_not_locations'] === 0, 'Administrator/locations.php'),
];

$fleetReadinessItems = [
    readiness_item('Fleet Without Profile', $readinessChecks['fleet_without_profile'], 'Each truck needs a technical profile for dispatch filtering.', $readinessChecks['fleet_without_profile'] === 0, 'Administrator/fleet.php'),
    readiness_item('Fleet Missing Specs', $readinessChecks['fleet_missing_specs'], 'Truck type, van type, make, body, color, plate color, and LTFRB status must be encoded.', $readinessChecks['fleet_missing_specs'] === 0, 'Administrator/fleet.php'),
    readiness_item('Fleet Missing CPC', $readinessChecks['fleet_missing_cpc'], 'CPC details are required for regulatory readiness.', $readinessChecks['fleet_missing_cpc'] === 0, 'Administrator/fleet.php'),
    readiness_item('Expired Registration', $readinessChecks['fleet_expired_registration'], 'Expired units should be held from dispatch selection.', $readinessChecks['fleet_expired_registration'] === 0, 'Administrator/fleet.php', true),
    readiness_item('Fleet Without Driver', $readinessChecks['fleet_without_driver'], 'Booked trucks should have an assigned driver.', $readinessChecks['fleet_without_driver'] === 0, 'Administrator/fleet.php'),
    readiness_item('Fleet Without Helper', $readinessChecks['fleet_without_helper'], 'Booked trucks should have an assigned helper when required.', $readinessChecks['fleet_without_helper'] === 0, 'Administrator/fleet.php'),
];

$allReadinessItems = array_merge($foundationItems, $routeQualityItems, $fleetReadinessItems);
$readinessScore = readiness_percent($allReadinessItems);
$blockerCount = 0;
$warningCount = 0;

foreach (array_merge($routeQualityItems, $fleetReadinessItems) as $item) {
    if ($item['ready']) {
        continue;
    }

    if ($item['tone'] === 'warning') {
        $warningCount++;
    } else {
        $blockerCount++;
    }
}

$readinessTone = $blockerCount > 0 ? 'danger' : ($warningCount > 0 ? 'warning' : 'success');
$readinessLabel = $blockerCount > 0 ? 'Not Ready for Booking' : ($warningCount > 0 ? 'Ready With Review' : 'Ready to Start Booking');

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="readiness-hero readiness-hero-<?php echo h($readinessTone); ?>">
    <div>
        <p class="eyebrow">System Readiness</p>
        <h2><?php echo h($readinessLabel); ?></h2>
        <p>Live indicators for the admin data needed by booking, dispatch, billing, payroll, and reports.</p>
    </div>
    <div class="readiness-score-card" aria-label="System readiness score">
        <div class="readiness-score-ring" style="--score: <?php echo h($readinessScore); ?>;">
            <strong><?php echo h($readinessScore); ?>%</strong>
            <span>ready</span>
        </div>
        <div class="readiness-score-meta">
            <span class="count-badge <?php echo $blockerCount > 0 ? 'count-badge-danger' : 'count-badge-success'; ?>">Blockers <strong><?php echo h($blockerCount); ?></strong></span>
            <span class="count-badge <?php echo $warningCount > 0 ? 'count-badge-warning' : 'count-badge-muted'; ?>">Review <strong><?php echo h($warningCount); ?></strong></span>
        </div>
    </div>
</section>

<section class="readiness-summary-grid" aria-label="System readiness summary">
    <?php foreach ($foundationItems as $item): ?>
        <a class="readiness-metric readiness-metric-<?php echo h($item['tone']); ?>" href="<?php echo h(app_url($item['url'])); ?>">
            <span><?php echo h($item['label']); ?></span>
            <strong><?php echo h($item['value']); ?></strong>
            <small><?php echo h($item['detail']); ?></small>
        </a>
    <?php endforeach; ?>
</section>

<section class="readiness-board">
    <article class="panel readiness-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Booking Data Quality</p>
                <h3>Customer Routes and Rates</h3>
            </div>
            <span class="badge <?php echo $blockerCount > 0 ? 'badge-danger' : 'badge-success'; ?>"><?php echo h($routeCounts['total']); ?> Routes</span>
        </div>
        <div class="readiness-list">
            <?php foreach ($routeQualityItems as $item): ?>
                <a class="readiness-row readiness-row-<?php echo h($item['tone']); ?>" href="<?php echo h(app_url($item['url'])); ?>">
                    <span class="readiness-state"><?php echo h(readiness_status_label($item['tone'])); ?></span>
                    <div>
                        <strong><?php echo h($item['label']); ?></strong>
                        <small><?php echo h($item['detail']); ?></small>
                    </div>
                    <b><?php echo h($item['value']); ?></b>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel readiness-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Dispatch Data Quality</p>
                <h3>Fleet and Crew Readiness</h3>
            </div>
            <span class="badge <?php echo $fleetCounts['profiled'] === $fleetCounts['total'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo h($fleetCounts['profiled']); ?> / <?php echo h($fleetCounts['total']); ?> Profiled</span>
        </div>
        <div class="readiness-list">
            <?php foreach ($fleetReadinessItems as $item): ?>
                <a class="readiness-row readiness-row-<?php echo h($item['tone']); ?>" href="<?php echo h(app_url($item['url'])); ?>">
                    <span class="readiness-state"><?php echo h(readiness_status_label($item['tone'])); ?></span>
                    <div>
                        <strong><?php echo h($item['label']); ?></strong>
                        <small><?php echo h($item['detail']); ?></small>
                    </div>
                    <b><?php echo h($item['value']); ?></b>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
