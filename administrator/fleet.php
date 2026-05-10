<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Fleet';
$activeNav = 'fleet';

function fleet_date_text($value)
{
    if (!$value) {
        return 'Not set';
    }

    return date('M d, Y', strtotime($value));
}

function fleet_validity_badge($value)
{
    if (!$value) {
        return '<span class="badge badge-muted">No Date</span>';
    }

    if (strtotime($value) < strtotime(date('Y-m-d'))) {
        return '<span class="badge badge-danger">Expired</span>';
    }

    return '<span class="badge badge-success">Valid</span>';
}

function fleet_profile_label($fleet)
{
    $parts = array_filter([
        $fleet['trucktype_name'] ?? '',
        $fleet['makename'] ?? '',
        $fleet['body_name'] ?? '',
    ]);

    return $parts ? implode(' / ', $parts) : 'Profile not completed';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('administrator/fleet.php');
    }

    try {
        if ($action === 'create') {
            $data = [
                'platenumber' => $_POST['platenumber'] ?? '',
                'casenumber' => $_POST['casenumber'] ?? '',
                'validity' => $_POST['validity'] ?? '',
                'paremarks' => $_POST['paremarks'] ?? '',
                'pavalidity' => $_POST['pavalidity'] ?? '',
                'decisionremarks' => $_POST['decisionremarks'] ?? '',
                'decisionvalidity' => $_POST['decisionvalidity'] ?? '',
            ];
            $errors = validate_fleet_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                save_fleet($data);
                flash('success', 'Fleet record created.');
            }
        } elseif ($action === 'update') {
            $fleetId = (int) ($_POST['fleetid'] ?? 0);

            if (!find_fleet_by_id($fleetId)) {
                flash('error', 'Fleet record was not found.');
            } else {
                $data = [
                    'platenumber' => $_POST['platenumber'] ?? '',
                    'casenumber' => $_POST['casenumber'] ?? '',
                    'validity' => $_POST['validity'] ?? '',
                    'paremarks' => $_POST['paremarks'] ?? '',
                    'pavalidity' => $_POST['pavalidity'] ?? '',
                    'decisionremarks' => $_POST['decisionremarks'] ?? '',
                    'decisionvalidity' => $_POST['decisionvalidity'] ?? '',
                ];
                $errors = validate_fleet_data($data, $fleetId);

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    save_fleet($data, $fleetId);
                    flash('success', 'Fleet record updated.');
                }
            }
        } elseif ($action === 'delete') {
            $fleetId = (int) ($_POST['fleetid'] ?? 0);

            if (!find_fleet_by_id($fleetId)) {
                flash('error', 'Fleet record was not found.');
            } elseif (delete_fleet($fleetId)) {
                flash('success', 'Fleet record deleted.');
            } else {
                flash('error', 'Fleet record could not be deleted.');
            }
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_fleet_by_id', function ($fleetId) {
                return delete_fleet($fleetId);
            });

            flash_bulk_delete_result('fleet record', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Fleet action failed: ' . $error->getMessage());
    }

    redirect_to('administrator/fleet.php');
}

$fleets = list_fleets();
$counts = fleet_counts();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Operations Setup</p>
        <h2>Fleet</h2>
        <p>Maintain trucks, permit validity, fleet profile details, and assigned drivers/helpers used by dispatch planning.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-modal-open="create-fleet-modal"><?php echo icon('plus'); ?> Register Fleet</button>
        <div class="count-badges" aria-label="Fleet overview">
            <span class="count-badge">Total <strong><?php echo h($counts['total']); ?></strong></span>
            <span class="count-badge count-badge-danger">Expired <strong><?php echo h($counts['expired']); ?></strong></span>
            <span class="count-badge count-badge-muted">Temporary <strong><?php echo h($counts['temporary']); ?></strong></span>
            <span class="count-badge count-badge-success">Profiled <strong><?php echo h($counts['profiled']); ?></strong></span>
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
                <p class="eyebrow">Registry</p>
                <h3>Fleet Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('administrator/fleet.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="fleet records">
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
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all fleet records"></th>
                            <th>Plate Number</th>
                            <th>Case Number</th>
                            <th>Validity</th>
                            <th>PA Remarks</th>
                            <th>Profile</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="fleet-records" data-infinite-list data-page-size="20">
                        <?php foreach ($fleets as $fleet): ?>
                            <tr data-infinite-item>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($fleet['fleetid']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($fleet['platenumber']); ?>"></td>
                                <td><strong><?php echo h($fleet['platenumber']); ?></strong></td>
                                <td><?php echo h($fleet['casenumber'] ?: 'Not set'); ?></td>
                                <td>
                                    <?php echo fleet_validity_badge($fleet['validity']); ?>
                                    <span><?php echo h(fleet_date_text($fleet['validity'])); ?></span>
                                </td>
                                <td><?php echo h($fleet['paremarks'] ?: 'Not set'); ?></td>
                                <td><?php echo h(fleet_profile_label($fleet)); ?></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Fleet actions">
                                        <a class="btn btn-light btn-sm btn-icon" href="<?php echo h(app_url('administrator/fleet_profile.php?fleetid=' . $fleet['fleetid'])); ?>"><?php echo icon('truck'); ?> Profile</a>
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-fleet-<?php echo h($fleet['fleetid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-fleet-<?php echo h($fleet['fleetid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$fleets): ?>
                            <tr class="empty-row"><td colspan="7">No fleet records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-status" data-infinite-status="fleet-records"></p>
        </form>
    </article>
</section>

<div class="modal" id="create-fleet-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="create-fleet-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-fleet-title">Register Fleet</h3>
                <p>Add the plate and permit tracking details before completing the technical profile.</p>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('administrator/fleet.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div>
                        <label for="platenumber">Plate Number</label>
                        <input id="platenumber" name="platenumber" type="text" maxlength="20" required>
                    </div>
                    <div>
                        <label for="casenumber">Case Number</label>
                        <input id="casenumber" name="casenumber" type="text" maxlength="20">
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label for="validity">Validity</label>
                        <input id="validity" name="validity" type="date">
                    </div>
                    <div>
                        <label for="paremarks">PA Remarks</label>
                        <select id="paremarks" name="paremarks">
                            <option value="TEMPORARY PLATE">Temporary Plate</option>
                            <option value="PERMANENT PLATE">Permanent Plate</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label for="pavalidity">PA Validity</label>
                        <input id="pavalidity" name="pavalidity" type="date">
                    </div>
                    <div>
                        <label for="decisionvalidity">Decision Validity</label>
                        <input id="decisionvalidity" name="decisionvalidity" type="date">
                    </div>
                </div>
                <label for="decisionremarks">Decision Remarks</label>
                <input id="decisionremarks" name="decisionremarks" type="text" maxlength="30">
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Fleet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($fleets as $fleet): ?>
    <div class="modal" id="edit-fleet-<?php echo h($fleet['fleetid']); ?>" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-fleet-title-<?php echo h($fleet['fleetid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-fleet-title-<?php echo h($fleet['fleetid']); ?>"><?php echo h($fleet['platenumber']); ?></h3>
                    <p>Update the fleet registration and permit tracking details.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/fleet.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="fleetid" value="<?php echo h($fleet['fleetid']); ?>">
                    <div class="form-grid">
                        <div>
                            <label>Plate Number</label>
                            <input name="platenumber" type="text" maxlength="20" value="<?php echo h($fleet['platenumber']); ?>" required>
                        </div>
                        <div>
                            <label>Case Number</label>
                            <input name="casenumber" type="text" maxlength="20" value="<?php echo h($fleet['casenumber']); ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label>Validity</label>
                            <input name="validity" type="date" value="<?php echo h($fleet['validity']); ?>">
                        </div>
                        <div>
                            <label>PA Remarks</label>
                            <select name="paremarks">
                                <option value="TEMPORARY PLATE"<?php echo $fleet['paremarks'] === 'TEMPORARY PLATE' ? ' selected' : ''; ?>>Temporary Plate</option>
                                <option value="PERMANENT PLATE"<?php echo $fleet['paremarks'] === 'PERMANENT PLATE' ? ' selected' : ''; ?>>Permanent Plate</option>
                                <option value="<?php echo h($fleet['paremarks']); ?>"<?php echo !in_array($fleet['paremarks'], ['TEMPORARY PLATE', 'PERMANENT PLATE'], true) ? ' selected' : ''; ?>><?php echo h($fleet['paremarks'] ?: 'Not set'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label>PA Validity</label>
                            <input name="pavalidity" type="date" value="<?php echo h($fleet['pavalidity']); ?>">
                        </div>
                        <div>
                            <label>Decision Validity</label>
                            <input name="decisionvalidity" type="date" value="<?php echo h($fleet['decisionvalidity']); ?>">
                        </div>
                    </div>
                    <label>Decision Remarks</label>
                    <input name="decisionremarks" type="text" maxlength="30" value="<?php echo h($fleet['decisionremarks']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-fleet-<?php echo h($fleet['fleetid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-fleet-title-<?php echo h($fleet['fleetid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-fleet-title-<?php echo h($fleet['fleetid']); ?>">Delete Fleet</h3>
                    <p>This will remove <?php echo h($fleet['platenumber']); ?> and its fleet profile.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/fleet.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="fleetid" value="<?php echo h($fleet['fleetid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Fleet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
