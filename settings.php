<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();
requireRole('admin');

$db = getDB();
$success = '';
$error   = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Edit user
    if ($action === 'edit_user') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $name     = trim($_POST['name']  ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'hr_staff';
        $newPass  = $_POST['new_password'] ?? '';

        if (!$name || !$email) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($newPass && strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            if ($newPass) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                $stmt->bind_param('ssssi', $name, $email, $role, $hash, $id);
            } else {
                $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                $stmt->bind_param('sssi', $name, $email, $role, $id);
            }
            if ($stmt->execute()) {
                logAudit('UPDATE', 'Users', $id, "User #{$id} updated: {$name} ({$email}), role: {$role}." . ($newPass ? ' Password changed.' : ''));
                $success = "User '{$name}' updated successfully.";
            } else {
                $error = 'Email already in use by another account.';
            }
            $stmt->close();
        }
    }

    // Add user
    if ($action === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'hr_staff';

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            if ($stmt->execute()) {
                $newId = $db->insert_id;
                logAudit('CREATE', 'Users', $newId, "User '{$name}' ({$email}) created with role '{$role}'.");
                $success = "User '{$name}' created successfully.";
            } else {
                $error = 'Email already exists.';
            }
            $stmt->close();
        }
    }

    // Unlock user
    if ($action === 'unlock_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET is_locked=0, fail_count=0 WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        logAudit('UPDATE', 'Users', $id, "User #{$id} account unlocked.");
        $success = 'Account unlocked.';
    }

    // Save system settings
    if ($action === 'save_settings') {
        $lunchEnabled = isset($_POST['lunch_break_enabled']) ? '1' : '0';
        $lunchMins    = max(0, min(120, (int)($_POST['lunch_break_minutes'] ?? 60)));
        $stdHours     = max(1, min(12,  (int)($_POST['standard_hours']      ?? 8)));

        $pairs = [
            'lunch_break_enabled' => $lunchEnabled,
            'lunch_break_minutes' => (string)$lunchMins,
            'standard_hours'      => (string)$stdHours,
        ];
        foreach ($pairs as $k => $v) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?");
            $stmt->bind_param('sss', $k, $v, $v);
            $stmt->execute(); $stmt->close();
        }
        logAudit('UPDATE', 'Settings', null, "Shift/Hours settings updated.");
        $success = 'Settings saved successfully.';
    }

    // Change own password
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param('i', currentUserId());
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, currentUserId());
            $stmt->execute();
            $stmt->close();
            logAudit('UPDATE', 'Users', currentUserId(), 'Password changed.');
            $success = 'Password updated successfully.';
        }
    }
}

$users = $db->query("SELECT id, name, email, role, is_locked, fail_count, created_at FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch system settings
$sRows = $db->query("SELECT setting_key, setting_val FROM system_settings")->fetch_all(MYSQLI_ASSOC);
$sysSettings = [];
foreach ($sRows as $r) $sysSettings[$r['setting_key']] = $r['setting_val'];
$lunchEnabled = ($sysSettings['lunch_break_enabled'] ?? '0') === '1';
$lunchMins    = (int)($sysSettings['lunch_break_minutes'] ?? 60);
$stdHours     = (int)($sysSettings['standard_hours']      ?? 8);

$pageTitle   = 'Settings';
$breadcrumbs = [['label' => 'Settings', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Settings</h1>
    <p>Manage system users and account settings.</p>
</div>

<?php if ($success): ?>
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:var(--success);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

<!-- Users List -->
<div class="card" style="grid-column:1/-1">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users text-orange"></i> System Users</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addUserModal')">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr style="cursor:pointer"
                    onclick="openEditUser(<?= htmlspecialchars(json_encode([
                        'id'        => $u['id'],
                        'name'      => $u['name'],
                        'email'     => $u['email'],
                        'role'      => $u['role'],
                        'is_locked' => (bool)$u['is_locked'],
                    ])) ?>)" title="Click to edit">
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($u['name']) ?></div>
                        <?php if ($u['id'] === currentUserId()): ?>
                        <div class="fs-11 text-muted">(you)</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin'?'badge-approved':'badge-submitted' ?>">
                            <?= ucfirst(str_replace('_',' ',$u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['is_locked']): ?>
                        <span class="badge badge-pending"><i class="fas fa-lock"></i> Locked</span>
                        <?php else: ?>
                        <span class="badge badge-active"><i class="fas fa-check"></i> Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="fs-12 text-muted"><?= htmlspecialchars($u['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted fs-12" style="padding:10px 16px">
            <i class="fas fa-info-circle"></i> Click any row to edit that user.
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-user-edit text-orange"></i> Edit User</span>
            <button class="modal-close" onclick="closeModal('editUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="editUserName" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="editUserEmail" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editUserRole" class="form-control">
                        <option value="hr_staff">HR Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span class="text-muted fs-12">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" id="editUserPassword"
                           class="form-control" minlength="8" placeholder="Min 8 characters">
                </div>
                <div id="editUserLockedSection" style="display:none;margin-top:12px;padding:12px;
                     background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:8px">
                    <div style="font-size:13px;color:var(--danger);margin-bottom:8px">
                        <i class="fas fa-lock"></i> This account is currently locked.
                    </div>
                    <button type="button" class="btn btn-sm" style="background:var(--success);color:#fff"
                            onclick="unlockFromEditModal()">
                        <i class="fas fa-unlock"></i> Unlock Account
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUser(u) {
    document.getElementById('editUserId').value       = u.id;
    document.getElementById('editUserName').value     = u.name;
    document.getElementById('editUserEmail').value    = u.email;
    document.getElementById('editUserRole').value     = u.role;
    document.getElementById('editUserPassword').value = '';
    document.getElementById('editUserLockedSection').style.display = u.is_locked ? 'block' : 'none';
    openModal('editUserModal');
}

function unlockFromEditModal() {
    const id = document.getElementById('editUserId').value;
    const fd = new FormData();
    fd.append('action',  'unlock_user');
    fd.append('user_id', id);
    fetch('/settings.php', { method:'POST', body:fd })
        .then(() => { showToast('Account unlocked.', 'success'); setTimeout(() => location.reload(), 800); });
}
</script>

<!-- Shift & Hours Settings -->
<div class="card mb-24">
    <div class="card-header"><span class="card-title"><i class="fas fa-clock text-orange"></i> Shift &amp; Hours Customization</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13.5px;font-weight:500">
                    <input type="checkbox" name="lunch_break_enabled" value="1"
                           <?= $lunchEnabled ? 'checked' : '' ?>
                           style="width:16px;height:16px;accent-color:var(--orange)">
                    Deduct lunch break / rest period from rendered hours
                </label>
                <p class="text-muted mt-4" style="font-size:12px;margin-left:26px">
                    When enabled, the lunch break duration below is subtracted from each DTR entry's rendered hours.
                </p>
            </div>
            <div class="form-row" style="max-width:420px">
                <div class="form-group">
                    <label class="form-label">Lunch Break Duration (minutes)</label>
                    <input type="number" name="lunch_break_minutes" class="form-control"
                           value="<?= $lunchMins ?>" min="0" max="120" step="5">
                    <span style="font-size:11px;color:var(--text-muted)">Default: 60 minutes</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Standard Daily Hours Threshold</label>
                    <input type="number" name="standard_hours" class="form-control"
                           value="<?= $stdHours ?>" min="1" max="12" step="0.5">
                    <span style="font-size:11px;color:var(--text-muted)">Used to compute overtime (default: 8 hrs)</span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-8">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>
</div>

<!-- Change Password -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-key text-orange"></i> Change Password</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
        </form>
    </div>
</div>

</div><!-- /grid -->

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add User</span>
            <button class="modal-close" onclick="closeModal('addUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                    <span class="form-error" style="color:var(--text-muted)">Minimum 8 characters</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="hr_staff">HR Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
