<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/audit.php';

$db = getDB();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$intern = null;

if ($token) {
    // Validate token exists and has not expired (72hr TTL)
    $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM interns WHERE registration_token = ? AND token_expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $intern = $stmt->get_result()->fetch_assoc();
    $stmt->close();
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

    // Decode and save the first capture as the profile photo
    $profilePhotoName = null;
    if (isset($images[0])) {
        $imgData = base64_decode($images[0]);
        if ($imgData !== false) {
            $uploadDir = __DIR__ . '/uploads/photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $profilePhotoName = $intern['id'] . '_' . time() . '.jpg';
            
            // Delete old photo if it exists
            $oldRes = $db->query("SELECT profile_photo FROM interns WHERE id = " . $intern['id']);
            if ($oldRes && $oldRow = $oldRes->fetch_assoc()) {
                $oldPhoto = $oldRow['profile_photo'];
                if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                    @unlink($uploadDir . $oldPhoto);
                }
            }
            
            file_put_contents($uploadDir . $profilePhotoName, $imgData);
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
             profile_photo = COALESCE(?, profile_photo),
             registration_token = NULL, token_expires_at = NULL 
         WHERE id = ?"
    );
    $stmt->bind_param('sssssi', $email, $embeddingsJson, $qrCode, $now, $profilePhotoName, $intern['id']);
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
            --orange:       #FF6B1A;
            --orange-dark:  #E8521A;
            --orange-light: #FFF0E8;
            --white:        #FFFFFF;
            --gray-light:   #F4F5F7;
            --gray-border:  #E2E4E8;
            --text-main:    #1A1A2E;
            --text-muted:   #6B7280;
            --success:      #22C55E;
            --danger:       #EF4444;
            --radius:       16px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
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
            box-shadow: 0 8px 32px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #111214;
            padding: 24px 20px;
            text-align: center;
            border-bottom: 3px solid var(--orange);
        }

        .header h1 {
            color: var(--white);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header p {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 4px;
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .camera-box.active {
            border-color: var(--orange);
            box-shadow: 0 0 16px rgba(255, 107, 26, 0.4);
        }

        #webcam {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* mirror effect */
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .status-icon.danger { color: var(--danger); }
        .status-icon.success { color: var(--success); }

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
            0% { opacity: 1; }
            100% { opacity: 0; }
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
            stroke: rgba(255,255,255,0.4);
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

        .hidden { display: none !important; }
    </style>
</head>
<body>

<!-- Early loading screen overlay -->
<div id="modelLoadingOverlay" class="model-loading-overlay">
    <div class="loader-spinner"></div>
    <div style="font-weight: 600; font-size: 15px;">Initializing Face Camera...</div>
    <div style="font-size: 12px; color: rgba(255, 255, 255, 0.65);">Downloading neural network model files (~6.6MB)</div>
</div>

<div class="container">
    <div class="header">
        <h1>TDT Powersteel</h1>
        <p>Intern Face Registration</p>
    </div>

    <?php if (!$intern): ?>
        <!-- Token Expired / Invalid Page -->
        <div class="status-card">
            <div class="status-icon danger"><i class="fas fa-times-circle"></i></div>
            <div class="status-title">Link Expired or Invalid</div>
            <div class="status-text">This registration link is invalid or has expired. Face registration links expire 72 hours after generation. Please contact HR to get a new link.</div>
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
            <div class="form-group" id="emailSection">
                <label class="form-label">Email Address</label>
                <input type="email" id="internEmail" class="form-control" placeholder="Enter your email" value="<?= htmlspecialchars($intern['email'] ?? '') ?>" required>
                <button type="button" class="btn btn-primary" style="margin-top: 10px;" id="startCaptureBtn">Proceed to Camera</button>
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

                <div id="faceWarningMessage" style="text-align: center; color: var(--danger); font-size: 12px; min-height: 18px; font-weight: 600; margin-bottom: 8px;"></div>

                <div class="steps-bar">
                    <div class="step-dot" id="dot-0"></div>
                    <div class="step-dot" id="dot-1"></div>
                    <div class="step-dot" id="dot-2"></div>
                    <div class="step-dot" id="dot-3"></div>
                    <div class="step-dot" id="dot-4"></div>
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
                <div class="status-title">Processing Face Data</div>
                <div class="status-text">Uploading images to ONNX model server and generating embeddings. Please wait...</div>
            </div>
        </div>

        <!-- Success Screen -->
        <div id="successFlow" class="status-card hidden">
            <div class="status-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="status-title">Registration Complete!</div>
            <div class="status-text">Your face has been successfully registered. The QR code below has been emailed to you. Use it to clock in/out at the kiosk.</div>
            <div class="qr-display">
                <img id="qrCodeOutput" src="" alt="Intern QR Code">
            </div>
            <div class="code-string" id="qrCodeString"></div>
            <button type="button" class="btn btn-secondary" style="margin-top: 15px;" id="downloadQRBtn"><i class="fas fa-download"></i> Download QR Code</button>
        </div>
    <?php endif; ?>
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
const dots = [
    document.getElementById('dot-0'),
    document.getElementById('dot-1'),
    document.getElementById('dot-2'),
    document.getElementById('dot-3'),
    document.getElementById('dot-4')
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

function processLandmarkResults(results) {
    faceWarningMessage.innerText = "";
    guideCircle.className.baseVal = "guide-circle";

    if (!results || !results.faceLandmarks || results.faceLandmarks.length === 0) {
        faceWarningMessage.innerText = "No face detected. Align your face in the circle.";
        guideCircle.className.baseVal = "guide-circle error-state";
        return;
    }

    if (results.faceLandmarks.length > 1) {
        faceWarningMessage.innerText = "Multiple faces detected. Keep only one person in frame.";
        guideCircle.className.baseVal = "guide-circle error-state";
        return;
    }

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
    }
}

let initialFaceSize = null;
let lastCaptureTime = 0;
const CAPTURE_COOLDOWN_MS = 1500;
let overrideTimer = null;

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
    lastCaptureTime = Date.now();
    playShutterSound();
    triggerScreenFlash();
    ctx.drawImage(webcam, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
    const base64Data = dataUrl.split(',')[1];
    capturedImages.push(base64Data);
    dots[currentStep].classList.remove('active');
    dots[currentStep].classList.add('completed');
    currentStep++;
    if (currentStep < 5) {
        updateStepUI();
    } else {
        submitFaceData();
    }
}

function processCaptureAngles(yaw, pitch, faceWidth) {
    if (Date.now() - lastCaptureTime < CAPTURE_COOLDOWN_MS) return;
    let matched = false;
    if (currentStep === 0) {
        if (Math.abs(yaw) <= 15 && Math.abs(pitch) <= 15) {
            initialFaceSize = faceWidth;
            matched = true;
        }
    } else if (currentStep === 1) {
        if (Math.abs(yaw) <= 15 && Math.abs(pitch) <= 15) {
            if (initialFaceSize !== null && faceWidth <= initialFaceSize * 0.82) {
                matched = true;
            }
        }
    } else if (currentStep === 2) {
        if (yaw >= 15) {
            matched = true;
        }
    } else if (currentStep === 3) {
        if (yaw <= -15) {
            matched = true;
        }
    } else if (currentStep === 4) {
        if (pitch >= 12) {
            matched = true;
        }
    }
    if (matched) {
        captureAutoAngle();
    }
}

let stream = null;
let currentStep = 0;
const capturedImages = [];

const steps = [
    { title: "Look Straight", desc: "Position your face in the center circle and look directly at the camera." },
    { title: "Look Straight (Far)", desc: "Maintain your gaze, but pull your head back slightly." },
    { title: "Turn Left", desc: "Slightly rotate your face horizontally to the left." },
    { title: "Turn Right", desc: "Slightly rotate your face horizontally to the right." },
    { title: "Tilt Up", desc: "Tilt your chin upwards slightly." }
];

startCaptureBtn.addEventListener('click', async () => {
    const email = internEmail.value.trim();
    if (!email || !validateEmail(email)) {
        alert('Please enter a valid email address.');
        return;
    }

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
        alert('Webcam access is required. Please check permissions and ensure you are using HTTPS.');
        console.error(err);
    }
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

    fetch('register', {
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
