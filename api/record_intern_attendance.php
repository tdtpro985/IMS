<?php
date_default_timezone_set('Asia/Manila');
// API endpoint for kiosk to record intern clock-in / clock-out.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit.php';
$db = getDB();

// Accept input via JSON or standard POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$internId = isset($input['intern_id']) ? (int)$input['intern_id'] : 0;
$action = isset($input['action']) ? trim($input['action']) : '';
$isOffline = isset($input['is_offline']) ? (bool)$input['is_offline'] : false;

// Security: Use server time for active online requests to prevent clock tampering.
// Only respect client-provided time for historical offline syncs.
if ($isOffline && isset($input['date']) && isset($input['time'])) {
    $date = trim($input['date']);
    $time = trim($input['time']);
} else {
    $date = date('Y-m-d');
    $time = date('H:i:s');
}

if ($internId <= 0 || !in_array($action, ['clock_in', 'clock_out'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Fetch intern name to verify status and log audit
$stmt = $db->prepare("SELECT first_name, last_name FROM interns WHERE id = ? AND status = 'Active'");
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$intern) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Active intern not found']);
    exit;
}

$name = $intern['first_name'] . ' ' . $intern['last_name'];

// Check for any manual entry today (HR override takes precedence)
$manualStmt = $db->prepare("SELECT id FROM dtr_entries WHERE intern_id = ? AND entry_date = ? AND entry_source = 'manual' AND is_archived = 0 LIMIT 1");
$manualStmt->bind_param('is', $internId, $date);
$manualStmt->execute();
$hasManual = $manualStmt->get_result()->fetch_assoc();
$manualStmt->close();

if ($hasManual) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Manual DTR entry already exists for today']);
    exit;
}

if ($action === 'clock_in') {
    // Check if there is an open session today (where time_out IS NULL)
    $stmt = $db->prepare("SELECT id FROM dtr_entries WHERE intern_id = ? AND entry_date = ? AND time_out IS NULL AND is_archived = 0 LIMIT 1");
    $stmt->bind_param('is', $internId, $date);
    $stmt->execute();
    $openSession = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($openSession) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Already clocked in today']);
        exit;
    }

    // Create a new clock_in session
    $stmt = $db->prepare("INSERT INTO dtr_entries (intern_id, entry_date, time_in, entry_source) VALUES (?, ?, ?, 'kiosk')");
    $stmt->bind_param('iss', $internId, $date, $time);
    $success = $stmt->execute();
    $newId = $db->insert_id;
    $stmt->close();

    if ($success) {
        logAudit('CREATE', 'DTR', $newId, "Intern {$name} clocked in via kiosk at {$time} on {$date}.");
        
        echo json_encode([
            'ok' => true,
            'message' => 'Clock in successful',
            'time_in' => $time,
            'time_out' => null,
            'rendered_hours' => 0.0
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to save DTR data']);
    }
    exit;
} else if ($action === 'clock_out') {
    // Find the most recent open session today (where time_out IS NULL)
    $stmt = $db->prepare("SELECT id, time_in FROM dtr_entries WHERE intern_id = ? AND entry_date = ? AND time_out IS NULL AND is_archived = 0 ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('is', $internId, $date);
    $stmt->execute();
    $openSession = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($openSession) {
        // Update the open session with time_out
        $stmt = $db->prepare("UPDATE dtr_entries SET time_out = ? WHERE id = ?");
        $stmt->bind_param('si', $time, $openSession['id']);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Recalculate rendered hours for the intern
            $db->query("UPDATE interns SET rendered_hours = (SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id = {$internId} AND is_archived = 0) WHERE id = {$internId}");
            
            // Fetch newly calculated hours for this entry
            $hRes = $db->query("SELECT rendered_hours FROM dtr_entries WHERE id = " . $openSession['id']);
            $hours = $hRes ? $hRes->fetch_row()[0] : 0;

            logAudit('UPDATE', 'DTR', $openSession['id'], "Intern {$name} clocked out via kiosk at {$time} on {$date}. Rendered: {$hours} hrs.");

            echo json_encode([
                'ok' => true,
                'message' => 'Clock out successful',
                'time_in' => $openSession['time_in'],
                'time_out' => $time,
                'rendered_hours' => (float)$hours
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to save DTR data']);
        }
    } else {
        // No open session exists today. Check if there are ANY entries today
        $stmt = $db->prepare("SELECT COUNT(*) FROM dtr_entries WHERE intern_id = ? AND entry_date = ? AND is_archived = 0");
        $stmt->bind_param('is', $internId, $date);
        $stmt->execute();
        $count = 0;
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Already clocked out today']);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => "You forgot to clock out yesterday. Please communicate with HR to fix it."]);
        }
    }
    exit;
}
