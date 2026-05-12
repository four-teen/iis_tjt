<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/coordinator.php';

require_any_role(['Administrator', 'Coordinator']);

$pageTitle = 'Coordinator';
$activeNav = 'coordinator';
$returnUrl = 'administrator/coordinator.php';

ensure_coordinator_schema();

$bookings = apply_plate_schedule_labels(list_coordinator_bookings());
$counts = coordinator_counts();
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
            <span class="count-badge count-badge-warning">Booked <strong><?php echo h($counts['pending'] + $counts['prepared']); ?></strong></span>
            <span class="count-badge count-badge-success">Prepared <strong><?php echo h($counts['prepared']); ?></strong></span>
            <span class="count-badge">Dispatched <strong><?php echo h($counts['dispatched']); ?></strong></span>
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
                <h3>Booked Operations</h3>
            </div>
            <span class="dispatch-counter"><?php echo h(count($bookings)); ?> booked</span>
        </div>

        <div class="table-wrap record-scroll">
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
                                <strong><?php echo h($booking['driver_names'] ?: 'No driver selected'); ?></strong>
                                <span><?php echo h(($booking['helper_count'] ? $booking['helper_count'] . ' helper(s): ' . $booking['helper_names'] : 'No helpers selected')); ?></span>
                            </td>
                            <td class="table-actions coordinator-actions">
                                <a class="btn btn-primary btn-sm btn-icon" href="<?php echo h($manageUrl); ?>"><?php echo icon('truck'); ?> Manage to Dispatch</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bookings): ?>
                        <tr class="empty-row"><td colspan="12">No booked routes are waiting for coordinator dispatch.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
