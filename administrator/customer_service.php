<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/customer_service.php';

require_any_role(['Administrator', 'Customer Service']);

$pageTitle = 'Customer Service';
$activeNav = 'customer_service';
$returnUrl = 'administrator/customer_service.php';

function booking_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

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

ensure_customer_service_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$fleetOptions = list_booking_fleet_options();
$bookings = list_active_bookings();
$counts = booking_counts();
$crewCounts = booking_crew_counts();
$canCreateBooking = $customers && $routes && $fleetOptions;
$defaultBookingDate = date('Y-m-d\TH:i');
$defaultPickupDate = date('Y-m-d\TH:i', strtotime('+1 day 08:00'));
$defaultDeliveryDate = date('Y-m-d\TH:i', strtotime('+1 day 17:00'));
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero customer-service-hero">
    <div>
        <p class="eyebrow">Customer Service</p>
        <h2>Booking Workspace</h2>
        <p>Create Per Trip and Lock In reservations with route validation, plate conflict checks, and a live active booking board.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light btn-icon" href="#active-bookings"><?php echo icon('clipboard'); ?> Active Bookings</a>
        <div class="count-badges" aria-label="Booking overview">
            <span class="count-badge">Active <strong><?php echo h($counts['active']); ?></strong></span>
            <span class="count-badge count-badge-warning">Pickup Due <strong><?php echo h($counts['pickup_due']); ?></strong></span>
            <span class="count-badge count-badge-muted">Canceled <strong><?php echo h($counts['canceled']); ?></strong></span>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="cs-metric-grid" aria-label="Customer service metrics">
    <article class="cs-metric-card cs-metric-primary">
        <span>Bookings Today</span>
        <strong><?php echo h($counts['pickup_today']); ?></strong>
        <small>Reservations with pickup scheduled today.</small>
    </article>
    <article class="cs-metric-card">
        <span>Active Customers</span>
        <strong><?php echo h(count($customers)); ?></strong>
        <small>Customer accounts ready for booking.</small>
    </article>
    <article class="cs-metric-card">
        <span>Routes Ready</span>
        <strong><?php echo h(count($routes)); ?></strong>
        <small>Origin-destination and rate combinations.</small>
    </article>
    <article class="cs-metric-card">
        <span>Crew Pool</span>
        <strong><?php echo h($crewCounts['drivers']); ?> / <?php echo h($crewCounts['helpers']); ?></strong>
        <small>Drivers and helpers encoded in the system.</small>
    </article>
</section>

<section class="customer-service-grid" data-booking-workspace>
    <article class="panel booking-form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">New Reservation</p>
                <h3>Create Booking</h3>
            </div>
            <span class="badge <?php echo $canCreateBooking ? 'badge-success' : 'badge-danger'; ?>"><?php echo $canCreateBooking ? 'Ready' : 'Setup Required'; ?></span>
        </div>
        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form booking-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_booking">

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

            <div class="form-grid">
                <div>
                    <label for="customer-id">Customer</label>
                    <select id="customer-id" name="customer_id" required data-booking-customer data-booking-preview-source="customer">
                        <?php if (!$customers): ?>
                            <option value="">No active customers</option>
                        <?php endif; ?>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo h($customer['customerid']); ?>"><?php echo h('[' . $customer['soa'] . '] ' . $customer['customername']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="route-id">Origin and Destination</label>
                    <select id="route-id" name="route_id" required data-booking-route-select data-booking-preview-source="route">
                        <option value="">Select a route</option>
                        <?php foreach ($routes as $route): ?>
                            <?php $routeType = ((int) $route['deliverytype'] === 9) ? 'lock_in' : 'per_trip'; ?>
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
                                <?php echo h($route['origin'] . ' to ' . $route['destination'] . ' / ' . $route['trucktype_name'] . ' / ' . $route['deliverytype_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="representative">Company Representative / Contact</label>
                <input id="representative" name="representative" type="text" maxlength="80" data-booking-preview-source="representative">
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
                        <?php if (!$fleetOptions): ?>
                            <option value="">No fleet records</option>
                        <?php endif; ?>
                        <?php foreach ($fleetOptions as $fleet): ?>
                            <option value="<?php echo h($fleet['fleetid']); ?>">
                                <?php echo h($fleet['platenumber'] . ' / ' . (clean_text($fleet['paremarks'] ?? '') ?: 'No plate remarks') . ' / ' . ((int) $fleet['active_booking_count'] > 0 ? $fleet['active_booking_count'] . ' active booking' : 'Available')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!$canCreateBooking): ?>
                <div class="alert alert-error" role="alert">Complete active customers, customer routes, and fleet records before creating bookings.</div>
            <?php endif; ?>

            <div class="modal-actions">
                <button type="reset" class="btn btn-light">Reset</button>
                <button type="submit" class="btn btn-primary btn-icon" <?php echo !$canCreateBooking ? 'disabled' : ''; ?>><?php echo icon('save'); ?> Save Booking</button>
            </div>
        </form>
    </article>

    <aside class="panel booking-preview-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Preview</p>
                <h3>Reservation Summary</h3>
            </div>
            <span class="badge badge-info" data-booking-preview="type">Per Trip</span>
        </div>
        <dl class="booking-preview-list">
            <div>
                <dt>Shipment Number</dt>
                <dd data-booking-preview="shipment_number">Not encoded</dd>
            </div>
            <div>
                <dt>Customer</dt>
                <dd data-booking-preview="customer">Select a customer</dd>
            </div>
            <div>
                <dt>Origin / Destination</dt>
                <dd data-booking-preview="route">Select a route</dd>
            </div>
            <div>
                <dt>Reserved Plate</dt>
                <dd data-booking-preview="plate">Select a fleet unit</dd>
            </div>
            <div>
                <dt>Pickup / Delivery</dt>
                <dd><span data-booking-preview="pickup_date">Not set</span><span data-booking-preview="delivery_date">Not set</span></dd>
            </div>
            <div>
                <dt>Representative</dt>
                <dd data-booking-preview="representative">Not encoded</dd>
            </div>
        </dl>
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
</section>

<section class="management-directory" id="active-bookings">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Reservation Board</p>
                <h3>Active Bookings</h3>
            </div>
            <span class="badge badge-info"><?php echo h(count($bookings)); ?> Active</span>
        </div>

        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="bookings">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_cancel_bookings">

            <div class="bulk-table-toolbar">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" data-bulk-delete-button disabled><?php echo icon('trash'); ?> Cancel Selected</button>
                <span data-bulk-delete-count>0 selected</span>
            </div>

            <div class="table-wrap record-scroll">
                <table class="data-table record-table">
                    <thead>
                        <tr>
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all bookings"></th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Booking Date</th>
                            <th>Schedule</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Route</th>
                            <th>Plate</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-page-size="10">
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $status = booking_status_badge($booking['pickupdate'], $booking['deliverydate']);
                            $lapsed = booking_lapsed_label($booking['pickupdate']);
                            ?>
                            <tr>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($booking['bookingidauto']); ?>" data-bulk-delete-item aria-label="Select booking <?php echo h($booking['bookingid']); ?>"></td>
                                <td>
                                    <strong><?php echo h($booking['bookingid']); ?></strong>
                                    <span><?php echo h($booking['shipmentnumber'] ?: 'No shipment number'); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo h($status['class']); ?>"><?php echo h($status['label']); ?></span>
                                    <span class="booking-lapsed booking-lapsed-<?php echo h($lapsed['tone']); ?>"><?php echo h($lapsed['label']); ?></span>
                                </td>
                                <td><?php echo h(booking_datetime_text($booking['bookingdate'])); ?></td>
                                <td>
                                    <strong><?php echo h(booking_datetime_text($booking['pickupdate'])); ?></strong>
                                    <span><?php echo h(booking_datetime_text($booking['deliverydate'])); ?></span>
                                </td>
                                <td><span class="badge <?php echo (int) $booking['deliverytype'] === 9 ? 'badge-warning' : 'badge-info'; ?>"><?php echo h(booking_type_label($booking['deliverytype'])); ?></span></td>
                                <td>
                                    <strong><?php echo h(($booking['soa'] ? '[' . $booking['soa'] . '] ' : '') . ($booking['customer_label'] ?: 'Unknown customer')); ?></strong>
                                    <span><?php echo h($booking['companyrepresentative'] ?: 'No representative'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo h(($booking['origin'] ?: 'Unknown origin') . ' to ' . ($booking['destination'] ?: 'Unknown destination')); ?></strong>
                                    <span><?php echo h(($booking['trucktype_name'] ?: 'Truck type pending') . ' / ' . ($booking['deliverytype_name'] ?: 'Delivery type pending')); ?></span>
                                </td>
                                <td><strong><?php echo h($booking['platenumber'] ?: 'No plate'); ?></strong></td>
                                <td class="table-actions">
                                    <button type="submit" form="cancel-booking-<?php echo h($booking['bookingidauto']); ?>" class="btn btn-danger btn-sm btn-icon"><?php echo icon('trash'); ?> Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bookings): ?>
                            <tr class="empty-row"><td colspan="10">No active bookings yet.</td></tr>
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
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
