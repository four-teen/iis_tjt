<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Truck Type Management';
$activeNav = 'truck_types';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('Administrator/truck_types.php');
    }

    try {
        if ($action === 'create') {
            save_truck_type($_POST['trucktype'] ?? '');
            flash('success', 'Truck type created.');
        } elseif ($action === 'update') {
            save_truck_type($_POST['trucktype'] ?? '', (int) ($_POST['trucktypeid'] ?? 0));
            flash('success', 'Truck type updated.');
        } elseif ($action === 'delete') {
            delete_truck_type((int) ($_POST['trucktypeid'] ?? 0));
            flash('success', 'Truck type deleted.');
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_truck_type_by_id', function ($truckTypeId) {
                return delete_truck_type($truckTypeId);
            });

            flash_bulk_delete_result('truck type', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Truck type action failed: ' . $error->getMessage());
    }

    redirect_to('Administrator/truck_types.php');
}

$truckTypes = list_truck_types();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Rate Setup</p>
        <h2>Truck Types</h2>
        <p>Maintain truck classifications used by route rates and dispatch planning.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-open="create-truck-type-modal"><?php echo icon('plus'); ?> New Truck Type</button>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>Truck Type Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('Administrator/truck_types.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="truck types">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_delete">

            <div class="bulk-table-toolbar">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" data-bulk-delete-button disabled><?php echo icon('trash'); ?> Delete Selected</button>
                <span data-bulk-delete-count>0 selected</span>
            </div>

            <div class="table-wrap">
                <table class="data-table record-table">
                    <thead>
                        <tr>
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all truck types"></th>
                            <th>Truck Type</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($truckTypes as $type): ?>
                            <tr>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($type['trucktypeid']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($type['trucktype']); ?>"></td>
                                <td><strong><?php echo h($type['trucktype']); ?></strong></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Truck type actions">
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-truck-type-<?php echo h($type['trucktypeid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-truck-type-<?php echo h($type['trucktypeid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$truckTypes): ?>
                            <tr class="empty-row"><td colspan="3">No truck type records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </article>
</section>

<div class="modal" id="create-truck-type-modal" hidden>
    <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="create-truck-type-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-truck-type-title">New Truck Type</h3>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('Administrator/truck_types.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <label for="trucktype">Truck Type</label>
                <input id="trucktype" name="trucktype" type="text" maxlength="50" required>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Truck Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($truckTypes as $type): ?>
    <div class="modal" id="edit-truck-type-<?php echo h($type['trucktypeid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="edit-truck-type-title-<?php echo h($type['trucktypeid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-truck-type-title-<?php echo h($type['trucktypeid']); ?>"><?php echo h($type['trucktype']); ?></h3>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/truck_types.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="trucktypeid" value="<?php echo h($type['trucktypeid']); ?>">
                    <label>Truck Type</label>
                    <input name="trucktype" type="text" value="<?php echo h($type['trucktype']); ?>" maxlength="50" required>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-truck-type-<?php echo h($type['trucktypeid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-truck-type-title-<?php echo h($type['trucktypeid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-truck-type-title-<?php echo h($type['trucktypeid']); ?>">Delete Truck Type</h3>
                    <p>This will remove <?php echo h($type['trucktype']); ?>.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/truck_types.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="trucktypeid" value="<?php echo h($type['trucktypeid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Truck Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
