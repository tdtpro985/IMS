<?php
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

// Check today's entry
$stmt = $db->prepare("SELECT id, time_in, time_out, entry_source FROM dtr_entries WHERE intern_id = ? AND entry_date = ? AND is_archived = 0");
$stmt->bind_param('is', $internId, $date);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($entry) {
    if ($entry['entry_source'] === 'manual') {
        // Skip over manual entries (HR entries override kiosk)
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Manual DTR entry already exists for today']);
        exit;
    }

    if ($action === 'clock_in') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Already clocked in today']);
        exit;
    }

    // clock_out
    if ($entry['time_out']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Already clocked out today']);
        exit;
    }

    // Update existing clock_in entry with time_out
    $stmt = $db->prepare("UPDATE dtr_entries SET time_out = ? WHERE id = ?");
    $stmt->bind_param('si', $time, $entry['id']);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Recalculate rendered hours
        $db->query("UPDATE interns SET rendered_hours = (SELECT COALESCE(SUM(rendered_hours),0) FROM dtr_entries WHERE intern_id = {$internId} AND is_archived = 0) WHERE id = {$internId}");
        
        // Fetch newly calculated hours for this entry
        $hRes = $db->query("SELECT rendered_hours FROM dtr_entries WHERE id = " . $entry['id']);
        $hours = $hRes ? $hRes->fetch_row()[0] : 0;

        logAudit('UPDATE', 'DTR', $entry['id'], "Intern {$name} clocked out via kiosk at {$time} on {$date}. Rendered: {$hours} hrs.");

        echo json_encode([
            'ok' => true,
            'message' => 'Clock out successful',
            'time_in' => $entry['time_in'],
            'time_out' => $time,
            'rendered_hours' => (float)$hours
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to save DTR data']);
    }
} else {
    // No entry exists
    if ($action === 'clock_out') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No clock in entry found for today']);
        exit;
    }

    // clock_in
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
}
