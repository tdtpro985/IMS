<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
checkSession();

$db       = getDB();
$internId = (int)($_GET['intern_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT i.*, d.name AS dept_name FROM interns i
     JOIN departments d ON d.id = i.department_id WHERE i.id = ?"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$intern) { http_response_code(404); exit('Intern not found.'); }

// ── End date: use last DTR entry date if available, else intern end_date ──
$stmt = $db->prepare(
    "SELECT MAX(entry_date) AS last_date FROM dtr_entries
     WHERE intern_id = ? AND is_archived = 0 AND entry_date IS NOT NULL"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$lastDtr = $stmt->get_result()->fetch_assoc();
$stmt->close();

$effectiveEndDate = $lastDtr['last_date'] ?? $intern['end_date'] ?? null;

// ── Name: FIRST MIDDLE_INITIAL. LAST ──
$mn = $intern['middle_name'] ?? '';
$middleInitial = $mn ? strtoupper(substr(trim($mn), 0, 1)) . '.' : '';
$fullName = strtoupper(
    $intern['first_name'] . ' ' .
    ($middleInitial ? $middleInitial . ' ' : '') .
    $intern['last_name']
);

$lastName  = $intern['last_name'];
$hours     = number_format($intern['required_hours'], 0);
$startDate = $intern['start_date']  ? date('F j, Y', strtotime($intern['start_date'])) : '___________';
$endDate   = $effectiveEndDate      ? date('F j, Y', strtotime($effectiveEndDate))      : '___________';

// Ordinal for "Given this X day"
$givenDate = $effectiveEndDate ?? date('Y-m-d');
$day       = (int)date('j', strtotime($givenDate));
$monthYear = date('F Y', strtotime($givenDate));
$ordinal   = match(true) {
    $day % 100 >= 11 && $day % 100 <= 13 => $day . 'th',
    $day % 10 === 1 => $day . 'st',
    $day % 10 === 2 => $day . 'nd',
    $day % 10 === 3 => $day . 'rd',
    default         => $day . 'th'
};

// Gender pronoun
$pronoun    = ($intern['gender'] ?? '') === 'Female' ? 'her' : 'his';
$salutation = ($intern['gender'] ?? '') === 'Female' ? 'Ms.' : 'Mr.';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate of Completion — <?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
      font-family: 'Times New Roman', Times, serif;
      background: #f0f0f0;
      color: #222;
  }

  .print-btn {
      display: block;
      margin: 20px auto 12px;
      background: #E8621A;
      color: #fff;
      border: none;
      padding: 10px 24px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: Arial, sans-serif;
      width: fit-content;
  }

  /* ── Page ── */
  .page {
      width: 780px;
      min-height: 1040px;
      margin: 0 auto 30px;
      background: #fff;
      display: flex;
      flex-direction: column;
      border: 1px solid #ccc;
      box-shadow: 0 4px 20px rgba(0,0,0,.12);
  }

  /* ── Header ── */
  .cert-header {
      padding: 14px 48px 10px;
      border-bottom: 3px solid #E8621A;
      text-align: center;
  }

  .cert-header img {
      height: 54px;
      width: auto;
      max-width: 320px;
      display: block;
      margin: 0 auto;
      object-fit: contain;
  }

  /* ── Body ── */
  .cert-body {
      flex: 1;
      padding: 48px 80px 36px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
  }

  .cert-title {
      font-family: Arial, sans-serif;
      font-size: 24px;
      font-weight: 900;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: #111;
      margin-bottom: 28px;
  }

  .cert-certify {
      font-size: 13px;
      color: #444;
      font-style: italic;
      margin-bottom: 14px;
  }

  .cert-name {
      font-size: 22px;
      font-weight: 700;
      text-decoration: underline;
      letter-spacing: 1px;
      margin-bottom: 22px;
      color: #111;
  }

  .cert-text {
      font-size: 13px;
      line-height: 1.95;
      color: #333;
      max-width: 520px;
  }

  .cert-company {
      font-size: 14px;
      font-weight: 700;
      letter-spacing: .5px;
      color: #111;
  }

  .cert-dates {
      font-size: 13px;
      color: #333;
      margin-top: 16px;
  }

  .cert-dates u { text-decoration: underline; }

  .cert-request {
      font-size: 12.5px;
      color: #333;
      margin-top: 22px;
      line-height: 1.85;
      max-width: 500px;
  }

  .cert-given {
      font-size: 12.5px;
      color: #333;
      margin-top: 14px;
  }

  /* ── Signature ── */
  .cert-signature {
      margin-top: 56px;
      text-align: center;
  }

  .sig-name {
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: 700;
      color: #111;
  }

  .sig-title {
      font-size: 12px;
      font-style: italic;
      color: #444;
      line-height: 1.75;
      margin-top: 3px;
  }

  .cert-watermark {
      font-family: Arial, sans-serif;
      font-size: 9px;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: #aaa;
      margin-top: 32px;
  }

  /* ── Footer ── */
  .cert-footer {
      padding: 10px 48px;
      border-top: 2px solid #E8621A;
      text-align: center;
      font-family: Arial, sans-serif;
      font-size: 8.5px;
      color: #888;
      line-height: 1.8;
  }

  @media print {
      body { background: #fff; }
      .print-btn { display: none !important; }
      .page { border: none; box-shadow: none; margin: 0; }
  }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>

<div class="page">

    <!-- Header — logo image, matches the actual COC template -->
    <div class="cert-header">
        <img src="/uploads/photos/logo-light.jpg" alt="TDT Powersteel Corp.">
    </div>

    <!-- Body -->
    <div class="cert-body">

        <div class="cert-title">Certificate of Completion</div>

        <p class="cert-certify">This is to certify that</p>

        <div class="cert-name"><?= htmlspecialchars($fullName) ?></div>

        <p class="cert-text">
            has completed <?= $pronoun ?> internship program with total hours of
            <strong><?= $hours ?> hours</strong> at<br>
            <span class="cert-company">TDT POWERSTEEL CORP.</span>
        </p>

        <p class="cert-dates">
            from <u><?= $startDate ?></u> to <u><?= $endDate ?></u>
        </p>

        <p class="cert-request">
            This certification is being issued upon request of
            <strong><?= $salutation ?> <?= htmlspecialchars($lastName) ?></strong>
            for academic purposes only.
        </p>

        <p class="cert-given">
            Given this <?= $ordinal ?> day of <?= $monthYear ?> at Sampaloc, Manila.
        </p>

        <!-- Signature block -->
        <div class="cert-signature">
            <div class="sig-name">Monaliza R. Acu&#241;a, CPA, MIR</div>
            <div class="sig-title">
                AVP for Finance and Accounting<br>
                HR &amp; Admin Officer-in-charge
            </div>
        </div>

        <div class="cert-watermark">NOT VALID WITHOUT THE SIGN OF IMMEDIATE HEAD</div>

    </div>

    <!-- Footer -->
    <div class="cert-footer">
        1017 – A. Vicente Cruz St., Sampaloc, Zone 047, Brgy. 475, Manila<br>
        Tel. No. (02) 8 831-0000 &nbsp;&nbsp; www.powersteel.com.ph
    </div>

</div>

</body>
</html>
