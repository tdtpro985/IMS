<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Create policies table
$db->query("CREATE TABLE IF NOT EXISTS intern_policies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(100) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    content     TEXT NOT NULL,
    icon        VARCHAR(50) DEFAULT 'fa-file-alt',
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "Table created: intern_policies\n";

$db->query("DELETE FROM intern_policies");

$policies = [
    ['Traineeship Terms', 'Training Agreement', 
     "TDT Powersteel Corporation agrees to provide the Trainee with a traineeship period for identified learning objectives. The Trainee agrees to provide all work and services reasonably required by the Company.",
     'fa-handshake', 1],

    ['Attendance & Schedule', 'Training Duration and Commencement',
     "The Trainee is expected to attend the training period for work experience. The total training period is based on the number of required hours indicated in the internship agreement (e.g., 400 hours). The Trainee shall follow all rules and regulations of the Company during this period.",
     'fa-calendar-check', 2],

    ['Attendance & Schedule', 'Absence Policy',
     "If the Trainee will be unable to attend the training, the assigned Trainer must be informed on the first day of absence. Three (3) consecutive absences without prior notice is a ground for immediate termination of the traineeship.",
     'fa-user-times', 3],

    ['Attendance & Schedule', 'Timekeeping & Late Policy',
     "The Trainee must keep a record of all tasks through the daily time record (DTR). Exceeding the allowable maximum number of hours/minutes of late per week — equivalent to 5% of the total number of hours per week — is a ground for immediate termination.",
     'fa-clock', 4],

    ['Dress Code', 'Monday to Thursday — Corporate Attire',
     "Slacks, Skirt, Blouse, Button-down Shirts, Polo, and Closed Black Shoes are required from Monday to Thursday.",
     'fa-user-tie', 5],

    ['Dress Code', 'Friday — Casual Attire',
     "On Fridays, Trainees may wear casual attire including Shirt, Jeans, Blouse, or Dress.",
     'fa-tshirt', 6],

    ['Conduct & Performance', 'Performance Standards',
     "The Trainee is expected to reach a reasonable standard of competence and performance for each task assigned. Progress and performance will be reviewed based on: (a) Standard of work and behaviour, (b) Reliability and performance, (c) Timekeeping & task records, (d) General conduct.",
     'fa-star', 7],

    ['Conduct & Performance', 'Code of Conduct',
     "The Trainee shall behave as part of the Company on whatever position assigned and abide by all terms and conditions applicable to the Company's staff. The Trainee must respect the Company's policies, values, and procedures at all times.",
     'fa-shield-alt', 8],

    ['Conduct & Performance', 'Grounds for Immediate Termination',
     "The following are grounds for immediate termination: (a) Three consecutive absences without prior notice, (b) Incapacity to attend training, (c) Inappropriate language or conduct toward customers or employees, (d) Inappropriate behaviour toward any Company employee, (e) Misuse of Company tools or information, (f) Actions against Company rules and regulations, (g) Failure to make progress toward agreed goals, (h) Exceeding allowable late hours per week (5% of weekly total), (i) Fraud or any criminal offence, (j) Breach of the training agreement.",
     'fa-exclamation-triangle', 9],

    ['Trainer & Supervision', 'Trainer Designation',
     "The Company will designate a Trainer to train, mentor, and monitor the Trainee. The Trainer shall be the primary point of contact. All requests from the Trainee must be communicated through the Trainer.",
     'fa-chalkboard-teacher', 10],

    ['Trainer & Supervision', 'Termination Notice',
     "A Trainee wishing to terminate the traineeship must give one week's notice to the Trainer. The Company may also terminate the traineeship if the Trainee's performance is unsatisfactory, at the Trainer's discretion.",
     'fa-sign-out-alt', 11],

    ['Compensation', 'No Monetary Compensation',
     "No monetary allowance, stipend, or salary will be provided to the Trainee during the training period. The traineeship is not an employment contract and creates no contractual relationship between the Trainee and the Company.",
     'fa-ban', 12],
];

foreach ($policies as [$cat, $title, $content, $icon, $order]) {
    $stmt = $db->prepare("INSERT INTO intern_policies (category, title, content, icon, sort_order) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $cat, $title, $content, $icon, $order);
    $stmt->execute();
    $stmt->close();
    echo "Added: {$title}\n";
}

echo "\nDone. " . count($policies) . " policies seeded.\n";
