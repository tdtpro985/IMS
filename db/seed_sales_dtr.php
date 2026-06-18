<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Get Sales and Marketing dept ID
$r = $db->query("SELECT id FROM departments WHERE name='Sales and Marketing'")->fetch_assoc();
if (!$r) { die("Sales and Marketing dept not found\n"); }
$deptId = $r['id'];
echo "Dept ID: {$deptId}\n";

// Get intern IDs
function getInternId($db, $fn, $ln, $deptId, $rh, $mn = '') {
    $chk = $db->prepare("SELECT id FROM interns WHERE first_name=? AND last_name=? AND department_id=?");
    $chk->bind_param('ssi', $fn, $ln, $deptId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($row) {
        // Update required_hours if needed
        $db->query("UPDATE interns SET required_hours={$rh} WHERE id={$row['id']}");
        echo "Exists: {$fn} {$ln} [ID:{$row['id']}]\n";
        return $row['id'];
    }
    $stmt = $db->prepare("INSERT INTO interns (department_id, first_name, last_name, middle_name, required_hours, start_date) VALUES (?,?,?,?,?,'2026-05-04')");
    $stmt->bind_param('isssd', $deptId, $fn, $ln, $mn, $rh);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    echo "Created: {$fn} {$ln} [ID:{$id}]\n";
    return $id;
}

$escobarId = getInternId($db, 'Divino Excel', 'Escobar',  $deptId, 600, 'U.');
$gamulId   = getInternId($db, 'Glenn Jim',    'Gamul',    $deptId, 400, 'G.');
$barcosId  = getInternId($db, 'Christian Jay','Barcos',   $deptId, 400, 'P.');

// ── DTR Data ──────────────────────────────────────────────────────────────
// Colors from sheet:
// Red highlight = Absent, Purple/lavender = Holiday, no highlight = normal
// 5/19 Escobar = Absent (red), 5/22 Escobar = Absent, 5/26 = Absent, 5/27 = Holiday(purple), 5/29 = Absent
// 5/22 Gamul = Absent, 5/27 = Holiday
// 5/11 Barcos = Absent, 5/22 = Absent, 5/27 = Holiday

$dtrData = [

    // ── Divino Excel Escobar (600 hrs) ────────────────────────────────
    $escobarId => [
        ['2026-05-04', '07:40', '18:00', ''],
        ['2026-05-05', '07:40', '18:01', ''],
        ['2026-05-06', '07:31', '18:00', ''],
        ['2026-05-07', '07:26', '18:00', ''],
        ['2026-05-08', '07:24', '17:00', ''],
        ['2026-05-11', '07:16', '18:00', ''],
        ['2026-05-12', '07:43', '18:00', ''],
        ['2026-05-13', '07:43', '18:00', ''],
        ['2026-05-14', '07:35', '18:00', ''],
        ['2026-05-15', '07:45', '17:00', ''],
        ['2026-05-18', '07:37', '18:00', ''],
        ['2026-05-19', null,    null,    'Absent'],
        ['2026-05-20', '07:40', '18:00', ''],
        ['2026-05-21', '07:37', '18:00', ''],
        ['2026-05-22', null,    null,    'Absent'],
        ['2026-05-25', '07:37', '18:00', ''],
        ['2026-05-26', null,    null,    'Absent'],
        ['2026-05-27', null,    null,    'Holiday'],
        ['2026-05-28', '07:43', '18:00', ''],
        ['2026-05-29', null,    null,    'Absent'],
        ['2026-06-01', '07:50', '18:13', ''],
        ['2026-06-02', '07:41', '18:00', ''],
        ['2026-06-03', '07:42', '18:00', ''],
        ['2026-06-04', '07:33', '18:00', ''],
        ['2026-06-05', '07:43', '17:00', ''],
        ['2026-06-08', '07:58', '18:00', ''],
        ['2026-06-09', '07:49', '18:00', ''],
        ['2026-06-10', '08:16', '18:00', ''],
        ['2026-06-11', '07:29', '18:00', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:52', '18:00', ''],
        ['2026-06-16', '07:38', '18:00', ''],
    ],

    // ── Glenn Jim Gamul (400 hrs) ──────────────────────────────────────
    $gamulId => [
        ['2026-05-04', '07:47', '18:00', ''],
        ['2026-05-05', '07:50', '18:00', ''],
        ['2026-05-06', '07:48', '18:00', ''],
        ['2026-05-07', '07:46', '18:00', ''],
        ['2026-05-08', '07:42', '17:00', ''],
        ['2026-05-11', '07:35', '18:00', ''],
        ['2026-05-12', '07:35', '18:00', ''],
        ['2026-05-13', '07:39', '18:00', ''],
        ['2026-05-14', '07:02', '18:00', ''],
        ['2026-05-15', '07:26', '17:00', ''],
        ['2026-05-18', '07:17', '18:00', ''],
        ['2026-05-19', '07:12', '18:00', ''],
        ['2026-05-20', '07:18', '18:00', ''],
        ['2026-05-21', '07:19', '18:00', ''],
        ['2026-05-22', null,    null,    'Absent'],
        ['2026-05-25', '07:11', '18:00', ''],
        ['2026-05-26', '07:06', '18:00', ''],
        ['2026-05-27', null,    null,    'Holiday'],
        ['2026-05-28', '07:30', '18:00', ''],
        ['2026-05-29', '07:25', '17:00', ''],
        ['2026-06-01', '07:46', '18:00', ''],
        ['2026-06-02', '07:21', '18:00', ''],
        ['2026-06-03', '07:24', '18:00', ''],
        ['2026-06-04', '07:13', '18:00', ''],
        ['2026-06-05', '07:43', '17:00', ''],
        ['2026-06-08', '07:27', '18:00', ''],
        ['2026-06-09', '07:20', '18:00', ''],
        ['2026-06-10', '07:14', '18:00', ''],
        ['2026-06-11', '07:37', '18:00', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '07:54', '18:00', ''],
        ['2026-06-16', '07:22', '18:00', ''],
    ],

    // ── Christian Jay Barcos (400 hrs) ────────────────────────────────
    $barcosId => [
        ['2026-05-04', '07:47', '18:00', ''],
        ['2026-05-05', '07:45', '18:00', ''],
        ['2026-05-06', '07:29', '18:00', ''],
        ['2026-05-07', '07:32', '18:00', ''],
        ['2026-05-08', '07:44', '17:00', ''],
        ['2026-05-11', null,    null,    'Absent'],
        ['2026-05-12', '11:35', '18:00', ''],
        ['2026-05-13', '08:18', '18:00', ''],
        ['2026-05-14', '08:27', '18:00', ''],
        ['2026-05-15', '08:16', '17:00', ''],
        ['2026-05-18', '08:20', '18:00', ''],
        ['2026-05-19', '07:40', '18:00', ''],
        ['2026-05-20', '07:55', '18:00', ''],
        ['2026-05-21', '07:51', '18:00', ''],
        ['2026-05-22', null,    null,    'Absent'],
        ['2026-05-25', '07:45', '18:00', ''],
        ['2026-05-26', '08:00', '18:00', ''],
        ['2026-05-27', null,    null,    'Holiday'],
        ['2026-05-28', '09:48', '18:00', ''],
        ['2026-05-29', '08:11', '18:00', ''],
        ['2026-06-01', '08:40', '18:00', ''],
        ['2026-06-02', '08:08', '18:00', ''],
        ['2026-06-03', '07:50', '18:00', ''],
        ['2026-06-04', '07:54', '18:00', ''],
        ['2026-06-05', '08:12', '17:00', ''],
        ['2026-06-08', '08:34', '18:00', ''],
        ['2026-06-09', '07:59', '18:00', ''],
        ['2026-06-10', '08:10', '18:00', ''],
        ['2026-06-11', '07:10', '18:00', ''],
        ['2026-06-12', null,    null,    'Holiday'],
        ['2026-06-15', '08:09', '18:00', ''],
        ['2026-06-16', '07:35', '18:00', ''],
    ],
];

$inserted = 0;
$skipped  = 0;

foreach ($dtrData as $internId => $entries) {
    foreach ($entries as [$date, $timeIn, $timeOut, $remarks]) {
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

    // Recalculate rendered hours
    $db->query("UPDATE interns SET rendered_hours=(SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id={$internId} AND is_archived=0) WHERE id={$internId}");
    $rh = $db->query("SELECT first_name, last_name, rendered_hours FROM interns WHERE id={$internId}")->fetch_assoc();
    echo "  -> {$rh['first_name']} {$rh['last_name']} [#{$internId}]: {$rh['rendered_hours']} hrs\n";
}

echo "\nDone. Inserted: {$inserted} | Skipped (duplicate): {$skipped}\n";
