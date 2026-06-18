<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Get Operations Management dept ID
$r = $db->query("SELECT id FROM departments WHERE name='Operations Management'")->fetch_assoc();
if (!$r) { die("Operations Management dept not found\n"); }
$deptId = $r['id'];
echo "Dept ID: {$deptId}\n";

// ── Interns to seed (if not already existing) ─────────────────────────────
$interns = [
    ['Charles Dwyane', 'Basilio',   'B.',        400],
    ['Hannah Grace',   'Santos',    'C.',        400],
    ['Cyril Fionnah',  'Castillo',  'G.',        400],
    ['Jesus Vincent',  'Malunes',   'D.',        400],
];

$internIds = [];
foreach ($interns as [$fn, $ln, $mn, $rh]) {
    // Check if already exists
    $chk = $db->prepare("SELECT id FROM interns WHERE first_name=? AND last_name=? AND department_id=?");
    $chk->bind_param('ssi', $fn, $ln, $deptId);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        $internIds[$ln] = $existing['id'];
        echo "Exists: {$fn} {$ln} [ID:{$existing['id']}]\n";
    } else {
        $stmt = $db->prepare("INSERT INTO interns (department_id, first_name, last_name, middle_name, required_hours, start_date) VALUES (?,?,?,?,?,'2026-06-01')");
        $stmt->bind_param('isss d', $deptId, $fn, $ln, $mn, $rh);
        $stmt->close();

        $stmt = $db->prepare("INSERT INTO interns (department_id, first_name, last_name, middle_name, required_hours, start_date) VALUES (?,?,?,?,?,'2026-06-01')");
        $stmt->bind_param('isssd', $deptId, $fn, $ln, $mn, $rh);
        $stmt->execute();
        $internIds[$ln] = $db->insert_id;
        $stmt->close();
        echo "Created: {$fn} {$ln} [ID:{$internIds[$ln]}]\n";
    }
}


$basilio = $internIds['Basilio'];
$santos  = $internIds['Santos'];
$castillo= $internIds['Castillo'];
$malunes = $internIds['Malunes'];

$dtrData = [
    // ── Charles Dwyane Basilio ──────────────────────────────────────────
    $basilio => [
        ['2026-06-01', '07:43', '17:50', ''],
        ['2026-06-02', '07:46', '18:00', ''],
        ['2026-06-03', '07:45', '17:05', ''],
        ['2026-06-04', '07:46', '17:53', ''],
        ['2026-06-05', '07:51', '17:00', ''],
        ['2026-06-08', '08:05', '17:05', ''],
        ['2026-06-09', '07:46', '17:57', ''],
        ['2026-06-10', '08:02', '17:35', ''],
        ['2026-06-11', '07:59', '17:23', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:58', '17:00', ''],
        ['2026-06-16', '07:58', '18:00', 'No Office'],
    ],

    // ── Hannah Grace Santos ─────────────────────────────────────────────
    $santos => [
        ['2026-06-01', '07:43', '17:50', ''],
        ['2026-06-02', '07:46', '18:00', ''],
        ['2026-06-03', null,    null,    'Absent'],
        ['2026-06-04', '07:33', '17:53', ''],
        ['2026-06-05', '07:48', '17:00', ''],
        ['2026-06-08', '07:00', '17:00', ''],
        ['2026-06-09', '07:00', '17:57', ''],
        ['2026-06-10', '07:00', '17:35', ''],
        ['2026-06-11', '07:00', '17:23', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:16', '17:00', ''],
        ['2026-06-16', '06:59', '17:05', ''],
    ],

    // ── Cyril Fionnah Castillo ──────────────────────────────────────────
    $castillo => [
        ['2026-06-01', '07:43', '17:50', ''],
        ['2026-06-02', '07:46', '18:00', ''],
        ['2026-06-03', null,    null,    'Absent'],
        ['2026-06-04', '07:33', '17:53', ''],
        ['2026-06-05', '07:48', '17:00', ''],
        ['2026-06-08', '07:33', '18:00', ''],
        ['2026-06-09', '07:18', '17:57', ''],
        ['2026-06-10', '07:19', '17:35', ''],
        ['2026-06-11', '07:23', '17:23', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:12', '17:00', ''],
        ['2026-06-16', '07:47', '18:00', 'No Office'],
    ],

    // ── Jesus Vincent Malunes ───────────────────────────────────────────
    $malunes => [
        ['2026-06-01', '07:43', '17:50', ''],
        ['2026-06-02', '07:46', '18:00', ''],
        ['2026-06-03', '07:35', '17:05', ''],
        ['2026-06-04', '07:40', '17:53', ''],
        ['2026-06-05', '07:38', '17:00', ''],
        ['2026-06-08', '07:36', '18:00', ''],
        ['2026-06-09', '07:27', '17:57', ''],
        ['2026-06-10', '07:19', '17:35', ''],
        ['2026-06-11', '07:22', '17:23', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:16', '17:00', ''],
        ['2026-06-16', '07:50', '18:00', 'No Office'],
    ],
];

$inserted = 0;
$skipped  = 0;

foreach ($dtrData as $internId => $entries) {
    foreach ($entries as [$date, $timeIn, $timeOut, $remarks]) {
        // Skip duplicate
        $chk = $db->prepare("SELECT id FROM dtr_entries WHERE intern_id=? AND entry_date=?");
        $chk->bind_param('is', $internId, $date);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) { $chk->close(); $skipped++; continue; }
        $chk->close();

        $stmt = $db->prepare("INSERT INTO dtr_entries (intern_id, entry_date, time_in, time_out, remarks) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss', $internId, $date, $timeIn, $timeOut, $remarks);
        $stmt->execute();
        $stmt->close();
        $inserted++;
    }

    // Update intern rendered_hours
    $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
    $rh = $db->query("SELECT rendered_hours FROM interns WHERE id={$internId}")->fetch_row()[0];
    echo "  -> Intern #{$internId} rendered_hours updated: {$rh}\n";
}

echo "\nDone. Inserted: {$inserted} | Skipped (duplicate): {$skipped}\n";
