<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/coordinator.php';

require_any_role(['Administrator', 'Coordinator']);

$reference = (int) ($_GET['reference'] ?? ($_POST['reference'] ?? 0));
$pageTitle = 'Manage Dispatch';
$activeNav = 'coordinator';
$returnUrl = 'Coordinator/index.php';
$dispatchUrl = 'Coordinator/dispatch.php?reference=' . urlencode((string) $reference);

ensure_coordinator_schema();

if ($reference <= 0) {
    flash('error', 'Select a booked operation to manage for dispatch.');
    redirect_to($returnUrl);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $user = current_user();
    $coordinatorId = (int) ($user['id'] ?? 0);

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to($dispatchUrl);
    }

    $data = [
        'reference' => $reference,
        'dispatch_date' => $_POST['dispatch_date'] ?? '',
        'route_id' => $_POST['route_id'] ?? '',
        'plate_id' => $_POST['plate_id'] ?? '',
        'driver_ids' => $_POST['driver_ids'] ?? [],
        'helper_ids' => $_POST['helper_ids'] ?? [],
    ];

    try {
        if ($action === 'save_confirmation') {
            save_dispatch_preparation($data, $coordinatorId, false);
            flash('success', 'Dispatch confirmation was saved.');
            redirect_to($dispatchUrl);
        }

        if ($action === 'dispatch_now') {
            save_and_dispatch_booking($data, $coordinatorId);
            flash('success', 'Booked operation was dispatched.');
            redirect_to($returnUrl);
        }
    } catch (Throwable $error) {
        flash('error', 'Dispatch action failed: ' . $error->getMessage());
        redirect_to($dispatchUrl);
    }

    redirect_to($dispatchUrl);
}

$booking = find_coordinator_booking_detail($reference);

if (!$booking) {
    flash('error', 'Booked operation was not found or has already been dispatched.');
    redirect_to($returnUrl);
}

$selectedRouteId = (int) (!empty($booking['prep_id']) ? $booking['prep_ods'] : $booking['origindestination']);
$selectedPlateId = (int) (!empty($booking['prep_id']) ? $booking['prep_plaka'] : $booking['reservedplate']);
$selectedDriverIds = coordinator_csv_ids($booking['driver_ids'] ?? '');
$selectedHelperIds = coordinator_csv_ids($booking['helper_ids'] ?? '');
$dispatchValue = booking_datetime_value($booking['prep_dispatched_date'] ?: $booking['pickupdate']);
$customerRoutes = list_customer_routes_for_customer((int) $booking['customername']);
$fleetOptions = list_booking_fleet_options();
$drivers = list_dispatch_people('1');
$helpers = list_dispatch_people('2');
$readiness = coordinator_readiness($booking);
$lapsed = booking_lapsed_label($booking['pickupdate']);
$canConfirm = $fleetOptions;
$canDispatch = $fleetOptions && $drivers && $helpers;
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero coordinator-hero dispatch-manage-hero">
    <div>
        <p class="eyebrow">Coordinator</p>
        <h2>Manage to Dispatch</h2>
        <p>Confirm the dispatch data for reference <?php echo h($booking['bookingid']); ?> before moving this booked operation to active dispatch.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light btn-icon" href="<?php echo h(app_url($returnUrl)); ?>"><?php echo icon('arrow-left'); ?> Back to Booked Routes</a>
        <div class="count-badges">
            <span class="count-badge"><?php echo h(booking_type_label($booking['deliverytype'])); ?></span>
            <span class="count-badge count-badge-warning"><?php echo h($lapsed['label']); ?></span>
            <span class="count-badge <?php echo h(str_replace('badge', 'count-badge', $readiness['class'])); ?>"><?php echo h($readiness['label']); ?></span>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<?php if (!$canDispatch): ?>
    <div class="alert alert-error" role="alert">Complete fleet, driver, and helper records before final dispatch.</div>
<?php endif; ?>

<section class="dispatch-page-layout">
    <aside class="panel dispatch-booking-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Booked Operation</p>
                <h3>Reference <?php echo h($booking['bookingid']); ?></h3>
            </div>
            <span class="badge <?php echo h($readiness['class']); ?>"><?php echo h($readiness['label']); ?></span>
        </div>

        <dl class="dispatch-booking-list">
            <div>
                <dt>Shipment Number</dt>
                <dd><?php echo h($booking['shipmentnumber'] ?: 'Not encoded'); ?></dd>
            </div>
            <div>
                <dt>Customer</dt>
                <dd><?php echo h(coordinator_customer_text($booking)); ?></dd>
            </div>
            <div>
                <dt>Booked Origin-Destination</dt>
                <dd><?php echo h(coordinator_route_text($booking['origin'] ?? '', $booking['destination'] ?? '')); ?></dd>
            </div>
            <div>
                <dt>Booked Route Details</dt>
                <dd><?php echo h(coordinator_route_detail_text($booking)); ?></dd>
            </div>
            <div class="dispatch-booking-split">
                <div>
                    <dt>Booking Date</dt>
                    <dd><?php echo h(booking_datetime_text($booking['bookingdate'])); ?></dd>
                </div>
                <div>
                    <dt>Pick-Up Date</dt>
                    <dd><?php echo h(booking_datetime_text($booking['pickupdate'])); ?></dd>
                </div>
            </div>
            <div>
                <dt>Delivery Date</dt>
                <dd><?php echo h(booking_datetime_text($booking['deliverydate'])); ?></dd>
            </div>
            <div>
                <dt>Suggested Plate</dt>
                <dd><?php echo h($booking['platenumber'] ?: 'No plate selected'); ?></dd>
            </div>
            <div>
                <dt>Representative</dt>
                <dd><?php echo h($booking['companyrepresentative'] ?: 'No representative'); ?></dd>
            </div>
        </dl>
    </aside>

    <article class="panel dispatch-confirm-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Data Confirmation</p>
                <h3>Dispatch Details</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url($dispatchUrl)); ?>" class="dispatch-manage-form" data-dispatch-manage-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reference" value="<?php echo h($booking['bookingid']); ?>">

            <section class="dispatch-form-section">
                <div class="booking-section-title">
                    <span>Operation Confirmation</span>
                </div>

                <div class="dispatch-fixed-field">
                    <span>Customer</span>
                    <strong><?php echo h(coordinator_customer_text($booking)); ?></strong>
                    <small>Customer cannot be changed at this stage.</small>
                </div>

                <div class="form-grid dispatch-date-grid">
                    <div>
                        <label for="dispatch-date">Dispatch Date</label>
                        <input id="dispatch-date" name="dispatch_date" type="datetime-local" value="<?php echo h($dispatchValue); ?>" required>
                    </div>
                    <div>
                        <label for="dispatch-plate">Plate Number</label>
                        <select id="dispatch-plate" name="plate_id" required>
                            <option value="">Select plate</option>
                            <?php foreach ($fleetOptions as $fleet): ?>
                                <?php
                                $fleetId = (int) $fleet['fleetid'];
                                $isSelectedPlate = $fleetId === $selectedPlateId;
                                $isDispatched = (int) ($fleet['dispatch_count'] ?? 0) > 0;
                                ?>
                                <option value="<?php echo h($fleetId); ?>"<?php echo coordinator_selected($fleetId, $selectedPlateId); ?> <?php echo $isDispatched && !$isSelectedPlate ? 'disabled' : ''; ?>>
                                    <?php echo h(coordinator_plate_option_label($fleet)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="dispatch-route">Origin and Destination</label>
                    <select id="dispatch-route" name="route_id" required>
                        <option value="">Select route</option>
                        <?php foreach ($customerRoutes as $route): ?>
                            <option value="<?php echo h($route['customerinformationid']); ?>"<?php echo coordinator_selected($route['customerinformationid'], $selectedRouteId); ?>>
                                <?php echo h(coordinator_route_option_label($route)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="dispatch-form-section">
                <div class="booking-section-title">
                    <span>Driver and Helpers</span>
                </div>

                <div class="dispatch-crew-grid">
                    <div class="dispatch-helper-picker" data-dispatch-crew-group>
                        <div class="dispatch-helper-title">
                            <label for="dispatch-driver">Drivers</label>
                            <span><strong data-dispatch-selected-count><?php echo h(count($selectedDriverIds)); ?></strong> selected</span>
                        </div>
                        <div class="dispatch-select2-wrap">
                            <select id="dispatch-driver" class="js-example-basic-multiple" name="driver_ids[]" multiple="multiple" data-placeholder="Search drivers" data-dispatch-multi-select data-dispatch-preview-field="driver" <?php echo !$drivers ? 'disabled' : ''; ?>>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo h($driver['employee_id']); ?>"<?php echo in_array((int) $driver['employee_id'], array_map('intval', $selectedDriverIds), true) ? ' selected' : ''; ?>>
                                        <?php echo h(coordinator_person_option_label($driver)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$drivers): ?>
                                <p class="empty-panel-copy">No drivers are encoded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dispatch-helper-picker" data-dispatch-crew-group data-dispatch-helper-group>
                        <div class="dispatch-helper-title">
                            <label for="dispatch-helpers">Helpers</label>
                            <span><strong data-dispatch-selected-count data-dispatch-helper-count><?php echo h(count($selectedHelperIds)); ?></strong> selected</span>
                        </div>
                        <div class="dispatch-select2-wrap">
                            <select id="dispatch-helpers" class="js-example-basic-multiple" name="helper_ids[]" multiple="multiple" data-placeholder="Search helpers" data-dispatch-multi-select data-dispatch-helper-select data-dispatch-preview-field="helpers" <?php echo !$helpers ? 'disabled' : ''; ?>>
                            <?php foreach ($helpers as $helper): ?>
                                <option value="<?php echo h($helper['employee_id']); ?>"<?php echo in_array((int) $helper['employee_id'], array_map('intval', $selectedHelperIds), true) ? ' selected' : ''; ?>>
                                    <?php echo h(coordinator_person_option_label($helper)); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                            <?php if (!$helpers): ?>
                                <p class="empty-panel-copy">No helpers are encoded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="dispatch-manage-actions">
                <a class="btn btn-light" href="<?php echo h(app_url($returnUrl)); ?>">Cancel</a>
                <button type="submit" name="action" value="save_confirmation" class="btn btn-light btn-icon" <?php echo !$canConfirm ? 'disabled' : ''; ?>><?php echo icon('save'); ?> Save Confirmation</button>
                <button type="submit" name="action" value="dispatch_now" class="btn btn-primary btn-icon" data-dispatch-preview-open="dispatch-preview-modal" <?php echo !$canDispatch ? 'disabled' : ''; ?>><?php echo icon('truck'); ?> Dispatch Now</button>
            </div>

            <div class="modal dispatch-preview-modal" id="dispatch-preview-modal" hidden role="dialog" aria-modal="true" aria-labelledby="dispatch-preview-title">
                <div class="modal-card dispatch-preview-card">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow">Confirm Dispatch</p>
                            <h3 id="dispatch-preview-title">Review Before Dispatch</h3>
                        </div>
                        <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <dl class="dispatch-preview-list">
                            <div>
                                <dt>Reference</dt>
                                <dd><?php echo h($booking['bookingid']); ?></dd>
                            </div>
                            <div>
                                <dt>Customer</dt>
                                <dd><?php echo h(coordinator_customer_text($booking)); ?></dd>
                            </div>
                            <div>
                                <dt>Dispatch Date</dt>
                                <dd data-dispatch-preview="dispatch_date">Not set</dd>
                            </div>
                            <div>
                                <dt>Plate Number</dt>
                                <dd data-dispatch-preview="plate">Not selected</dd>
                            </div>
                            <div>
                                <dt>Origin and Destination</dt>
                                <dd data-dispatch-preview="route">Not selected</dd>
                            </div>
                            <div>
                                <dt>Drivers</dt>
                                <dd data-dispatch-preview="driver">Not selected</dd>
                            </div>
                            <div>
                                <dt>Helpers</dt>
                                <dd data-dispatch-preview="helpers">No helpers selected</dd>
                            </div>
                        </dl>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                            <button type="submit" name="action" value="dispatch_now" class="btn btn-primary btn-icon"><?php echo icon('truck'); ?> Confirm Dispatch</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </article>
</section>

<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
