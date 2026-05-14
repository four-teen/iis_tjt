<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Delivery Type Management';
$activeNav = 'delivery_types';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('Administrator/delivery_types.php');
    }

    try {
        if ($action === 'create') {
            save_delivery_type($_POST['deliverytype'] ?? '');
            flash('success', 'Delivery type created.');
        } elseif ($action === 'update') {
            save_delivery_type($_POST['deliverytype'] ?? '', (int) ($_POST['deliverytypeid'] ?? 0));
            flash('success', 'Delivery type updated.');
        } elseif ($action === 'delete') {
            delete_delivery_type((int) ($_POST['deliverytypeid'] ?? 0));
            flash('success', 'Delivery type deleted.');
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_delivery_type_by_id', function ($deliveryTypeId) {
                return delete_delivery_type($deliveryTypeId);
            });

            flash_bulk_delete_result('delivery type', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Delivery type action failed: ' . $error->getMessage());
    }

    redirect_to('Administrator/delivery_types.php');
}

$deliveryTypes = list_delivery_types();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Rate Setup</p>
        <h2>Delivery Types</h2>
        <p>Maintain the delivery categories used by route rates, booking, billing, and reports.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-open="create-delivery-type-modal"><?php echo icon('plus'); ?> New Delivery Type</button>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>Delivery Type Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('Administrator/delivery_types.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="delivery types">
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
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all delivery types"></th>
                            <th>Delivery Type</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveryTypes as $type): ?>
                            <tr>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($type['deliverytypeid']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($type['deliverytype']); ?>"></td>
                                <td><strong><?php echo h($type['deliverytype']); ?></strong></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Delivery type actions">
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-delivery-type-<?php echo h($type['deliverytypeid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-delivery-type-<?php echo h($type['deliverytypeid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$deliveryTypes): ?>
                            <tr class="empty-row"><td colspan="3">No delivery type records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </article>
</section>

<div class="modal" id="create-delivery-type-modal" hidden>
    <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="create-delivery-type-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-delivery-type-title">New Delivery Type</h3>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('Administrator/delivery_types.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <label for="deliverytype">Delivery Type</label>
                <input id="deliverytype" name="deliverytype" type="text" maxlength="60" required>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Delivery Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($deliveryTypes as $type): ?>
    <div class="modal" id="edit-delivery-type-<?php echo h($type['deliverytypeid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="edit-delivery-type-title-<?php echo h($type['deliverytypeid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-delivery-type-title-<?php echo h($type['deliverytypeid']); ?>"><?php echo h($type['deliverytype']); ?></h3>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/delivery_types.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="deliverytypeid" value="<?php echo h($type['deliverytypeid']); ?>">
                    <label>Delivery Type</label>
                    <input name="deliverytype" type="text" value="<?php echo h($type['deliverytype']); ?>" maxlength="60" required>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-delivery-type-<?php echo h($type['deliverytypeid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-delivery-type-title-<?php echo h($type['deliverytypeid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-delivery-type-title-<?php echo h($type['deliverytypeid']); ?>">Delete Delivery Type</h3>
                    <p>This will remove <?php echo h($type['deliverytype']); ?>.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/delivery_types.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="deliverytypeid" value="<?php echo h($type['deliverytypeid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Delivery Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
