<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$pageTitle = 'Account Management';
$activeNav = 'accounts';
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

function selected_attr($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
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
                'role' => $_POST['role'] ?? '',
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
                    'role' => $_POST['role'] ?? '',
                    'status' => $_POST['status'] ?? 'active',
                ];
                $errors = validate_account_data($data, 'update', $accountId);

                if ($accountId === $currentUserId && $data['status'] !== 'active') {
                    $errors[] = 'You cannot deactivate your own account.';
                }

                if ($accountId === $currentUserId && $data['role'] !== 'Administrator') {
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
        }
    } catch (Throwable $error) {
        flash('error', 'Account action failed: ' . $error->getMessage());
    }

    redirect_to('administrator/accounts.php');
}

$roles = account_roles();
$statuses = account_statuses();
$accounts = list_accounts($search, $roleFilter, $statusFilter);
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
    <button type="button" class="btn btn-primary" data-modal-open="create-account-modal"><?php echo icon('plus'); ?> New Account</button>
</section>

<section class="stats-grid" aria-label="Account overview">
    <article class="stat-card">
        <span class="stat-label">Total Accounts</span>
        <strong><?php echo h($counts['total']); ?></strong>
        <p>All administrator-managed users.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Active</span>
        <strong><?php echo h($counts['active']); ?></strong>
        <p>Accounts allowed to sign in.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Inactive</span>
        <strong><?php echo h($counts['inactive']); ?></strong>
        <p>Accounts blocked from login.</p>
    </article>
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

        <form method="get" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="filter-bar">
            <input name="search" type="search" value="<?php echo h($search); ?>" placeholder="Search name, username, or email">
            <select name="role">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo h($role); ?>"<?php echo selected_attr($role, $roleFilter); ?>><?php echo h($role); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo h($status); ?>"<?php echo selected_attr($status, $statusFilter); ?>><?php echo h(ucfirst($status)); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-light">Filter</button>
        </form>

        <div class="table-wrap record-scroll" data-infinite-scroll>
            <table class="data-table record-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="account-records" data-infinite-list data-page-size="20">
                    <?php foreach ($accounts as $account): ?>
                        <tr data-infinite-item>
                            <td>
                                <strong><?php echo h($account['full_name']); ?></strong>
                                <span><?php echo h($account['email'] ?: 'No email'); ?></span>
                            </td>
                            <td><?php echo h($account['username']); ?></td>
                            <td><span class="<?php echo h(account_role_badge_class($account['role'])); ?>"><?php echo h($account['role']); ?></span></td>
                            <td><span class="<?php echo h(account_badge_class($account['status'])); ?>"><?php echo h(ucfirst($account['status'])); ?></span></td>
                            <td><?php echo $account['last_login_at'] ? h(date('M d, Y h:i A', strtotime($account['last_login_at']))) : 'Never'; ?></td>
                            <td class="table-actions">
                                <div class="action-group">
                                    <button type="button" class="btn btn-edit btn-icon" data-modal-open="edit-account-<?php echo h($account['id']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                    <button type="button" class="btn btn-warning btn-icon" data-modal-open="password-account-<?php echo h($account['id']); ?>"><?php echo icon('key'); ?> Password</button>
                                    <button type="button" class="btn btn-danger btn-icon" data-modal-open="delete-account-<?php echo h($account['id']); ?>"<?php echo (int) $account['id'] === $currentUserId ? ' disabled' : ''; ?>><?php echo icon('trash'); ?> Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$accounts): ?>
                        <tr class="empty-row">
                            <td colspan="6">No account records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-status" data-infinite-status="account-records"></p>
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

                <div class="form-grid">
                    <div>
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo h($role); ?>"><?php echo h($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo h($status); ?>"><?php echo h(ucfirst($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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

                    <div class="form-grid">
                        <div>
                            <label>Role</label>
                            <select name="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo h($role); ?>"<?php echo selected_attr($role, $account['role']); ?>><?php echo h($role); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo h($status); ?>"<?php echo selected_attr($status, $account['status']); ?>><?php echo h(ucfirst($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
