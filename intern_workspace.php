<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db       = getDB();
$internId = (int)($_GET['id'] ?? 0);

// ── Load intern early (needed for POST handlers) ──────────────────────────
$stmt = $db->prepare(
    "SELECT i.*, d.name AS dept_name, d.id AS dept_id
     FROM interns i JOIN departments d ON d.id = i.department_id
     WHERE i.id = ?"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$intern) { header('Location: /interns.php'); exit; }

// ════════════════════════════════════════════════════════════════════════════
// ALL POST HANDLERS — must run BEFORE any HTML output
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $backTab = '201';

    // ── Profile: update ──────────────────────────────────────────────────
    if ($action === 'update_profile') {
        $backTab = '201';

        $fn   = trim($_POST['first_name']      ?? '');
        $ln   = trim($_POST['last_name']       ?? '');
        $mn   = trim($_POST['middle_name']     ?? '');
        $em   = trim($_POST['email']           ?? '');
        $ph   = trim($_POST['phone']           ?? '');
        $addr = trim($_POST['address']         ?? '');
        $bd   = trim($_POST['birthdate']       ?? '') ?: null;
        $gen  = trim($_POST['gender']          ?? '');
        $sch  = trim($_POST['school']          ?? '');
        $crs  = trim($_POST['course']          ?? '');
        $yl   = trim($_POST['year_level']      ?? '');
        $sa   = trim($_POST['school_address']  ?? '');
        $rh   = (float)($_POST['required_hours'] ?: 486);
        $sd   = trim($_POST['start_date']      ?? '') ?: null;
        $ed   = trim($_POST['end_date']        ?? '') ?: null;
        $sup  = trim($_POST['supervisor']      ?? '');
        $nat  = trim($_POST['nationality']     ?? '');
        $deptNew = (int)($_POST['department_id'] ?? $intern['department_id']);
        $cs   = trim($_POST['civil_status']    ?? '');
        $gn   = trim($_POST['guardian_name']   ?? '');
        $gc   = trim($_POST['guardian_contact']?? '');

        if (!$fn || !$ln) {
            $_SESSION['profile_error'] = 'First name and last name are required.';
        } else {
            // Photo upload
            $photoName = $intern['profile_photo'];
            if (!empty($_FILES['profile_photo']['name'])) {
                $file    = $_FILES['profile_photo'];
                $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png'];
                $maxSize = 5 * 1024 * 1024;

                if (!in_array($ext, $allowed)) {
                    $_SESSION['profile_error'] = 'Profile photo must be JPEG or PNG.';
                } elseif ($file['size'] > $maxSize) {
                    $_SESSION['profile_error'] = 'Profile photo must not exceed 5 MB.';
                } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                    $_SESSION['profile_error'] = 'Upload failed (error code ' . $file['error'] . ').';
                } else {
                    $uploadDir = __DIR__ . '/uploads/photos/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $photoName = $internId . '_' . time() . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $photoName)) {
                        $_SESSION['profile_error'] = 'Could not save photo. Check folder permissions.';
                        $photoName = $intern['profile_photo']; // revert
                    }
                }
            }

            if (!isset($_SESSION['profile_error'])) {
                // department + 12s + d + 8s + i = 23 params
                $stmt = $db->prepare(
                    "UPDATE interns
                     SET department_id=?,
                         first_name=?, last_name=?, middle_name=?, email=?, phone=?, address=?,
                         birthdate=?, gender=?, school=?, course=?, year_level=?, school_address=?,
                         required_hours=?,
                         start_date=?, end_date=?, supervisor=?,
                         nationality=?, civil_status=?, guardian_name=?, guardian_contact=?,
                         profile_photo=?
                     WHERE id=?"
                );
                // types: i + s×12 + d + s×8 + i = 23 chars
                $stmt->bind_param(
                    'issssssssssssdssssssssi',
                    $deptNew,
                    $fn, $ln, $mn, $em, $ph, $addr,
                    $bd, $gen, $sch, $crs, $yl, $sa,
                    $rh,
                    $sd, $ed, $sup,
                    $nat, $cs, $gn, $gc,
                    $photoName,
                    $internId
                );
                $stmt->execute();
                $stmt->close();

                // Log if department changed
                $deptChanged = $deptNew !== (int)$intern['department_id'];
                $logNote = "Profile updated for {$fn} {$ln}." . ($deptChanged ? " Department changed." : "");
                logAudit('UPDATE', 'Interns', $internId, $logNote);
                $_SESSION['profile_success'] = 'Profile updated successfully.' . ($deptChanged ? ' Department has been changed.' : '');
            }
        }
        header("Location: /intern_workspace.php?id={$internId}&tab=201");
        exit;
    }

    // ── Profile: archive ─────────────────────────────────────────────────
    if ($action === 'archive_from_profile') {
        $stmt = $db->prepare("UPDATE interns SET status='Archived' WHERE id=?");
        $stmt->bind_param('i', $internId);
        $stmt->execute();
        $stmt->close();
        logAudit('ARCHIVE', 'Interns', $internId, "Intern #{$internId} archived.");
        header("Location: /intern_workspace.php?id={$internId}&tab=201");
        exit;
    }

    // ── DTR: add ─────────────────────────────────────────────────────────
    if ($action === 'add_dtr') {
        header('Content-Type: application/json');
        $date    = trim($_POST['entry_date'] ?? '');
        $timeIn  = trim($_POST['time_in']    ?? '') ?: null;
        $timeOut = trim($_POST['time_out']   ?? '') ?: null;
        $remarks = trim($_POST['remarks']    ?? '');
        $allowed = ['', 'Half Day', 'Excused', 'Absent', 'Holiday', 'No Office'];
        if (!in_array($remarks, $allowed)) $remarks = '';

        if (!$date) {
            echo json_encode(['success'=>false,'error'=>'Date is required.']); exit;
        }

        // Duplicate date check
        $chk = $db->prepare("SELECT id FROM dtr_entries WHERE intern_id=? AND entry_date=? AND is_archived=0");
        $chk->bind_param('is', $internId, $date);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $chk->close();
            echo json_encode(['success'=>false,'error'=>'An entry for this date already exists.']); exit;
        }
        $chk->close();

        // Load lunch break setting
        $lbRow = $db->query("SELECT setting_key, setting_val FROM system_settings WHERE setting_key IN ('lunch_break_enabled','lunch_break_minutes')")->fetch_all(MYSQLI_ASSOC);
        $lbSettings = [];
        foreach ($lbRow as $r) $lbSettings[$r['setting_key']] = $r['setting_val'];
        $lunchEnabled = ($lbSettings['lunch_break_enabled'] ?? '0') === '1';
        $lunchMins    = (int)($lbSettings['lunch_break_minutes'] ?? 60);

        // Non-working remarks don't need times
        $noTimeRemarks = ['Absent','Holiday','No Office','Excused'];
        if (in_array($remarks, $noTimeRemarks)) {
            $timeIn  = null;
            $timeOut = null;
        } elseif ($timeIn && $timeOut && $timeOut <= $timeIn) {
            echo json_encode(['success'=>false,'error'=>'Time Out must be later than Time In.']); exit;
        } elseif (!in_array($remarks, $noTimeRemarks) && (!$timeIn || !$timeOut)) {
            echo json_encode(['success'=>false,'error'=>'Time In and Time Out are required.']); exit;
        }

        // If lunch break enabled and we have valid times, adjust time_out in the stored value
        // We store a lunch_break_deducted flag in remarks or we store adjusted time
        // Simplest: store the deduction amount separately — we'll use a lunch_minutes column
        // For now: deduct by inserting with a note, actual rendered hours computed by DB generated col
        // Better: store original times, deduct via a lunch_break_mins column on the entry
        $lunchDeduct = ($lunchEnabled && $timeIn && $timeOut) ? $lunchMins : 0;

        $stmt = $db->prepare("INSERT INTO dtr_entries (intern_id, entry_date, time_in, time_out, remarks, lunch_break_mins) VALUES (?,?,?,?,?,?)");
        // Check if lunch_break_mins column exists, if not fall back
        if ($stmt === false) {
            $stmt = $db->prepare("INSERT INTO dtr_entries (intern_id, entry_date, time_in, time_out, remarks) VALUES (?,?,?,?,?)");
            $stmt->bind_param('issss', $internId, $date, $timeIn, $timeOut, $remarks);
        } else {
            $stmt->bind_param('issssi', $internId, $date, $timeIn, $timeOut, $remarks, $lunchDeduct);
        }
        $stmt->execute();
        $newId = $db->insert_id;
        $stmt->close();
        $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
        logAudit('CREATE', 'DTR', $newId, "DTR entry added for intern #{$internId} on {$date}." . ($lunchDeduct ? " Lunch deducted: {$lunchDeduct}min." : ""));
        echo json_encode(['success'=>true]); exit;
    }

    // ── DTR: edit ────────────────────────────────────────────────────────
    if ($action === 'edit_dtr') {
        header('Content-Type: application/json');
        $id      = (int)($_POST['entry_id'] ?? 0);
        $timeIn  = trim($_POST['time_in']  ?? '') ?: null;
        $timeOut = trim($_POST['time_out'] ?? '') ?: null;

        if ($timeIn && $timeOut && $timeOut <= $timeIn) {
            echo json_encode(['success'=>false,'error'=>'Time Out must be later than Time In.']); exit;
        }

        $stmt = $db->prepare("UPDATE dtr_entries SET time_in=?, time_out=? WHERE id=? AND intern_id=?");
        $stmt->bind_param('ssii', $timeIn, $timeOut, $id, $internId);
        $stmt->execute(); $stmt->close();
        $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
        logAudit('UPDATE', 'DTR', $id, "DTR entry #{$id} updated.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── DTR: delete ──────────────────────────────────────────────────────
    if ($action === 'delete_dtr') {
        header('Content-Type: application/json');
        $id = (int)($_POST['entry_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM dtr_entries WHERE id=? AND intern_id=?");
        $stmt->bind_param('ii', $id, $internId);
        $stmt->execute(); $stmt->close();
        $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
        logAudit('DELETE', 'DTR', $id, "DTR entry #{$id} deleted.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── DTR: update remark only ───────────────────────────────────────────
    if ($action === 'edit_dtr_remark') {
        header('Content-Type: application/json');
        $id      = (int)($_POST['entry_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $allowed = ['', 'Half Day', 'Excused', 'Absent', 'Holiday', 'No Office'];
        if (!in_array($remarks, $allowed)) $remarks = '';

        // Non-working remarks → clear times so rendered hours = 0
        $clearTime = in_array($remarks, ['Absent', 'Holiday', 'No Office', 'Excused']);

        if ($clearTime) {
            $stmt = $db->prepare("UPDATE dtr_entries SET remarks=?, time_in=NULL, time_out=NULL WHERE id=? AND intern_id=?");
            $stmt->bind_param('sii', $remarks, $id, $internId);
        } else {
            $stmt = $db->prepare("UPDATE dtr_entries SET remarks=? WHERE id=? AND intern_id=?");
            $stmt->bind_param('sii', $remarks, $id, $internId);
        }
        $stmt->execute(); $stmt->close();
        // Recalculate total rendered hours
        $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
        logAudit('UPDATE', 'DTR', $id, "DTR remark updated to '{$remarks}'.");
        echo json_encode(['success'=>true, 'cleared'=>$clearTime]); exit;
    }

    // ── Requirements: add ────────────────────────────────────────────────
    if ($action === 'add_requirement') {
        header('Content-Type: application/json');
        $name = trim($_POST['req_name'] ?? '');
        if (!$name) { echo json_encode(['success'=>false,'error'=>'Requirement name is required.']); exit; }
        $stmt = $db->prepare("INSERT INTO requirement_items (intern_id, name) VALUES (?,?)");
        $stmt->bind_param('is', $internId, $name);
        $stmt->execute();
        $newId = $db->insert_id; $stmt->close();
        logAudit('CREATE', 'Requirements', $newId, "Requirement '{$name}' added for intern #{$internId}.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── Requirements: update status ──────────────────────────────────────
    if ($action === 'update_status') {
        header('Content-Type: application/json');
        $id     = (int)($_POST['req_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['Pending','Submitted','Approved'])) {
            echo json_encode(['success'=>false,'error'=>'Invalid status.']); exit;
        }
        $now  = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE requirement_items SET status=?, status_changed_at=? WHERE id=? AND intern_id=?");
        $stmt->bind_param('ssii', $status, $now, $id, $internId);
        $stmt->execute(); $stmt->close();
        logAudit('UPDATE', 'Requirements', $id, "Requirement #{$id} status → '{$status}'.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── Requirements: update remarks ─────────────────────────────────────
    if ($action === 'update_remarks') {
        header('Content-Type: application/json');
        $id      = (int)($_POST['req_id'] ?? 0);
        $remarks = mb_substr(trim($_POST['remarks'] ?? ''), 0, 500);
        $stmt = $db->prepare("UPDATE requirement_items SET remarks=? WHERE id=? AND intern_id=?");
        $stmt->bind_param('sii', $remarks, $id, $internId);
        $stmt->execute(); $stmt->close();
        logAudit('UPDATE', 'Requirements', $id, "Remarks updated for requirement #{$id}.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── Requirements: upload file ────────────────────────────────────────
    if ($action === 'upload_file') {
        header('Content-Type: application/json');
        $id = (int)($_POST['req_id'] ?? 0);
        if (empty($_FILES['req_file']['name'])) {
            echo json_encode(['success'=>false,'error'=>'No file received.']); exit;
        }
        $file    = $_FILES['req_file'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png','docx'];
        $maxSize = 10 * 1024 * 1024;
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success'=>false,'error'=>'Accepted: PDF, JPEG, PNG, DOCX.']); exit;
        }
        if ($file['size'] > $maxSize) {
            echo json_encode(['success'=>false,'error'=>'File must not exceed 10 MB.']); exit;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false,'error'=>'Upload error code: '.$file['error']]); exit;
        }
        $uploadDir = __DIR__ . '/uploads/requirements/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = $internId . '_' . $id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            echo json_encode(['success'=>false,'error'=>'Could not save file. Check folder permissions.']); exit;
        }
        $today    = date('Y-m-d');
        $origName = $file['name'];
        $stmt = $db->prepare("UPDATE requirement_items SET file_path=?, file_name=?, submission_date=? WHERE id=? AND intern_id=?");
        $stmt->bind_param('sssii', $fileName, $origName, $today, $id, $internId);
        $stmt->execute(); $stmt->close();
        logAudit('UPDATE', 'Requirements', $id, "File uploaded for requirement #{$id}.");
        echo json_encode(['success'=>true, 'file'=>$fileName, 'ext'=>$ext]); exit;
    }

    // ── Requirements: archive ────────────────────────────────────────────
    if ($action === 'archive_requirement') {
        header('Content-Type: application/json');
        $id = (int)($_POST['req_id'] ?? 0);
        $stmt = $db->prepare("UPDATE requirement_items SET is_archived=1 WHERE id=? AND intern_id=?");
        $stmt->bind_param('ii', $id, $internId); $stmt->execute(); $stmt->close();
        logAudit('ARCHIVE', 'Requirements', $id, "Requirement #{$id} archived.");
        echo json_encode(['success'=>true]); exit;
    }

    // ── Requirements: restore ────────────────────────────────────────────
    if ($action === 'restore_requirement') {
        header('Content-Type: application/json');
        $id = (int)($_POST['req_id'] ?? 0);
        $stmt = $db->prepare("UPDATE requirement_items SET is_archived=0 WHERE id=? AND intern_id=?");
        $stmt->bind_param('ii', $id, $internId); $stmt->execute(); $stmt->close();
        logAudit('RESTORE', 'Requirements', $id, "Requirement #{$id} restored.");
        echo json_encode(['success'=>true]); exit;
    }
}
// ════════════════════════════════════════════════════════════════════════════
// END POST HANDLERS — HTML output starts below
// ════════════════════════════════════════════════════════════════════════════

// Re-fetch intern after any updates
$stmt = $db->prepare(
    "SELECT i.*, d.name AS dept_name, d.id AS dept_id
     FROM interns i JOIN departments d ON d.id = i.department_id
     WHERE i.id = ?"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

$activeTab = $_GET['tab'] ?? '201';
$initials  = strtoupper(substr($intern['first_name'],0,1) . substr($intern['last_name'],0,1));
$fullName  = htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']);

$pageTitle   = $fullName;
$breadcrumbs = [
    ['label' => 'Departments',        'url' => '/departments.php'],
    ['label' => $intern['dept_name'], 'url' => '/department_view.php?id='.$intern['dept_id']],
    ['label' => $fullName,            'url' => ''],
];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Intern Workspace Header -->
<div class="card mb-24">
    <div class="card-body" style="padding:20px 24px">
        <div class="d-flex align-center gap-12">
            <div class="intern-avatar" style="width:56px;height:56px;font-size:22px;flex-shrink:0;border:2px solid var(--orange);box-shadow:var(--neon-glow-sm)">
                <?php if ($intern['profile_photo']): ?>
                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                <?php else: ?>
                <?= $initials ?>
                <?php endif; ?>
            </div>
            <div style="flex:1">
                <div style="font-size:18px;font-weight:700"><?= $fullName ?></div>
                <div class="text-muted" style="font-size:13px;margin-top:2px">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($intern['dept_name']) ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($intern['school'] ?: '—') ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="badge badge-<?= strtolower($intern['status']) ?>"><?= $intern['status'] ?></span>
                <div style="text-align:right">
                    <div style="font-size:20px;font-weight:700;color:var(--orange)"><?= number_format($intern['rendered_hours'],1) ?></div>
                    <div class="text-muted fs-12">of <?= number_format($intern['required_hours'],0) ?> hrs</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="tab-nav">
    <button class="tab-btn <?= $activeTab==='201'  ? 'active':'' ?>" onclick="switchTab('201')">
        <i class="fas fa-id-card"></i> 201 Profile
    </button>
    <button class="tab-btn <?= $activeTab==='dtr'  ? 'active':'' ?>" onclick="switchTab('dtr')">
        <i class="fas fa-calendar-check"></i> DTR
    </button>
    <button class="tab-btn <?= $activeTab==='reqs' ? 'active':'' ?>" onclick="switchTab('reqs')">
        <i class="fas fa-file-alt"></i> Requirements
    </button>
</div>

<!-- TAB: 201 Profile -->
<div class="tab-pane <?= $activeTab==='201' ? 'active':'' ?>" id="tab-201">
    <?php require __DIR__ . '/modules/profile_tab.php'; ?>
</div>

<!-- TAB: DTR -->
<div class="tab-pane <?= $activeTab==='dtr' ? 'active':'' ?>" id="tab-dtr">
    <?php require __DIR__ . '/modules/dtr_tab.php'; ?>
</div>

<!-- TAB: Requirements -->
<div class="tab-pane <?= $activeTab==='reqs' ? 'active':'' ?>" id="tab-reqs">
    <?php require __DIR__ . '/modules/requirements_tab.php'; ?>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.currentTarget.classList.add('active');
    history.replaceState(null, '', '?id=<?= $internId ?>&tab=' + tab);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
