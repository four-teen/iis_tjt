<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';

require_role('Administrator');

$pageTitle = 'Account Management';
$activeNav = 'accounts';
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);

function selected_attr($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function checked_attr($value, $current)
{
    return in_array($value, (array) $current, true) ? ' checked' : '';
}

function account_badge_class($status)
{
    return $status === 'active' ? 'badge badge-success' : 'badge badge-muted';
}

function account_role_badge_class($role)
{
    $classes = [
        'Administrator' => 'badge badge-danger',
        'Manager' => 'badge badge-warning',
        'Finance' => 'badge badge-info',
        'Operations' => 'badge badge-success',
        'Human Resource' => 'badge badge-info',
        'Billing' => 'badge badge-warning',
        'Budget' => 'badge badge-warning',
        'Coordinator' => 'badge badge-success',
        'Customer Service' => 'badge badge-info',
        'Fleet Management' => 'badge badge-success',
        'Inventory' => 'badge badge-muted',
    ];

    return $classes[$role] ?? 'badge badge-muted';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('administrator/accounts.php');
    }

    try {
        if ($action === 'create') {
            $data = [
                'full_name' => $_POST['full_name'] ?? '',
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'roles' => $_POST['roles'] ?? [],
                'status' => $_POST['status'] ?? 'active',
                'password' => $_POST['password'] ?? '',
                'must_change_password' => 1,
            ];
            $errors = validate_account_data($data, 'create');

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                create_account($data, $currentUserId);
                flash('success', 'Account created successfully.');
            }
        } elseif ($action === 'update') {
            $accountId = (int) ($_POST['account_id'] ?? 0);
            $account = find_account_by_id($accountId);

            if (!$account) {
                flash('error', 'Account was not found.');
            } else {
                $data = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'username' => $_POST['username'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'roles' => $_POST['roles'] ?? [],
                    'status' => $_POST['status'] ?? 'active',
                ];
                $errors = validate_account_data($data, 'update', $accountId);
                $selectedRoles = normalize_account_roles($data['roles']);

                if ($accountId === $currentUserId && $data['status'] !== 'active') {
                    $errors[] = 'You cannot deactivate your own account.';
                }

                if ($accountId === $currentUserId && !in_array('Administrator', $selectedRoles, true)) {
                    $errors[] = 'You cannot remove Administrator access from your own account.';
                }

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    update_account($accountId, $data, $currentUserId);
                    flash('success', 'Account updated successfully.');
                }
            }
        } elseif ($action === 'reset_password') {
            $accountId = (int) ($_POST['account_id'] ?? 0);
            $password = (string) ($_POST['password'] ?? '');

            if (!find_account_by_id($accountId)) {
                flash('error', 'Account was not found.');
            } elseif (strlen($password) < 8) {
                flash('error', 'Password must be at least 8 characters.');
            } else {
                update_account_password($accountId, $password, $currentUserId);
                flash('success', 'Password reset successfully.');
            }
        } elseif ($action === 'delete') {
            $accountId = (int) ($_POST['account_id'] ?? 0);

            if ($accountId === $currentUserId) {
                flash('error', 'You cannot delete your own signed-in account.');
            } elseif (!find_account_by_id($accountId)) {
                flash('error', 'Account was not found.');
            } elseif (delete_account($accountId, $currentUserId)) {
                flash('success', 'Account deleted successfully.');
            } else {
                flash('error', 'Account could not be deleted.');
            }
        } elseif ($action === 'bulk_delete') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, 'find_account_by_id', function ($accountId) use ($currentUserId) {
                if ((int) $accountId === (int) $currentUserId) {
                    throw new RuntimeException('Your signed-in account was skipped.');
                }

                return delete_account($accountId, $currentUserId);
            });

            flash_bulk_delete_result('account', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Account action failed: ' . $error->getMessage());
    }

    redirect_to('administrator/accounts.php');
}

$roles = account_roles();
$statuses = account_statuses();
$accounts = list_accounts();
$counts = account_counts();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Administration</p>
        <h2>Accounts</h2>
        <p>Control who can enter the system, assign department roles, reset temporary passwords, and keep inactive users from signing in.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-modal-open="create-account-modal"><?php echo icon('plus'); ?> New Account</button>
        <div class="count-badges" aria-label="Account overview">
            <span class="count-badge">Total <strong><?php echo h($counts['total']); ?></strong></span>
            <span class="count-badge count-badge-success">Active <strong><?php echo h($counts['active']); ?></strong></span>
            <span class="count-badge count-badge-muted">Inactive <strong><?php echo h($counts['inactive']); ?></strong></span>
        </div>
    </div>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert">
        <?php echo h($message['message']); ?>
    </div>
<?php endforeach; ?>

<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>Manage Accounts</h3>
            </div>
        </div>

        <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="accounts">
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
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all accounts"></th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="account-records" data-infinite-list data-page-size="20">
                        <?php foreach ($accounts as $account): ?>
                            <tr data-infinite-item>
                                <td class="select-column">
                                    <input type="checkbox" name="ids[]" value="<?php echo h($account['id']); ?>" data-bulk-delete-item aria-label="Select <?php echo h($account['full_name']); ?>"<?php echo (int) $account['id'] === $currentUserId ? ' disabled title="You cannot delete your signed-in account."' : ''; ?>>
                                </td>
                                <td>
                                    <strong><?php echo h($account['full_name']); ?></strong>
                                    <span><?php echo h($account['email'] ?: 'No email'); ?></span>
                                </td>
                                <td><?php echo h($account['username']); ?></td>
                                <td>
                                    <div class="badge-list">
                                        <?php foreach ($account['roles'] as $role): ?>
                                            <span class="<?php echo h(account_role_badge_class($role)); ?>"><?php echo h($role); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><span class="<?php echo h(account_badge_class($account['status'])); ?>"><?php echo h(ucfirst($account['status'])); ?></span></td>
                                <td><?php echo $account['last_login_at'] ? h(date('M d, Y h:i A', strtotime($account['last_login_at']))) : 'Never'; ?></td>
                                <td class="table-actions">
                                    <div class="btn-group action-group" role="group" aria-label="Account actions">
                                        <button type="button" class="btn btn-warning btn-sm btn-icon" data-modal-open="edit-account-<?php echo h($account['id']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                        <button type="button" class="btn btn-light btn-sm btn-icon" data-modal-open="password-account-<?php echo h($account['id']); ?>"><?php echo icon('key'); ?> Password</button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" data-modal-open="delete-account-<?php echo h($account['id']); ?>"<?php echo (int) $account['id'] === $currentUserId ? ' disabled' : ''; ?>><?php echo icon('trash'); ?> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$accounts): ?>
                            <tr class="empty-row">
                                <td colspan="7">No account records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-status" data-infinite-status="account-records"></p>
        </form>
    </article>
</section>

<div class="modal" id="create-account-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="create-account-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-account-title">New Account</h3>
                <p>Add a system user and assign the correct department role.</p>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name" type="text" maxlength="120" required>

                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="50" autocomplete="off" required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="120">

                <label>Roles</label>
                <div class="role-check-grid">
                    <?php foreach ($roles as $role): ?>
                        <label class="role-check">
                            <input type="checkbox" name="roles[]" value="<?php echo h($role); ?>">
                            <span><?php echo h($role); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo h($status); ?>"><?php echo h(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label for="password">Temporary Password</label>
                <input id="password" name="password" type="password" minlength="8" required>

                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($accounts as $account): ?>
    <div class="modal" id="edit-account-<?php echo h($account['id']); ?>" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-account-title-<?php echo h($account['id']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-account-title-<?php echo h($account['id']); ?>"><?php echo h($account['full_name']); ?></h3>
                    <p>Update identity, role, and login status.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="account_id" value="<?php echo h($account['id']); ?>">

                    <label>Full Name</label>
                    <input name="full_name" type="text" value="<?php echo h($account['full_name']); ?>" maxlength="120" required>

                    <label>Username</label>
                    <input name="username" type="text" value="<?php echo h($account['username']); ?>" maxlength="50" required>

                    <label>Email</label>
                    <input name="email" type="email" value="<?php echo h($account['email']); ?>" maxlength="120">

                    <label>Roles</label>
                    <div class="role-check-grid">
                        <?php foreach ($roles as $role): ?>
                            <label class="role-check">
                                <input type="checkbox" name="roles[]" value="<?php echo h($role); ?>"<?php echo checked_attr($role, $account['roles']); ?>>
                                <span><?php echo h($role); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo h($status); ?>"<?php echo selected_attr($status, $account['status']); ?>><?php echo h(ucfirst($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="password-account-<?php echo h($account['id']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="password-account-title-<?php echo h($account['id']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Password</p>
                    <h3 id="password-account-title-<?php echo h($account['id']); ?>">Reset Password</h3>
                    <p>Set a new temporary password for <?php echo h($account['full_name']); ?>.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="account_id" value="<?php echo h($account['id']); ?>">

                    <label>New Temporary Password</label>
                    <input name="password" type="password" minlength="8" required>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-account-<?php echo h($account['id']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-account-title-<?php echo h($account['id']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-account-title-<?php echo h($account['id']); ?>">Delete Account</h3>
                    <p>This will remove <?php echo h($account['full_name']); ?> from the account directory.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="account_id" value="<?php echo h($account['id']); ?>">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
