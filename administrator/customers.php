<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Customer Management';
$activeNav = 'customers';

function customer_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function customer_badge_class($status)
{
    return (int) $status === 1 ? 'badge badge-success' : 'badge badge-muted';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('Administrator/customers.php');
    }

    try {
        if ($action === 'create') {
            $data = [
                'soa' => $_POST['soa'] ?? '',
                'customername' => $_POST['customername'] ?? '',
                'customeraddress' => $_POST['customeraddress'] ?? '',
                'status' => $_POST['status'] ?? '1',
            ];
            $errors = validate_customer_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                create_customer($data);
                flash('success', 'Customer record created.');
            }
        } elseif ($action === 'update') {
            $customerId = (int) ($_POST['customerid'] ?? 0);

            if (!find_customer_by_id($customerId)) {
                flash('error', 'Customer record was not found.');
            } else {
                $data = [
                    'soa' => $_POST['soa'] ?? '',
                    'customername' => $_POST['customername'] ?? '',
                    'customeraddress' => $_POST['customeraddress'] ?? '',
                    'status' => $_POST['status'] ?? '1',
                ];
                $errors = validate_customer_data($data, $customerId);

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    update_customer($customerId, $data);
                    flash('success', 'Customer record updated.');
                }
            }
        } elseif ($action === 'delete') {
            $customerId = (int) ($_POST['customerid'] ?? 0);

            if (!find_customer_by_id($customerId)) {
                flash('error', 'Customer record was not found.');
            } elseif (delete_customer($customerId)) {
                flash('success', 'Customer record deleted.');
            } else {
                flash('error', 'Customer record could not be deleted.');
            }
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_customer_by_id', function ($customerId) {
                return delete_customer($customerId);
            });

            flash_bulk_delete_result('customer', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Customer action failed: ' . $error->getMessage());
    }

    redirect_to('Administrator/customers.php');
}

$customers = list_customers();
$customerRouteTotals = customer_route_totals_for_customers(array_column($customers, 'customerid'));
$counts = customer_counts();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Master Data</p>
        <h2>Customers</h2>
        <p>Maintain client identities used by rates, booking, billing, collection, and management reports.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-modal-open="create-customer-modal"><?php echo icon('plus'); ?> New Customer</button>
        <div class="count-badges" aria-label="Customer overview">
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
                <h3>Customer Records</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('Administrator/customers.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="customers">
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
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all customers"></th>
                            <th>SOA</th>
                            <th>Customer</th>
                            <th>Billing Address</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="customer-records" data-infinite-list data-page-size="20">
                        <?php foreach ($customers as $customer): ?>
                            <tr data-infinite-item>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($customer['customerid']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($customer['customername']); ?>"></td>
                                <td><strong><?php echo h($customer['soa']); ?></strong></td>
                                <td><a class="table-link" href="<?php echo h(app_url('Administrator/customer_routes.php?customerid=' . $customer['customerid'])); ?>"><?php echo h($customer['customername']); ?></a></td>
                                <td><?php echo h($customer['customeraddress']); ?></td>
                                <td><span class="<?php echo h(customer_badge_class($customer['status'])); ?>"><?php echo h(customer_status_label($customer['status'])); ?></span></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Customer actions">
                                        <a class="btn btn-light btn-sm btn-icon" href="<?php echo h(app_url('Administrator/customer_routes.php?customerid=' . $customer['customerid'])); ?>"><?php echo icon('map'); ?> Routes (<?php echo h($customerRouteTotals[(int) $customer['customerid']] ?? 0); ?>)</a>
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-customer-<?php echo h($customer['customerid']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-customer-<?php echo h($customer['customerid']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$customers): ?>
                            <tr class="empty-row">
                                <td colspan="6">No customer records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-status" data-infinite-status="customer-records"></p>
        </form>
    </article>
</section>

<div class="modal" id="create-customer-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="create-customer-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-customer-title">New Customer</h3>
                <p>Add the billing identity used by rates, bookings, and receivables.</p>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('Administrator/customers.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <label for="soa">SOA Code</label>
                <input id="soa" name="soa" type="text" maxlength="7" required>

                <label for="customername">Customer Name</label>
                <input id="customername" name="customername" type="text" maxlength="100" required>

                <label for="customeraddress">Billing Address</label>
                <textarea id="customeraddress" name="customeraddress" rows="4" maxlength="300" required></textarea>

                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>

                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($customers as $customer): ?>
    <div class="modal" id="edit-customer-<?php echo h($customer['customerid']); ?>" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-customer-title-<?php echo h($customer['customerid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-customer-title-<?php echo h($customer['customerid']); ?>"><?php echo h($customer['customername']); ?></h3>
                    <p>Update the billing profile and status.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/customers.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="customerid" value="<?php echo h($customer['customerid']); ?>">

                    <label>SOA Code</label>
                    <input name="soa" type="text" value="<?php echo h($customer['soa']); ?>" maxlength="7" required>

                    <label>Customer Name</label>
                    <input name="customername" type="text" value="<?php echo h($customer['customername']); ?>" maxlength="100" required>

                    <label>Billing Address</label>
                    <textarea name="customeraddress" rows="4" maxlength="300" required><?php echo h($customer['customeraddress']); ?></textarea>

                    <label>Status</label>
                    <select name="status">
                        <option value="1"<?php echo customer_selected('1', $customer['status']); ?>>Active</option>
                        <option value="0"<?php echo customer_selected('0', $customer['status']); ?>>Inactive</option>
                    </select>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-customer-<?php echo h($customer['customerid']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-customer-title-<?php echo h($customer['customerid']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-customer-title-<?php echo h($customer['customerid']); ?>">Delete Customer Record</h3>
                    <p>This will remove <?php echo h($customer['customername']); ?> from the customer directory.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('Administrator/customers.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customerid" value="<?php echo h($customer['customerid']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
