<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

$db = getDB();

$policies = $db->query(
    "SELECT * FROM intern_policies WHERE is_active=1 ORDER BY sort_order ASC"
)->fetch_all(MYSQLI_ASSOC);

// Group by category
$grouped = [];
foreach ($policies as $p) {
    $grouped[$p['category']][] = $p;
}

$pageTitle   = 'Intern Policy Hub';
$breadcrumbs = [['label' => 'Intern Policy Hub', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Intern Policy Hub</h1>
    <p>Company policies and guidelines for TDT Powersteel OJT/Intern Program — based on the On-The-Job Training Agreement.</p>
</div>

<!-- Policy notice banner -->
<div style="background:rgba(232,98,26,.08);border:1.5px solid rgba(232,98,26,.25);border-radius:10px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
    <i class="fas fa-info-circle" style="color:var(--orange);font-size:20px;flex-shrink:0"></i>
    <div>
        <div style="font-weight:600;font-size:13.5px;color:var(--text-main)">TDT Powersteel Corporation — On-The-Job Training Agreement</div>
        <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px">
            All interns are expected to be familiar with and abide by the following policies. These are extracted from the official OJT Agreement signed by both the Trainee and TDT Powersteel Corporation.
        </div>
    </div>
</div>

<!-- Category sections -->
<?php
$catIcons = [
    'Traineeship Terms'    => 'fa-handshake',
    'Attendance & Schedule'=> 'fa-calendar-check',
    'Dress Code'           => 'fa-tshirt',
    'Conduct & Performance'=> 'fa-shield-alt',
    'Trainer & Supervision'=> 'fa-chalkboard-teacher',
    'Compensation'         => 'fa-ban',
];
$catColors = [
    'Traineeship Terms'    => 'var(--info)',
    'Attendance & Schedule'=> 'var(--orange)',
    'Dress Code'           => '#8B5CF6',
    'Conduct & Performance'=> 'var(--danger)',
    'Trainer & Supervision'=> 'var(--success)',
    'Compensation'         => 'var(--gray-mid)',
];
?>

<?php foreach ($grouped as $category => $items): ?>
<div class="card mb-20">
    <div class="card-header" style="background:var(--gray-light)">
        <span class="card-title" style="display:flex;align-items:center;gap:10px">
            <span style="width:32px;height:32px;border-radius:8px;background:<?= $catColors[$category] ?? 'var(--orange)' ?>;
                         display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas <?= $catIcons[$category] ?? 'fa-file-alt' ?>" style="color:#fff;font-size:14px"></i>
            </span>
            <?= htmlspecialchars($category) ?>
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <?php foreach ($items as $i => $policy): ?>
        <div style="padding:18px 22px;<?= $i < count($items)-1 ? 'border-bottom:1px solid var(--gray-border)' : '' ?>">
            <div style="display:flex;align-items:flex-start;gap:14px">
                <div style="width:36px;height:36px;border-radius:8px;
                            background:<?= $catColors[$category] ?? 'var(--orange)' ?>22;
                            color:<?= $catColors[$category] ?? 'var(--orange)' ?>;
                            display:flex;align-items:center;justify-content:center;
                            flex-shrink:0;margin-top:2px">
                    <i class="fas <?= htmlspecialchars($policy['icon']) ?>" style="font-size:14px"></i>
                </div>
                <div style="flex:1">
                    <div style="font-size:14px;font-weight:600;color:var(--text-main);margin-bottom:6px">
                        <?= htmlspecialchars($policy['title']) ?>
                    </div>
                    <div style="font-size:13px;color:var(--text-muted);line-height:1.7">
                        <?= nl2br(htmlspecialchars($policy['content'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Footer note -->
<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px">
    <i class="fas fa-lock" style="margin-right:6px"></i>
    These policies are sourced from the official TDT Powersteel On-The-Job Training Agreement.
    For questions, contact the HR &amp; Admin department.
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
