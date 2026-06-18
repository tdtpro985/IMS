<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Clear existing if re-running
$db->query("DELETE FROM moa_agreements WHERE seq IS NOT NULL");

$moas = [
    // seq, school, validity, start, end, status, remarks
    [1,  'Batangas State University ARASOF - Nasugbu Campus',         '3 years',         '2026-02-01', '2029-02-01', 'Active',           ''],
    [2,  'Eulogio "Amang" Rodriguez Institute of Science and Technology', '2 years',     '2025-04-01', '2027-03-01', 'Active',           ''],
    [3,  'Jesus Reigns Christian Academy',                             '1 year',          '2024-10-01', '2025-10-01', 'Expired',          'For Renewal'],
    [4,  'Technological University of the Philippines',                '3 years',         '2026-02-01', '2029-02-01', 'Active',           ''],
    [5,  'Universidad De Manila',                                      'For Verification','2026-01-01', null,         'For Verification',  ''],
    [6,  'St. Matthew of Blumentritt Institute of Technology',         '1 year',          '2025-11-01', '2026-02-01', 'Expired',          'For Renewal'],
    [7,  'Access Computer and Technical Colleges - Manila Campus',     'For Verification','2025-04-01', null,         'For Verification',  ''],
    [8,  'Manila Business College',                                    '1 year',          null,         null,         'For Verification',  ''],
    [9,  'Pamantasan ng Lungsod ng Maynila',                           '3 years',         '2025-01-01', '2028-01-01', 'Active',           ''],
    [10, 'National University',                                        '1 year',          '2026-02-01', '2027-02-01', 'Active',           ''],
    [11, 'University of Santo Tomas',                                  'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [12, 'Far Eastern University',                                     'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [13, 'Bestlink College of the Philippines',                        '1 year',          '2026-02-01', '2027-02-01', 'Active',           ''],
    [14, 'Perpetual Help College of Manila',                           'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [15, 'PHINMA Saint Jude College – Manila',                         'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [16, 'University of Manila',                                       '3 years',         '2026-05-01', '2029-05-01', 'On Process',        'For Sign'],
    [17, 'Centro Escolar University',                                  'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [18, 'University of the East',                                     'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [19, 'Lyceum of the Philippines',                                  'On Process',      null,         null,         'On Process',        'Pending to Receive'],
    [20, 'Antipolo Institute and Technology',                          '1 year',          '2025-06-01', '2026-06-01', 'On Process',        'For Renewal'],
    [21, 'Adamson University',                                         '',                null,         null,         'On Process',        ''],
    [22, 'De La Salle University',                                     '',                null,         null,         'On Process',        ''],
    [23, 'University of Caloocan City',                                '',                null,         null,         'On Process',        ''],
    [24, 'Bulacan State University',                                   '',                null,         null,         'On Process',        ''],
    [25, 'Navotas Polytechnic College',                                '',                null,         null,         'On Process',        ''],
    [26, 'Global Reciprocal Colleges',                                 '',                null,         null,         'On Process',        ''],
];

$inserted = 0;
foreach ($moas as $m) {
    [$seq, $school, $validity, $start, $end, $status, $remarks] = $m;

    $stmt = $db->prepare(
        "INSERT INTO moa_agreements (seq, school_name, validity, period_start, period_end, status, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issssss', $seq, $school, $validity, $start, $end, $status, $remarks);
    $stmt->execute();
    $stmt->close();
    $inserted++;
    echo "Inserted [{$seq}] {$school}\n";
}

echo "\nDone. Total inserted: {$inserted}\n";
