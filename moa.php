<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db = getDB();

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_moa' || $action === 'edit_moa') {
        $id          = (int)($_POST['moa_id'] ?? 0);
        $school      = trim($_POST['school_name'] ?? '');
        $validity    = trim($_POST['validity']    ?? '');
        $pStart      = trim($_POST['period_start']?? '') ?: null;
        $pEnd        = trim($_POST['period_end']  ?? '') ?: null;
        $status      = $_POST['status']   ?? 'On Process';
        $remarks     = trim($_POST['remarks']     ?? '');
        $seq         = (int)($_POST['seq'] ?? 0) ?: null;

        $allowed = ['Active','Expired','For Verification','On Process','For Renewal'];
        if (!in_array($status, $allowed)) $status = 'On Process';

        // File upload
        $filePath = null; $fileName = null;
        if (!empty($_FILES['moa_file']['name'])) {
            $file    = $_FILES['moa_file'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','jpg','jpeg','png','docx'];
            if (!in_array($ext, $allowed) || $file['size'] > 20*1024*1024) {
                $_SESSION['moa_error'] = 'File must be PDF, image, or DOCX under 20MB.';
                header('Location: /moa.php'); exit;
            }
            $uploadDir = __DIR__ . '/uploads/moa/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $filePath = $fileName;
            move_uploaded_file($file['tmp_name'], $uploadDir . $fileName);
        }

        if ($action === 'add_moa') {
            if (!$school) { $_SESSION['moa_error'] = 'School name is required.'; header('Location: /moa.php'); exit; }
            $stmt = $db->prepare(
                "INSERT INTO moa_agreements (seq, school_name, validity, period_start, period_end, status, remarks, file_path, file_name)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('isssssss s', $seq, $school, $validity, $pStart, $pEnd, $status, $remarks, $filePath, $fileName);
            $stmt->close();
            $stmt = $db->prepare(
                "INSERT INTO moa_agreements (seq, school_name, validity, period_start, period_end, status, remarks, file_path, file_name)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('issssssss', $seq, $school, $validity, $pStart, $pEnd, $status, $remarks, $filePath, $fileName);
            $stmt->execute();
            $newId = $db->insert_id; $stmt->close();
            logAudit('CREATE', 'MOA', $newId, "MOA added for {$school}.");
        } else {
            $stmt = $db->prepare(
                "UPDATE moa_agreements SET seq=?, school_name=?, validity=?, period_start=?, period_end=?,
                 status=?, remarks=?" . ($filePath ? ", file_path=?, file_name=?" : "") . " WHERE id=?"
            );
            if ($filePath) {
                $stmt->bind_param('issssssssi', $seq, $school, $validity, $pStart, $pEnd, $status, $remarks, $filePath, $fileName, $id);
            } else {
                $stmt->bind_param('isssssssi', $seq, $school, $validity, $pStart, $pEnd, $status, $remarks, $id);
            }
            $stmt->execute(); $stmt->close();
            logAudit('UPDATE', 'MOA', $id, "MOA updated for {$school}.");
        }
    }

    if ($action === 'delete_moa') {
        $id = (int)($_POST['moa_id'] ?? 0);
        $stmt = $db->prepare("UPDATE moa_agreements SET is_archived=1 WHERE id=?");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        logAudit('ARCHIVE', 'MOA', $id, "MOA #{$id} archived.");
    }

    if ($action === 'restore_moa') {
        $id = (int)($_POST['moa_id'] ?? 0);
        $stmt = $db->prepare("UPDATE moa_agreements SET is_archived=0 WHERE id=?");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        logAudit('RESTORE', 'MOA', $id, "MOA #{$id} restored.");
    }

    header('Location: /moa.php'); exit;
}

// ── Fetch ──────────────────────────────────────────────────────────────────
$showArchived = isset($_GET['archived']);
$search       = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where  = "WHERE is_archived = " . ($showArchived ? 1 : 0);
$params = []; $types = '';

if ($search !== '') {
    $where   .= " AND school_name LIKE ?";
    $params[] = "%{$search}%"; $types .= 's';
}
if ($statusFilter !== '') {
    $where   .= " AND status = ?";
    $params[] = $statusFilter; $types .= 's';
}

$sql = "SELECT * FROM moa_agreements {$where} ORDER BY seq ASC, school_name ASC";
if ($types) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $moas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $moas = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Status counts
$statusCounts = [];
foreach (['Active','Expired','For Verification','On Process','For Renewal'] as $s) {
    $r = $db->query("SELECT COUNT(*) FROM moa_agreements WHERE status='{$s}' AND is_archived=0");
    $statusCounts[$s] = $r->fetch_row()[0];
}

$moaError = $_SESSION['moa_error'] ?? null; unset($_SESSION['moa_error']);

$pageTitle   = 'MOA Management';
$breadcrumbs = [['label' => 'MOA Management', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:12px">
    <div>
        <h1>MOA Management</h1>
        <p>Academic Memorandum of Agreement Repository — partner universities and schools.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addMoaModal')">
        <i class="fas fa-plus"></i> Add MOA
    </button>
</div>

<?php if ($moaError): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:var(--danger);border-radius:8px;padding:11px 16px;margin-bottom:16px;display:flex;gap:8px;align-items:center;font-size:13px">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($moaError) ?>
</div>
<?php endif; ?>

<!-- Status summary cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:20px">
    <?php
    $cardColors = [
        'Active'           => ['#ECFDF5','#16A34A','fa-check-circle'],
        'Expired'          => ['#FEF2F2','#DC2626','fa-times-circle'],
        'For Verification' => ['#EFF6FF','#2563EB','fa-search'],
        'On Process'       => ['#FFFBEB','#D97706','fa-spinner'],
        'For Renewal'      => ['#FFF7ED','#EA580C','fa-sync-alt'],
    ];
    foreach ($statusCounts as $s => $cnt):
        [$bg, $clr, $ico] = $cardColors[$s];
    ?>
    <a href="/moa.php?status=<?= urlencode($s) ?>"
       style="background:var(--white);border-radius:var(--radius);padding:16px;border:1px solid var(--gray-border);box-shadow:var(--shadow);text-decoration:none;display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:10px;background:<?= $bg ?>;color:<?= $clr ?>;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0">
            <i class="fas <?= $ico ?>"></i>
        </div>
        <div>
            <div style="font-size:22px;font-weight:700;color:<?= $clr ?>"><?= $cnt ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $s ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div class="card mb-16">
    <div class="card-body" style="padding:12px 20px">
        <form method="GET" class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search school / university…"
                       value="<?= htmlspecialchars($search) ?>" maxlength="100">
            </div>
            <select name="status" class="form-control" style="width:auto">
                <option value="">All Statuses</option>
                <?php foreach (array_keys($statusCounts) as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
            <a href="/moa.php" class="btn btn-secondary btn-sm">Reset</a>
            <a href="/moa.php?<?= $showArchived ? '' : 'archived=1' ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-archive"></i> <?= $showArchived ? 'Active' : 'Archived' ?>
            </a>
        </form>
    </div>
</div>

<!-- MOA Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-file-contract text-orange"></i>
            <?= $showArchived ? 'Archived' : 'Active' ?> MOA Records
        </span>
        <span class="text-muted fs-12"><?= count($moas) ?> record<?= count($moas)!=1?'s':'' ?></span>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($moas)): ?>
        <div class="empty-state">
            <i class="fas fa-file-contract"></i>
            <p>No MOA records found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th style="width:40px">SEQ</th>
                        <th>School / University</th>
                        <th style="width:110px">Validity</th>
                        <th style="width:120px">Start</th>
                        <th style="width:120px">End</th>
                        <th style="width:140px">Status</th>
                        <th>Remarks</th>
                        <th style="width:100px">File</th>
                        <th style="width:90px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($moas as $moa):
                    $statusStyles = [
                        'Active'           => 'background:#ECFDF5;color:#16A34A',
                        'Expired'          => 'background:#FEF2F2;color:#DC2626',
                        'For Verification' => 'background:#EFF6FF;color:#2563EB',
                        'On Process'       => 'background:#FFFBEB;color:#D97706',
                        'For Renewal'      => 'background:#FFF7ED;color:#EA580C',
                    ];
                    $sStyle = $statusStyles[$moa['status']] ?? '';
                    $isExpired = $moa['status'] === 'Expired' || $moa['status'] === 'For Renewal';
                ?>
                <tr style="<?= $isExpired ? 'background:#fffbfb' : '' ?>">
                    <td class="text-muted fs-12" style="text-align:center"><?= $moa['seq'] ?: '—' ?></td>
                    <td class="fw-600" style="color:<?= $isExpired ? 'var(--danger)' : 'var(--text-main)' ?>">
                        <?= htmlspecialchars($moa['school_name']) ?>
                    </td>
                    <td class="text-muted fs-12"><?= htmlspecialchars($moa['validity'] ?: '—') ?></td>
                    <td class="fs-12"><?= $moa['period_start'] ? date('M d, Y', strtotime($moa['period_start'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="fs-12" style="color:<?= $isExpired?'var(--danger)':'' ?>">
                        <?= $moa['period_end'] ? date('M d, Y', strtotime($moa['period_end'])) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td>
                        <span class="badge" style="<?= $sStyle ?>"><?= $moa['status'] ?></span>
                    </td>
                    <td class="fs-12 text-muted"><?= htmlspecialchars($moa['remarks'] ?: '—') ?></td>
                    <td>
                        <?php if ($moa['file_path']): ?>
                        <a href="/uploads/moa/<?= htmlspecialchars($moa['file_path']) ?>" target="_blank"
                           class="btn btn-icon btn-sm" title="View file">
                            <i class="fas fa-eye" style="color:var(--info)"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted fs-12">No file</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-6">
                            <button class="btn btn-icon btn-sm" title="Edit"
                                onclick="editMoa(<?= htmlspecialchars(json_encode($moa)) ?>)">
                                <i class="fas fa-pen" style="color:var(--orange)"></i>
                            </button>
                            <?php if ($showArchived): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"  value="restore_moa">
                                <input type="hidden" name="moa_id" value="<?= $moa['id'] ?>">
                                <button type="submit" class="btn btn-icon btn-sm" title="Restore">
                                    <i class="fas fa-undo" style="color:var(--success)"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"  value="delete_moa">
                                <input type="hidden" name="moa_id" value="<?= $moa['id'] ?>">
                                <button type="submit" class="btn btn-icon btn-sm" title="Archive"
                                    onclick="return confirm('Archive this MOA record?')">
                                    <i class="fas fa-archive" style="color:var(--gray-mid)"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ Add MOA Modal ═══ -->
<div class="modal-overlay" id="addMoaModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-plus text-orange"></i> Add MOA Record</span>
            <button class="modal-close" onclick="closeModal('addMoaModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_moa">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="max-width:80px">
                        <label class="form-label">SEQ</label>
                        <input type="number" name="seq" class="form-control" min="1">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">School / University <span class="required">*</span></label>
                        <input type="text" name="school_name" class="form-control" required maxlength="200">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Validity</label>
                        <input type="text" name="validity" class="form-control" maxlength="50" placeholder="e.g. 3 years / For Verification">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="On Process">On Process</option>
                            <option value="Active">Active</option>
                            <option value="For Verification">For Verification</option>
                            <option value="Expired">Expired</option>
                            <option value="For Renewal">For Renewal</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Period Start</label>
                        <input type="date" name="period_start" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Period End</label>
                        <input type="date" name="period_end" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control" maxlength="255"
                           placeholder="e.g. For Renewal / Pending to Receive / For Sign">
                </div>
                <div class="form-group">
                    <label class="form-label">Upload MOA File <span class="text-muted fs-12">(PDF, image, DOCX — max 20MB)</span></label>
                    <input type="file" name="moa_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.docx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMoaModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Edit MOA Modal ═══ -->
<div class="modal-overlay" id="editMoaModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-pen text-orange"></i> Edit MOA Record</span>
            <button class="modal-close" onclick="closeModal('editMoaModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"  value="edit_moa">
            <input type="hidden" name="moa_id"  id="editMoaId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="max-width:80px">
                        <label class="form-label">SEQ</label>
                        <input type="number" name="seq" id="editMoaSeq" class="form-control" min="1">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">School / University <span class="required">*</span></label>
                        <input type="text" name="school_name" id="editMoaSchool" class="form-control" required maxlength="200">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Validity</label>
                        <input type="text" name="validity" id="editMoaValidity" class="form-control" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="editMoaStatus" class="form-control">
                            <option value="On Process">On Process</option>
                            <option value="Active">Active</option>
                            <option value="For Verification">For Verification</option>
                            <option value="Expired">Expired</option>
                            <option value="For Renewal">For Renewal</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Period Start</label>
                        <input type="date" name="period_start" id="editMoaStart" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Period End</label>
                        <input type="date" name="period_end" id="editMoaEnd" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" id="editMoaRemarks" class="form-control" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Replace MOA File <span class="text-muted fs-12">(leave blank to keep existing)</span></label>
                    <input type="file" name="moa_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.docx">
                </div>
                <div id="editMoaCurrentFile" style="font-size:12px;color:var(--text-muted);margin-top:4px"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editMoaModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editMoa(moa) {
    document.getElementById('editMoaId').value       = moa.id;
    document.getElementById('editMoaSeq').value      = moa.seq      || '';
    document.getElementById('editMoaSchool').value   = moa.school_name;
    document.getElementById('editMoaValidity').value = moa.validity  || '';
    document.getElementById('editMoaStatus').value   = moa.status;
    document.getElementById('editMoaStart').value    = moa.period_start || '';
    document.getElementById('editMoaEnd').value      = moa.period_end   || '';
    document.getElementById('editMoaRemarks').value  = moa.remarks  || '';
    document.getElementById('editMoaCurrentFile').textContent =
        moa.file_name ? 'Current file: ' + moa.file_name : 'No file uploaded';
    openModal('editMoaModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
