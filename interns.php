<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db = getDB();

// Handle POST actions for face registration link generation / re-registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $internId = (int)($_POST['intern_id'] ?? 0);

    if ($internId > 0) {
        // Fetch intern name for audit logging
        $stmt = $db->prepare("SELECT first_name, last_name, email FROM interns WHERE id = ?");
        $stmt->bind_param('i', $internId);
        $stmt->execute();
        $internInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($internInfo) {
            $name = $internInfo['first_name'] . ' ' . $internInfo['last_name'];
            
            if ($action === 'generate_link') {
                // Generate secure 32-character hex token (16 bytes)
                $token = bin2hex(random_bytes(16));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $stmt = $db->prepare("UPDATE interns SET registration_token = ?, token_expires_at = ?, email = NULL WHERE id = ?");
                $stmt->bind_param('ssi', $token, $expiresAt, $internId);
                $stmt->execute();
                $stmt->close();

                logAudit('GENERATE_TOKEN', 'Interns', $internId, "Generated face registration token for {$name}.");
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register_intern.php?token=' . $token
                ]);
                exit;
            } elseif ($action === 're_register') {
                // Wipe embedding and generate new token
                $token = bin2hex(random_bytes(16));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $stmt = $db->prepare("UPDATE interns SET face_embedding = NULL, face_embedding_large = NULL, face_registered_at = NULL, registration_token = ?, token_expires_at = ?, email = NULL WHERE id = ?");
                $stmt->bind_param('ssi', $token, $expiresAt, $internId);
                $stmt->execute();
                $stmt->close();

                logAudit('RE_REGISTER', 'Interns', $internId, "Wiped face data and generated new token for {$name}.");

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register_intern.php?token=' . $token
                ]);
                exit;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$search       = trim($_GET['search'] ?? '');
$deptFilter   = (int)($_GET['dept']   ?? 0);
$statusFilter = $_GET['status']       ?? 'Active';

$allowed = ['Active', 'Inactive', 'Archived'];
if (!in_array($statusFilter, $allowed)) $statusFilter = 'Active';

$where  = "WHERE i.status = '{$statusFilter}'";
$params = [];
$types  = '';

if ($deptFilter) {
    $where   .= " AND i.department_id = ?";
    $params[] = $deptFilter;
    $types   .= 'i';
}

if ($search !== '') {
    $where   .= " AND CONCAT(i.first_name,' ',i.last_name) LIKE ?";
    $params[] = "%{$search}%";
    $types   .= 's';
}

// ── Pagination ────────────────────────────────────────────────────────────
$perPage     = 25;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Total count query
$countSql = "SELECT COUNT(*) FROM interns i JOIN departments d ON d.id = i.department_id {$where}";
if ($types) {
    $cStmt = $db->prepare($countSql);
    $cStmt->bind_param($types, ...$params);
    $cStmt->execute();
    $totalInterns = $cStmt->get_result()->fetch_row()[0];
    $cStmt->close();
} else {
    $totalInterns = $db->query($countSql)->fetch_row()[0];
}
$totalPages = max(1, (int)ceil($totalInterns / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;

// Paginated data query
$sql = "SELECT i.*, d.name AS dept_name FROM interns i
        JOIN departments d ON d.id = i.department_id
        {$where}
        ORDER BY i.last_name, i.first_name
        LIMIT ? OFFSET ?";

$paginatedParams = array_merge($params, [$perPage, $offset]);
$paginatedTypes  = $types . 'ii';
$stmt = $db->prepare($sql);
$stmt->bind_param($paginatedTypes, ...$paginatedParams);
$stmt->execute();
$interns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$depts = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Count per status (respecting dept filter)
$counts = [];
foreach (['Active','Inactive','Archived'] as $s) {
    $cWhere  = "WHERE i.status = '{$s}'";
    $cParams = [];
    $cTypes  = '';
    if ($deptFilter) {
        $cWhere   .= " AND i.department_id = ?";
        $cParams[] = $deptFilter;
        $cTypes   .= 'i';
    }
    $cSql = "SELECT COUNT(*) FROM interns i {$cWhere}";
    if ($cTypes) {
        $r = $db->prepare($cSql);
        $r->bind_param($cTypes, ...$cParams);
        $r->execute();
        $counts[$s] = $r->get_result()->fetch_row()[0];
        $r->close();
    } else {
        $counts[$s] = $db->query($cSql)->fetch_row()[0];
    }
}

$pageTitle   = 'Intern Management';
$breadcrumbs = [['label' => 'Intern Management', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Intern Management</h1>
    <p>View and manage all interns across departments.</p>
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
        $url = '/interns.php?status=' . $s . ($deptFilter ? '&dept='.$deptFilter : '') . ($search ? '&search='.urlencode($search) : '');
    ?>
    <a href="<?= $url ?>"
       style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:8px;
              font-size:13px;font-weight:<?= $active?'700':'500' ?>;text-decoration:none;
              background:<?= $active?'var(--white)':'transparent' ?>;
              border:1.5px solid <?= $active?$color:'var(--gray-border)' ?>;
              color:<?= $active?$color:'var(--text-muted)' ?>;
              box-shadow:<?= $active?'0 2px 8px rgba(0,0,0,.08)':'' ?>">
        <?= $s ?>
        <span style="background:<?= $active?$color:'var(--gray-border)' ?>;color:#fff;
                     border-radius:20px;padding:1px 8px;font-size:11px;font-weight:700">
            <?= $counts[$s] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search + Dept filter -->
<div class="card mb-20">
    <div class="card-body" style="padding:12px 20px">
        <form method="GET" class="toolbar">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name…"
                       value="<?= htmlspecialchars($search) ?>" maxlength="100">
            </div>
            <select name="dept" class="form-control" style="width:auto">
                <option value="">All Departments</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deptFilter==$d['id']?'selected':'' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="/interns.php?status=<?= $statusFilter ?>" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Results -->
<?php if (empty($interns) && $currentPage === 1): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No <?= strtolower($statusFilter) ?> interns found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
    </div>
</div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?= $totalInterns ?> <?= $statusFilter ?> Intern<?= $totalInterns!=1?'s':'' ?> found
            <?php if ($totalPages > 1): ?>
            <span class="text-muted fs-12" style="font-weight:400"> — Page <?= $currentPage ?> of <?= $totalPages ?></span>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Intern</th>
                        <th style="text-align: left;">Department</th>
                        <th style="text-align: left;">School</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Face ID</th>
                        <th style="text-align: left;">Progress</th>
                        <th style="text-align: right;">Hours</th>
                        <th style="text-align: center; width:140px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($interns as $intern):
                    $pct = $intern['required_hours'] > 0
                        ? min(100, round(($intern['rendered_hours'] / $intern['required_hours']) * 100))
                        : 0;
                    $initials = strtoupper(substr($intern['first_name'],0,1).substr($intern['last_name'],0,1));
                    $statusStyle = match($intern['status']) {
                        'Active'   => 'background:#ECFDF5;color:#16A34A',
                        'Inactive' => 'background:#FFFBEB;color:#D97706',
                        'Archived' => 'background:#F3F4F6;color:#6B7280',
                        default    => ''
                    };
                ?>
                <tr style="cursor:pointer" onclick="location.href='/intern_workspace.php?id=<?= $intern['id'] ?>'">
                    <td style="text-align: left;">
                        <div class="d-flex align-center gap-8">
                            <div class="intern-avatar" style="width:36px;height:36px;font-size:14px;flex-shrink:0">
                                <?php if ($intern['profile_photo']): ?>
                                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                                <?php else: ?><?= $initials ?><?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></div>
                                <div class="fs-12 text-muted"><?= htmlspecialchars($intern['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="text-align: left;"><?= htmlspecialchars($intern['dept_name']) ?></td>
                    <td class="text-muted" style="text-align: left;"><?= htmlspecialchars($intern['school'] ?: '—') ?></td>
                    <td style="text-align: center;">
                        <span class="badge" style="<?= $statusStyle ?>"><?= $intern['status'] ?></span>
                    </td>
                    <td style="text-align: center;">
                        <?php 
                        $isTokenActive = $intern['registration_token'] && strtotime($intern['token_expires_at']) > time();
                        if ($intern['face_embedding']): 
                        ?>
                            <span class="badge" style="background:#ECFDF5;color:#16A34A"><i class="fas fa-check-circle"></i> Registered</span>
                        <?php elseif ($isTokenActive): ?>
                            <span class="badge" style="background:#FFFBEB;color:#D97706" title="Expires: <?= $intern['token_expires_at'] ?>"><i class="fas fa-link"></i> Link Active</span>
                        <?php else: ?>
                            <span class="badge" style="background:#F3F4F6;color:#6B7280"><i class="fas fa-times-circle"></i> Pending Face ID</span>
                        <?php endif; ?>
                    </td>
                    <td style="min-width:100px; text-align: left;">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="fs-12 text-muted"><?= $pct ?>%</span>
                    </td>
                    <td class="fs-12" style="text-align: right;"><?= number_format($intern['rendered_hours'],1) ?> / <?= number_format($intern['required_hours'],0) ?></td>
                    <td onclick="event.stopPropagation()" style="text-align: center;">
                        <div class="d-flex gap-8" style="justify-content: center;">
                            <a href="/intern_workspace.php?id=<?= $intern['id'] ?>" class="btn btn-icon btn-sm" title="Open Workspace">
                                <i class="fas fa-arrow-right" style="color:var(--orange)"></i>
                            </a>
                            <?php if ($intern['face_embedding']): ?>
                                <button class="btn btn-icon btn-sm" title="View QR Code" onclick="showQR(<?= $intern['id'] ?>, '<?= htmlspecialchars($intern['first_name'].' '.$intern['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($intern['dept_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($intern['qr_code'] ?? ('TDTINTRN'.$intern['id']), ENT_QUOTES) ?>')">
                                    <i class="fas fa-qrcode" style="color: #22C55E;"></i>
                                </button>
                                <button class="btn btn-icon btn-sm" title="Re-register Face ID" onclick="reRegister(<?= $intern['id'] ?>, '<?= htmlspecialchars($intern['first_name'].' '.$intern['last_name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-sync-alt" style="color: #EF4444;"></i>
                                </button>
                            <?php else: ?>
                                <?php if ($isTokenActive): ?>
                                    <button class="btn btn-icon btn-sm" title="Copy Registration Link" onclick="copyLink('<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register_intern.php?token=' . $intern['registration_token'] ?>', '<?= htmlspecialchars($intern['first_name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-copy" style="color: #3B82F6;"></i>
                                    </button>
                                    <button class="btn btn-icon btn-sm" title="Re-generate Link" onclick="generateLink(<?= $intern['id'] ?>, '<?= htmlspecialchars($intern['first_name'].' '.$intern['last_name'], ENT_QUOTES) ?>', true)">
                                        <i class="fas fa-sync-alt" style="color: #F59E0B;"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-icon btn-sm" title="Generate Registration Link" onclick="generateLink(<?= $intern['id'] ?>, '<?= htmlspecialchars($intern['first_name'].' '.$intern['last_name'], ENT_QUOTES) ?>', false)">
                                        <i class="fas fa-link" style="color: var(--orange);"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Build base URL for pagination links
$paginationBase = '/interns.php?' . http_build_query([
    'status' => $statusFilter,
    'dept'   => $deptFilter ?: '',
    'search' => $search,
]);
if ($totalPages > 1): ?>
<div class="d-flex align-center justify-between mt-16" style="flex-wrap:wrap;gap:10px">
    <div class="text-muted fs-12">
        Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalInterns) ?> of <?= $totalInterns ?> interns
    </div>
    <div class="d-flex gap-6" style="flex-wrap:wrap">
        <?php if ($currentPage > 1): ?>
        <a href="<?= $paginationBase ?>&page=1" class="btn btn-secondary btn-sm">
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="<?= $paginationBase ?>&page=<?= $currentPage - 1 ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-angle-left"></i> Prev
        </a>
        <?php endif; ?>

        <?php
        $start = max(1, $currentPage - 2);
        $end   = min($totalPages, $currentPage + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="<?= $paginationBase ?>&page=<?= $p ?>"
           class="btn btn-sm <?= $p === $currentPage ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a href="<?= $paginationBase ?>&page=<?= $currentPage + 1 ?>" class="btn btn-secondary btn-sm">
            Next <i class="fas fa-angle-right"></i>
        </a>
        <a href="<?= $paginationBase ?>&page=<?= $totalPages ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-angle-double-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- QR Code Modal -->
<div id="qrModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Intern QR Code</h3>
            <button class="modal-close" onclick="closeModal('qrModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div id="qrPrintArea" style="padding: 20px;">
                <h2 id="qrInternName" style="margin-bottom: 5px; font-family: 'Inter', sans-serif;"></h2>
                <p id="qrInternDept" style="color: var(--text-muted); margin-bottom: 20px; font-family: 'Inter', sans-serif;"></p>
                <div style="display: inline-block; padding: 15px; border: 2px solid var(--orange); border-radius: 12px; background: white;">
                    <img id="qrImage" src="" alt="QR Code" style="width: 200px; height: 200px; display: block; margin: 0 auto;">
                </div>
                <div id="qrCodeText" style="margin-top: 15px; font-family: monospace; font-size: 16px; font-weight: bold; color: var(--text-main);"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('qrModal')">Close</button>
            <button class="btn btn-primary" onclick="printQR()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showQR(id, name, dept, qrCodeStr) {
    const finalQrStr = qrCodeStr || ('TDTINTRN' + id);
    document.getElementById('qrInternName').innerText = name;
    document.getElementById('qrInternDept').innerText = dept;
    document.getElementById('qrImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(finalQrStr);
    document.getElementById('qrCodeText').innerText = finalQrStr;
    openModal('qrModal');
}

function printQR() {
    const name = document.getElementById('qrInternName').innerText;
    const dept = document.getElementById('qrInternDept').innerText;
    const qrSrc = document.getElementById('qrImage').src;
    const qrText = document.getElementById('qrCodeText').innerText;
    
    const printWindow = window.open('', '_blank', 'width=600,height=600');
    printWindow.document.write('<html><head><title>Print QR Code - ' + name + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: "Inter", sans-serif; text-align: center; padding: 40px; margin: 0; }');
    printWindow.document.write('h2 { margin: 0 0 5px; font-size: 24px; color: #1A1A2E; }');
    printWindow.document.write('p { color: #6B7280; margin: 0 0 30px; font-size: 16px; }');
    printWindow.document.write('.qr-wrap { display: inline-block; padding: 20px; border: 2px solid #FF6B1A; border-radius: 16px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }');
    printWindow.document.write('.qr-text { margin-top: 20px; font-family: monospace; font-size: 18px; font-weight: bold; color: #1A1A2E; letter-spacing: 1px; }');
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<h2>' + name + '</h2>');
    printWindow.document.write('<p>' + dept + '</p>');
    printWindow.document.write('<div class="qr-wrap"><img src="' + qrSrc + '" style="width:200px;height:200px;display:block;"></div>');
    printWindow.document.write('<div class="qr-text">' + qrText + '</div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function copyLink(link, name) {
    name = name || 'there';
    const text = `Hello ${name}!\n\nTDT Powersteel is setting up a new touchless Kiosk at the office for daily time and attendance (DTR) tracking. To register your face profile and generate your personal attendance QR code, please open this secure link on your smartphone:\n\n${link}\n\nNote: This link will expire in 24 hours. Once registered, your QR code will be displayed on-screen and emailed to you. Thank you!`;
    navigator.clipboard.writeText(text).then(function() {
        showToast('Registration message copied!', 'success');
    }).catch(function(err) {
        navigator.clipboard.writeText(link);
        showToast('Registration link copied!', 'success');
    });
}

function generateLink(id, name, isRegenerate) {
    const title = isRegenerate ? 'Regenerate Link' : 'Generate Link';
    const text = isRegenerate 
        ? 'Regenerate a new 24-hour face registration link for ' + name + '? The previous active link will be invalidated.'
        : 'Generate a new 24-hour face registration link for ' + name + '?';
    const confirmButtonText = isRegenerate ? 'Yes, regenerate' : 'Yes, generate';

    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#FF6B1A',
        cancelButtonColor: '#8A8B8D',
        confirmButtonText: confirmButtonText
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'generate_link');
            formData.append('intern_id', id);

            fetch('/interns.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Link Generated!',
                        html: '<p style="margin-bottom:15px">Share this registration link with the intern:</p>' +
                              '<input type="text" id="regLinkInput" class="form-control" readonly value="' + data.url + '" style="text-align:center; font-weight:500; border-color:var(--orange)">',
                        icon: 'success',
                        confirmButtonColor: '#FF6B1A',
                        confirmButtonText: 'Copy Link & Close',
                        showCloseButton: true
                    }).then((r) => {
                        const firstName = name ? name.split(' ')[0] : '';
                        copyLink(data.url, firstName);
                        setTimeout(() => location.reload(), 1200);
                    });
                } else {
                    Swal.fire('Error', data.error || 'Failed to generate link.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Connection failed.', 'error');
            });
        }
    });
}

function reRegister(id, name) {
    Swal.fire({
        title: 'Re-register Face ID',
        text: 'This will wipe the current face embedding for ' + name + ' and generate a new 24-hour registration link. The existing QR code will remain valid. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#8A8B8D',
        confirmButtonText: 'Yes, re-register'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 're_register');
            formData.append('intern_id', id);

            fetch('/interns.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Face Data Wiped!',
                        html: '<p style="margin-bottom:15px">New registration link generated for intern:</p>' +
                              '<input type="text" id="regLinkInput" class="form-control" readonly value="' + data.url + '" style="text-align:center; font-weight:500; border-color:var(--orange)">',
                        icon: 'success',
                        confirmButtonColor: '#FF6B1A',
                        confirmButtonText: 'Copy Link & Close',
                        showCloseButton: true
                    }).then((r) => {
                        const firstName = name ? name.split(' ')[0] : '';
                        copyLink(data.url, firstName);
                        setTimeout(() => location.reload(), 1200);
                    });
                } else {
                    Swal.fire('Error', data.error || 'Failed to re-register.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Connection failed.', 'error');
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
