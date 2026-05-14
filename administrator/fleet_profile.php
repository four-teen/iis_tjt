<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulk_actions.php';
require_once __DIR__ . '/../includes/master_data.php';

require_role('Administrator');

$fleetId = (int) ($_GET['fleetid'] ?? 0);
$fleet = find_fleet_by_id($fleetId);

if (!$fleet) {
    flash('error', 'Fleet record was not found.');
    redirect_to('Administrator/fleet.php');
}

$pageTitle = 'Fleet Profile';
$activeNav = 'fleet';
$returnUrl = 'Administrator/fleet_profile.php?fleetid=' . $fleetId;

function fleet_profile_is_filled($value)
{
    $value = clean_text($value ?? '');

    return $value !== '' && $value !== '-';
}

function fleet_profile_date_meta($value)
{
    $raw = trim((string) ($value ?? ''));

    if ($raw === '' || $raw === '0000-00-00') {
        return [
            'state' => 'missing',
            'date' => 'Not set',
            'label' => 'Not set',
            'detail' => 'No date encoded',
            'tone' => 'muted',
            'badge' => 'badge-muted',
        ];
    }

    $timestamp = strtotime($raw);

    if ($timestamp === false) {
        return [
            'state' => 'missing',
            'date' => 'Invalid date',
            'label' => 'Review',
            'detail' => 'Date needs review',
            'tone' => 'warning',
            'badge' => 'badge-warning',
        ];
    }

    $date = new DateTimeImmutable(date('Y-m-d', $timestamp));
    $today = new DateTimeImmutable('today');
    $days = (int) $today->diff($date)->format('%r%a');
    $detail = 'Clear for ' . $days . ' days';
    $state = 'valid';
    $tone = 'success';
    $badge = 'badge-success';
    $label = 'Valid';

    if ($days < 0) {
        $state = 'expired';
        $tone = 'danger';
        $badge = 'badge-danger';
        $label = 'Expired';
        $detail = abs($days) . ' days overdue';
    } elseif ($days === 0) {
        $state = 'warning';
        $tone = 'warning';
        $badge = 'badge-warning';
        $label = 'Due today';
        $detail = 'Expires today';
    } elseif ($days <= 30) {
        $state = 'warning';
        $tone = 'warning';
        $badge = 'badge-warning';
        $label = 'Expiring';
        $detail = 'Expires in ' . $days . ' days';
    }

    return [
        'state' => $state,
        'date' => date('M d, Y', $timestamp),
        'label' => $label,
        'detail' => $detail,
        'tone' => $tone,
        'badge' => $badge,
    ];
}

function fleet_profile_date_text($value)
{
    return fleet_profile_date_meta($value)['date'];
}

function fleet_profile_value($value)
{
    $value = clean_text($value ?? '');

    return $value === '' || $value === '-' ? 'Not encoded' : $value;
}

function fleet_profile_selected($value, $current)
{
    return (string) $value === (string) $current ? ' selected' : '';
}

function fleet_profile_status_pill($value)
{
    $status = fleet_profile_date_meta($value);

    return '<span class="fleet-status fleet-status-' . h($status['tone']) . '">' . h($status['label']) . '</span>';
}

function fleet_profile_completion($fleet, $fields)
{
    if (!$fields) {
        return 0;
    }

    $filled = 0;

    foreach ($fields as $field) {
        if (fleet_profile_is_filled($fleet[$field] ?? '')) {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

function render_fleet_lookup_options($rows, $idField, $labelField, $current)
{
    echo '<option value="">Not encoded</option>';

    foreach ($rows as $row) {
        $label = $row[$labelField] === '-' ? 'Not encoded' : $row[$labelField];
        echo '<option value="' . h($row[$idField]) . '"' . fleet_profile_selected($row[$idField], $current) . '>' . h($label) . '</option>';
    }
}

function fleet_person_name($person)
{
    return clean_text(($person['lastname'] ?? '') . ', ' . ($person['firstname'] ?? '') . ' ' . ($person['middlename'] ?? ''));
}

function fleet_person_role_label($type)
{
    return (string) $type === '1' ? 'Driver' : 'Helper';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_to($returnUrl);
    }

    try {
        if ($action === 'update_registry') {
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
                flash('success', 'Fleet registry updated.');
            }
        } elseif ($action === 'update_profile') {
            $data = [
                'cpc' => $_POST['cpc'] ?? '',
                'cpcvalidity' => $_POST['cpcvalidity'] ?? '',
                'platecolor' => $_POST['platecolor'] ?? '',
                'ltfrbstatus' => $_POST['ltfrbstatus'] ?? '',
                'trucktype' => $_POST['trucktype'] ?? '',
                'vantype' => $_POST['vantype'] ?? '',
                'make' => $_POST['make'] ?? '',
                'body' => $_POST['body'] ?? '',
                'color' => $_POST['color'] ?? '',
                'yearmodel' => $_POST['yearmodel'] ?? '',
                'yearacquired' => $_POST['yearacquired'] ?? '',
                'chassisnumber' => $_POST['chassisnumber'] ?? '',
                'enginenumber' => $_POST['enginenumber'] ?? '',
            ];
            $errors = validate_fleet_profile_data($data);

            if ($errors) {
                flash('error', implode(' ', $errors));
            } else {
                save_fleet_profile($fleetId, $data);
                flash('success', 'Fleet technical profile updated.');
            }
        } elseif ($action === 'assign_person') {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $expectedType = (string) ($_POST['employee_type'] ?? '');
            $employee = find_employee_by_id($employeeId);

            if (!$employee || (string) $employee['who_is'] !== $expectedType) {
                flash('error', 'Select a valid ' . strtolower(fleet_person_role_label($expectedType)) . '.');
            } elseif (assign_fleet_person($fleetId, $employeeId)) {
                flash('success', fleet_person_role_label($expectedType) . ' assigned to fleet.');
            } else {
                flash('error', 'That person is already assigned to this fleet.');
            }
        } elseif ($action === 'remove_assignment') {
            $assignmentId = (int) ($_POST['assigned_id'] ?? 0);
            $assignment = find_fleet_assignment_by_id($assignmentId);

            if (!$assignment || (int) $assignment['assigned_fleetid'] !== $fleetId) {
                flash('error', 'Assignment was not found for this fleet.');
            } elseif (remove_fleet_assignment($assignmentId)) {
                flash('success', 'Fleet assignment removed.');
            } else {
                flash('error', 'Fleet assignment could not be removed.');
            }
        } elseif ($action === 'bulk_remove_assignments') {
            $ids = normalize_bulk_ids($_POST['ids'] ?? []);
            $result = bulk_delete_records($ids, function ($assignmentId) use ($fleetId) {
                $assignment = find_fleet_assignment_by_id($assignmentId);

                return $assignment && (int) $assignment['assigned_fleetid'] === $fleetId ? $assignment : null;
            }, function ($assignmentId) {
                return remove_fleet_assignment($assignmentId);
            });

            flash_bulk_delete_result('assignment', $result);
        }
    } catch (Throwable $error) {
        flash('error', 'Fleet profile action failed: ' . $error->getMessage());
    }

    redirect_to($returnUrl);
}

$fleet = find_fleet_by_id($fleetId);
$lookups = [
    'truckTypes' => fleet_setup_rows('tbltrucktype'),
    'vanTypes' => fleet_setup_rows('tblvantype'),
    'makes' => fleet_setup_rows('tblmake'),
    'bodies' => fleet_setup_rows('tblbody'),
    'colors' => fleet_setup_rows('tblcolor'),
    'plateColors' => fleet_setup_rows('tblcolor_plate'),
    'ltfrbStatuses' => fleet_setup_rows('tblltfrb_status'),
];
$drivers = list_available_fleet_people('1');
$helpers = list_available_fleet_people('2');
$assignments = list_fleet_assignments($fleetId);
$driverAssignments = [];
$helperAssignments = [];

foreach ($assignments as $assignment) {
    if ((string) $assignment['who_is'] === '1') {
        $driverAssignments[] = $assignment;
    } elseif ((string) $assignment['who_is'] === '2') {
        $helperAssignments[] = $assignment;
    }
}

$driverCount = count($driverAssignments);
$helperCount = count($helperAssignments);
$profileFields = [
    'platenumber',
    'casenumber',
    'validity',
    'paremarks',
    'pavalidity',
    'decisionremarks',
    'decisionvalidity',
    'cpc',
    'cpcvalidity',
    'platecolor',
    'ltfrbstatus',
    'trucktype',
    'vantype',
    'make',
    'body',
    'color',
    'yearmodel',
    'yearacquired',
    'chassisnumber',
    'enginenumber',
];
$completionPercent = fleet_profile_completion($fleet, $profileFields);
$vehicleParts = [];

foreach ([$fleet['trucktype_name'] ?? '', $fleet['vantype_name'] ?? ($fleet['vantype'] ?? ''), $fleet['makename'] ?? '', $fleet['body_name'] ?? ''] as $part) {
    if (fleet_profile_is_filled($part)) {
        $vehicleParts[] = fleet_profile_value($part);
    }
}

$vehicleSummary = $vehicleParts ? implode(' / ', $vehicleParts) : 'Vehicle specification pending';
$complianceItems = [
    [
        'label' => 'Registration',
        'date' => $fleet['validity'] ?? '',
        'reference' => 'Case ' . fleet_profile_value($fleet['casenumber'] ?? ''),
        'icon' => 'id-card',
    ],
    [
        'label' => 'PA Validity',
        'date' => $fleet['pavalidity'] ?? '',
        'reference' => fleet_profile_value($fleet['paremarks'] ?? ''),
        'icon' => 'calendar',
    ],
    [
        'label' => 'Decision Validity',
        'date' => $fleet['decisionvalidity'] ?? '',
        'reference' => fleet_profile_value($fleet['decisionremarks'] ?? ''),
        'icon' => 'clipboard',
    ],
    [
        'label' => 'CPC Validity',
        'date' => $fleet['cpcvalidity'] ?? '',
        'reference' => 'CPC ' . fleet_profile_value($fleet['cpc'] ?? ''),
        'icon' => 'shield',
    ],
];
$statusCounts = ['expired' => 0, 'warning' => 0, 'missing' => 0, 'valid' => 0];

foreach ($complianceItems as $item) {
    $meta = fleet_profile_date_meta($item['date']);
    $statusCounts[$meta['state']] = ($statusCounts[$meta['state']] ?? 0) + 1;
}

$readinessTone = 'success';
$readinessLabel = 'Operational';

if ($statusCounts['expired'] > 0) {
    $readinessTone = 'danger';
    $readinessLabel = 'Needs attention';
} elseif ($statusCounts['warning'] > 0) {
    $readinessTone = 'warning';
    $readinessLabel = 'Monitor dates';
} elseif ($completionPercent < 70 || $statusCounts['missing'] > 0) {
    $readinessTone = 'muted';
    $readinessLabel = 'Profile incomplete';
}

$paRemarks = clean_text($fleet['paremarks'] ?? '');
$messages = flash_messages();

require APP_ROOT . '/partials/admin_header.php';
?>
<section class="fleet-profile-hero" data-fleet-profile-page>
    <div class="fleet-hero-main">
        <span class="fleet-hero-icon"><?php echo icon('truck'); ?></span>
        <div>
            <p class="eyebrow">Fleet Profile Management</p>
            <h2><?php echo h(fleet_profile_value($fleet['platenumber'] ?? '')); ?></h2>
            <p><?php echo h($vehicleSummary); ?></p>
            <div class="fleet-chip-row" aria-label="Fleet profile tags">
                <span class="fleet-chip">Fleet ID <?php echo h($fleetId); ?></span>
                <span class="fleet-chip fleet-chip-<?php echo h($readinessTone); ?>"><?php echo h($readinessLabel); ?></span>
                <span class="fleet-chip"><?php echo h(fleet_profile_value($fleet['ltfrb_status'] ?? 'LTFRB pending')); ?></span>
            </div>
        </div>
    </div>
    <div class="fleet-hero-actions">
        <a class="btn btn-light btn-icon" href="<?php echo h(app_url('Administrator/fleet.php')); ?>"><?php echo icon('arrow-left'); ?> Back to Fleet</a>
        <div class="fleet-completion" aria-label="Profile completion">
            <div>
                <span>Profile completeness</span>
                <strong><?php echo h($completionPercent); ?>%</strong>
            </div>
            <div class="fleet-progress"><span style="width: <?php echo h($completionPercent); ?>%"></span></div>
        </div>
    </div>
</section>

<div data-fleet-alerts>
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-<?php echo h($message['type']); ?>" role="alert"><?php echo h($message['message']); ?></div>
    <?php endforeach; ?>
</div>

<section class="fleet-health-grid" aria-label="Fleet compliance overview">
    <?php foreach ($complianceItems as $item): ?>
        <?php $status = fleet_profile_date_meta($item['date']); ?>
        <article class="fleet-health-card fleet-health-<?php echo h($status['tone']); ?>">
            <div class="fleet-health-icon"><?php echo icon($item['icon']); ?></div>
            <div>
                <p><?php echo h($item['label']); ?></p>
                <strong><?php echo h($status['date']); ?></strong>
                <span><?php echo h($status['detail']); ?></span>
                <small><?php echo h($item['reference']); ?></small>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="fleet-management-layout">
    <article class="panel fleet-snapshot-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Operating Snapshot</p>
                <h3>Truck Registry</h3>
            </div>
            <span class="badge <?php echo h(fleet_profile_date_meta($fleet['validity'] ?? '')['badge']); ?>"><?php echo h(fleet_profile_date_meta($fleet['validity'] ?? '')['label']); ?></span>
        </div>

        <dl class="fleet-key-details">
            <div>
                <dt>Plate Number</dt>
                <dd><?php echo h(fleet_profile_value($fleet['platenumber'] ?? '')); ?></dd>
            </div>
            <div>
                <dt>Case Number</dt>
                <dd><?php echo h(fleet_profile_value($fleet['casenumber'] ?? '')); ?></dd>
            </div>
            <div>
                <dt>Truck Type</dt>
                <dd><?php echo h(fleet_profile_value($fleet['trucktype_name'] ?? '')); ?></dd>
            </div>
            <div>
                <dt>Van Type</dt>
                <dd><?php echo h(fleet_profile_value($fleet['vantype_name'] ?? ($fleet['vantype'] ?? ''))); ?></dd>
            </div>
            <div>
                <dt>Make / Body</dt>
                <dd><?php echo h(fleet_profile_value($fleet['makename'] ?? '') . ' / ' . fleet_profile_value($fleet['body_name'] ?? '')); ?></dd>
            </div>
            <div>
                <dt>LTFRB Status</dt>
                <dd><?php echo h(fleet_profile_value($fleet['ltfrb_status'] ?? '')); ?></dd>
            </div>
        </dl>

        <div class="fleet-divider"></div>

        <div class="fleet-spec-list">
            <div>
                <span>Plate Color</span>
                <strong><?php echo h(fleet_profile_value($fleet['color_plate_desc'] ?? '')); ?></strong>
            </div>
            <div>
                <span>Color</span>
                <strong><?php echo h(fleet_profile_value($fleet['color_name'] ?? '')); ?></strong>
            </div>
            <div>
                <span>Year Model</span>
                <strong><?php echo h(fleet_profile_value($fleet['yearmodel'] ?? '')); ?></strong>
            </div>
            <div>
                <span>Year Acquired</span>
                <strong><?php echo h(fleet_profile_value($fleet['yearacquired'] ?? '')); ?></strong>
            </div>
            <div>
                <span>Chassis Number</span>
                <strong><?php echo h(fleet_profile_value($fleet['chassisnumber'] ?? '')); ?></strong>
            </div>
            <div>
                <span>Engine Number</span>
                <strong><?php echo h(fleet_profile_value($fleet['enginenumber'] ?? '')); ?></strong>
            </div>
        </div>
    </article>

    <article class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Registry</p>
                <h3>Operating Documents</h3>
            </div>
        </div>
        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form fleet-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_registry">
            <div class="form-grid">
                <div>
                    <label for="platenumber">Plate Number</label>
                    <input id="platenumber" name="platenumber" type="text" maxlength="20" value="<?php echo h($fleet['platenumber'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="casenumber">Case Number</label>
                    <input id="casenumber" name="casenumber" type="text" maxlength="20" value="<?php echo h($fleet['casenumber'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="validity">Registration Validity</label>
                    <input id="validity" name="validity" type="date" value="<?php echo h($fleet['validity'] ?? ''); ?>">
                    <?php echo fleet_profile_status_pill($fleet['validity'] ?? ''); ?>
                </div>
                <div>
                    <label for="paremarks">PA Remarks</label>
                    <select id="paremarks" name="paremarks">
                        <option value="">Not set</option>
                        <option value="TEMPORARY PLATE"<?php echo $paRemarks === 'TEMPORARY PLATE' ? ' selected' : ''; ?>>Temporary Plate</option>
                        <option value="PERMANENT PLATE"<?php echo $paRemarks === 'PERMANENT PLATE' ? ' selected' : ''; ?>>Permanent Plate</option>
                        <?php if ($paRemarks !== '' && !in_array($paRemarks, ['TEMPORARY PLATE', 'PERMANENT PLATE'], true)): ?>
                            <option value="<?php echo h($paRemarks); ?>" selected><?php echo h($paRemarks); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="pavalidity">PA Validity</label>
                    <input id="pavalidity" name="pavalidity" type="date" value="<?php echo h($fleet['pavalidity'] ?? ''); ?>">
                    <?php echo fleet_profile_status_pill($fleet['pavalidity'] ?? ''); ?>
                </div>
                <div>
                    <label for="decisionvalidity">Decision Validity</label>
                    <input id="decisionvalidity" name="decisionvalidity" type="date" value="<?php echo h($fleet['decisionvalidity'] ?? ''); ?>">
                    <?php echo fleet_profile_status_pill($fleet['decisionvalidity'] ?? ''); ?>
                </div>
            </div>
            <div>
                <label for="decisionremarks">Decision Remarks</label>
                <input id="decisionremarks" name="decisionremarks" type="text" maxlength="30" value="<?php echo h($fleet['decisionremarks'] ?? ''); ?>">
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary btn-icon"><?php echo icon('save'); ?> Save Registry</button>
            </div>
        </form>
    </article>
</section>

<section class="fleet-operations-grid">
    <article class="panel fleet-technical-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Technical Profile</p>
                <h3>Vehicle Specification</h3>
            </div>
            <span class="badge <?php echo h(fleet_profile_date_meta($fleet['cpcvalidity'] ?? '')['badge']); ?>">CPC <?php echo h(fleet_profile_date_meta($fleet['cpcvalidity'] ?? '')['label']); ?></span>
        </div>
        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="stack-form fleet-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="form-grid">
                <div>
                    <label for="cpc">CPC</label>
                    <input id="cpc" name="cpc" type="text" maxlength="30" value="<?php echo h($fleet['cpc'] ?? ''); ?>">
                </div>
                <div>
                    <label for="cpcvalidity">CPC Validity</label>
                    <input id="cpcvalidity" name="cpcvalidity" type="date" value="<?php echo h($fleet['cpcvalidity'] ?? ''); ?>">
                    <?php echo fleet_profile_status_pill($fleet['cpcvalidity'] ?? ''); ?>
                </div>
            </div>
            <div class="form-grid route-rate-grid">
                <div>
                    <label for="platecolor">Plate Color</label>
                    <select id="platecolor" name="platecolor"><?php render_fleet_lookup_options($lookups['plateColors'], 'color_plate_id', 'color_plate_desc', $fleet['platecolor'] ?? ''); ?></select>
                </div>
                <div>
                    <label for="ltfrbstatus">LTFRB Status</label>
                    <select id="ltfrbstatus" name="ltfrbstatus"><?php render_fleet_lookup_options($lookups['ltfrbStatuses'], 'ltfrb_status_id', 'ltfrb_status', $fleet['ltfrbstatus'] ?? ''); ?></select>
                </div>
                <div>
                    <label for="trucktype">Truck Type</label>
                    <select id="trucktype" name="trucktype"><?php render_fleet_lookup_options($lookups['truckTypes'], 'trucktypeid', 'trucktype', $fleet['trucktype'] ?? ''); ?></select>
                </div>
            </div>
            <div class="form-grid route-rate-grid">
                <div>
                    <label for="vantype">Van Type</label>
                    <select id="vantype" name="vantype"><?php render_fleet_lookup_options($lookups['vanTypes'], 'vantypeid', 'vantype', $fleet['vantype_id'] ?? ($fleet['vantype'] ?? '')); ?></select>
                </div>
                <div>
                    <label for="make">Make</label>
                    <select id="make" name="make"><?php render_fleet_lookup_options($lookups['makes'], 'makeid', 'makename', $fleet['make'] ?? ''); ?></select>
                </div>
                <div>
                    <label for="body">Body</label>
                    <select id="body" name="body"><?php render_fleet_lookup_options($lookups['bodies'], 'body_id', 'body_name', $fleet['body'] ?? ''); ?></select>
                </div>
            </div>
            <div class="form-grid route-rate-grid">
                <div>
                    <label for="color">Color</label>
                    <select id="color" name="color"><?php render_fleet_lookup_options($lookups['colors'], 'color_id', 'color_name', $fleet['color'] ?? ''); ?></select>
                </div>
                <div>
                    <label for="yearmodel">Year Model</label>
                    <input id="yearmodel" name="yearmodel" type="text" maxlength="4" value="<?php echo h($fleet['yearmodel'] ?? ''); ?>">
                </div>
                <div>
                    <label for="yearacquired">Year Acquired</label>
                    <input id="yearacquired" name="yearacquired" type="text" maxlength="4" value="<?php echo h($fleet['yearacquired'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="chassisnumber">Chassis Number</label>
                    <input id="chassisnumber" name="chassisnumber" type="text" maxlength="20" value="<?php echo h($fleet['chassisnumber'] ?? ''); ?>">
                </div>
                <div>
                    <label for="enginenumber">Engine Number</label>
                    <input id="enginenumber" name="enginenumber" type="text" maxlength="20" value="<?php echo h($fleet['enginenumber'] ?? ''); ?>">
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary btn-icon"><?php echo icon('save'); ?> Save Technical Profile</button>
            </div>
        </form>
    </article>

    <article class="panel fleet-crew-panel" data-fleet-fragment="crew-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Crew Assignment</p>
                <h3>Drivers and Helpers</h3>
            </div>
            <div class="count-badges" aria-label="Fleet crew counts">
                <span class="count-badge">Drivers <strong><?php echo h($driverCount); ?></strong></span>
                <span class="count-badge count-badge-success">Helpers <strong><?php echo h($helperCount); ?></strong></span>
            </div>
        </div>

        <div class="assignment-forms fleet-assignment-forms">
            <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="inline-form" data-fleet-ajax-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="assign_person">
                <input type="hidden" name="employee_type" value="1">
                <label class="sr-only" for="employee-driver">Select Driver</label>
                <select id="employee-driver" name="employee_id" required <?php echo !$drivers ? 'disabled' : ''; ?>>
                    <?php if (!$drivers): ?>
                        <option value="">No available drivers</option>
                    <?php endif; ?>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?php echo h($driver['employee_id']); ?>"><?php echo h(fleet_person_name($driver)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm btn-icon" <?php echo !$drivers ? 'disabled' : ''; ?>><?php echo icon('plus'); ?> Driver</button>
            </form>
            <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="inline-form" data-fleet-ajax-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="assign_person">
                <input type="hidden" name="employee_type" value="2">
                <label class="sr-only" for="employee-helper">Select Helper</label>
                <select id="employee-helper" name="employee_id" required <?php echo !$helpers ? 'disabled' : ''; ?>>
                    <?php if (!$helpers): ?>
                        <option value="">No available helpers</option>
                    <?php endif; ?>
                    <?php foreach ($helpers as $helper): ?>
                        <option value="<?php echo h($helper['employee_id']); ?>"><?php echo h(fleet_person_name($helper)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm btn-icon" <?php echo !$helpers ? 'disabled' : ''; ?>><?php echo icon('plus'); ?> Helper</button>
            </form>
        </div>

        <form method="post" action="<?php echo h(app_url($returnUrl)); ?>" class="bulk-delete-form" data-bulk-delete-form data-bulk-delete-label="assignments" data-fleet-ajax-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_remove_assignments">
            <div class="bulk-table-toolbar">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" data-bulk-delete-button disabled><?php echo icon('trash'); ?> Remove Selected</button>
                <span data-bulk-delete-count>0 selected</span>
            </div>
            <div class="table-wrap">
                <table class="data-table record-table">
                    <thead>
                        <tr>
                            <th class="select-column"><input type="checkbox" data-bulk-delete-toggle aria-label="Select all assignments"></th>
                            <th>Role</th>
                            <th>Name</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-page-size="10">
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td class="select-column"><input type="checkbox" name="ids[]" value="<?php echo h($assignment['assigned_id']); ?>" data-bulk-delete-item aria-label="Select <?php echo h(fleet_person_name($assignment)); ?>"></td>
                                <td><span class="<?php echo (string) $assignment['who_is'] === '1' ? 'badge badge-danger' : 'badge badge-success'; ?>"><?php echo h(fleet_person_role_label($assignment['who_is'])); ?></span></td>
                                <td><strong><?php echo h(fleet_person_name($assignment)); ?></strong></td>
                                <td class="table-actions">
                                    <button type="submit" form="remove-assignment-<?php echo h($assignment['assigned_id']); ?>" class="btn btn-danger btn-sm btn-icon"><?php echo icon('trash'); ?> Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$assignments): ?>
                            <tr class="empty-row"><td colspan="4">No driver or helper assignments yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php foreach ($assignments as $assignment): ?>
            <form id="remove-assignment-<?php echo h($assignment['assigned_id']); ?>" method="post" action="<?php echo h(app_url($returnUrl)); ?>" hidden data-fleet-ajax-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="remove_assignment">
                <input type="hidden" name="assigned_id" value="<?php echo h($assignment['assigned_id']); ?>">
            </form>
        <?php endforeach; ?>
    </article>
</section>
<?php require APP_ROOT . '/partials/admin_footer.php'; ?>
