<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/coordinator.php';

require_any_role(['Administrator', 'Coordinator']);

$pageTitle = 'Coordinator';
$activeNav = 'coordinator';
$returnUrl = 'administrator/coordinator.php';
$coordinatorStatus = strtolower((string) ($_GET['status'] ?? 'booked'));
$coordinatorStatus = in_array($coordinatorStatus, ['booked', 'prepared', 'dispatched'], true) ? $coordinatorStatus : 'booked';

ensure_coordinator_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $user = current_user();
    $coordinatorId = (int) ($user['id'] ?? 0);

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('administrator/coordinator.php?status=dispatched');
    }

    try {
        if ($action === 'cancel_dispatch') {
            $reference = (int) ($_POST['reference'] ?? 0);

            if (cancel_dispatched_booking($reference, $coordinatorId)) {
                flash('success', 'Dispatched operation was returned to booked routes.');
                redirect_to('administrator/coordinator.php?status=booked');
            }

            flash('error', 'Dispatched operation was not found.');
            redirect_to('administrator/coordinator.php?status=dispatched');
        }
    } catch (Throwable $error) {
        flash('error', 'Coordinator action failed: ' . $error->getMessage());
        redirect_to('administrator/coordinator.php?status=dispatched');
    }

    redirect_to('administrator/coordinator.php?status=' . $coordinatorStatus);
}

$counts = coordinator_counts();
$coordinatorSidebarCounts = $counts;
$allBookings = [];
$bookings = [];
$dispatches = [];

if ($coordinatorStatus === 'dispatched') {
    $dispatches = list_coordinator_dispatches(500);
} else {
    $allBookings = apply_plate_schedule_labels(list_coordinator_bookings());
    $bookings = $coordinatorStatus === 'prepared'
        ? array_values(array_filter($allBookings, function ($booking) {
            return !empty($booking['prep_id']);
        }))
        : $allBookings;
}

$statusLabels = [
    'booked' => 'Booked Operations',
    'prepared' => 'Prepared Operations',
    'dispatched' => 'Dispatched Operations',
];
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero coordinator-hero">
    <div>
        <p class="eyebrow">Coordinator</p>
        <h2>Booked Routes</h2>
        <p>Review booked operations and open each reference for dispatch confirmation, route/plate confirmation, and crew assignment.</p>
    </div>
    <div class="hero-actions">
        <div class="count-badges" aria-label="Coordinator overview">
            <a class="count-badge count-badge-warning <?php echo h($coordinatorStatus === 'booked' ? 'count-badge-active' : ''); ?>" href="<?php echo h(app_url('administrator/coordinator.php?status=booked')); ?>">Booked <strong><?php echo h($counts['pending'] + $counts['prepared']); ?></strong></a>
            <a class="count-badge count-badge-success <?php echo h($coordinatorStatus === 'prepared' ? 'count-badge-active' : ''); ?>" href="<?php echo h(app_url('administrator/coordinator.php?status=prepared')); ?>">Prepared <strong><?php echo h($counts['prepared']); ?></strong></a>
            <a class="count-badge <?php echo h($coordinatorStatus === 'dispatched' ? 'count-badge-active' : ''); ?>" href="<?php echo h(app_url('administrator/coordinator.php?status=dispatched')); ?>">Dispatched <strong><?php echo h($counts['dispatched']); ?></strong></a>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="coordinator-list-layout">
    <article class="panel coordinator-queue-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Manage Reservation</p>
                <h3><?php echo h($statusLabels[$coordinatorStatus]); ?></h3>
            </div>
            <span class="dispatch-counter"><?php echo h($coordinatorStatus === 'dispatched' ? count($dispatches) : count($bookings)); ?> <?php echo h($coordinatorStatus); ?></span>
        </div>

        <div class="table-wrap record-scroll">
            <?php if ($coordinatorStatus === 'dispatched'): ?>
            <table class="data-table record-table coordinator-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Dispatch Date</th>
                        <th>Booking Date</th>
                        <th>Pick-Up Date</th>
                        <th>Delivery Date</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Origin-Destination</th>
                        <th>Plate</th>
                        <th>Crew</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-page-size="10">
                    <?php $rowNumber = 0; ?>
                    <?php foreach ($dispatches as $dispatch): ?>
                        <?php $rowNumber++; ?>
                        <tr>
                            <td class="number-cell"><?php echo h($rowNumber); ?></td>
                            <td>
                                <strong><?php echo h($dispatch['dis_referenceid']); ?></strong>
                                <span><?php echo h('SN: ' . ($dispatch['shipmentnumber'] ?: 'Not encoded')); ?></span>
                            </td>
                            <td><?php echo h(booking_datetime_text($dispatch['dis_dispatched_date'])); ?></td>
                            <td><?php echo h(booking_datetime_text($dispatch['bookingdate'])); ?></td>
                            <td><?php echo h(booking_datetime_text($dispatch['pickupdate'])); ?></td>
                            <td><?php echo h(booking_datetime_text($dispatch['deliverydate'])); ?></td>
                            <td><span class="badge <?php echo (int) $dispatch['deliverytype'] === 9 ? 'badge-warning' : 'badge-info'; ?>"><?php echo h(booking_type_label($dispatch['deliverytype'])); ?></span></td>
                            <td>
                                <strong><?php echo h(coordinator_customer_text($dispatch)); ?></strong>
                                <span><?php echo h($dispatch['companyrepresentative'] ?: 'No representative'); ?></span>
                            </td>
                            <td class="coordinator-route-cell">
                                <strong><?php echo h(coordinator_route_text($dispatch['origin'] ?? '', $dispatch['destination'] ?? '')); ?></strong>
                                <small><?php echo h(coordinator_route_detail_text($dispatch)); ?></small>
                            </td>
                            <td>
                                <strong><?php echo h($dispatch['platenumber'] ?: 'No plate selected'); ?></strong>
                                <span><?php echo h($dispatch['paremarks'] ?: 'No plate remarks'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo h($dispatch['driver_count'] ? $dispatch['driver_count'] . ' driver(s): ' . $dispatch['driver_names'] : 'No drivers selected'); ?></strong>
                                <span><?php echo h(($dispatch['helper_count'] ? $dispatch['helper_count'] . ' helper(s): ' . $dispatch['helper_names'] : 'No helpers selected')); ?></span>
                            </td>
                            <td class="table-actions coordinator-actions">
                                <form method="post" action="<?php echo h(app_url('administrator/coordinator.php?status=dispatched')); ?>" class="dispatch-inline-form" onsubmit="return confirm('Return this dispatched operation to booked routes?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="cancel_dispatch">
                                    <input type="hidden" name="reference" value="<?php echo h($dispatch['dis_referenceid']); ?>">
                                    <button type="submit" class="btn btn-warning btn-sm btn-icon"><?php echo icon('arrow-left'); ?> Return to Booked</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$dispatches): ?>
                        <tr class="empty-row"><td colspan="12">No dispatched operations yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php else: ?>
            <table class="data-table record-table coordinator-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Lapsed</th>
                        <th>Booking Date</th>
                        <th>Pick-Up Date</th>
                        <th>Delivery Date</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Origin-Destination</th>
                        <th>Plate</th>
                        <th>Crew</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-page-size="10">
                    <?php $rowNumber = 0; ?>
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $rowNumber++;
                        $readiness = coordinator_readiness($booking);
                        $lapsed = booking_lapsed_label($booking['pickupdate']);
                        $manageUrl = app_url('administrator/coordinator_dispatch.php?reference=' . urlencode((string) $booking['bookingid']));
                        ?>
                        <tr>
                            <td class="number-cell"><?php echo h($rowNumber); ?></td>
                            <td>
                                <strong><?php echo h($booking['bookingid']); ?></strong>
                                <span><?php echo h('SN: ' . ($booking['shipmentnumber'] ?: 'Not encoded')); ?></span>
                            </td>
                            <td>
                                <span class="booking-lapsed booking-lapsed-<?php echo h($lapsed['tone']); ?>"><?php echo h($lapsed['label']); ?></span>
                                <span class="badge <?php echo h($readiness['class']); ?>"><?php echo h($readiness['label']); ?></span>
                            </td>
                            <td><?php echo h(booking_datetime_text($booking['bookingdate'])); ?></td>
                            <td><?php echo h(booking_datetime_text($booking['pickupdate'])); ?></td>
                            <td><?php echo h(booking_datetime_text($booking['deliverydate'])); ?></td>
                            <td><span class="badge <?php echo (int) $booking['deliverytype'] === 9 ? 'badge-warning' : 'badge-info'; ?>"><?php echo h(booking_type_label($booking['deliverytype'])); ?></span></td>
                            <td>
                                <strong><?php echo h(coordinator_customer_text($booking)); ?></strong>
                                <span><?php echo h($booking['companyrepresentative'] ?: 'No representative'); ?></span>
                            </td>
                            <td class="coordinator-route-cell">
                                <strong><?php echo h(coordinator_booking_route_text($booking)); ?></strong>
                                <small><?php echo h(coordinator_route_detail_text($booking)); ?></small>
                            </td>
                            <td>
                                <strong><?php echo h(coordinator_booking_plate_text($booking)); ?></strong>
                                <span><?php echo h(!empty($booking['prep_id']) ? 'Confirmed for dispatch' : ($booking['plate_schedule_label'] ?? 'Booked reservation plate')); ?></span>
                            </td>
                            <td>
                                <strong><?php echo h($booking['driver_count'] ? $booking['driver_count'] . ' driver(s): ' . $booking['driver_names'] : 'No drivers selected'); ?></strong>
                                <span><?php echo h(($booking['helper_count'] ? $booking['helper_count'] . ' helper(s): ' . $booking['helper_names'] : 'No helpers selected')); ?></span>
                            </td>
                            <td class="table-actions coordinator-actions">
                                <a class="btn btn-primary btn-sm btn-icon" href="<?php echo h($manageUrl); ?>"><?php echo icon('truck'); ?> Manage to Dispatch</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bookings): ?>
                        <tr class="empty-row"><td colspan="12">No <?php echo h($coordinatorStatus); ?> routes are waiting for coordinator dispatch.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </article>
</section>

<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
