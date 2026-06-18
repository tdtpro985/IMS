<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

$db = getDB();

// Stats
$totalInterns   = $db->query("SELECT COUNT(*) FROM interns WHERE status='Active'")->fetch_row()[0];
$totalHours     = $db->query("SELECT COALESCE(SUM(rendered_hours),0) FROM interns WHERE status='Active'")->fetch_row()[0];
$requiredHours  = $db->query("SELECT COALESCE(SUM(required_hours),0) FROM interns WHERE status='Active'")->fetch_row()[0];
$avgCompletion  = $requiredHours > 0 ? round(($totalHours / $requiredHours) * 100) : 0;
$totalDepts     = $db->query("SELECT COUNT(*) FROM departments")->fetch_row()[0];

// Departments with intern counts
$depts = $db->query(
    "SELECT d.id, d.name,
            COUNT(CASE WHEN i.status='Active' THEN 1 END) AS active_count
     FROM departments d
     LEFT JOIN interns i ON i.department_id = d.id
     GROUP BY d.id, d.name
     ORDER BY d.name ASC"
)->fetch_all(MYSQLI_ASSOC);

// Recent interns
$recentInterns = $db->query(
    "SELECT i.id, i.first_name, i.last_name, i.status, i.rendered_hours, i.required_hours,
            i.profile_photo, d.name AS dept_name
     FROM interns i
     JOIN departments d ON d.id = i.department_id
     WHERE i.status = 'Active'
     ORDER BY i.created_at DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Dashboard';
$breadcrumbs = [];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars(currentUserName()) ?>. Here's an overview of TDT Powersteel Intern Management.</p>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalInterns ?></div>
            <div class="stat-label">Total Active Interns</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalHours, 0) ?></div>
            <div class="stat-label">Total Hours Rendered</div>
            <div class="stat-sub">of <?= number_format($requiredHours, 0) ?> required</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $avgCompletion ?>%</div>
            <div class="stat-label">Avg. Completion</div>
            <div class="stat-sub"><?= $avgCompletion >= 50 ? 'On track' : 'Needs attention' ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-building"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalDepts ?></div>
            <div class="stat-label">Departments</div>
            <div class="stat-sub">Active departments</div>
        </div>
    </div>
</div>

<!-- Departments Grid -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-building text-orange"></i> Departments</span>
        <a href="/departments.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($depts)): ?>
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <p>No departments configured yet.</p>
        </div>
        <?php else: ?>
        <div class="dept-grid">
            <?php foreach ($depts as $dept): ?>
            <a href="/department_view.php?id=<?= $dept['id'] ?>" class="dept-card">
                <div class="dept-card-icon"><i class="fas fa-building"></i></div>
                <div class="dept-card-name"><?= htmlspecialchars($dept['name']) ?></div>
                <div class="dept-card-count"><?= $dept['active_count'] ?></div>
                <div class="dept-card-label">Active Intern<?= $dept['active_count'] != 1 ? 's' : '' ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Interns -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users text-orange"></i> Recent Interns</span>
        <a href="/interns.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($recentInterns)): ?>
        <div class="empty-state"><i class="fas fa-users"></i><p>No active interns yet.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Intern</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentInterns as $intern):
                    $pct = $intern['required_hours'] > 0
                        ? min(100, round(($intern['rendered_hours'] / $intern['required_hours']) * 100))
                        : 0;
                    $initials = strtoupper(substr($intern['first_name'],0,1) . substr($intern['last_name'],0,1));
                ?>
                <tr style="cursor:pointer" onclick="location.href='/intern_workspace.php?id=<?= $intern['id'] ?>'">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="intern-avatar">
                                <?php if ($intern['profile_photo']): ?>
                                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                                <?php else: ?>
                                <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($intern['dept_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($intern['status']) ?>"><?= $intern['status'] ?></span></td>
                    <td style="min-width:120px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="fs-12 text-muted"><?= $pct ?>%</span>
                    </td>
                    <td><?= number_format($intern['rendered_hours'], 1) ?> / <?= number_format($intern['required_hours'], 0) ?> hrs</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Policy Hub Widget -->
<?php
$policyCount = $db->query("SELECT COUNT(*) FROM intern_policies WHERE is_active=1")->fetch_row()[0] ?? 0;
$categories  = $db->query("SELECT DISTINCT category FROM intern_policies WHERE is_active=1 ORDER BY category")->fetch_all(MYSQLI_ASSOC);
?>
<div class="card mt-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-shield-alt text-orange"></i> Intern Policy Hub</span>
        <a href="/policies.php" class="btn btn-outline btn-sm">View All Policies</a>
    </div>
    <div class="card-body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            TDT Powersteel OJT/Intern policies based on the official On-The-Job Training Agreement.
            All interns are expected to be familiar with these guidelines.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
            <?php
            $catInfo = [
                'Traineeship Terms'     => ['fa-handshake',         'var(--info)'],
                'Attendance & Schedule' => ['fa-calendar-check',    'var(--orange)'],
                'Dress Code'            => ['fa-tshirt',             '#8B5CF6'],
                'Conduct & Performance' => ['fa-shield-alt',         'var(--danger)'],
                'Trainer & Supervision' => ['fa-chalkboard-teacher', 'var(--success)'],
                'Compensation'          => ['fa-ban',                'var(--gray-mid)'],
            ];
            foreach ($categories as $cat):
                $name = $cat['category'];
                [$ico, $clr] = $catInfo[$name] ?? ['fa-file-alt', 'var(--orange)'];
                $cnt = $db->query("SELECT COUNT(*) FROM intern_policies WHERE category='{$db->real_escape_string($name)}' AND is_active=1")->fetch_row()[0];
            ?>
            <a href="/policies.php#<?= urlencode($name) ?>"
               style="display:flex;align-items:center;gap:12px;padding:14px;border-radius:10px;
                      border:1.5px solid var(--gray-border);text-decoration:none;
                      transition:border-color .2s,box-shadow .2s"
               onmouseover="this.style.borderColor='<?= $clr ?>'; this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)'"
               onmouseout="this.style.borderColor='var(--gray-border)'; this.style.boxShadow='none'">
                <div style="width:38px;height:38px;border-radius:8px;background:<?= $clr ?>22;
                             color:<?= $clr ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas <?= $ico ?>" style="font-size:16px"></i>
                </div>
                <div>
                    <div style="font-size:12.5px;font-weight:600;color:var(--text-main)"><?= htmlspecialchars($name) ?></div>
                    <div style="font-size:11px;color:var(--text-muted)"><?= $cnt ?> polic<?= $cnt==1?'y':'ies' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
