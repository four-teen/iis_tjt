<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/budget.php';

require_any_role(['Administrator', 'Budget', 'Finance']);

$pageTitle = 'Budget';
$activeNav = 'budget';
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$allowedSections = ['dispatches', 'routes', 'owners'];
$section = strtolower((string) ($_GET['section'] ?? 'dispatches'));
$section = in_array($section, $allowedSections, true) ? $section : 'dispatches';

ensure_budget_schema();

function budget_page_url($section, array $params = [])
{
    return 'Budget/index.php?' . http_build_query(array_merge(['section' => $section], $params));
}

function budget_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function budget_section_class($section, $current)
{
    return $section === $current ? ' count-badge-active' : '';
}

function budget_date_text($value)
{
    if (!$value) {
        return 'Not set';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M d, Y', $timestamp) : (string) $value;
}

function budget_datetime_text($value)
{
    if (!$value) {
        return 'Not set';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M d, Y g:i A', $timestamp) : (string) $value;
}

function budget_dispatch_badge(array $dispatch)
{
    $routeBudget = (float) ($dispatch['od_budget'] ?? 0);
    $releaseCount = (int) ($dispatch['release_count'] ?? 0);

    if ($routeBudget <= 0) {
        return ['class' => 'badge badge-danger', 'label' => 'No route budget'];
    }

    if ($releaseCount <= 0) {
        return ['class' => 'badge badge-warning', 'label' => 'Ready'];
    }

    return ['class' => 'badge badge-success', 'label' => 'Released'];
}

function budget_role_label($whoIs, $fallback = '')
{
    $labels = employee_types();

    return $fallback ?: ($labels[(string) $whoIs] ?? 'Crew');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $postSection = $_POST['section'] ?? $section;
    $postSection = in_array($postSection, $allowedSections, true) ? $postSection : 'dispatches';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to(budget_page_url($postSection));
    }

    try {
        if ($action === 'save_route_budget') {
            save_route_budget((int) ($_POST['route_id'] ?? 0), $_POST['amount'] ?? '0', $currentUserId);
            flash('success', 'Route budget saved.');
            redirect_to(budget_page_url('routes', [
                'search' => $_POST['return_search'] ?? '',
                'customerid' => $_POST['return_customerid'] ?? '',
                'budget_filter' => $_POST['return_budget_filter'] ?? '',
            ]));
        }

        if ($action === 'release_dispatch_budget') {
            release_dispatch_budget(
                (int) ($_POST['reference'] ?? 0),
                (int) ($_POST['employee_id'] ?? 0),
                $_POST['amount'] ?? '0',
                $_POST['remarks'] ?? '',
                $currentUserId
            );
            flash('success', 'Trip budget released.');
            redirect_to(budget_page_url('dispatches', [
                'search' => $_POST['return_search'] ?? '',
                'status' => $_POST['return_status'] ?? 'all',
            ]));
        }

        if ($action === 'void_dispatch_release') {
            if (void_dispatch_budget_release((int) ($_POST['release_id'] ?? 0), (int) ($_POST['reference'] ?? 0), $_POST['void_reason'] ?? '', $currentUserId)) {
                flash('success', 'Budget release voided.');
            } else {
                flash('error', 'Budget release was not found or was already voided.');
            }
            redirect_to(budget_page_url('dispatches', [
                'search' => $_POST['return_search'] ?? '',
                'status' => $_POST['return_status'] ?? 'all',
            ]));
        }

        if ($action === 'save_owner_budget') {
            save_owner_budget((int) ($_POST['owner_id'] ?? 0), $_POST['date_released'] ?? '', $_POST['amount'] ?? '0', $currentUserId);
            flash('success', 'Owner budget saved.');
            redirect_to(budget_page_url('owners', ['search' => $_POST['return_search'] ?? '']));
        }

        if ($action === 'void_owner_budget') {
            if (void_owner_budget((int) ($_POST['owner_budget_id'] ?? 0), (int) ($_POST['owner_id'] ?? 0), $currentUserId)) {
                flash('success', 'Owner budget release voided.');
            } else {
                flash('error', 'Owner budget release was not found or was already voided.');
            }
            redirect_to(budget_page_url('owners', ['search' => $_POST['return_search'] ?? '']));
        }
    } catch (Throwable $error) {
        flash('error', 'Budget action failed: ' . $error->getMessage());
        redirect_to(budget_page_url($postSection));
    }

    redirect_to(budget_page_url($postSection));
}

$counts = budget_counts();
$messages = flash_messages();

$dispatchSearch = trim((string) ($_GET['search'] ?? ''));
$dispatchStatus = (string) ($_GET['status'] ?? 'all');
$dispatchStatus = in_array($dispatchStatus, ['all', 'needs_route_budget', 'needs_release', 'released'], true) ? $dispatchStatus : 'all';

$routeSearch = trim((string) ($_GET['search'] ?? ''));
$routeCustomerId = (string) ($_GET['customerid'] ?? '');
$routeBudgetFilter = (string) ($_GET['budget_filter'] ?? '');
$routeBudgetFilter = in_array($routeBudgetFilter, ['', 'missing', 'set'], true) ? $routeBudgetFilter : '';

$ownerSearch = trim((string) ($_GET['search'] ?? ''));

$dispatches = $section === 'dispatches' ? list_budget_dispatches($dispatchSearch, $dispatchStatus) : [];
$routes = $section === 'routes' ? list_budget_routes($routeSearch, $routeCustomerId, $routeBudgetFilter) : [];
$customers = $section === 'routes' ? list_customers('', '') : [];
$owners = $section === 'owners' ? list_owner_budget_summaries($ownerSearch) : [];

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero budget-hero">
    <div>
        <p class="eyebrow">Budget Account</p>
        <h2>Budget Control</h2>
        <p>Route budget setup, trip budget releases, and owner fund records.</p>
    </div>
    <div class="hero-actions">
        <div class="count-badges budget-section-tabs" aria-label="Budget sections">
            <a class="count-badge<?php echo h(budget_section_class('dispatches', $section)); ?>" href="<?php echo h(app_url(budget_page_url('dispatches'))); ?>">Trip Releases <strong><?php echo h($counts['dispatches_with_budget']); ?>/<?php echo h($counts['active_dispatches']); ?></strong></a>
            <a class="count-badge count-badge-warning<?php echo h(budget_section_class('routes', $section)); ?>" href="<?php echo h(app_url(budget_page_url('routes', ['budget_filter' => 'missing']))); ?>">Route Budgets <strong><?php echo h($counts['budgeted_routes']); ?>/<?php echo h($counts['total_routes']); ?></strong></a>
            <a class="count-badge count-badge-muted<?php echo h(budget_section_class('owners', $section)); ?>" href="<?php echo h(app_url(budget_page_url('owners'))); ?>">Owner Funds <strong><?php echo h(budget_money($counts['owner_total'])); ?></strong></a>
        </div>
    </div>
</section>

<section class="budget-summary-grid" aria-label="Budget summary">
    <div class="budget-metric">
        <span>Route Budget</span>
        <strong><?php echo h(budget_money($counts['total_route_budget'])); ?></strong>
        <small><?php echo h($counts['missing_routes']); ?> routes need budget</small>
    </div>
    <div class="budget-metric budget-metric-success">
        <span>Trip Released</span>
        <strong><?php echo h(budget_money($counts['total_released'])); ?></strong>
        <small><?php echo h($counts['dispatches_without_budget']); ?> dispatches without release</small>
    </div>
    <div class="budget-metric budget-metric-muted">
        <span>Owner Funds</span>
        <strong><?php echo h(budget_money($counts['owner_total'])); ?></strong>
        <small><?php echo h($counts['owner_release_count']); ?> ledger rows</small>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<?php if ($section === 'dispatches'): ?>
<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Trip Budget</p>
                <h3>Dispatched Releases</h3>
            </div>
            <form method="get" action="<?php echo h(app_url('Budget/index.php')); ?>" class="filter-bar budget-filter-bar">
                <input type="hidden" name="section" value="dispatches">
                <input type="search" name="search" value="<?php echo h($dispatchSearch); ?>" placeholder="Search reference, customer, route, plate">
                <select name="status">
                    <option value="all"<?php echo budget_selected('all', $dispatchStatus); ?>>All dispatches</option>
                    <option value="needs_route_budget"<?php echo budget_selected('needs_route_budget', $dispatchStatus); ?>>No route budget</option>
                    <option value="needs_release"<?php echo budget_selected('needs_release', $dispatchStatus); ?>>Ready to release</option>
                    <option value="released"<?php echo budget_selected('released', $dispatchStatus); ?>>Released</option>
                </select>
                <button type="submit" class="btn btn-light btn-icon"><?php echo icon('search'); ?> Filter</button>
            </form>
        </div>

        <div class="table-wrap record-scroll">
            <table class="data-table record-table budget-dispatch-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Dispatch Date</th>
                        <th>Customer</th>
                        <th>Origin-Destination</th>
                        <th>Plate</th>
                        <th>Crew</th>
                        <th>Route Budget</th>
                        <th>Released</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-page-size="10">
                    <?php foreach ($dispatches as $dispatch): ?>
                        <?php
                        $badge = budget_dispatch_badge($dispatch);
                        $reference = (int) $dispatch['dis_referenceid'];
                        $routeBudget = (float) ($dispatch['od_budget'] ?? 0);
                        $releaseCount = (int) ($dispatch['release_count'] ?? 0);
                        ?>
                        <tr>
                            <td><strong><?php echo h($reference); ?></strong></td>
                            <td><?php echo h(budget_datetime_text($dispatch['dis_dispatched_date'] ?? '')); ?></td>
                            <td><?php echo h(budget_customer_label($dispatch)); ?></td>
                            <td>
                                <strong><?php echo h(budget_route_label($dispatch)); ?></strong>
                                <span><?php echo h(($dispatch['deliverytype_name'] ?? 'Delivery') . ' / ' . ($dispatch['trucktype_name'] ?? 'Truck')); ?></span>
                            </td>
                            <td><?php echo h($dispatch['platenumber'] ?: 'No plate'); ?></td>
                            <td>
                                <strong><?php echo h($dispatch['driver_count'] ? $dispatch['driver_names'] : 'No driver'); ?></strong>
                                <span><?php echo h($dispatch['helper_count'] ? $dispatch['helper_names'] : 'No helper'); ?></span>
                            </td>
                            <td class="number-cell"><?php echo h(budget_money($routeBudget)); ?></td>
                            <td class="number-cell">
                                <strong><?php echo h(budget_money($dispatch['total_released'])); ?></strong>
                                <span><?php echo h($releaseCount); ?> row<?php echo $releaseCount === 1 ? '' : 's'; ?></span>
                            </td>
                            <td><span class="<?php echo h($badge['class']); ?>"><?php echo h($badge['label']); ?></span></td>
                            <td class="table-actions">
                                <button type="button" class="btn btn-primary btn-sm btn-icon" data-modal-open="dispatch-budget-<?php echo h($reference); ?>"><?php echo icon('wallet'); ?> Manage</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$dispatches): ?>
                        <tr class="empty-row"><td colspan="10">No dispatched trips found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php foreach ($dispatches as $dispatch): ?>
    <?php
    $reference = (int) $dispatch['dis_referenceid'];
    $routeBudget = (float) ($dispatch['od_budget'] ?? 0);
    $releaseCount = (int) ($dispatch['release_count'] ?? 0);
    $recipients = dispatch_budget_recipients($reference);
    $releases = list_dispatch_budget_releases($reference);
    $defaultAmount = $releaseCount === 0 ? $routeBudget : 0;
    ?>
    <div class="modal" id="dispatch-budget-<?php echo h($reference); ?>" hidden>
        <div class="modal-card budget-modal-card" role="dialog" aria-modal="true" aria-labelledby="dispatch-budget-title-<?php echo h($reference); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Trip Release</p>
                    <h3 id="dispatch-budget-title-<?php echo h($reference); ?>">Reference <?php echo h($reference); ?></h3>
                    <p><?php echo h(budget_customer_label($dispatch)); ?> / <?php echo h(budget_route_label($dispatch)); ?></p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="budget-modal-grid">
                    <dl class="dispatch-preview-list budget-facts">
                        <div><dt>Plate</dt><dd><?php echo h($dispatch['platenumber'] ?: 'No plate'); ?></dd></div>
                        <div><dt>Route Budget</dt><dd><?php echo h(budget_money($routeBudget)); ?></dd></div>
                        <div><dt>Released</dt><dd><?php echo h(budget_money($dispatch['total_released'])); ?></dd></div>
                        <div><dt>Balance Against Route</dt><dd><?php echo h(budget_money($routeBudget - (float) $dispatch['total_released'])); ?></dd></div>
                    </dl>

                    <div class="budget-release-panel">
                        <?php if ($routeBudget <= 0): ?>
                            <div class="alert alert-error">Route budget is required before releasing trip budget.</div>
                        <?php elseif (!$recipients): ?>
                            <div class="alert alert-error">No assigned driver or helper is available for release.</div>
                        <?php else: ?>
                            <form method="post" action="<?php echo h(app_url('Budget/index.php')); ?>" class="stack-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="release_dispatch_budget">
                                <input type="hidden" name="section" value="dispatches">
                                <input type="hidden" name="reference" value="<?php echo h($reference); ?>">
                                <input type="hidden" name="return_search" value="<?php echo h($dispatchSearch); ?>">
                                <input type="hidden" name="return_status" value="<?php echo h($dispatchStatus); ?>">

                                <div class="form-grid">
                                    <div>
                                        <label>Release To</label>
                                        <select name="employee_id" required>
                                            <?php foreach ($recipients as $recipient): ?>
                                                <option value="<?php echo h($recipient['employee_id']); ?>"><?php echo h(budget_employee_name($recipient)); ?> / <?php echo h($recipient['crew_role']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Amount</label>
                                        <input name="amount" type="number" min="0.01" step="0.01" value="<?php echo h($defaultAmount > 0 ? budget_number_value($defaultAmount) : ''); ?>" required>
                                    </div>
                                </div>
                                <label>Remarks</label>
                                <input name="remarks" maxlength="100" value="<?php echo h($releaseCount === 0 ? 'Initial route budget' : 'Additional budget'); ?>">
                                <div class="modal-actions">
                                    <button type="submit" class="btn btn-primary"><?php echo icon('save'); ?> Release Budget</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="budget-ledger">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Ledger</p>
                            <h3>Release Rows</h3>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Recipient</th>
                                    <th>Remarks</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($releases as $release): ?>
                                    <tr>
                                        <td><?php echo h(budget_datetime_text($release['dis_budget_dated'])); ?></td>
                                        <td><?php echo h(budget_employee_name($release)); ?></td>
                                        <td><?php echo h($release['remarks']); ?></td>
                                        <td class="number-cell"><?php echo h(budget_money($release['dis_budget_amount'])); ?></td>
                                        <td>
                                            <?php if ($release['voided_at']): ?>
                                                <span class="badge badge-muted">Voided</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <?php if (!$release['voided_at']): ?>
                                                <form method="post" action="<?php echo h(app_url('Budget/index.php')); ?>" onsubmit="return confirm('Void this budget release?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="void_dispatch_release">
                                                    <input type="hidden" name="section" value="dispatches">
                                                    <input type="hidden" name="reference" value="<?php echo h($reference); ?>">
                                                    <input type="hidden" name="release_id" value="<?php echo h($release['dis_budget_id']); ?>">
                                                    <input type="hidden" name="void_reason" value="Voided from budget module">
                                                    <input type="hidden" name="return_search" value="<?php echo h($dispatchSearch); ?>">
                                                    <input type="hidden" name="return_status" value="<?php echo h($dispatchStatus); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm btn-icon"><?php echo icon('trash'); ?> Void</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$releases): ?>
                                    <tr class="empty-row"><td colspan="6">No release rows yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($section === 'routes'): ?>
<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Route Setup</p>
                <h3>Trip Budgets</h3>
            </div>
            <form method="get" action="<?php echo h(app_url('Budget/index.php')); ?>" class="filter-bar budget-filter-bar">
                <input type="hidden" name="section" value="routes">
                <input type="search" name="search" value="<?php echo h($routeSearch); ?>" placeholder="Search customer, origin, destination">
                <select name="customerid">
                    <option value="">All customers</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo h($customer['customerid']); ?>"<?php echo budget_selected($customer['customerid'], $routeCustomerId); ?>><?php echo h('[' . $customer['soa'] . '] ' . $customer['customername']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="budget_filter">
                    <option value=""<?php echo budget_selected('', $routeBudgetFilter); ?>>All budgets</option>
                    <option value="missing"<?php echo budget_selected('missing', $routeBudgetFilter); ?>>Missing budget</option>
                    <option value="set"<?php echo budget_selected('set', $routeBudgetFilter); ?>>With budget</option>
                </select>
                <button type="submit" class="btn btn-light btn-icon"><?php echo icon('search'); ?> Filter</button>
            </form>
        </div>

        <div class="table-wrap record-scroll">
            <table class="data-table record-table budget-route-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Origin-Destination</th>
                        <th>Delivery Type</th>
                        <th>Truck Type</th>
                        <th>Delivery Rate</th>
                        <th>Driver Rate</th>
                        <th>Helper Rate</th>
                        <th>Trip Budget</th>
                    </tr>
                </thead>
                <tbody data-page-size="20">
                    <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><?php echo h(budget_customer_label($route)); ?></td>
                            <td><strong><?php echo h(budget_route_label($route)); ?></strong></td>
                            <td><?php echo h($route['deliverytype_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo h($route['trucktype_name'] ?? 'Unassigned'); ?></td>
                            <td class="number-cell"><?php echo h(budget_money($route['deliveryrate'])); ?></td>
                            <td class="number-cell"><?php echo h(budget_money($route['driversrate'])); ?></td>
                            <td class="number-cell"><?php echo h(budget_money($route['helpersrate'])); ?></td>
                            <td>
                                <form method="post" action="<?php echo h(app_url('Budget/index.php')); ?>" class="budget-inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="save_route_budget">
                                    <input type="hidden" name="section" value="routes">
                                    <input type="hidden" name="route_id" value="<?php echo h($route['customerinformationid']); ?>">
                                    <input type="hidden" name="return_search" value="<?php echo h($routeSearch); ?>">
                                    <input type="hidden" name="return_customerid" value="<?php echo h($routeCustomerId); ?>">
                                    <input type="hidden" name="return_budget_filter" value="<?php echo h($routeBudgetFilter); ?>">
                                    <input name="amount" type="number" min="0" step="0.01" value="<?php echo h(budget_number_value($route['od_budget'] ?? 0)); ?>" aria-label="Trip budget for <?php echo h(budget_route_label($route)); ?>">
                                    <button type="submit" class="btn btn-primary btn-sm btn-icon"><?php echo icon('save'); ?> Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$routes): ?>
                        <tr class="empty-row"><td colspan="8">No route records found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($section === 'owners'): ?>
<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Owner Budget</p>
                <h3>Fleet Owner Funds</h3>
            </div>
            <form method="get" action="<?php echo h(app_url('Budget/index.php')); ?>" class="filter-bar budget-filter-bar">
                <input type="hidden" name="section" value="owners">
                <input type="search" name="search" value="<?php echo h($ownerSearch); ?>" placeholder="Search owner">
                <button type="submit" class="btn btn-light btn-icon"><?php echo icon('search'); ?> Filter</button>
            </form>
        </div>

        <div class="table-wrap record-scroll">
            <table class="data-table record-table">
                <thead>
                    <tr>
                        <th>Owner</th>
                        <th>Release Rows</th>
                        <th>Last Release</th>
                        <th>Total Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-page-size="10">
                    <?php foreach ($owners as $owner): ?>
                        <?php
                        $ownerId = (int) $owner['employee_id'];
                        ?>
                        <tr>
                            <td><strong><?php echo h(budget_employee_name($owner)); ?></strong></td>
                            <td><?php echo h((int) $owner['release_count']); ?></td>
                            <td><?php echo h(budget_date_text($owner['last_release_date'])); ?></td>
                            <td class="number-cell"><strong><?php echo h(budget_money($owner['total_budget'])); ?></strong></td>
                            <td class="table-actions">
                                <button type="button" class="btn btn-primary btn-sm btn-icon" data-modal-open="owner-budget-<?php echo h($ownerId); ?>"><?php echo icon('wallet'); ?> Manage</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$owners): ?>
                        <tr class="empty-row"><td colspan="5">No fleet owner employees found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php foreach ($owners as $owner): ?>
    <?php
    $ownerId = (int) $owner['employee_id'];
    $ownerRows = list_owner_budget_rows($ownerId);
    ?>
    <div class="modal" id="owner-budget-<?php echo h($ownerId); ?>" hidden>
        <div class="modal-card budget-modal-card" role="dialog" aria-modal="true" aria-labelledby="owner-budget-title-<?php echo h($ownerId); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Owner Funds</p>
                    <h3 id="owner-budget-title-<?php echo h($ownerId); ?>"><?php echo h(budget_employee_name($owner)); ?></h3>
                    <p>Total received: <?php echo h(budget_money($owner['total_budget'])); ?></p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Budget/index.php')); ?>" class="stack-form owner-budget-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_owner_budget">
                    <input type="hidden" name="section" value="owners">
                    <input type="hidden" name="owner_id" value="<?php echo h($ownerId); ?>">
                    <input type="hidden" name="return_search" value="<?php echo h($ownerSearch); ?>">
                    <div class="form-grid">
                        <div>
                            <label>Date Received</label>
                            <input name="date_released" type="date" value="<?php echo h(date('Y-m-d')); ?>" required>
                        </div>
                        <div>
                            <label>Amount</label>
                            <input name="amount" type="number" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icon('save'); ?> Save Owner Budget</button>
                    </div>
                </form>

                <div class="budget-ledger">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ownerRows as $ownerRow): ?>
                                    <tr>
                                        <td><?php echo h(budget_date_text($ownerRow['date_released'])); ?></td>
                                        <td class="number-cell"><?php echo h(budget_money($ownerRow['budget_amount'])); ?></td>
                                        <td>
                                            <?php if ($ownerRow['deleted'] === 'Y'): ?>
                                                <span class="badge badge-muted">Voided</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <?php if ($ownerRow['deleted'] !== 'Y'): ?>
                                                <form method="post" action="<?php echo h(app_url('Budget/index.php')); ?>" onsubmit="return confirm('Void this owner budget release?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="void_owner_budget">
                                                    <input type="hidden" name="section" value="owners">
                                                    <input type="hidden" name="owner_id" value="<?php echo h($ownerId); ?>">
                                                    <input type="hidden" name="owner_budget_id" value="<?php echo h($ownerRow['owner_budget_id']); ?>">
                                                    <input type="hidden" name="return_search" value="<?php echo h($ownerSearch); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm btn-icon"><?php echo icon('trash'); ?> Void</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$ownerRows): ?>
                                    <tr class="empty-row"><td colspan="4">No owner budget rows yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
