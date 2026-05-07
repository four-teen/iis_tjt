<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$pageTitle = 'Employee Management';
$activeNav = 'employees';
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';

function employee_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function employee_badge_class($type)
{
    $classes = [
        '0' => 'badge badge-muted',
        '1' => 'badge badge-success',
        '2' => 'badge badge-info',
        '3' => 'badge badge-warning',
    ];

    return $classes[(string) $type] ?? 'badge badge-muted';
}

function employee_display_name($employee)
{
    $parts = [
        $employee['firstname'] ?? '',
        $employee['middlename'] ?? '',
        $employee['lastname'] ?? '',
    ];

    return clean_text(implode(' ', array_filter($parts)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to('administrator/employees.php');
    }

    try {
        if ($action === 'create') {
            $data = [
                'firstname' => $_POST['firstname'] ?? '',
                'middlename' => $_POST['middlename'] ?? '',
                'lastname' => $_POST['lastname'] ?? '',
                'who_is' => $_POST['who_is'] ?? '',
            ];
            $errors = validate_employee_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                create_employee($data);
                flash('success', 'Employee record created.');
            }
        } elseif ($action === 'update') {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);

            if (!find_employee_by_id($employeeId)) {
                flash('error', 'Employee record was not found.');
            } else {
                $data = [
                    'firstname' => $_POST['firstname'] ?? '',
                    'middlename' => $_POST['middlename'] ?? '',
                    'lastname' => $_POST['lastname'] ?? '',
                    'who_is' => $_POST['who_is'] ?? '',
                ];
                $errors = validate_employee_data($data);

                if ($errors) {
                    flash('error', implode(' ', $errors));
                } else {
                    update_employee($employeeId, $data);
                    flash('success', 'Employee record updated.');
                }
            }
        } elseif ($action === 'delete') {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);

            if (!find_employee_by_id($employeeId)) {
                flash('error', 'Employee record was not found.');
            } elseif (delete_employee($employeeId)) {
                flash('success', 'Employee record deleted.');
            } else {
                flash('error', 'Employee record could not be deleted.');
            }
        }
    } catch (Throwable $error) {
        flash('error', 'Employee action failed: ' . $error->getMessage());
    }

    redirect_to('administrator/employees.php');
}

$employeeTypes = employee_types();
$employees = list_employees($search, $typeFilter);
$counts = employee_counts();
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="module-hero">
    <div>
        <p class="eyebrow">Master Data</p>
        <h2>Employees & Crews</h2>
        <p>Maintain the employee, driver, helper, and fleet owner directory used by dispatch, payroll, liquidation, and reporting.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-open="create-employee-modal"><?php echo icon('plus'); ?> New Employee</button>
</section>

<section class="stats-grid" aria-label="Employee overview">
    <article class="stat-card">
        <span class="stat-label">Total Records</span>
        <strong><?php echo h($counts['total']); ?></strong>
        <p>Imported and newly encoded people records.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Drivers</span>
        <strong><?php echo h($counts['1']); ?></strong>
        <p>Available for trip assignment setup.</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">Helpers</span>
        <strong><?php echo h($counts['2']); ?></strong>
        <p>Available for trip crew assignment setup.</p>
    </article>
</section>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
<?php endforeach; ?>

<section class="management-directory">
    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>People Records</h3>
            </div>
        </div>

        <form method="get" action="<?php echo h(app_url('administrator/employees.php')); ?>" class="filter-bar">
            <input name="search" type="search" value="<?php echo h($search); ?>" placeholder="Search employee name">
            <select name="type">
                <option value="">All Classifications</option>
                <?php foreach ($employeeTypes as $code => $label): ?>
                    <option value="<?php echo h($code); ?>"<?php echo employee_selected($code, $typeFilter); ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-light">Filter</button>
        </form>

        <div class="table-wrap record-scroll" data-infinite-scroll>
            <table class="data-table record-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="employee-records" data-infinite-list data-page-size="20">
                    <?php foreach ($employees as $employee): ?>
                        <tr data-infinite-item>
                            <td><strong><?php echo h($employee['employee_id']); ?></strong></td>
                            <td><?php echo h(employee_display_name($employee)); ?></td>
                            <td><span class="<?php echo h(employee_badge_class($employee['who_is'])); ?>"><?php echo h(employee_type_label($employee['who_is'])); ?></span></td>
                            <td class="table-actions">
                                <div class="action-group">
                                    <button type="button" class="btn btn-edit btn-icon" data-modal-open="edit-employee-<?php echo h($employee['employee_id']); ?>"><?php echo icon('edit'); ?> Edit</button>
                                    <button type="button" class="btn btn-danger btn-icon" data-modal-open="delete-employee-<?php echo h($employee['employee_id']); ?>"><?php echo icon('trash'); ?> Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$employees): ?>
                        <tr class="empty-row">
                            <td colspan="4">No employee records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-status" data-infinite-status="employee-records"></p>
    </article>
</section>

<div class="modal" id="create-employee-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="create-employee-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow">Create</p>
                <h3 id="create-employee-title">New Employee or Crew</h3>
                <p>Add a person record and classify the role used by operations and payroll.</p>
            </div>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo h(app_url('administrator/employees.php')); ?>" class="stack-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <label for="firstname">First Name</label>
                <input id="firstname" name="firstname" type="text" maxlength="20" required>

                <label for="middlename">Middle Name</label>
                <input id="middlename" name="middlename" type="text" maxlength="20">

                <label for="lastname">Last Name</label>
                <input id="lastname" name="lastname" type="text" maxlength="20" required>

                <label for="who_is">Classification</label>
                <select id="who_is" name="who_is" required>
                    <?php foreach ($employeeTypes as $code => $label): ?>
                        <option value="<?php echo h($code); ?>"><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($employees as $employee): ?>
    <div class="modal" id="edit-employee-<?php echo h($employee['employee_id']); ?>" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-employee-title-<?php echo h($employee['employee_id']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h3 id="edit-employee-title-<?php echo h($employee['employee_id']); ?>"><?php echo h(employee_display_name($employee)); ?></h3>
                    <p>Update name and classification.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/employees.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="employee_id" value="<?php echo h($employee['employee_id']); ?>">

                    <label>First Name</label>
                    <input name="firstname" type="text" value="<?php echo h($employee['firstname']); ?>" maxlength="20" required>

                    <label>Middle Name</label>
                    <input name="middlename" type="text" value="<?php echo h($employee['middlename']); ?>" maxlength="20">

                    <label>Last Name</label>
                    <input name="lastname" type="text" value="<?php echo h($employee['lastname']); ?>" maxlength="20" required>

                    <label>Classification</label>
                    <select name="who_is" required>
                        <?php foreach ($employeeTypes as $code => $label): ?>
                            <option value="<?php echo h($code); ?>"<?php echo employee_selected($code, $employee['who_is']); ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-employee-<?php echo h($employee['employee_id']); ?>" hidden>
        <div class="modal-card small" role="dialog" aria-modal="true" aria-labelledby="delete-employee-title-<?php echo h($employee['employee_id']); ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Delete</p>
                    <h3 id="delete-employee-title-<?php echo h($employee['employee_id']); ?>">Delete Employee Record</h3>
                    <p>This will remove <?php echo h(employee_display_name($employee)); ?> from the people directory.</p>
                </div>
                <button type="button" class="icon-close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?php echo h(app_url('administrator/employees.php')); ?>" class="stack-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="employee_id" value="<?php echo h($employee['employee_id']); ?>">
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
