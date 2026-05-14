<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/customer_service.php';

require_any_role(['Administrator', 'Customer Service']);

$pageTitle = 'Customer Service';
$activeNav = 'customer_service';
$returnUrl = 'Customer%20Service/index.php';

function booking_checked($value, $current)
{
    return (string) $value === (string) $current ? ' checked' : '';
}

function booking_flash_cancel_result(array $result)
{
    if ($result['selected'] === 0) {
        flash('error', 'Select at least one booking to cancel.');
        return;
    }

    if ($result['deleted'] > 0) {
        flash('success', $result['deleted'] . ' booking' . ($result['deleted'] === 1 ? ' was' : 's were') . ' canceled.');
    }

    $notCanceled = (int) $result['blocked'] + (int) $result['missing'];

    if ($notCanceled > 0) {
        $message = $notCanceled . ' selected booking' . ($notCanceled === 1 ? ' was' : 's were') . ' not canceled.';

        if ($result['errors']) {
            $message .= ' ' . implode(' ', array_unique($result['errors']));
        }

        flash('error', $message);
    }
}

function booking_customer_tokens(array $availability, $customerId)
{
    return $availability[(int) $customerId] ?? '';
}

ensure_customer_service_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to($returnUrl);
    }

    try {
        if ($action === 'create_booking') {
            $data = [
                'booking_type' => $_POST['booking_type'] ?? 'per_trip',
                'booking_reference' => $_POST['booking_reference'] ?? '',
                'booking_date' => $_POST['booking_date'] ?? '',
                'shipment_number' => $_POST['shipment_number'] ?? '',
                'customer_id' => $_POST['customer_id'] ?? '',
                'route_id' => $_POST['route_id'] ?? '',
                'representative' => $_POST['representative'] ?? '',
                'pickup_date' => $_POST['pickup_date'] ?? '',
                'delivery_date' => $_POST['delivery_date'] ?? '',
                'reserved_plate' => $_POST['reserved_plate'] ?? '',
            ];
            $errors = validate_booking_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                create_booking($data);
                flash('success', 'Booking reservation created.');
            }
        } elseif ($action === 'cancel_booking') {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);

            if (!find_booking_by_id($bookingId)) {
                flash('error', 'Booking record was not found.');
            } elseif (cancel_booking($bookingId)) {
                flash('success', 'Booking reservation canceled.');
            } else {
                flash('error', 'Booking reservation could not be canceled.');
            }
        } elseif ($action === 'bulk_cancel_bookings') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_booking_by_id', function ($bookingId) {
                return cancel_booking($bookingId);
            });

            booking_flash_cancel_result($result);
        }
    } catch (Throwable $error) {
        flash('error', 'Customer Service action failed: ' . $error->getMessage());
    }

    redirect_to($returnUrl);
}

$typeOptions = booking_type_options();
$selectedBookingType = $_GET['type'] ?? 'per_trip';
$selectedBookingType = array_key_exists($selectedBookingType, $typeOptions) ? $selectedBookingType : 'per_trip';
$customers = list_booking_customers();
$routes = list_booking_routes();
$customerTypeAvailability = booking_customer_type_availability($routes);
$fleetOptions = list_booking_fleet_options();
$bookings = apply_plate_schedule_labels(list_active_bookings());
$counts = booking_counts();
$crewCounts = booking_crew_counts();
$setupWarnings = booking_setup_warnings();
$canCreateBooking = $customers && $routes && $fleetOptions;
$defaultBookingDate = date('Y-m-d\TH:i');
$defaultPickupDate = date('Y-m-d\TH:i', strtotime('+1 day 08:00'));
$defaultDeliveryDate = date('Y-m-d\TH:i', strtotime('+1 day 17:00'));
$referencePreview = generate_booking_reference();
$setupBadge = !$canCreateBooking ? 'badge-danger' : ($setupWarnings ? 'badge-warning' : 'badge-success');
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero customer-service-hero booking-hero">
    <div>
        <p class="eyebrow">Customer Service</p>
        <h2>Booking Management</h2>
        <p>Manage active reservations, create Per Trip and Lock In bookings, and keep plate, route, shipment, and crew details visible before dispatch.</p>
    </div>
    <div class="hero-actions">
        <div class="booking-create-actions" aria-label="Create reservation">
            <button type="button" class="btn btn-primary btn-icon" data-modal-open="bookingReservationModal" data-booking-modal-type="per_trip" <?php echo !$canCreateBooking ? 'disabled' : ''; ?>><?php echo icon('plus'); ?> Per Trip</button>
            <button type="button" class="btn btn-warning btn-icon" data-modal-open="bookingReservationModal" data-booking-modal-type="lock_in" <?php echo !$canCreateBooking ? 'disabled' : ''; ?>><?php echo icon('key'); ?> Lock In</button>
        </div>
        <div class="count-badges" aria-label="Booking overview">
            <span class="count-badge">Active <strong><?php echo h($counts['active']); ?></strong></span>
            <span class="count-badge count-badge-warning">Pickup Due <strong><?php echo h($counts['pickup_due']); ?></strong></span>
            <span class="count-badge count-badge-muted">Canceled <strong><?php echo h($counts['canceled']); ?></strong></span>
        </div>
        <div class="count-badges booking-summary-badges" aria-label="Customer service summary">
            <span class="count-badge count-badge-success">Booking <strong><?php echo h($counts['active']); ?></strong></span>
            <span class="count-badge">Dispatched <strong><?php echo h($counts['dispatched']); ?></strong></span>
            <span class="count-badge count-badge-muted">Driver / Helper <strong><?php echo h($crewCounts['drivers']); ?> / <?php echo h($crewCounts['helpers']); ?></strong></span>
            <span class="count-badge <?php echo h(str_replace('badge', 'count-badge', $setupBadge)); ?>" id="booking-checks">Booking Checks <strong><?php echo h(count($setupWarnings)); ?></strong></span>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="booking-management-layout">
    <article class="panel booking-board-panel" id="active-bookings">
        <div class="panel-header booking-board-header">
            <div>
                <p class="eyebrow">Manage Reservation</p>
                <h3>Active Bookings</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="bookings" data-bulk-delete-action="Cancel">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_cancel_bookings">

            <div class="bulk-table-toolbar">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" data-bulk-delete-button disabled><?php echo icon('trash'); ?> Cancel Selected</button>
                <span data-bulk-delete-count>0 selected</span>
            </div>

            <div class="table-wrap record-scroll">
                <table class="data-table record-table booking-record-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all bookings"></th>
                            <th>Reference</th>
                            <th>Lapsed</th>
                            <th>Booking Date</th>
                            <th>Pick-Up Date</th>
                            <th>Delivery Date</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Origin-Destination</th>
                            <th>Plate</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-page-size="10">
                        <?php $rowNumber = 0; ?>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $rowNumber++;
                            $status = booking_status_badge($booking['pickupdate'], $booking['deliverydate']);
                            $lapsed = booking_lapsed_label($booking['pickupdate']);
                            ?>
                            <tr>
                                <td class="number-cell"><?php echo h($rowNumber); ?></td>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($booking['bookingidauto']); ?>" data-bulk-delete-item aria-label="Select booking <?php echo h($booking['bookingid']); ?>"></td>
                                <td>
                                    <strong><?php echo h($booking['bookingid']); ?></strong>
                                    <span><?php echo h('SN: ' . ($booking['shipmentnumber'] ?: 'Not encoded')); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo h($status['class']); ?>"><?php echo h($status['label']); ?></span>
                                    <span class="booking-lapsed booking-lapsed-<?php echo h($lapsed['tone']); ?>"><?php echo h($lapsed['label']); ?></span>
                                </td>
                                <td><?php echo h(booking_datetime_text($booking['bookingdate'])); ?></td>
                                <td><?php echo h(booking_datetime_text($booking['pickupdate'])); ?></td>
                                <td><?php echo h(booking_datetime_text($booking['deliverydate'])); ?></td>
                                <td><span class="badge <?php echo (int) $booking['deliverytype'] === 9 ? 'badge-warning' : 'badge-info'; ?>"><?php echo h(booking_type_label($booking['deliverytype'])); ?></span></td>
                                <td>
                                    <strong><?php echo h(($booking['soa'] ? '[' . $booking['soa'] . '] ' : '') . ($booking['customer_label'] ?: 'Unknown customer')); ?></strong>
                                    <span><?php echo h($booking['companyrepresentative'] ?: 'No representative'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo h(($booking['origin'] ?: 'Unknown origin') . ' to ' . ($booking['destination'] ?: 'Unknown destination')); ?></strong>
                                    <span><?php echo h(($booking['trucktype_name'] ?: 'Truck type pending') . ' / ' . ($booking['deliverytype_name'] ?: 'Delivery type pending')); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo h($booking['platenumber'] ?: 'No plate'); ?></strong>
                                    <?php if (!empty($booking['plate_schedule_label'])): ?>
                                        <span class="plate-schedule-label"><?php echo h($booking['plate_schedule_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button type="submit" form="cancel-booking-<?php echo h($booking['bookingidauto']); ?>" class="btn btn-danger btn-sm btn-icon"><?php echo icon('trash'); ?> Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bookings): ?>
                            <tr class="empty-row"><td colspan="12">No active bookings yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php foreach ($bookings as $booking): ?>
            <form id="cancel-booking-<?php echo h($booking['bookingidauto']); ?>" method="post" action="<?php echo h(app_url($returnUrl)); ?>" hidden>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel_booking">
                <input type="hidden" name="booking_id" value="<?php echo h($booking['bookingidauto']); ?>">
            </form>
        <?php endforeach; ?>
    </article>
</section>

<div class="modal booking-modal" id="bookingReservationModal" data-booking-workspace aria-labelledby="bookingReservationTitle" hidden>
    <div class="modal-card booking-modal-card">
        <div class="modal-header">
            <div class="booking-modal-header-main">
                <div class="booking-modal-title-group">
                    <p class="eyebrow">Reservation Form</p>
                    <h3 id="bookingReservationTitle"><span data-booking-preview="type">Per Trip</span> Reservation</h3>
                </div>
                <div class="booking-modal-reference" aria-label="Reference number">
                    <span>Reference No.</span>
                    <strong data-booking-preview="reference"><?php echo h($referencePreview); ?></strong>
                </div>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close reservation form">&times;</button>
        </div>

        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="booking-modal-form booking-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_booking">
                <input type="hidden" name="booking_reference" value="<?php echo h($referencePreview); ?>" data-booking-reference-field>

                <div class="booking-entry-column">
                    <section class="booking-form-section booking-type-section" hidden>
                        <div class="booking-section-title">
                            <span>Reservation Type</span>
                        </div>
                        <fieldset class="booking-type-toggle" aria-label="Booking type">
                            <?php foreach ($typeOptions as $key => $option): ?>
                                <label>
                                    <input type="radio" name="booking_type" value="<?php echo h($key); ?>"<?php echo booking_checked($key, $selectedBookingType); ?> data-booking-type>
                                    <span>
                                        <strong><?php echo h($option['label']); ?></strong>
                                        <small><?php echo h($option['detail']); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </section>

                    <section class="booking-form-section">
                        <div class="booking-section-title">
                            <span>Booking Details</span>
                        </div>
                        <div class="form-grid">
                            <div>
                                <label for="booking-date">Booking Date</label>
                                <input id="booking-date" name="booking_date" type="datetime-local" value="<?php echo h($defaultBookingDate); ?>" required data-booking-preview-source="booking_date">
                            </div>
                            <div>
                                <label for="shipment-number">Shipment Number</label>
                                <input id="shipment-number" name="shipment_number" type="text" maxlength="60" required data-booking-preview-source="shipment_number">
                            </div>
                        </div>
                    </section>

                    <section class="booking-form-section">
                        <div class="booking-section-title">
                            <span>Customer And Route</span>
                        </div>
                        <div class="booking-route-fields">
                            <div>
                                <label for="customer-id">Customer</label>
                                <select id="customer-id" name="customer_id" required data-booking-customer data-booking-preview-source="customer">
                                    <option value="">Select customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <?php $tokens = booking_customer_tokens($customerTypeAvailability, $customer['customerid']); ?>
                                        <option value="<?php echo h($customer['customerid']); ?>" data-booking-types="<?php echo h($tokens); ?>" <?php echo $tokens === '' ? 'disabled' : ''; ?>>
                                            <?php echo h('[' . $customer['soa'] . '] ' . $customer['customername']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="route-id">Origin and Destination</label>
                                <select id="route-id" name="route_id" required data-booking-route-select data-booking-preview-source="route">
                                    <option value="">Select a route</option>
                                    <?php foreach ($routes as $route): ?>
                                        <?php $routeType = booking_type_key_from_code($route['deliverytype']); ?>
                                        <option
                                            value="<?php echo h($route['customerinformationid']); ?>"
                                            data-customer-id="<?php echo h($route['customerid']); ?>"
                                            data-booking-type="<?php echo h($routeType); ?>"
                                            data-origin="<?php echo h($route['origin']); ?>"
                                            data-destination="<?php echo h($route['destination']); ?>"
                                            data-truck-type="<?php echo h($route['trucktype_name']); ?>"
                                            data-delivery-type="<?php echo h($route['deliverytype_name']); ?>"
                                            data-delivery-rate="<?php echo h(number_format((float) $route['deliveryrate'], 2)); ?>"
                                            data-driver-rate="<?php echo h(number_format((float) $route['driversrate'], 2)); ?>"
                                            data-helper-rate="<?php echo h(number_format((float) $route['helpersrate'], 2)); ?>"
                                        >
                                            <?php echo h('[' . $route['origin'] . '] to [' . $route['destination'] . '] / ' . $route['trucktype_name'] . ' / ' . $route['deliverytype_name'] . ' / DD: ' . number_format((float) $route['deliveryrate'], 2)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="representative">Company Representative / Contact</label>
                                <input id="representative" name="representative" type="text" maxlength="80" data-booking-preview-source="representative">
                            </div>
                        </div>
                    </section>

                    <section class="booking-form-section">
                        <div class="booking-section-title">
                            <span>Schedule And Fleet</span>
                        </div>
                        <div class="form-grid route-rate-grid">
                            <div>
                                <label for="pickup-date">Pick-Up Date</label>
                                <input id="pickup-date" name="pickup_date" type="datetime-local" value="<?php echo h($defaultPickupDate); ?>" required data-booking-preview-source="pickup_date">
                            </div>
                            <div>
                                <label for="delivery-date">Delivery Date</label>
                                <input id="delivery-date" name="delivery_date" type="datetime-local" value="<?php echo h($defaultDeliveryDate); ?>" required data-booking-preview-source="delivery_date">
                            </div>
                            <div>
                                <label for="reserved-plate">Reserve Plate Number</label>
                                <select id="reserved-plate" name="reserved_plate" required data-booking-preview-source="plate">
                                    <option value="">Select a fleet unit</option>
                                    <?php foreach ($fleetOptions as $fleet): ?>
                                        <?php
                                        $activeCount = (int) ($fleet['active_booking_count'] ?? 0);
                                        $dispatchCount = (int) ($fleet['dispatch_count'] ?? 0);
                                        $activeLabel = $activeCount . ' active booking' . ($activeCount === 1 ? '' : 's');
                                        $plateNote = '';

                                        if ($dispatchCount > 0) {
                                            $availability = 'Dispatched - unavailable';
                                            $plateNote = 'This unit is already dispatched and cannot be reserved.';
                                        } elseif ($activeCount > 0) {
                                            $availability = 'Reserved - reuse allowed (' . $activeLabel . ')';
                                            $plateNote = 'This unit already has ' . $activeLabel . '. Reuse is allowed for this reservation.';
                                        } else {
                                            $availability = 'Available';
                                        }
                                        ?>
                                        <option
                                            value="<?php echo h($fleet['fleetid']); ?>"
                                            data-active-count="<?php echo h($activeCount); ?>"
                                            data-dispatch-count="<?php echo h($dispatchCount); ?>"
                                            data-truck-type="<?php echo h($fleet['trucktype_name'] ?: 'Truck type pending'); ?>"
                                            data-plate-note="<?php echo h($plateNote); ?>"
                                            <?php echo $dispatchCount > 0 ? 'disabled' : ''; ?>
                                        >
                                            <?php echo h($fleet['platenumber'] . ' / ' . (clean_text($fleet['paremarks'] ?? '') ?: 'No plate remarks') . ' / ' . $availability); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="booking-plate-note" data-booking-plate-note hidden></small>
                            </div>
                        </div>
                    </section>

                    <?php if (!$canCreateBooking): ?>
                        <div class="alert alert-error" role="alert">Complete active customers, customer routes, and fleet records before creating bookings.</div>
                    <?php endif; ?>

                    <div class="modal-actions booking-modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="reset" class="btn btn-light">Reset</button>
                        <button type="submit" class="btn btn-primary btn-icon" <?php echo !$canCreateBooking ? 'disabled' : ''; ?>><?php echo icon('save'); ?> Book Now</button>
                    </div>
                </div>

                <aside class="booking-preview-panel booking-modal-preview">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">Preview</p>
                            <h3>Reservation Summary</h3>
                        </div>
                        <span class="badge badge-info" data-booking-preview="type">Per Trip</span>
                    </div>
                    <div class="booking-manifest-preview">
                        <div class="booking-manifest-meta">
                            <span><small>Reference</small><strong data-booking-preview="reference"><?php echo h($referencePreview); ?></strong></span>
                            <span><small>Shipment</small><strong data-booking-preview="shipment_number">Not encoded</strong></span>
                        </div>

                        <section class="booking-manifest-section">
                            <h4>Customer</h4>
                            <p data-booking-preview="customer">Select a customer</p>
                        </section>

                        <section class="booking-manifest-section booking-manifest-route">
                            <h4>Route</h4>
                            <div data-booking-preview="route">Select a route</div>
                        </section>

                        <div class="booking-manifest-grid">
                            <section class="booking-manifest-section">
                                <h4>Schedule</h4>
                                <dl class="booking-manifest-pairs">
                                    <div>
                                        <dt>Pick-up</dt>
                                        <dd data-booking-preview="pickup_date">Not set</dd>
                                    </div>
                                    <div>
                                        <dt>Delivery</dt>
                                        <dd data-booking-preview="delivery_date">Not set</dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="booking-manifest-section">
                                <h4>Assignment</h4>
                                <dl class="booking-manifest-pairs">
                                    <div>
                                        <dt>Plate</dt>
                                        <dd data-booking-preview="plate">Select a fleet unit</dd>
                                    </div>
                                    <div>
                                        <dt>Rep</dt>
                                        <dd data-booking-preview="representative">Not encoded</dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>
                    <div class="booking-rate-strip" aria-label="Selected route rates">
                        <div>
                            <span>Delivery</span>
                            <strong data-booking-preview="delivery_rate">0.00</strong>
                        </div>
                        <div>
                            <span>Driver</span>
                            <strong data-booking-preview="driver_rate">0.00</strong>
                        </div>
                        <div>
                            <span>Helper</span>
                            <strong data-booking-preview="helper_rate">0.00</strong>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
