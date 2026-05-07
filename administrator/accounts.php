<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$pageTitle = 'Account Management';
$activeNav = 'accounts';
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);

function selected_attr($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function badge_class($status)
{
    return $status === 'active' ? 'badge badge-success' : 'badge badge-muted';
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
        } elseif ($action === 'set_status') {
            $accountId = (int) ($_POST['account_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($accountId === $currentUserId && $status !== 'active') {
                flash('error', 'You cannot deactivate your own account.');
            } elseif (!find_account_by_id($accountId)) {
                flash('error', 'Account was not found.');
            } else {
                update_account_status($accountId, $status, $currentUserId);
                flash('success', 'Account status updated.');
            }
        }
    } catch (Throwable $error) {
        flash('error', 'Account action failed: ' . $error->getMessage());
    }

    redirect_to('administrator/accounts.php');
}

$accounts = list_accounts();
$counts = account_counts();
$roles = account_roles();
$statuses = account_statuses();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
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

<section class="account-layout">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Administrator</p>
                <h3>Create Account</h3>
            </div>
        </div>

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

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
    </article>

    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>Manage Accounts</h3>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
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
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td>
                                <strong><?php echo h($account['full_name']); ?></strong>
                                <span><?php echo h($account['email'] ?: 'No email'); ?></span>
                            </td>
                            <td><?php echo h($account['username']); ?></td>
                            <td><?php echo h($account['role']); ?></td>
                            <td><span class="<?php echo h(badge_class($account['status'])); ?>"><?php echo h(ucfirst($account['status'])); ?></span></td>
                            <td><?php echo $account['last_login_at'] ? h(date('M d, Y h:i A', strtotime($account['last_login_at']))) : 'Never'; ?></td>
                            <td class="table-actions">
                                <details>
                                    <summary class="btn btn-light">Manage</summary>
                                    <div class="details-panel">
                                        <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form compact">
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

                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </form>

                                        <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="inline-form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="set_status">
                                            <input type="hidden" name="account_id" value="<?php echo h($account['id']); ?>">
                                            <input type="hidden" name="status" value="<?php echo $account['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn btn-light">
                                                <?php echo $account['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?php echo h(app_url('administrator/accounts.php')); ?>" class="stack-form compact">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="account_id" value="<?php echo h($account['id']); ?>">

                                            <label>New Temporary Password</label>
                                            <input name="password" type="password" minlength="8" required>

                                            <button type="submit" class="btn btn-light">Reset Password</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
