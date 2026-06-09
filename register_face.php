<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/audit.php';

$db = getDB();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$intern = null;

if ($token) {
    // Validate token exists and has not expired (24hr TTL)
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, gender FROM interns WHERE registration_token = ? AND token_expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $intern = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$genderSuffix = ($intern && ($intern['gender'] ?? '') === 'Female') ? '_f' : '_m';

// Handle AJAX email availability check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_email_availability') {
    header('Content-Type: application/json');
    if (!$intern) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired registration token.']);
        exit;
    }
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'available' => false, 'error' => 'Please provide a valid email address.']);
        exit;
    }
    $stmt = $db->prepare("SELECT id FROM interns WHERE email = ? AND id != ? AND status = 'Active'");
    $stmt->bind_param('si', $email, $intern['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        echo json_encode(['success' => true, 'available' => false, 'message' => 'This email is already registered.']);
    } else {
        echo json_encode(['success' => true, 'available' => true]);
    }
    exit;
}

// Handle AJAX face data submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_registration') {
    header('Content-Type: application/json');
    if (!$intern) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired registration token.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $images = $_POST['images'] ?? []; // Array of 5 base64 JPEG images

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
        exit;
    }

    // Check duplicate email
    $stmt = $db->prepare("SELECT id FROM interns WHERE email = ? AND id != ? AND status = 'Active'");
    $stmt->bind_param('si', $email, $intern['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'This email is already registered.']);
        exit;
    }

    if (!is_array($images) || count($images) !== 5) {
        echo json_encode(['success' => false, 'error' => 'Exactly 5 face captures are required.']);
        exit;
    }

    // Call Python ONNX Face Service to get embeddings
    // Python service is expected to run on localhost:5001
    $pythonServiceUrl = 'http://localhost:5001/embed';

    $ch = curl_init($pythonServiceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['images' => $images]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'Face service offline. Please try again later.']);
        exit;
    }

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to generate face embedding. Ensure your face is clear.']);
        exit;
    }

    $result = json_decode($response, true);
    $embeddings = $result['embeddings'] ?? null; // Should be array of 5 arrays of 512 floats

    // Validate embeddings structure
    if (!is_array($embeddings) || count($embeddings) !== 5) {
        echo json_encode(['success' => false, 'error' => 'Failed to process all face angles. Please retry.']);
        exit;
    }

    foreach ($embeddings as $emb) {
        if (!is_array($emb) || count($emb) !== 512) {
            echo json_encode(['success' => false, 'error' => 'Invalid embedding shape returned from face service.']);
            exit;
        }
    }

    // Generate unique QR code payload based on ID
    $qrCode = 'TDTINTRN' . $intern['id'];
    $embeddingsJson = json_encode($embeddings);
    $now = date('Y-m-d H:i:s');

    // Update intern record and clear token
    $stmt = $db->prepare(
        "UPDATE interns 
         SET email = ?, face_embedding = ?, qr_code = ?, face_registered_at = ?, 
             registration_token = NULL, token_expires_at = NULL 
         WHERE id = ?"
    );
    $stmt->bind_param('ssssi', $email, $embeddingsJson, $qrCode, $now, $intern['id']);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        logAudit('REGISTER_FACE', 'Interns', $intern['id'], "Registered face ID for {$intern['first_name']} {$intern['last_name']}.");

        // Send QR Code Email
        $subject = "Your TDT Powersteel Intern QR Code";
        $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrCode);

        $message = "
        <html>
        <head>
            <title>Your Intern QR Code</title>
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #F4F5F7; padding: 20px; color: #1A1A2E; }
                .card { background: white; border-radius: 12px; padding: 30px; max-width: 500px; margin: 0 auto; text-align: center; border: 1px solid #E2E4E8; }
                h2 { color: #FF6B1A; margin-bottom: 5px; }
                .qr-wrap { display: inline-block; padding: 15px; border: 2px solid #FF6B1A; border-radius: 12px; background: white; margin: 20px 0; }
                .footer { font-size: 12px; color: #8A8B8D; margin-top: 25px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>Hello, " . htmlspecialchars($intern['first_name']) . "!</h2>
                <p>Your face registration is complete. Use this QR code to clock in/out at the HRIS Kiosk.</p>
                <div class='qr-wrap'>
                    <img src='{$qrImageUrl}' alt='QR Code' style='width:200px;height:200px;'>
                </div>
                <div style='font-family: monospace; font-size: 16px; font-weight: bold;'>{$qrCode}</div>
                <p class='footer'>TDT Powersteel Corp. Intern Management System</p>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@tdtpowersteel.com" . "\r\n";

        // Suppress mail errors, fallback to on-screen download
        @mail($email, $subject, $message, $headers);

        echo json_encode(['success' => true, 'qr_code' => $qrCode, 'name' => $intern['first_name'] . ' ' . $intern['last_name']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database save failure. Please contact HR.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Face Registration — TDT Powersteel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- MediaPipe Face Landmarker loaded dynamically in script -->
    <style>
        :root {
            --orange: #FF6B1A;
            --orange-dark: #E8521A;
            --orange-light: #FFF0E8;
            --white: #FFFFFF;
            --gray-light: #F4F5F7;
            --gray-border: #E2E4E8;
            --text-main: #1A1A2E;
            --text-muted: #6B7280;
            --success: #22C55E;
            --danger: #EF4444;
            --radius: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-light);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 16px;
        }

        .container {
            width: 100%;
            max-width: 440px;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header {
            padding: 28px 20px 10px;
            text-align: center;
        }

        .header p {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            margin-top: 6px;
        }

        .content {
            padding: 24px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-summary {
            background: var(--gray-light);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--gray-border);
        }

        .profile-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--orange-light);
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .profile-details h3 {
            font-size: 15px;
            font-weight: 600;
        }

        .profile-details p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
        }

        .form-control {
            padding: 12px;
            border: 1.5px solid var(--gray-border);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--orange);
        }

        /* Camera / Capture UI */
        .camera-box {
            position: relative;
            width: 280px;
            height: 280px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            background: #000;
            border: 4px solid var(--gray-border);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .camera-box.active {
            border-color: var(--orange);
            box-shadow: 0 0 16px rgba(255, 107, 26, 0.4);
        }

        #webcam {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
            /* mirror effect */
        }

        .scanning-ring {
            display: none;
            position: absolute;
            inset: 0;
            border: 4px solid transparent;
            border-top-color: var(--orange);
            border-bottom-color: var(--orange);
            border-radius: 50%;
            animation: spin 2s linear infinite;
            pointer-events: none;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .camera-overlay {
            position: absolute;
            inset: 15px;
            border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            pointer-events: none;
        }

        .capture-instructions {
            text-align: center;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 500;
            padding: 0 10px;
        }

        .steps-bar {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 10px 0;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gray-border);
            transition: background 0.3s;
        }

        .step-dot.active {
            background: var(--orange);
        }

        .step-dot.completed {
            background: var(--success);
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s, transform 0.1s;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: var(--orange);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--orange-dark);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--text-main);
            border: 1px solid var(--gray-border);
        }

        /* Error & Success States */
        .status-card {
            text-align: center;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .status-icon {
            font-size: 54px;
            margin-bottom: 8px;
        }

        .status-icon.danger {
            color: var(--danger);
        }

        .status-icon.success {
            color: var(--success);
        }

        .status-title {
            font-size: 18px;
            font-weight: 700;
        }

        .status-text {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .qr-display {
            padding: 16px;
            border: 2px solid var(--orange);
            border-radius: 12px;
            background: white;
            margin: 10px 0;
            display: inline-block;
        }

        #qrCodeOutput {
            width: 180px;
            height: 180px;
            display: block;
        }

        .code-string {
            font-family: monospace;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        /* Full screen glassmorphic loader */
        .model-loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 18, 20, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            color: var(--white);
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }

        .model-loading-overlay.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--orange);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Camera flash overlay */
        .camera-flash {
            position: absolute;
            inset: 0;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 10;
        }

        .camera-flash.flash-active {
            animation: flash-anim 0.15s ease-out;
        }

        @keyframes flash-anim {
            0% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        /* SVG Guide overlay elements */
        .svg-guide-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 5;
        }

        .guide-circle {
            fill: none;
            stroke: rgba(255, 255, 255, 0.4);
            stroke-width: 3;
            stroke-dasharray: 6 6;
            transition: stroke 0.3s, stroke-dasharray 0.3s;
        }

        .guide-circle.aligned {
            stroke: var(--orange);
            stroke-dasharray: none;
        }

        .guide-circle.captured {
            stroke: var(--success);
            stroke-dasharray: none;
        }

        .guide-circle.error-state {
            stroke: var(--danger);
        }

        /* Manual override button */
        .btn-override {
            background: var(--orange-light);
            color: var(--orange);
            border: 1px dashed var(--orange);
            font-size: 12px;
            padding: 8px 12px;
            margin-top: 10px;
        }

        .hidden {
            display: none !important;
        }

        /* Email float animation */
        @keyframes floatMail {
            0% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-6px) rotate(-3deg);
            }

            100% {
                transform: translateY(0px) rotate(0deg);
            }
        }

        .animated-envelope {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 54px;
            color: var(--orange);
            animation: floatMail 2s ease-in-out infinite;
            margin-bottom: 10px;
        }

        /* Kiosk tutorial container */
        .kiosk-tutorial {
            background: var(--gray-light);
            border: 1px solid var(--gray-border);
            border-radius: 12px;
            padding: 16px;
            margin-top: 24px;
            text-align: left;
        }

        .kiosk-tutorial h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kiosk-tutorial ol {
            padding-left: 20px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text-main);
        }

        .kiosk-tutorial li {
            margin-bottom: 8px;
        }

        .kiosk-tutorial li strong {
            color: var(--orange);
        }

        /* Toggler why info styles */
        .info-toggle-container {
            margin-bottom: 4px;
        }

        .info-toggle-btn {
            background: none;
            border: none;
            color: var(--orange);
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            padding: 4px 0;
            outline: none;
            transition: color 0.2s;
            font-family: inherit;
        }

        .info-toggle-btn:hover {
            color: var(--orange-dark);
        }

        .info-toggle-content {
            font-size: 13px;
            line-height: 1.5;
            color: var(--text-muted);
            background: var(--gray-light);
            padding: 12px 14px;
            border-radius: 10px;
            border-left: 3px solid var(--orange);
            margin-top: 8px;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Email form highlight styles */
        .form-group-highlight {
            background: var(--orange-light);
            border: 1.5px solid rgba(255, 107, 26, 0.25);
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(255, 107, 26, 0.05);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group-highlight:focus-within {
            border-color: var(--orange);
            box-shadow: 0 4px 12px rgba(255, 107, 26, 0.12);
        }

        .form-group-highlight .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .form-group-highlight .form-control {
            background: var(--white);
            border: 1.5px solid var(--gray-border);
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .form-group-highlight .form-control:focus {
            border-color: var(--orange);
        }

        .form-group-highlight.invalid {
            border-color: var(--danger) !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15) !important;
        }

        .form-group-highlight.invalid .form-label {
            color: var(--danger);
        }

        .form-group-highlight.invalid .form-control {
            border-color: var(--danger);
        }

        .error-text {
            color: var(--danger);
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
            display: block;
        }

        /* Shake animation keyframes */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .shake {
            animation: shake 0.4s ease-in-out;
        }

        /* Custom Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 18, 20, 0.7);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: fadeInModal 0.25s ease-out;
        }

        .modal-card {
            background: var(--white);
            border-radius: var(--radius);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--gray-border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideUpModal 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInModal {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUpModal {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: var(--orange-light);
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 107, 26, 0.15);
        }

        .modal-icon {
            font-size: 32px;
            color: var(--orange);
        }

        .modal-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 350px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .modal-intro {
            font-size: 13px;
            color: var(--text-main);
            line-height: 1.5;
            text-align: center;
        }

        .help-section {
            background: var(--gray-light);
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--gray-border);
        }

        .help-section h3 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .help-section h3 i {
            color: var(--orange);
        }

        .help-section p {
            font-size: 12px;
            line-height: 1.5;
            color: var(--text-muted);
            margin: 0;
        }

        .help-section strong {
            color: var(--text-main);
        }

        .modal-footer {
            padding: 16px 20px 20px;
            border-top: 1px solid var(--gray-border);
        }

        /* Onboarding Tutorial Modal & Slider Styles */
        .modal-skip-btn {
            position: absolute;
            top: 16px;
            right: 18px;
            background: none;
            border: none;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
            z-index: 10;
        }

        .modal-skip-btn:hover {
            color: var(--orange);
        }

        .tutorial-slider {
            position: relative;
            min-height: 270px;
            margin-top: 10px;
        }

        .tutorial-slide {
            display: none;
            animation: tutFadeIn 0.3s ease;
        }

        .tutorial-slide.active {
            display: block;
        }

        .tutorial-step-tag {
            font-size: 10px;
            font-weight: 700;
            color: var(--orange);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .tutorial-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-main);
        }

        .tutorial-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
            min-height: 34px;
            margin: 0;
            padding: 0 8px;
        }

        .phone-mockup {
            width: 110px;
            height: 170px;
            border: 4.5px solid #2a2b2e;
            border-radius: 18px;
            position: relative;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            background: #fff;
            overflow: hidden;
            margin: 15px auto 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .phone-mockup::before {
            content: '';
            position: absolute;
            top: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 26px;
            height: 3px;
            background: #2a2b2e;
            border-radius: 1.5px;
            z-index: 2;
        }

        .phone-screen {
            width: 100%;
            height: 100%;
            position: relative;
            background: #f4f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .phone-screen img {
            width: 85%;
            height: 85%;
            object-fit: contain;
        }

        .tutorial-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
        }

        .tut-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gray-border);
            transition: background 0.2s, transform 0.2s;
            cursor: pointer;
        }

        .tut-dot.active {
            background: var(--orange);
            transform: scale(1.15);
        }

        @keyframes tutFadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #tutorialModal:not(.open) {
            display: none !important;
        }
    </style>
</head>

<body>

    <!-- Early loading screen overlay -->
    <div id="modelLoadingOverlay" class="model-loading-overlay">
        <div class="loader-spinner"></div>
        <div style="font-weight: 600; font-size: 15px;">Initializing Face Camera...</div>
        <div style="font-size: 12px; color: rgba(255, 255, 255, 0.65);">Downloading neural network model files (~6.6MB)
        </div>
    </div>

    <!-- Onboarding Tutorial Modal -->
    <div class="modal-overlay" id="tutorialModal">
        <div class="modal-card"
            style="max-width: 400px; width: 92%; box-sizing: border-box; overflow: hidden; margin: 10px; position: relative;">

            <!-- Skip Button -->
            <button type="button" class="modal-skip-btn" id="skipTutorialBtn">Skip</button>

            <!-- Scrollable Content Body -->
            <div class="modal-body"
                style="padding: 24px 20px 10px; text-align: center; overflow-y: visible; max-height: none;">
                <!-- Slider Container -->
                <div class="tutorial-slider">

                    <!-- Slide 1: Look Straight -->
                    <div class="tutorial-slide active" data-slide="0">
                        <div class="tutorial-step-tag">Step 1 of 4</div>
                        <h3 class="tutorial-title">Look Straight</h3>
                        <p class="tutorial-desc">Align your face in the center of the frame and look directly at the
                            camera.</p>
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <img src="assets/img/guide_straight<?= $genderSuffix ?>.png" alt="Look Straight">
                            </div>
                        </div>
                    </div>

                    <!-- Slide 2: Turn Left -->
                    <div class="tutorial-slide" data-slide="1">
                        <div class="tutorial-step-tag">Step 2 of 4</div>
                        <h3 class="tutorial-title">Turn Left</h3>
                        <p class="tutorial-desc">Rotate your head slightly to the left side so the camera captures your
                            profile.</p>
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <img src="assets/img/guide_left<?= $genderSuffix ?>.png" alt="Turn Left">
                            </div>
                        </div>
                    </div>

                    <!-- Slide 3: Turn Right -->
                    <div class="tutorial-slide" data-slide="2">
                        <div class="tutorial-step-tag">Step 3 of 4</div>
                        <h3 class="tutorial-title">Turn Right</h3>
                        <p class="tutorial-desc">Rotate your head slightly to the right side to capture your opposite
                            profile.</p>
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <img src="assets/img/guide_right<?= $genderSuffix ?>.png" alt="Turn Right">
                            </div>
                        </div>
                    </div>

                    <!-- Slide 4: Tilt Up -->
                    <div class="tutorial-slide" data-slide="3">
                        <div class="tutorial-step-tag">Step 4 of 4</div>
                        <h3 class="tutorial-title">Tilt Up</h3>
                        <p class="tutorial-desc">Tilt your chin and head slightly upwards while facing forward.</p>
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <img src="assets/img/guide_up<?= $genderSuffix ?>.png" alt="Tilt Up">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Fixed Footer -->
            <div class="modal-footer"
                style="padding: 12px 20px 20px; text-align: center; background: var(--white); display: flex; flex-direction: column; gap: 12px;">
                <!-- Indicators -->
                <div class="tutorial-dots" id="tutorialDots" style="margin-top: 0; justify-content: center;">
                    <div class="tut-dot active" data-index="0"></div>
                    <div class="tut-dot" data-index="1"></div>
                    <div class="tut-dot" data-index="2"></div>
                    <div class="tut-dot" data-index="3"></div>
                </div>

                <!-- Action button -->
                <div>
                    <button type="button" class="btn btn-primary w-100" id="tutNextBtn"
                        style="justify-content: center; padding: 12px 16px;">
                        Next <i class="fas fa-arrow-right" style="margin-left: 6px;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <img src="assets/img/tdt-logo.png" alt="TDT Powersteel Logo"
                style="height: auto; max-height: 40px; max-width: 260px; width: 100%; object-fit: contain; margin: 0 auto 4px; display: block;">
            <p>Intern Face Registration</p>
        </div>

        <?php if (!$intern): ?>
            <!-- Token Expired / Invalid Page -->
            <div class="status-card">
                <div class="status-icon danger"><i class="fas fa-times-circle"></i></div>
                <div class="status-title">Link Expired or Invalid</div>
                <div class="status-text">This registration link is invalid or has expired. Face registration links expire 24
                    hours after generation. Please contact HR to get a new link.</div>
            </div>
        <?php else: ?>
            <!-- Main Form & Capture Section -->
            <div id="registrationFlow" class="content">
                <div class="profile-summary">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($intern['first_name'], 0, 1) . substr($intern['last_name'], 0, 1)) ?>
                    </div>
                    <div class="profile-details">
                        <h3><?= htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']) ?></h3>
                        <p>Confirm profile details and register your face.</p>
                    </div>
                </div>

                <!-- Email confirmation -->
                <div id="emailSection" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="form-group-highlight" id="emailFormGroup">
                        <label class="form-label">
                            <i class="fas fa-envelope-open-text" style="color: var(--orange); margin-right: 4px;"></i> Email
                            Address <span style="color: var(--danger); font-size: 14px;">*</span>
                        </label>
                        <input type="email" id="internEmail" class="form-control"
                            placeholder="Enter your active email address"
                            value="<?= htmlspecialchars($intern['email'] ?? '') ?>" required>
                        <div id="emailError" class="error-text hidden"></div>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px; line-height: 1.4;">Please make
                            sure to use an active, real email address that you have access to. Your personalized QR code
                            will be sent here immediately, which you will need along with your face scan to clock in and
                            out.</p>
                    </div>

                    <div class="info-toggle-container">
                        <button type="button" class="info-toggle-btn" id="infoToggleBtn">
                            <i class="fas fa-question-circle" style="font-size: 16px;"></i> Why do we need to register?
                        </button>
                        <div class="info-toggle-content hidden" id="infoToggleContent">
                            TDT Powersteel uses a touchless biometric Kiosk to track daily time and attendance (DTR) for
                            Interns. To get started, you need to register your face profile and generate your personal QR
                            code.
                            When arriving at or leaving the office, you will scan this QR code and look at the kiosk camera
                            to
                            verify your identity and log your hours instantly.
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="startCaptureBtn">
                        Proceed to Camera <i class="fas fa-arrow-right" style="margin-left: 4px;"></i>
                    </button>
                </div>

                <!-- Camera section -->
                <div id="cameraSection" class="hidden">
                    <div class="camera-box" id="cameraBox">
                        <video id="webcam" autoplay playsinline></video>
                        <div class="scanning-ring" id="scanningRing"></div>
                        <div class="camera-overlay hidden"></div>
                        <!-- Camera Flash Overlay -->
                        <div id="cameraFlash" class="camera-flash"></div>
                        <!-- Dynamic SVG face alignment guide overlay -->
                        <svg class="svg-guide-overlay" viewBox="0 0 280 280">
                            <circle class="guide-circle" id="guideCircle" cx="140" cy="140" r="95" />
                        </svg>
                    </div>

                    <div id="faceWarningMessage"
                        style="text-align: center; color: var(--danger); font-size: 12px; min-height: 18px; font-weight: 600; margin-bottom: 8px;">
                    </div>

                    <div class="steps-bar">
                        <div class="step-dot" id="dot-0"></div>
                        <div class="step-dot" id="dot-1"></div>
                        <div class="step-dot" id="dot-2"></div>
                        <div class="step-dot" id="dot-3"></div>
                    </div>

                    <div class="capture-instructions" id="captureInstructions">
                        Click Start Capture to begin face registration
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <button type="button" class="btn btn-primary hidden" id="captureBtn">Capture Angle</button>
                        <button type="button" class="btn btn-secondary" id="cancelCameraBtn">Back</button>
                    </div>
                </div>

                <div id="submittingState" class="status-card hidden">
                    <div class="status-icon"><i class="fas fa-spinner fa-spin" style="color: var(--orange);"></i></div>
                    <div class="status-title">Analyzing Face Profiles</div>
                    <div class="status-text">Analyzing your captured photos and securing your Face ID profile. Please do not
                        close this window.</div>
                </div>
            </div>

            <!-- Success Screen -->
            <div id="successFlow" class="status-card hidden">
                <div class="animated-envelope">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div class="status-title">Check Your Email!</div>
                <div class="status-text" style="margin-bottom: 20px;">
                    We have sent your unique attendance QR code to your email. You can also download it directly below.
                </div>

                <div class="qr-display" style="margin: 0 auto 10px;">
                    <img id="qrCodeOutput" src="" alt="Intern QR Code">
                </div>
                <div class="code-string" id="qrCodeString" style="margin-bottom: 15px;"></div>

                <button type="button" class="btn btn-primary" id="downloadQRBtn">
                    <i class="fas fa-download"></i> Download QR Code
                </button>

                <div class="kiosk-tutorial">
                    <h3><i class="fas fa-desktop" style="color: var(--orange);"></i> How to Clock In/Out at the Kiosk:</h3>
                    <ol>
                        <li><strong>Present QR Code:</strong> Hold your downloaded QR code in front of the kiosk camera.
                        </li>
                        <li><strong>Face Verification:</strong> Stand steady and look directly at the kiosk screen for quick
                            face recognition.</li>
                        <li><strong>Confirmation:</strong> Your time log (Time In/Out) is saved automatically!</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Camera Error Help Modal -->
    <div id="cameraErrorModal" class="modal-overlay hidden">
        <div class="modal-card">
            <div class="modal-header">
                <i class="fas fa-video-slash modal-icon"></i>
                <h2>Camera Access Required</h2>
            </div>
            <div class="modal-body">
                <p class="modal-intro">We couldn't access your camera. This is usually due to browser permissions or
                    restriction settings.</p>

                <div class="help-section">
                    <h3><i class="fab fa-facebook-messenger"></i> Using Messenger/Viber?</h3>
                    <p>In-app browsers (like Facebook Messenger or Viber) block camera access. Tap the <strong>three
                            dots (...)</strong> or the <strong>Share</strong> icon in the top right, then select
                        <strong>"Open in Chrome"</strong> or <strong>"Open in Safari"</strong>.
                    </p>
                </div>

                <div class="help-section">
                    <h3><i class="fas fa-mobile-alt"></i> Enforce Phone Usage</h3>
                    <p>We highly recommend using a <strong>smartphone</strong> rather than a laptop. Mobile front-facing
                        cameras have significantly higher quality, better autofocus, and auto-exposure, leading to a
                        much faster and more accurate face scan.</p>
                </div>

                <div class="help-section">
                    <h3><i class="fas fa-user-shield"></i> Grant Camera Permission</h3>
                    <p>When prompted by your browser (Chrome/Safari), make sure to click <strong>"Allow"</strong> or
                        <strong>"Grant Permission"</strong> to enable the camera.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="closeErrorModalBtn">I Understand</button>
            </div>
        </div>
    </div>

    <canvas id="captureCanvas" class="hidden" width="224" height="224"></canvas>

    <script>
        <?php if ($intern): ?>
            let faceLandmarker = null;
            const overlay = document.getElementById('modelLoadingOverlay');

            async function initializeFaceDetector() {
                try {
                    const visionModule = await import("https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/vision_bundle.mjs");
                    const vision = await visionModule.FilesetResolver.forVisionTasks(
                        "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm"
                    );
                    faceLandmarker = await visionModule.FaceLandmarker.createFromOptions(vision, {
                        baseOptions: {
                            modelAssetPath: "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
                            delegate: "GPU"
                        },
                        outputFacialTransformationMatrixes: true,
                        runningMode: "VIDEO",
                        numFaces: 1
                    });
                    overlay.classList.add('fade-out');
                    console.log("MediaPipe Face Landmarker initialized successfully.");
                } catch (err) {
                    console.error("Failed to load MediaPipe. Falling back to manual capture mode:", err);
                    overlay.classList.add('fade-out');
                    alert("Face detection service is unavailable. Falling back to manual capture mode.");
                }
            }

            if (document.readyState === 'loading') {
                window.addEventListener('DOMContentLoaded', initializeFaceDetector);
            } else {
                initializeFaceDetector();
            }

            const emailSection = document.getElementById('emailSection');
            const cameraSection = document.getElementById('cameraSection');
            const startCaptureBtn = document.getElementById('startCaptureBtn');
            const cancelCameraBtn = document.getElementById('cancelCameraBtn');
            const captureBtn = document.getElementById('captureBtn');
            const internEmail = document.getElementById('internEmail');
            const webcam = document.getElementById('webcam');
            const cameraBox = document.getElementById('cameraBox');
            const scanningRing = document.getElementById('scanningRing');
            const captureInstructions = document.getElementById('captureInstructions');

            // Toggle why info
            const infoToggleBtn = document.getElementById('infoToggleBtn');
            const infoToggleContent = document.getElementById('infoToggleContent');
            if (infoToggleBtn && infoToggleContent) {
                infoToggleBtn.addEventListener('click', () => {
                    infoToggleContent.classList.toggle('hidden');
                });
            }

            // Clear email validation error on typing
            if (internEmail) {
                internEmail.addEventListener('input', () => {
                    const emailError = document.getElementById('emailError');
                    const emailFormGroup = document.getElementById('emailFormGroup');
                    if (emailError) {
                        emailError.innerText = '';
                        emailError.classList.add('hidden');
                    }
                    if (emailFormGroup) {
                        emailFormGroup.classList.remove('invalid', 'shake');
                    }
                });
            }

            // Close camera error modal
            const closeErrorModalBtn = document.getElementById('closeErrorModalBtn');
            const cameraErrorModal = document.getElementById('cameraErrorModal');
            if (closeErrorModalBtn && cameraErrorModal) {
                closeErrorModalBtn.addEventListener('click', () => {
                    cameraErrorModal.classList.add('hidden');
                });
            }
            const dots = [
                document.getElementById('dot-0'),
                document.getElementById('dot-1'),
                document.getElementById('dot-2'),
                document.getElementById('dot-3')
            ];
            const canvas = document.getElementById('captureCanvas');
            const ctx = canvas.getContext('2d');

            let lastVideoTime = -1;
            const faceWarningMessage = document.getElementById('faceWarningMessage');
            const guideCircle = document.getElementById('guideCircle');

            function runDetectionLoop() {
                if (!stream) return;

                let startTimeMs = performance.now();
                if (webcam.currentTime !== lastVideoTime) {
                    lastVideoTime = webcam.currentTime;

                    if (faceLandmarker) {
                        const results = faceLandmarker.detectForVideo(webcam, startTimeMs);
                        processLandmarkResults(results);
                    }
                }
                requestAnimationFrame(runDetectionLoop);
            }

            let initialFaceSize = null;
            let lastCaptureTime = 0;
            const CAPTURE_COOLDOWN_MS = 1500;
            let overrideTimer = null;
            let poseStableStartTime = null;
            const STABLE_DURATION_MS = 800;
            let faceIsPresent = false;

            function updateManualBtnState() {
                const manualBtn = document.getElementById('manualOverrideBtn');
                if (manualBtn) {
                    if (faceIsPresent) {
                        manualBtn.removeAttribute('disabled');
                        manualBtn.style.opacity = "1";
                        manualBtn.style.cursor = "pointer";
                    } else {
                        manualBtn.setAttribute('disabled', 'true');
                        manualBtn.style.opacity = "0.5";
                        manualBtn.style.cursor = "not-allowed";
                    }
                }
            }

            function processLandmarkResults(results) {
                faceWarningMessage.innerText = "";
                guideCircle.className.baseVal = "guide-circle";
                guideCircle.style.stroke = "";

                if (!results || !results.faceLandmarks || results.faceLandmarks.length === 0) {
                    faceWarningMessage.innerText = "No face detected. Align your face in the circle.";
                    guideCircle.className.baseVal = "guide-circle error-state";
                    faceIsPresent = false;
                    poseStableStartTime = null;
                    updateManualBtnState();
                    return;
                }

                if (results.faceLandmarks.length > 1) {
                    faceWarningMessage.innerText = "Multiple faces detected. Keep only one person in frame.";
                    guideCircle.className.baseVal = "guide-circle error-state";
                    faceIsPresent = false;
                    poseStableStartTime = null;
                    updateManualBtnState();
                    return;
                }

                faceIsPresent = true;
                updateManualBtnState();
                guideCircle.className.baseVal = "guide-circle aligned";

                if (results.facialTransformationMatrixes && results.facialTransformationMatrixes.length > 0) {
                    const matrix = results.facialTransformationMatrixes[0].data;

                    const yaw = Math.atan2(-matrix[8], matrix[10]) * (180 / Math.PI);
                    const pitch = Math.asin(Math.max(-1, Math.min(1, matrix[9]))) * (180 / Math.PI);

                    const landmarks = results.faceLandmarks[0];
                    if (landmarks && landmarks[263] && landmarks[33]) {
                        const width = Math.abs(landmarks[263].x - landmarks[33].x);
                        processCaptureAngles(yaw, pitch, width);
                    }
                } else {
                    poseStableStartTime = null;
                }
            }

            function playShutterSound() {
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const bufferSize = audioCtx.sampleRate * 0.15;
                    const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
                    const data = buffer.getChannelData(0);
                    for (let i = 0; i < bufferSize; i++) {
                        data[i] = Math.random() * 2 - 1;
                    }
                    const noise = audioCtx.createBufferSource();
                    noise.buffer = buffer;
                    const filter = audioCtx.createBiquadFilter();
                    filter.type = 'highpass';
                    filter.frequency.value = 1000;
                    const gainNode = audioCtx.createGain();
                    gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.12);
                    noise.connect(filter);
                    filter.connect(gainNode);
                    gainNode.connect(audioCtx.destination);

                    const osc = audioCtx.createOscillator();
                    const oscGain = audioCtx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(1500, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(400, audioCtx.currentTime + 0.08);
                    oscGain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    oscGain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
                    osc.connect(oscGain);
                    oscGain.connect(audioCtx.destination);

                    noise.start();
                    osc.start();
                    noise.stop(audioCtx.currentTime + 0.15);
                    osc.stop(audioCtx.currentTime + 0.15);
                    setTimeout(() => {
                        if (audioCtx.state !== 'closed') {
                            audioCtx.close();
                        }
                    }, 250);
                } catch (e) {
                    console.warn(e);
                }
            }

            function triggerScreenFlash() {
                const flash = document.getElementById('cameraFlash');
                if (flash) {
                    flash.classList.add('flash-active');
                    setTimeout(() => {
                        flash.classList.remove('flash-active');
                    }, 150);
                }
            }

            function startOverrideTimer() {
                clearOverrideTimer();
                overrideTimer = setTimeout(() => {
                    if (document.getElementById('manualOverrideBtn')) return;
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.id = 'manualOverrideBtn';
                    btn.className = 'btn btn-override';
                    btn.innerText = 'Capture Manually (Stuck)';
                    btn.addEventListener('click', captureAutoAngle);
                    if (cancelCameraBtn && cancelCameraBtn.parentNode) {
                        cancelCameraBtn.parentNode.insertBefore(btn, cancelCameraBtn);
                    }
                    updateManualBtnState();
                }, 10000);
            }

            function clearOverrideTimer() {
                if (overrideTimer) {
                    clearTimeout(overrideTimer);
                    overrideTimer = null;
                }
                const btn = document.getElementById('manualOverrideBtn');
                if (btn) {
                    btn.remove();
                }
            }

            function captureAutoAngle() {
                if (!faceIsPresent) {
                    faceWarningMessage.innerText = "Cannot capture: No face detected in frame. Align your face.";
                    return;
                }
                lastCaptureTime = Date.now();
                playShutterSound();
                triggerScreenFlash();

                // Capture first frontal frame (cropping center square to prevent distortion)
                const vWidth1 = webcam.videoWidth;
                const vHeight1 = webcam.videoHeight;
                const minDim1 = Math.min(vWidth1, vHeight1);
                const sx1 = (vWidth1 - minDim1) / 2;
                const sy1 = (vHeight1 - minDim1) / 2;
                ctx.drawImage(webcam, sx1, sy1, minDim1, minDim1, 0, 0, canvas.width, canvas.height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
                const base64Data = dataUrl.split(',')[1];
                capturedImages.push(base64Data);

                if (currentStep === 0) {
                    // Capture second frontal frame silently 200ms later
                    setTimeout(() => {
                        if (!stream) return;
                        // Crop center square for the second silent capture
                        const vWidth2 = webcam.videoWidth;
                        const vHeight2 = webcam.videoHeight;
                        const minDim2 = Math.min(vWidth2, vHeight2);
                        const sx2 = (vWidth2 - minDim2) / 2;
                        const sy2 = (vHeight2 - minDim2) / 2;
                        ctx.drawImage(webcam, sx2, sy2, minDim2, minDim2, 0, 0, canvas.width, canvas.height);
                        const dataUrl2 = canvas.toDataURL('image/jpeg', 0.95);
                        const base64Data2 = dataUrl2.split(',')[1];
                        capturedImages.push(base64Data2);

                        dots[currentStep].classList.remove('active');
                        dots[currentStep].classList.add('completed');
                        currentStep++;
                        updateStepUI();
                    }, 200);
                } else {
                    dots[currentStep].classList.remove('active');
                    dots[currentStep].classList.add('completed');
                    currentStep++;
                    if (currentStep < 4) {
                        updateStepUI();
                    } else {
                        submitFaceData();
                    }
                }
            }

            function processCaptureAngles(yaw, pitch, faceWidth) {
                if (Date.now() - lastCaptureTime < CAPTURE_COOLDOWN_MS) {
                    poseStableStartTime = null;
                    return;
                }
                let matched = false;
                if (currentStep === 0) {
                    if (Math.abs(yaw) <= 15 && Math.abs(pitch) <= 15) {
                        matched = true;
                    }
                } else if (currentStep === 1) {
                    if (yaw >= 15) {
                        matched = true;
                    }
                } else if (currentStep === 2) {
                    if (yaw <= -15) {
                        matched = true;
                    }
                } else if (currentStep === 3) {
                    if (pitch >= 12) {
                        matched = true;
                    }
                }

                if (matched) {
                    guideCircle.style.stroke = "var(--success)";
                    captureInstructions.innerHTML = steps[currentStep].title + " — <strong>Hold still...</strong>";

                    if (poseStableStartTime === null) {
                        poseStableStartTime = Date.now();
                    } else if (Date.now() - poseStableStartTime >= STABLE_DURATION_MS) {
                        poseStableStartTime = null;
                        captureAutoAngle();
                    }
                } else {
                    poseStableStartTime = null;
                }
            }

            let stream = null;
            let currentStep = 0;
            const capturedImages = [];

            // Tutorial Modal Controller
            const tutorialModal = document.getElementById('tutorialModal');
            const skipTutorialBtn = document.getElementById('skipTutorialBtn');
            const tutNextBtn = document.getElementById('tutNextBtn');
            const tutSlides = document.querySelectorAll('.tutorial-slide');
            const tutDots = document.querySelectorAll('.tut-dot');
            let currentTutSlide = 0;

            function showSlide(index) {
                tutSlides.forEach((slide, idx) => {
                    if (idx === index) {
                        slide.classList.add('active');
                    } else {
                        slide.classList.remove('active');
                    }
                });
                tutDots.forEach((dot, idx) => {
                    if (idx === index) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
                currentTutSlide = index;

                if (currentTutSlide === tutSlides.length - 1) {
                    tutNextBtn.innerHTML = 'I\'m Ready! <i class="fas fa-camera" style="margin-left: 6px;"></i>';
                } else {
                    tutNextBtn.innerHTML = 'Next <i class="fas fa-arrow-right" style="margin-left: 6px;"></i>';
                }
            }

            tutNextBtn.addEventListener('click', () => {
                if (currentTutSlide < tutSlides.length - 1) {
                    showSlide(currentTutSlide + 1);
                } else {
                    closeTutorialAndStartCamera();
                }
            });

            tutDots.forEach((dot, idx) => {
                dot.addEventListener('click', () => {
                    showSlide(idx);
                });
            });

            skipTutorialBtn.addEventListener('click', () => {
                closeTutorialAndStartCamera();
            });

            async function closeTutorialAndStartCamera() {
                tutorialModal.classList.remove('open');

                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',
                            width: { ideal: 640 },
                            height: { ideal: 480 }
                        },
                        audio: false
                    });
                    webcam.srcObject = stream;
                    webcam.onloadedmetadata = () => {
                        runDetectionLoop();
                    };

                    emailSection.classList.add('hidden');
                    cameraSection.classList.remove('hidden');
                    cameraBox.classList.add('active');
                    scanningRing.style.display = 'block';

                    currentStep = 0;
                    capturedImages.length = 0;
                    updateStepUI();
                } catch (err) {
                    console.error(err);
                    const modal = document.getElementById('cameraErrorModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                    }
                }
            }

            const steps = [
                { title: "Look Straight", desc: "Position your face in the center circle and look directly at the camera." },
                { title: "Turn Left", desc: "Slightly rotate your face horizontally to the left." },
                { title: "Turn Right", desc: "Slightly rotate your face horizontally to the right." },
                { title: "Tilt Up", desc: "Tilt your chin upwards slightly." }
            ];

            startCaptureBtn.addEventListener('click', async () => {
                const email = internEmail.value.trim();
                const emailError = document.getElementById('emailError');
                const emailFormGroup = document.getElementById('emailFormGroup');

                // Clear previous errors
                if (emailError) {
                    emailError.innerText = '';
                    emailError.classList.add('hidden');
                }
                if (emailFormGroup) {
                    emailFormGroup.classList.remove('invalid', 'shake');
                }

                if (!email) {
                    if (emailError && emailFormGroup) {
                        emailError.innerText = 'Email address is required.';
                        emailError.classList.remove('hidden');
                        emailFormGroup.classList.add('invalid');
                        void emailFormGroup.offsetWidth; // trigger reflow
                        emailFormGroup.classList.add('shake');
                    }
                    return;
                }

                if (!validateEmail(email)) {
                    if (emailError && emailFormGroup) {
                        emailError.innerText = 'Please enter a valid email address.';
                        emailError.classList.remove('hidden');
                        emailFormGroup.classList.add('invalid');
                        void emailFormGroup.offsetWidth; // trigger reflow
                        emailFormGroup.classList.add('shake');
                    }
                    return;
                }

                // Check email availability via AJAX
                try {
                    startCaptureBtn.disabled = true;
                    startCaptureBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i> Verifying...';

                    const checkData = new FormData();
                    checkData.append('action', 'check_email_availability');
                    checkData.append('email', email);
                    checkData.append('token', <?= json_encode($token) ?>);

                    const res = await fetch('register_face.php', {
                        method: 'POST',
                        body: checkData
                    });
                    const data = await res.json();

                    if (!data.success || !data.available) {
                        if (emailError && emailFormGroup) {
                            emailError.innerText = data.message || data.error || 'This email is already registered.';
                            emailError.classList.remove('hidden');
                            emailFormGroup.classList.add('invalid');
                            void emailFormGroup.offsetWidth;
                            emailFormGroup.classList.add('shake');
                        }
                        return;
                    }
                } catch (ajaxErr) {
                    console.error("Email verification failed:", ajaxErr);
                } finally {
                    startCaptureBtn.disabled = false;
                    startCaptureBtn.innerHTML = 'Next: Camera Capture <i class="fas fa-arrow-right" style="margin-left: 4px;"></i>';
                }

                tutorialModal.classList.add('open');
                showSlide(0);
            });

            cancelCameraBtn.addEventListener('click', stopCamera);

            captureBtn.addEventListener('click', captureAutoAngle);

            function updateStepUI() {
                dots.forEach((dot, idx) => {
                    if (idx === currentStep) {
                        dot.classList.add('active');
                    } else if (idx > currentStep) {
                        dot.classList.remove('active', 'completed');
                    }
                });

                captureInstructions.innerHTML = `<strong>Step ${currentStep + 1}: ${steps[currentStep].title}</strong><br><span style="font-size:12px; color:var(--text-muted)">${steps[currentStep].desc}</span>`;
                startOverrideTimer();
            }

            function stopCamera() {
                clearOverrideTimer();
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                webcam.srcObject = null;
                cameraSection.classList.add('hidden');
                emailSection.classList.remove('hidden');
                cameraBox.classList.remove('active');
                scanningRing.style.display = 'none';
            }

            function validateEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function submitFaceData() {
                stopCamera();

                document.getElementById('emailSection').classList.add('hidden');
                document.getElementById('cameraSection').classList.add('hidden');
                document.getElementById('submittingState').classList.remove('hidden');

                const formData = new FormData();
                formData.append('action', 'submit_registration');
                formData.append('token', <?= json_encode($token) ?>);
                formData.append('email', internEmail.value.trim());
                capturedImages.forEach((img, idx) => {
                    formData.append(`images[${idx}]`, img);
                });

                fetch('register_face.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('submittingState').classList.add('hidden');
                        if (data.success) {
                            showSuccess(data.qr_code);
                        } else {
                            alert('Error: ' + data.error);
                            // Reset back to email stage so they can retry
                            emailSection.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        document.getElementById('submittingState').classList.add('hidden');
                        alert('Server connection failed. Please try again.');
                        emailSection.classList.remove('hidden');
                    });
            }

            function showSuccess(qrCode) {
                document.getElementById('registrationFlow').classList.add('hidden');

                const qrOutput = document.getElementById('qrCodeOutput');
                const qrString = document.getElementById('qrCodeString');
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(qrCode);

                qrOutput.src = qrUrl;
                qrString.innerText = qrCode;

                document.getElementById('successFlow').classList.remove('hidden');

                // Setup download button
                document.getElementById('downloadQRBtn').onclick = () => {
                    // Fetch the QR image and trigger native download
                    fetch(qrUrl)
                        .then(res => res.blob())
                        .then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            a.download = `${qrCode}.png`;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                        })
                        .catch(() => {
                            // Fail-safe open in new tab
                            window.open(qrUrl, '_blank');
                        });
                };
            }
        <?php endif; ?>
    </script>
</body>

</html>