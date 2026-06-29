<?php
/**
 * Seed Batch 2 — June 2026 Interns
 * Source: Updated intern list from HR
 * School: Antipolo Institute of Technology (AITECH)
 * Course: BCETM - AITECH / BS Civil Engineering
 * Start date: 2026-06-22 (all), except noted
 * INCLUDED: All except James Elizar B. Rodolfo, Anjelo D. Eballonado, Raymart Valles
 */

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$depts = [];
$r = $db->query("SELECT id, name FROM departments");
while ($row = $r->fetch_assoc()) {
    $depts[$row['name']] = $row['id'];
}

echo "Departments loaded:\n";
foreach ($depts as $name => $id) echo "  [{$id}] {$name}\n";
echo "\n";

// Department mapping:
// "Sales Department"     → Sales and Marketing
// "Marketing Department" → Sales and Marketing
// "BD Department"        → Business Development
// "Operations Department"→ Operations Management

$school = 'Antipolo Institute of Technology';
$course = 'BCETM';

// [first_name, last_name, middle_name, gender, birthdate, address, phone, department, start_date, required_hours, supervisor, emergency_contact_name]
$interns = [

    // ── SALES DEPARTMENT → Sales and Marketing ──────────────────────────────
    [
        'fn'         => 'John Joseph',
        'ln'         => 'Medina',
        'mn'         => '',
        'gender'     => 'Male',
        'birthdate'  => '2003-01-26',
        'address'    => '310 Cirumago, Pinugay, Baras, Rizal',
        'phone'      => '9137082648',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 560,
        'supervisor' => 'Mr. Romar Remoreding Jr.',
        'guardian'   => 'Rommel Medina',
    ],
    [
        'fn'         => 'Sean Rovick',
        'ln'         => 'Modelo',
        'mn'         => '',
        'gender'     => 'Male',
        'birthdate'  => '2003-04-08',
        'address'    => 'Avesa, Brgy. Pinugay, Antipolo City',
        'phone'      => '9563070457',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 560,
        'supervisor' => 'Mr. Romar Remoreding Jr.',
        'guardian'   => 'Minerva Modelo',
    ],

    // ── MARKETING DEPARTMENT → Sales and Marketing ───────────────────────────
    [
        'fn'         => 'Emmanuel',
        'ln'         => 'Magistrado',
        'mn'         => 'L.',
        'gender'     => 'Male',
        'birthdate'  => '2003-11-12',
        'address'    => 'Maagay 2, Brgy. Inarawan Antipolo City',
        'phone'      => '9610788303',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Marestela Magistrado',
    ],
    [
        'fn'         => 'Nanagad',
        'ln'         => 'Jhon',
        'mn'         => 'C.',
        'gender'     => 'Male',
        'birthdate'  => '2004-02-23',
        'address'    => 'Sitio Bukal ng Buhay Barangay Dela paz Antipolo City',
        'phone'      => '9367382058',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Cecilia C. Nanagad',
    ],
    [
        'fn'         => 'Jocelyn',
        'ln'         => 'Quatis',
        'mn'         => '',
        'gender'     => 'Female',
        'birthdate'  => '2003-11-28',
        'address'    => 'Sitio Tanza 1 Brgy san Jose Antipolo city',
        'phone'      => '9276721367',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Joel Quatis',
    ],
    [
        'fn'         => 'Judy Ann',
        'ln'         => 'Paciente',
        'mn'         => 'G.',
        'gender'     => 'Female',
        'birthdate'  => '2004-01-25',
        'address'    => '38 Saint Anne St. Ma. Catipan Antipolo City',
        'phone'      => '9777317040',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Ginebra M. Paciente',
    ],
    [
        'fn'         => 'Annielyn',
        'ln'         => 'Nablo',
        'mn'         => 'D.',
        'gender'     => 'Female',
        'birthdate'  => '2004-02-14',
        'address'    => 'Sitio Kabig Brgy. San Jose Antipolo City',
        'phone'      => '9208695040',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Aneita M. Nablo',
    ],
    [
        'fn'         => 'Angel Terence',
        'ln'         => 'Nagawan',
        'mn'         => 'A.',
        'gender'     => 'Male',
        'birthdate'  => '2004-04-23',
        'address'    => 'St. Anthony 3 Brgy. Inarawan Antipolo City, Rizal',
        'phone'      => '9566344874',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 560,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Rochelle A. Nagawan',
    ],
    [
        'fn'         => 'Loraline Ann',
        'ln'         => 'Cruz',
        'mn'         => 'M.',
        'gender'     => 'Female',
        'birthdate'  => '2004-07-28',
        'address'    => 'Sitio Balikbaka Pinugay Baras, Rizal',
        'phone'      => '9299512564',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Arman M. Cruz',
    ],
    [
        'fn'         => 'Angelo',
        'ln'         => 'Suarez',
        'mn'         => 'L.',
        'gender'     => 'Male',
        'birthdate'  => '2001-10-30',
        'address'    => '217 J. Concepcion St., Santolan Pasig City',
        'phone'      => '9072890297',
        'dept'       => 'Sales and Marketing',
        'start'      => '2026-06-23',
        'hours'      => 240,
        'supervisor' => 'Ms. Shane Anne Gadia',
        'guardian'   => 'Michael J. Suarez',
    ],

    // ── BD DEPARTMENT → Business Development ────────────────────────────────
    [
        'fn'         => 'Mark David',
        'ln'         => 'Miranda',
        'mn'         => '',
        'gender'     => 'Male',
        'birthdate'  => '2003-04-01',
        'address'    => '3119 V. Mapa, Sta. Mesa, Manila',
        'phone'      => '9489469113',
        'dept'       => 'Business Development',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Mr. Mark Jeffrey D. Morales',
        'guardian'   => 'Rishele Miranda',
    ],
    [
        'fn'         => 'Angel Arlyn',
        'ln'         => 'Castro',
        'mn'         => 'G.',
        'gender'     => 'Female',
        'birthdate'  => '2004-05-02',
        'address'    => 'Block 3 Lot 10 Ligtasan St. Ceramic Compound Brgy. San Roque Antipolo City',
        'phone'      => '9399467734',
        'dept'       => 'Business Development',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Mr. Mark Jeffrey D. Morales',
        'guardian'   => 'Evelyn G. Castro',
    ],
    [
        'fn'         => 'Cindy Joy',
        'ln'         => 'Ombao',
        'mn'         => 'S.',
        'gender'     => 'Female',
        'birthdate'  => '2003-09-11',
        'address'    => 'Sitio Calumpang Brgy. San Jole, Antipolo City, Rizal',
        'phone'      => '9094049239',
        'dept'       => 'Business Development',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Mr. Mark Jeffrey D. Morales',
        'guardian'   => 'Rosemarie S. Ombao',
    ],
    [
        'fn'         => 'Wilfredo',
        'ln'         => 'Grantula',
        'mn'         => 'M.',
        'gender'     => 'Male',
        'birthdate'  => '2003-06-01',
        'address'    => 'Block 8 Lot 34 Phase 2 Brgy. Pinugay, Baras, Rizal',
        'phone'      => '9480470464',
        'dept'       => 'Business Development',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Mr. Mark Jeffrey D. Morales',
        'guardian'   => 'Wilfredo M. Grantula',
    ],

    // ── OPERATIONS DEPARTMENT → Operations Management ────────────────────────
    [
        'fn'         => 'Vincent',
        'ln'         => 'Magayaya',
        'mn'         => 'S.',
        'gender'     => 'Male',
        'birthdate'  => '2003-12-03',
        'address'    => 'Cornelio st. Melendres Subd. Brgy Dolores Taytay Rizal',
        'phone'      => '9937823886',
        'dept'       => 'Operations Management',
        'start'      => '2026-06-22',
        'hours'      => 240,
        'supervisor' => 'Mr. Arnaldo C. Saavedra',
        'guardian'   => 'Kyle Magayaya',
    ],

];

// ── Insert ──────────────────────────────────────────────────────────────────
$inserted = 0;
$skipped  = 0;

foreach ($interns as $i) {
    $deptName = $i['dept'];

    if (!isset($depts[$deptName])) {
        echo "⚠ Dept not found: {$deptName} — skipping {$i['fn']} {$i['ln']}\n";
        $skipped++;
        continue;
    }

    $deptId = $depts[$deptName];

    // Duplicate check
    $chk = $db->prepare("SELECT id FROM interns WHERE first_name=? AND last_name=? AND department_id=?");
    $chk->bind_param('ssi', $i['fn'], $i['ln'], $deptId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($exists) {
        echo "↷ Already exists: {$i['fn']} {$i['ln']} ({$deptName}) — skipped\n";
        $skipped++;
        continue;
    }

    // 14 placeholders: i + s×9 + d + s×3
    // department_id(i), fn(s), ln(s), mn(s), gender(s), birthdate(s),
    // address(s), phone(s), school(s), course(s), required_hours(d),
    // start_date(s), supervisor(s), guardian_name(s)
    $stmt = $db->prepare(
        "INSERT INTO interns
            (department_id, first_name, last_name, middle_name,
             gender, birthdate, address, phone,
             school, course, required_hours,
             start_date, supervisor, guardian_name, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
    );
    $stmt->bind_param(
        'isssssssssdsss',
        $deptId,
        $i['fn'], $i['ln'], $i['mn'],
        $i['gender'], $i['birthdate'], $i['address'], $i['phone'],
        $school, $course, $i['hours'],
        $i['start'], $i['supervisor'], $i['guardian']
    );

    if (!$stmt->execute()) {
        echo "✖ DB Error for {$i['fn']} {$i['ln']}: " . $stmt->error . "\n";
        $stmt->close();
        continue;
    }
    $newId = $db->insert_id;
    $stmt->close();

    echo "✔ [{$newId}] {$i['fn']} {$i['ln']} → {$deptName} | Start: {$i['start']} | {$i['hours']} hrs\n";
    $inserted++;
}

echo "\n✅ Done. Inserted: {$inserted} | Skipped: {$skipped}\n";
echo "⛔ Excluded (as instructed): James Elizar B. Rodolfo, Anjelo D. Eballonado, Raymart Valles\n";
