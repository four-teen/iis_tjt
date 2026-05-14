<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$customerId = (int) ($_GET['customerid'] ?? 0);
$customer = find_customer_by_id($customerId);

if (!$customer) {
    flash('error', 'Customer record was not found.');
    redirect_to('Administrator/customers.php');
}

$pageTitle = 'Customer Routes';
$activeNav = 'customers';
$returnUrl = 'Administrator/customer_routes.php?customerid=' . $customerId;

function route_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function route_money($value)
{
    return number_format((float) $value, 2);
}

function route_number_value($value)
{
    return number_format((float) $value, 2, '.', '');
}

function route_belongs_to_customer($route, $customerId)
{
    return $route && (int) $route['customerid'] === (int) $customerId;
}

function render_location_options($locations, $current = '')
{
    $current = clean_text($current);
    $foundCurrent = $current === '';

    foreach ($locations as $location) {
        if ((string) $location['location'] === $current) {
            $foundCurrent = true;
        }
    }

    if (!$foundCurrent) {
        echo '<option value="' . h($current) . '" selected>' . h($current) . '</option>';
    }

    foreach ($locations as $location) {
        echo '<option value="' . h($location['location']) . '"' . route_selected($location['location'], $current) . '>' . h($location['location']) . '</option>';
    }
}

function render_setup_options($rows, $idField, $labelField, $current = '')
{
    foreach ($rows as $row) {
        echo '<option value="' . h($row[$idField]) . '"' . route_selected($row[$idField], $current) . '>' . h($row[$labelField]) . '</option>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to($returnUrl);
    }

    try {
        if ($action === 'create') {
            $data = [
                'customerid' => $customerId,
                'origin' => $_POST['origin'] ?? '',
                'destination' => $_POST['destination'] ?? '',
                'deliveryrate' => $_POST['deliveryrate'] ?? '0',
                'driversrate' => $_POST['driversrate'] ?? '0',
                'helpersrate' => $_POST['helpersrate'] ?? '0',
                'deliverytype' => $_POST['deliverytype'] ?? '',
                'trucktype' => $_POST['trucktype'] ?? '',
            ];
            $errors = validate_customer_route_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                save_customer_route($data);
                flash('success', 'Origin and destination route created.');
            }
        } elseif ($action === 'update') {
            $routeId = (int) ($_POST['customerinformationid'] ?? 0);
            $route = find_customer_route_by_id($routeId);

            if (!route_belongs_to_customer($route, $customerId)) {
                flash('error', 'Route record was not found for this customer.');
            } else {
                $data = [
                    'customerid' => $customerId,
                    'origin' => $_POST['origin'] ?? '',
                    'destination' => $_POST['destination'] ?? '',
                    'deliveryrate' => $_POST['deliveryrate'] ?? '0',
                    'driversrate' => $_POST['driversrate'] ?? '0',
                    'helpersrate' => $_POST['helpersrate'] ?? '0',
                    'deliverytype' => $_POST['deliverytype'] ?? '',
                    'trucktype' => $_POST['trucktype'] ?? '',
                ];
                $errors = validate_customer_route_data($data);

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    save_customer_route($data, $routeId);
                    flash('success', 'Origin and destination route updated.');
                }
            }
        } elseif ($action === 'delete') {
            $routeId = (int) ($_POST['customerinformationid'] ?? 0);
            $route = find_customer_route_by_id($routeId);

            if (!route_belongs_to_customer($route, $customerId)) {
                flash('error', 'Route record was not found for this customer.');
            } elseif (delete_customer_route($routeId)) {
                flash('success', 'Origin and destination route deleted.');
            } else {
                flash('error', 'Route record could not be deleted.');
            }
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, function ($routeId) use ($customerId) {
                $route = find_customer_route_by_id($routeId);

                return route_belongs_to_customer($route, $customerId) ? $route : null;
            }, function ($routeId) {
                return delete_customer_route($routeId);
            });

            flash_bulk_delete_result('route', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Route action failed: ' . $error->getMessage());
    }

    redirect_to($returnUrl);
}

$routes = list_customer_routes_for_customer($customerId);
$locations = list_locations();
$deliveryTypes = list_delivery_types();
$truckTypes = list_truck_types();
$counts = customer_route_counts($customerId);
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Customer Route Setup</p>
        <h2>[<?php echo h($customer['soa']); ?>] <?php echo h($customer['customername']); ?></h2>
        <p><?php echo h($customer['customeraddress']); ?></p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-modal-open="create-customer-route-modal"><?php echo icon('plus'); ?> New Origin / Destination</button>
        <a class="btn btn-light" href="<?php echo h(app_url('Administrator/customers.php')); ?>">Back to Customers</a>
        <div class="count-badges" aria-label="Customer route overview">
            <span class="count-badge">Routes <strong><?php echo h($counts['total']); ?></strong></span>
            <span class="count-badge count-badge-success">Origins <strong><?php echo h($counts['origins']); ?></strong></span>
            <span class="count-badge count-badge-muted">Destinations <strong><?php echo h($counts['destinations']); ?></strong></span>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Origin & Destination</p>
                <h3>Route Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="routes">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_delete">

            <div class="bulk-table-toolbar">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" data-bulk-delete-button disabled><?php echo icon('trash'); ?> Delete Selected</button>
                <span data-bulk-delete-count>0 selected</span>
            </div>

            <div class="table-wrap record-scroll" data-infinite-scroll>
                <table class="data-table record-table">
                    <thead>
                        <tr>
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all routes"></th>
                            <th>Origin</th>
                            <th></th>
                            <th>Destination</th>
                            <th>Delivery Rate</th>
                            <th>Driver Rate</th>
                            <th>Helper Rate</th>
                            <th>Delivery Type</th>
                            <th>Truck Type</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="customer-route-records" data-infinite-list data-page-size="20">
                        <?php foreach ($routes as $route): ?>
                            <tr data-infinite-item>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($route['customerinformationid']); ?>" data-bulk-delete-item aria-label="Select route <?php echo h($route['origin']); ?> to <?php echo h($route['destination']); ?>"></td>
                                <td class="route-origin"><?php echo h($route['origin']); ?></td>
                                <td class="route-arrow"><?php echo icon('map'); ?></td>
                                <td><?php echo h($route['destination']); ?></td>
                                <td class="number-cell"><?php echo h(route_money($route['deliveryrate'])); ?></td>
                                <td class="number-cell"><?php echo h(route_money($route['driversrate'])); ?></td>
                                <td class="number-cell"><?php echo h(route_money($route['helpersrate'])); ?></td>
                                <td><?php echo h($route['deliverytype_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo h($route['trucktype_name'] ?? 'Unassigned'); ?></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Route actions">
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-customer-route-<?php echo h($route['customerinformationid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-customer-route-<?php echo h($route['customerinformationid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$routes): ?>
                            <tr class="empty-row">
                                <td colspan="10">No origin and destination records found for this customer.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-status" data-infinite-status="customer-route-records"></p>
        </form>
    </article>
</section>

<div class="modal" id="create-customer-route-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="create-customer-route-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-customer-route-title">New Origin and Destination</h3>
                <p>Add the route and rate setup used for booking, payroll, and billing.</p>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div>
                        <label for="origin">Origin</label>
                        <select id="origin" name="origin" required>
                            <?php render_location_options($locations); ?>
                        </select>
                    </div>
                    <div>
                        <label for="destination">Destination</label>
                        <select id="destination" name="destination" required>
                            <?php render_location_options($locations); ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid route-rate-grid">
                    <div>
                        <label for="deliveryrate">Delivery Rate</label>
                        <input id="deliveryrate" name="deliveryrate" type="number" min="0" step="0.01" value="0.00" required>
                    </div>
                    <div>
                        <label for="driversrate">Driver Rate</label>
                        <input id="driversrate" name="driversrate" type="number" min="0" step="0.01" value="0.00" required>
                    </div>
                    <div>
                        <label for="helpersrate">Helper Rate</label>
                        <input id="helpersrate" name="helpersrate" type="number" min="0" step="0.01" value="0.00" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="deliverytype">Delivery Type</label>
                        <select id="deliverytype" name="deliverytype" required>
                            <?php render_setup_options($deliveryTypes, 'deliverytypeid', 'deliverytype'); ?>
                        </select>
                    </div>
                    <div>
                        <label for="trucktype">Truck Type</label>
                        <select id="trucktype" name="trucktype" required>
                            <?php render_setup_options($truckTypes, 'trucktypeid', 'trucktype'); ?>
                        </select>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($routes as $route): ?>
    <div class="modal" id="edit-customer-route-<?php echo h($route['customerinformationid']); ?>" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-customer-route-title-<?php echo h($route['customerinformationid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-customer-route-title-<?php echo h($route['customerinformationid']); ?>">Origin and Destination</h3>
                    <p><?php echo h($route['origin']); ?> to <?php echo h($route['destination']); ?></p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="customerinformationid" value="<?php echo h($route['customerinformationid']); ?>">

                    <div class="form-grid">
                        <div>
                            <label>Origin</label>
                            <select name="origin" required>
                                <?php render_location_options($locations, $route['origin']); ?>
                            </select>
                        </div>
                        <div>
                            <label>Destination</label>
                            <select name="destination" required>
                                <?php render_location_options($locations, $route['destination']); ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid route-rate-grid">
                        <div>
                            <label>Delivery Rate</label>
                            <input name="deliveryrate" type="number" min="0" step="0.01" value="<?php echo h(route_number_value($route['deliveryrate'])); ?>" required>
                        </div>
                        <div>
                            <label>Driver Rate</label>
                            <input name="driversrate" type="number" min="0" step="0.01" value="<?php echo h(route_number_value($route['driversrate'])); ?>" required>
                        </div>
                        <div>
                            <label>Helper Rate</label>
                            <input name="helpersrate" type="number" min="0" step="0.01" value="<?php echo h(route_number_value($route['helpersrate'])); ?>" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label>Delivery Type</label>
                            <select name="deliverytype" required>
                                <?php render_setup_options($deliveryTypes, 'deliverytypeid', 'deliverytype', $route['deliverytype']); ?>
                            </select>
                        </div>
                        <div>
                            <label>Truck Type</label>
                            <select name="trucktype" required>
                                <?php render_setup_options($truckTypes, 'trucktypeid', 'trucktype', $route['trucktype']); ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-customer-route-<?php echo h($route['customerinformationid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-customer-route-title-<?php echo h($route['customerinformationid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-customer-route-title-<?php echo h($route['customerinformationid']); ?>">Delete Route</h3>
                    <p>This will remove <?php echo h($route['origin']); ?> to <?php echo h($route['destination']); ?>.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customerinformationid" value="<?php echo h($route['customerinformationid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
