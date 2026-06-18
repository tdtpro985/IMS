<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db     = getDB();
$deptId = (int)($_GET['id'] ?? 0);

$deptStmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$deptStmt->bind_param('i', $deptId);
$deptStmt->execute();
$dept = $deptStmt->get_result()->fetch_assoc();
$deptStmt->close();

if (!$dept) { header('Location: /departments.php'); exit; }

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add intern
    if ($action === 'add_intern') {
        $fn  = trim($_POST['first_name']    ?? '');
        $ln  = trim($_POST['last_name']     ?? '');
        $mn  = trim($_POST['middle_name']   ?? '');
        $sch = trim($_POST['school']        ?? '');
        $crs = trim($_POST['course']        ?? '');
        $rh  = (float)($_POST['required_hours'] ?: 486);
        $sd  = trim($_POST['start_date']    ?? '') ?: null;

        if (!$fn || !$ln) {
            $_SESSION['add_error'] = 'First name and last name are required.';
            header("Location: /department_view.php?id={$deptId}");
            exit;
        }
        $stmt = $db->prepare(
            "INSERT INTO interns (department_id, first_name, last_name, middle_name, school, course, required_hours, start_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssssds', $deptId, $fn, $ln, $mn, $sch, $crs, $rh, $sd);
        $stmt->execute();
        $newId = $db->insert_id;
        $stmt->close();
        logAudit('CREATE', 'Interns', $newId, "Intern {$fn} {$ln} added to {$dept['name']}.");
        header("Location: /intern_workspace.php?id={$newId}&tab=201&new=1");
        exit;
    }

    // Set Inactive
    if ($action === 'inactive_intern') {    
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Inactive' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute(); $stmt->close();
        logAudit('UPDATE', 'Interns', $id, "Intern #{$id} set to Inactive.");
    }

    // Archive
    if ($action === 'archive_intern') {
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Archived' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute(); $stmt->close();
        logAudit('ARCHIVE', 'Interns', $id, "Intern #{$id} archived.");

    } elseif ($action === 'unarchive_intern') {
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Active' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute();
        $stmt->close();
        logAudit('RESTORE', 'Interns', $id, "Intern #{$id} restored to active.");
    }

    // Restore to Active
    if ($action === 'restore_intern') {
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Active' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute(); $stmt->close();
        logAudit('RESTORE', 'Interns', $id, "Intern #{$id} restored to Active.");
    }

    header("Location: /department_view.php?id={$deptId}" . ($action !== 'add_intern' ? "&status=" . ($_POST['back_status'] ?? 'Active') : ''));
    exit;
}

// ── Fetch interns ──────────────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status']      ?? 'Active';

$allowed = ['Active', 'Inactive', 'Archived'];
if (!in_array($statusFilter, $allowed)) $statusFilter = 'Active';

$params = [$deptId];
$types  = 'i';
$where  = "WHERE i.department_id = ? AND i.status = '{$statusFilter}'";

if ($search !== '') {
    $where   .= " AND CONCAT(i.first_name,' ',i.last_name) LIKE ?";
    $params[] = "%{$search}%";
    $types   .= 's';
}

$sql  = "SELECT i.*, d.name AS dept_name FROM interns i
         JOIN departments d ON d.id = i.department_id
         {$where}
         ORDER BY i.last_name, i.first_name";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$interns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count per status for tab badges
$counts = [];
foreach (['Active','Inactive','Archived'] as $s) {
    $r = $db->prepare("SELECT COUNT(*) FROM interns WHERE department_id=? AND status=?");
    $r->bind_param('is', $deptId, $s);
    $r->execute();
    $counts[$s] = $r->get_result()->fetch_row()[0];
    $r->close();
}

$pageTitle   = htmlspecialchars($dept['name']);
$breadcrumbs = [
    ['label' => 'Departments', 'url' => '/departments.php'],
    ['label' => $dept['name'], 'url' => ''],
];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1><?= htmlspecialchars($dept['name']) ?></h1>
        <p>Manage interns assigned to this department.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addInternModal')">
        <i class="fas fa-user-plus"></i> Add Intern
    </button>
</div>

<!-- Status Tabs -->
<div class="d-flex gap-8 mb-16" style="flex-wrap:wrap">
    <?php foreach (['Active','Inactive','Archived'] as $s):
        $active = $statusFilter === $s;
        $color  = match($s) {
            'Active'   => 'var(--success)',
            'Inactive' => 'var(--warning)',
            'Archived' => 'var(--gray-mid)',
        };
    ?>
    <a href="/department_view.php?id=<?= $deptId ?>&status=<?= $s ?>"
       style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:<?= $active?'700':'500' ?>;
              background:<?= $active?'var(--white)':'transparent' ?>;
              border:1.5px solid <?= $active?$color:'var(--gray-border)' ?>;
              color:<?= $active?$color:'var(--text-muted)' ?>;
              text-decoration:none;box-shadow:<?= $active?'0 2px 8px rgba(0,0,0,.08)':'' ?>">
        <?= $s ?>
        <span style="background:<?= $active?$color:'var(--gray-border)' ?>;color:#fff;border-radius:20px;padding:1px 8px;font-size:11px;font-weight:700">
            <?= $counts[$s] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search bar -->
<div class="card mb-20">
    <div class="card-body" style="padding:12px 20px">
        <form method="GET" class="toolbar">
            <input type="hidden" name="id"     value="<?= $deptId ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search interns…"
                       value="<?= htmlspecialchars($search) ?>" maxlength="100">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
            <a href="/department_view.php?id=<?= $deptId ?>&status=<?= $statusFilter ?>" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Intern Grid -->
<?php if (empty($interns)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No <?= strtolower($statusFilter) ?> interns<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
    </div>
</div></div>
<?php else: ?>
<div class="intern-grid">
    <?php foreach ($interns as $intern):
        $pct      = $intern['required_hours'] > 0
            ? min(100, round(($intern['rendered_hours'] / $intern['required_hours']) * 100))
            : 0;
        $initials = strtoupper(substr($intern['first_name'],0,1).substr($intern['last_name'],0,1));
        $status   = $intern['status'];
    ?>
    <div class="intern-card" onclick="location.href='/intern_workspace.php?id=<?= $intern['id'] ?>'">
        <div class="intern-card-header">
            <div class="intern-avatar">
                <?php if ($intern['profile_photo']): ?>
                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                <?php else: ?><?= $initials ?><?php endif; ?>
            </div>
            <div style="flex:1;min-width:0">
                <div class="intern-card-name"><?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></div>
                <div class="intern-card-dept"><?= htmlspecialchars($intern['school'] ?: '—') ?></div>
            </div>
            <?php
            $badgeStyle = match($status) {
                'Active'   => 'background:#ECFDF5;color:#16A34A',
                'Inactive' => 'background:#FFFBEB;color:#D97706',
                'Archived' => 'background:#F3F4F6;color:#6B7280',
                default    => ''
            };
            ?>
            <span class="badge" style="<?= $badgeStyle ?>;margin-left:auto;flex-shrink:0"><?= $status ?></span>
        </div>

        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="intern-card-meta">
            <span><?= number_format($intern['rendered_hours'],1) ?> / <?= number_format($intern['required_hours'],0) ?> hrs</span>
            <span><?= $pct ?>% complete</span>
        </div>

        <!-- Action buttons -->
        <div style="margin-top:10px;display:flex;gap:6px" onclick="event.stopPropagation()">
            <a href="/intern_workspace.php?id=<?= $intern['id'] ?>"
               class="btn btn-outline btn-sm" style="flex:1;justify-content:center">
                <i class="fas fa-eye"></i> View
            </a>

            <?php if ($status === 'Active'): ?>
                <!-- Active → can set Inactive or Archive -->
                <button class="btn btn-secondary btn-sm" title="Set Inactive"
                    onclick="setStatus(<?= $intern['id'] ?>, 'inactive_intern', '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                    <i class="fas fa-user-slash"></i>
                </button>
                <button class="btn btn-secondary btn-sm" title="Archive"
                    onclick="setStatus(<?= $intern['id'] ?>, 'archive_intern', '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                    <i class="fas fa-archive"></i>
                </button>

            <?php elseif ($status === 'Inactive'): ?>
                <!-- Inactive → can restore to Active or Archive -->
                <button class="btn btn-success btn-sm" title="Restore to Active"
                    onclick="setStatus(<?= $intern['id'] ?>, 'restore_intern', '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                    <i class="fas fa-undo"></i>
                </button>
                <button class="btn btn-secondary btn-sm" title="Archive"
                    onclick="setStatus(<?= $intern['id'] ?>, 'archive_intern', '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                    <i class="fas fa-archive"></i>
                </button>

            <?php elseif ($status === 'Archived'): ?>
                <!-- Archived → can only restore to Active -->
                <button class="btn btn-success btn-sm" title="Restore to Active"
                    onclick="setStatus(<?= $intern['id'] ?>, 'restore_intern', '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                    <i class="fas fa-undo"></i> Restore
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ Add Intern Modal ═══ -->
<div class="modal-overlay" id="addInternModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-user-plus text-orange"></i> Add New Intern</span>
            <button class="modal-close" onclick="closeModal('addInternModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_intern">
            <div class="modal-body">
                <p class="text-muted mb-16" style="font-size:13px">
                    Fill in the basics — you'll be redirected to the full 201 Profile to complete all details.
                </p>
                <?php if (!empty($_SESSION['add_error'])): ?>
                <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:var(--danger);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;display:flex;gap:8px;align-items:center">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['add_error']) ?>
                </div>
                <?php unset($_SESSION['add_error']); endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" required maxlength="80" placeholder="e.g. Maria">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" required maxlength="80" placeholder="e.g. Santos">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" maxlength="80" placeholder="Optional">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">School / University</label>
                        <input type="text" name="school" class="form-control" maxlength="150">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" class="form-control" maxlength="150">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Required Hours</label>
                        <input type="number" name="required_hours" class="form-control" value="486" min="1" step="0.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addInternModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Create &amp; Open Profile
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Status Change Confirm Modal ═══ -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="statusModalTitle">Confirm Action</span>
            <button class="modal-close" onclick="closeModal('statusModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="statusForm">
            <input type="hidden" name="action"      id="statusAction">
            <input type="hidden" name="intern_id"   id="statusInternId">
            <input type="hidden" name="back_status" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="modal-body">
                <p id="statusModalMsg"></p>
                <p class="text-muted mt-8" style="font-size:13px" id="statusModalNote"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="statusModalBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Unarchive Confirm Modal -->
<div class="modal-overlay" id="unarchiveModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Restore Intern</span>
            <button class="modal-close" onclick="closeModal('unarchiveModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="unarchive_intern">
            <input type="hidden" name="intern_id" id="unarchiveInternId">
            <div class="modal-body">
                <p>Restore <strong id="unarchiveInternName"></strong> to Active?</p>
                <p class="text-muted mt-8" style="font-size:13px">The intern will be moved back to the active list.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('unarchiveModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-undo"></i> Restore</button>
            </div>
        </form>
    </div>
</div>

<script>
const STATUS_LABELS = {
    inactive_intern: {
        title:  'Set Inactive',
        msg:    (n) => `Set <strong>${n}</strong> as Inactive?`,
        note:   'The intern will be hidden from the Active list but the record is preserved. You can restore later.',
        btn:    'Set Inactive',
        btnCls: 'btn-warning',
    },
    archive_intern: {
        title:  'Archive Intern',
        msg:    (n) => `Archive <strong>${n}</strong>?`,
        note:   'The record will be preserved and can be restored at any time.',
        btn:    'Archive',
        btnCls: 'btn-danger',
    },
    restore_intern: {
        title:  'Restore Intern',
        msg:    (n) => `Restore <strong>${n}</strong> to Active?`,
        note:   'The intern will appear in the Active list again.',
        btn:    'Restore',
        btnCls: 'btn-success',
    },
};

function setStatus(id, action, name) {
    const cfg = STATUS_LABELS[action];
    document.getElementById('statusModalTitle').textContent = cfg.title;
    document.getElementById('statusModalMsg').innerHTML     = cfg.msg(name);
    document.getElementById('statusModalNote').textContent  = cfg.note;
    document.getElementById('statusAction').value           = action;
    document.getElementById('statusInternId').value         = id;
    const btn = document.getElementById('statusModalBtn');
    btn.textContent = cfg.btn;
    btn.className   = 'btn ' + cfg.btnCls;
    openModal('statusModal');
}
function unarchiveIntern(id, name) {
    document.getElementById('unarchiveInternId').value = id;
    document.getElementById('unarchiveInternName').textContent = name;
    openModal('unarchiveModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
