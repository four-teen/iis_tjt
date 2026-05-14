<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Location Management';
$activeNav = 'locations';

function location_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function location_badge_class($status)
{
    return (int) $status === 1 ? 'badge badge-success' : 'badge badge-muted';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('Administrator/locations.php');
    }

    try {
        if ($action === 'create') {
            $data = [
                'location' => $_POST['location'] ?? '',
                'status' => $_POST['status'] ?? '1',
            ];
            $errors = validate_location_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                create_location($data);
                flash('success', 'Location created.');
            }
        } elseif ($action === 'update') {
            $locationId = (int) ($_POST['locationid'] ?? 0);

            if (!find_location_by_id($locationId)) {
                flash('error', 'Location was not found.');
            } else {
                $data = [
                    'location' => $_POST['location'] ?? '',
                    'status' => $_POST['status'] ?? '1',
                ];
                $errors = validate_location_data($data);

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    update_location($locationId, $data);
                    flash('success', 'Location updated.');
                }
            }
        } elseif ($action === 'delete') {
            $locationId = (int) ($_POST['locationid'] ?? 0);

            if (!find_location_by_id($locationId)) {
                flash('error', 'Location was not found.');
            } elseif (delete_location($locationId)) {
                flash('success', 'Location deleted.');
            } else {
                flash('error', 'Location could not be deleted.');
            }
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_location_by_id', function ($locationId) {
                return delete_location($locationId);
            });

            flash_bulk_delete_result('location', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Location action failed: ' . $error->getMessage());
    }

    redirect_to('Administrator/locations.php');
}

$locations = list_locations();
$counts = location_counts();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Customer, Route, Rate Setup</p>
        <h2>Locations</h2>
        <p>Maintain origin, destination, drop, and pickup locations used by customer routes and trip setup.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-modal-open="create-location-modal"><?php echo icon('plus'); ?> New Location</button>
        <div class="count-badges" aria-label="Location overview">
            <span class="count-badge">Total <strong><?php echo h($counts['total']); ?></strong></span>
            <span class="count-badge count-badge-success">Active <strong><?php echo h($counts['active']); ?></strong></span>
            <span class="count-badge count-badge-muted">Inactive <strong><?php echo h($counts['inactive']); ?></strong></span>
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
                <p class="eyebrow">Directory</p>
                <h3>Location Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('Administrator/locations.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="locations">
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
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all locations"></th>
                            <th>Location</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="location-records" data-infinite-list data-page-size="30">
                        <?php foreach ($locations as $location): ?>
                            <tr data-infinite-item>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($location['locationid']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($location['location']); ?>"></td>
                                <td><strong><?php echo h($location['location']); ?></strong></td>
                                <td><span class="<?php echo h(location_badge_class($location['status'])); ?>"><?php echo h(setup_status_label($location['status'])); ?></span></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Location actions">
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-location-<?php echo h($location['locationid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-location-<?php echo h($location['locationid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$locations): ?>
                            <tr class="empty-row"><td colspan="4">No location records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-status" data-infinite-status="location-records"></p>
        </form>
    </article>
</section>

<div class="modal" id="create-location-modal" hidden>
    <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="create-location-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-location-title">New Location</h3>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('Administrator/locations.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <label for="location">Location</label>
                <input id="location" name="location" type="text" maxlength="120" required>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($locations as $location): ?>
    <div class="modal" id="edit-location-<?php echo h($location['locationid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="edit-location-title-<?php echo h($location['locationid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-location-title-<?php echo h($location['locationid']); ?>"><?php echo h($location['location']); ?></h3>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/locations.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="locationid" value="<?php echo h($location['locationid']); ?>">
                    <label>Location</label>
                    <input name="location" type="text" value="<?php echo h($location['location']); ?>" maxlength="120" required>
                    <label>Status</label>
                    <select name="status">
                        <option value="1"<?php echo location_selected('1', $location['status']); ?>>Active</option>
                        <option value="0"<?php echo location_selected('0', $location['status']); ?>>Inactive</option>
                    </select>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-location-<?php echo h($location['locationid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-location-title-<?php echo h($location['locationid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-location-title-<?php echo h($location['locationid']); ?>">Delete Location</h3>
                    <p>This will remove <?php echo h($location['location']); ?>.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/locations.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="locationid" value="<?php echo h($location['locationid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
