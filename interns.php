<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

$db = getDB();

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

$sql = "SELECT i.*, d.name AS dept_name FROM interns i
        JOIN departments d ON d.id = i.department_id
        {$where}
        ORDER BY i.last_name, i.first_name";

if ($types) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $interns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $interns = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

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
<?php if (empty($interns)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No <?= strtolower($statusFilter) ?> interns found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
    </div>
</div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($interns) ?> <?= $statusFilter ?> Intern<?= count($interns)!=1?'s':'' ?> found</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Intern</th>
                        <th>Department</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Hours</th>
                        <th style="width:60px">Action</th>
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
                    <td>
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
                    <td><?= htmlspecialchars($intern['dept_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($intern['school'] ?: '—') ?></td>
                    <td>
                        <span class="badge" style="<?= $statusStyle ?>"><?= $intern['status'] ?></span>
                    </td>
                    <td style="min-width:100px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="fs-12 text-muted"><?= $pct ?>%</span>
                    </td>
                    <td class="fs-12"><?= number_format($intern['rendered_hours'],1) ?> / <?= number_format($intern['required_hours'],0) ?></td>
                    <td onclick="event.stopPropagation()">
                        <a href="/intern_workspace.php?id=<?= $intern['id'] ?>" class="btn btn-icon btn-sm" title="Open">
                            <i class="fas fa-arrow-right" style="color:var(--orange)"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
