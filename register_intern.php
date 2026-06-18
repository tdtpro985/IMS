<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/audit.php';

$db = getDB();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$intern = null;
$expiredTitle = "Link Expired";
$expiredText = "This registration link is invalid or has expired. Registration links expire 24 hours after generation. Please contact HR to get a new link.";

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
    // Python service is expected to run on port 5001 (usually on the kiosk backend host)
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $pythonServiceUrl = 'http://127.0.0.1:5001/embed';

    $ch = curl_init($pythonServiceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['images' => $images]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // HIGH CONCURRENCY: Increase timeout to 60 seconds to allow queuing during peak registration
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'Face service offline. Please try again later.']);
        exit;
    }

    if ($httpCode !== 200) {
        $errorMsg = 'Failed to generate face embedding. Ensure your face is clear.';
        if ($response) {
            $result = json_decode($response, true);
            if (!empty($result['error'])) {
                $errorMsg = $result['error'];
            }
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    $result = json_decode($response, true);
    $embeddings = $result['embeddings'] ?? null; // Should be array of 5 arrays of 512 floats
    $embeddings_large = $result['embeddings_large'] ?? null;

    // Validate embeddings structure
    if (!is_array($embeddings) || count($embeddings) !== 5 || !is_array($embeddings_large) || count($embeddings_large) !== 5) {
        echo json_encode(['success' => false, 'error' => 'Failed to process all face angles. Please retry.']);
        exit;
    }

    foreach ($embeddings as $emb) {
        if (!is_array($emb) || count($emb) !== 512) {
            echo json_encode(['success' => false, 'error' => 'Invalid embedding shape returned from face service.']);
            exit;
        }
    }

    foreach ($embeddings_large as $emb) {
        if (!is_array($emb) || count($emb) !== 512) {
            echo json_encode(['success' => false, 'error' => 'Invalid large embedding shape returned from face service.']);
            exit;
        }
    }

    // Generate unique QR code payload based on ID + 4 random digits to prevent spoofing
    $qrCode = 'TDTINTRN' . $intern['id'] . '-' . str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $embeddingsJson = json_encode($embeddings);
    $embeddingsLargeJson = json_encode($embeddings_large);
    $now = date('Y-m-d H:i:s');

    // Update intern record and clear token
    $stmt = $db->prepare(
        "UPDATE interns 
         SET email = ?, face_embedding = ?, face_embedding_large = ?, qr_code = ?, face_registered_at = ?, 
             registration_token = NULL, token_expires_at = NULL 
         WHERE id = ?"
    );
    $stmt->bind_param('sssssi', $email, $embeddingsJson, $embeddingsLargeJson, $qrCode, $now, $intern['id']);
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
    <link rel="stylesheet" href="assets/css/register_intern.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php if ($intern): ?>
        <!-- Early loading screen overlay -->
        <div id="modelLoadingOverlay" class="model-loading-overlay">
            <div class="loader-spinner"></div>
            <div class="model-loading-text">Please wait</div>
        </div>

        <!-- Onboarding Tutorial Modal -->
        <div class="modal-overlay" id="tutorialModal">
            <div class="modal-card">



                <!-- Scrollable Content Body -->
                <div class="modal-body">
                    <!-- Slider Container -->
                    <div class="tutorial-slider">

                        <!-- Slide 1: Look Straight -->
                        <div class="tutorial-slide active" data-slide="0">
                            <div class="tutorial-step-tag">Step 1 of 4</div>
                            <h3 class="tutorial-title">Look Straight</h3>
                            <p class="tutorial-desc">Align your face in the center of the frame and look directly at the
                                camera.
                                <span class="tutorial-desc-warning">
                                    <i class="fas fa-glasses"></i> Keep glasses on if you normally wear them. Remove masks
                                    or hats.
                                </span>
                            </p>
                            <div class="phone-mockup">
                                <div class="phone-screen">
                                    <img src="assets/img/guide_straight<?= $genderSuffix ?>.png" alt="Look Straight">
                                </div>
                            </div>
                        </div>

                        <!-- Slide 2: Turn Left -->
                        <div class="tutorial-slide" data-slide="1">
                            <div class="tutorial-step-tag">Step 2 of 4</div>
                            <h3 class="tutorial-title">Turn Right</h3>
                            <p class="tutorial-desc">Rotate your head to the right side so the camera captures your
                                side
                                profile.
                                <span class="tutorial-desc-spacer">&nbsp;</span>
                            </p>
                            <div class="phone-mockup">
                                <div class="phone-screen">
                                    <img src="assets/img/guide_right<?= $genderSuffix ?>.png" alt="Turn Right">
                                </div>
                            </div>
                        </div>

                        <!-- Slide 3: Turn Right -->
                        <div class="tutorial-slide" data-slide="2">
                            <div class="tutorial-step-tag">Step 3 of 4</div>
                            <h3 class="tutorial-title">Turn Left</h3>
                            <p class="tutorial-desc">Rotate your head to the left side to capture your side
                                profile.
                                <span class="tutorial-desc-spacer">&nbsp;</span>
                            </p>
                            <div class="phone-mockup">
                                <div class="phone-screen">
                                    <img src="assets/img/guide_left<?= $genderSuffix ?>.png" alt="Turn Left">
                                </div>
                            </div>
                        </div>

                        <!-- Slide 4: Tilt Up -->
                        <div class="tutorial-slide" data-slide="3">
                            <div class="tutorial-step-tag">Step 4 of 4</div>
                            <h3 class="tutorial-title">Tilt Up</h3>
                            <p class="tutorial-desc">Tilt your chin and head upwards while facing forward.
                                <span class="tutorial-desc-spacer">&nbsp;</span>
                            </p>
                            <div class="phone-mockup">
                                <div class="phone-screen">
                                    <img src="assets/img/guide_up<?= $genderSuffix ?>.png" alt="Tilt Up">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Fixed Footer -->
                <div class="modal-footer">
                    <!-- Indicators -->
                    <div class="tutorial-dots" id="tutorialDots">
                        <div class="tut-dot active" data-index="0"></div>
                        <div class="tut-dot" data-index="1"></div>
                        <div class="tut-dot" data-index="2"></div>
                        <div class="tut-dot" data-index="3"></div>
                    </div>

                    <!-- Action button & Skip -->
                    <div class="modal-footer-actions">
                        <button type="button" class="btn btn-primary w-100" id="tutNextBtn">
                            Next <i class="fas fa-arrow-right ml-6"></i>
                        </button>
                        <button type="button" class="modal-skip-btn" id="skipTutorialBtn">Skip Tutorial</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="header">
            <img src="assets/img/tdt-logo.png" alt="TDT Powersteel Logo" class="header-logo">
            <?php if ($intern): ?>
                <div class="header-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="header-name"><?= htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']) ?></div>
                <p>Confirm your details and complete registration.</p>
            <?php endif; ?>
        </div>

        <?php if (!$intern): ?>
            <!-- Token Expired / Invalid Page -->
            <div class="status-card">
                <div class="status-icon danger"><i class="fas fa-times-circle"></i></div>
                <div class="status-title"><?= htmlspecialchars($expiredTitle) ?></div>
                <div class="status-text"><?= htmlspecialchars($expiredText) ?></div>
            </div>
        <?php else: ?>
            <!-- Main Form & Capture Section -->
            <div id="registrationFlow" class="content">

                <!-- Email confirmation -->
                <div id="emailSection">
                    <div class="form-group-highlight" id="emailFormGroup">
                        <label class="form-label">
                            <i class="fas fa-envelope-open-text text-orange mr-4"></i> Email
                            Address <span class="required-star">*</span>
                        </label>
                        <input type="email" id="internEmail" class="form-control"
                            placeholder="Enter your active email address"
                            value="<?= htmlspecialchars($intern['email'] ?? '') ?>" required>
                        <div id="emailError" class="error-text hidden"></div>
                        <p class="email-hint">Please make
                            sure to use an active, real email address that you have access to. Your personalized QR code
                            will be sent here immediately, which you will need along with your face scan to clock in and
                            out.</p>
                    </div>

                    <div class="info-toggle-container">
                        <button type="button" class="info-toggle-btn" id="infoToggleBtn">
                            <i class="fas fa-question-circle font-16"></i> Why do we need to register?
                        </button>
                        <div class="info-toggle-content" id="infoToggleContent">
                            TDT Powersteel uses a touchless biometric Kiosk to track daily time and attendance (DTR) for
                            Interns. To get started, you need to register your face profile and generate your personal QR
                            code.
                            When arriving at or leaving the office, you will scan this QR code and look at the kiosk camera
                            to
                            verify your identity and log your hours instantly.
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="startCaptureBtn">
                        Proceed <i class="fas fa-arrow-right ml-4"></i>
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
                            <circle class="guide-circle" id="guideCircle" cx="140" cy="140" r="75" />
                        </svg>
                    </div>

                    <div id="faceWarningMessage">
                    </div>

                    <div class="steps-bar">
                        <div class="step-dot" id="dot-0"></div>
                        <div class="step-dot" id="dot-1"></div>
                        <div class="step-dot" id="dot-2"></div>
                        <div class="step-dot" id="dot-3"></div>
                    </div>

                    <div class="capture-title" id="captureTitle">
                        Step 1: Look Straight
                    </div>

                    <div class="capture-instructions" id="captureInstructions">
                        Click Start Capture to begin face registration
                    </div>

                    <!-- Obstruction warning tip -->
                    <div class="camera-hint">
                        <i class="fas fa-glasses text-orange mr-4"></i> Wear daily glasses? Keep them on. Remove masks,
                        hats, or sunglasses.
                    </div>

                    <div class="camera-actions">
                        <button type="button" class="btn btn-secondary" id="startOverBtn">Start Over</button>
                    </div>
                </div>

                <div id="submittingState" class="status-card hidden">
                    <div class="status-icon"><i class="fas fa-spinner fa-spin text-orange"></i></div>
                    <div class="status-title">Processing Biometrics</div>
                    <div class="status-text">We are analyzing your face profiles and securing your identity. This may take
                        up to 30-60 seconds during peak times. <strong>Please do not close this window or refresh.</strong>
                    </div>
                </div>
            </div>

            <!-- Success Screen -->
            <div id="successFlow" class="status-card hidden">
                <div class="animated-envelope">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div class="status-title">Check Your Email!</div>
                <div class="status-text">
                    We have sent your unique attendance QR code to your email. You can also download it directly below.
                </div>

                <div class="qr-display">
                    <img id="qrCodeOutput" src="" alt="Intern QR Code">
                </div>

                <button type="button" class="btn btn-primary" id="downloadQRBtn">
                    <i class="fas fa-download"></i> Download QR Code
                </button>

                <div class="kiosk-tutorial">
                    <h3><i class="fas fa-desktop text-orange"></i> How to Clock In/Out at the Kiosk:</h3>
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

    <?php if ($intern): ?>
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

        <canvas id="captureCanvas" class="hidden" width="480" height="480"></canvas>
    <?php endif; ?>

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
                    infoToggleContent.classList.toggle('open');
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
                    } else {
                        manualBtn.setAttribute('disabled', 'true');
                    }
                }
            }

            function getFrameBrightness() {
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = 64;
                tempCanvas.height = 64;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.drawImage(webcam, 0, 0, 64, 64);
                const imageData = tempCtx.getImageData(0, 0, 64, 64);
                const data = imageData.data;
                let totalBrightness = 0;
                for (let i = 0; i < data.length; i += 4) {
                    totalBrightness += (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114);
                }
                return totalBrightness / (data.length / 4);
            }

            function processLandmarkResults(results) {
                faceWarningMessage.innerText = "";
                guideCircle.className.baseVal = "guide-circle";

                if (!results || !results.faceLandmarks || results.faceLandmarks.length === 0) {
                    faceWarningMessage.innerText = "No face detected. Keep face visible (remove masks, hats, or sunglasses). If you wear daily glasses, keep them on.";
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
                        const boundaryPoints = [
                            landmarks[10],   // forehead
                            landmarks[152],  // chin
                            landmarks[234],  // left cheek
                            landmarks[454]   // right cheek
                        ];

                        // Helper to convert normalized coordinate to SVG space
                        const toSvgCoords = (pt) => {
                            const rawSvgX = ((pt.x - 0.125) / 0.75) * 280;
                            const svgX = 280 - rawSvgX;
                            const svgY = pt.y * 280;
                            return { x: svgX, y: svgY };
                        };

                        const pForehead = toSvgCoords(landmarks[10]);
                        const pChin = toSvgCoords(landmarks[152]);
                        const pLeftCheek = toSvgCoords(landmarks[234]);
                        const pRightCheek = toSvgCoords(landmarks[454]);

                        // Compute center and dimensions
                        const centerX = (pLeftCheek.x + pRightCheek.x) / 2;
                        const centerY = (pForehead.y + pChin.y) / 2;
                        const faceW = Math.abs(pLeftCheek.x - pRightCheek.x);
                        const faceH = Math.abs(pForehead.y - pChin.y);
                        const faceDiameter = (faceW + faceH) / 2;

                        let hint = "";
                        if (faceDiameter < 70) {
                            hint = "Too Far. Move Closer.";
                        } else if (faceDiameter > 115) {
                            hint = "Too Close. Move Back.";
                        } else if (centerX < 115) {
                            hint = "Move Right";
                        } else if (centerX > 165) {
                            hint = "Move Left";
                        } else if (centerY < 115) {
                            hint = "Move Down";
                        } else if (centerY > 165) {
                            hint = "Move Up";
                        }

                        let faceInsideCircle = true;
                        for (const pt of boundaryPoints) {
                            if (!pt) continue;
                            const p = toSvgCoords(pt);
                            const dist = Math.sqrt(Math.pow(p.x - 140, 2) + Math.pow(p.y - 140, 2));
                            if (dist > 75) {
                                faceInsideCircle = false;
                                break;
                            }
                        }

                        if (hint) {
                            faceWarningMessage.innerText = hint;
                            guideCircle.className.baseVal = "guide-circle error-state";
                            poseStableStartTime = null;
                            return;
                        }

                        if (!faceInsideCircle) {
                            faceWarningMessage.innerText = "Align your face inside the circle.";
                            guideCircle.className.baseVal = "guide-circle error-state";
                            poseStableStartTime = null;
                            return;
                        }


                        const brightness = getFrameBrightness();
                        if (brightness < 50) {
                            faceWarningMessage.innerText = "Too dark. Move to a well-lit area.";
                            guideCircle.className.baseVal = "guide-circle error-state";
                            poseStableStartTime = null;
                            return;
                        }

                        processCaptureAngles(yaw, pitch);
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
                    btn.addEventListener('click', confirmManualCapture);
                    const container = document.querySelector('.camera-actions');
                    if (container) {
                        container.appendChild(btn);
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

            function confirmManualCapture() {
                if (!faceIsPresent) {
                    faceWarningMessage.innerText = "Cannot capture: No face detected in frame. Align your face.";
                    return;
                }

                Swal.fire({
                    title: 'Manual Capture Warning',
                    html: `Please rotate or tilt your face according to the active step before capturing:<br><br><strong class="swal-step-title">${steps[currentStep].title}</strong><br><span class="swal-step-desc">${steps[currentStep].desc}</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--orange)',
                    cancelButtonColor: '#8a8b8d',
                    confirmButtonText: 'Yes, Capture Angle',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        captureAutoAngle();
                    }
                });
            }

            function isImageBlurry(canvasElement) {
                const ctx = canvasElement.getContext('2d');
                const width = canvasElement.width;
                const height = canvasElement.height;
                const imageData = ctx.getImageData(0, 0, width, height);
                const data = imageData.data;

                // Convert to grayscale (luminosity method)
                const gray = new Uint8Array(width * height);
                for (let i = 0; i < data.length; i += 4) {
                    gray[i / 4] = Math.round(data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114);
                }

                // Compute Laplacian kernel: [[0, 1, 0], [1, -4, 1], [0, 1, 0]]
                const laplacian = new Float32Array(width * height);
                let mean = 0;
                let count = 0;

                for (let y = 1; y < height - 1; y++) {
                    for (let x = 1; x < width - 1; x++) {
                        const idx = y * width + x;
                        const val = gray[idx + 1] + gray[idx - 1] + gray[idx + width] + gray[idx - width] - 4 * gray[idx];
                        laplacian[idx] = val;
                        mean += val;
                        count++;
                    }
                }
                mean /= count;

                // Calculate variance of the Laplacian filter
                let variance = 0;
                for (let y = 1; y < height - 1; y++) {
                    for (let x = 1; x < width - 1; x++) {
                        const idx = y * width + x;
                        const diff = laplacian[idx] - mean;
                        variance += diff * diff;
                    }
                }
                variance /= count;

                console.log("[Blur Check] Laplacian Variance:", variance.toFixed(2));

                // A variance threshold of 50 effectively catches out-of-focus and motion-blurred faces
                return variance < 50;
            }

            function captureAutoAngle() {
                if (!faceIsPresent) {
                    faceWarningMessage.innerText = "Cannot capture: No face detected in frame. Align your face.";
                    return;
                }

                // Capture frame (cropping center square to prevent distortion)
                const vWidth1 = webcam.videoWidth;
                const vHeight1 = webcam.videoHeight;
                const minDim1 = Math.min(vWidth1, vHeight1);
                const sx1 = (vWidth1 - minDim1) / 2;
                const sy1 = (vHeight1 - minDim1) / 2;

                ctx.drawImage(webcam, sx1, sy1, minDim1, minDim1, 0, 0, canvas.width, canvas.height);

                // Reject blurry captures immediately to guarantee high-quality embeddings
                if (isImageBlurry(canvas)) {
                    console.warn("[Capture] Discarded blurry frame.");
                    faceWarningMessage.innerText = "Image was blurry. Please hold still and try again.";
                    guideCircle.className.baseVal = "guide-circle error-state";
                    poseStableStartTime = null;
                    return;
                }

                lastCaptureTime = Date.now();
                playShutterSound();
                triggerScreenFlash();

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

                        // Reject second capture if it's blurry
                        if (isImageBlurry(canvas)) {
                            console.warn("[Capture] Discarded blurry second frame. Retrying Step 1.");
                            capturedImages.pop(); // Remove first frontal frame
                            faceWarningMessage.innerText = "Second image was blurry. Please hold still and try again.";
                            guideCircle.className.baseVal = "guide-circle error-state";
                            poseStableStartTime = null;
                            return;
                        }

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

            function processCaptureAngles(yaw, pitch) {
                if (Date.now() - lastCaptureTime < CAPTURE_COOLDOWN_MS) {
                    poseStableStartTime = null;
                    return;
                }
                let matched = false;
                let correctionHint = "";

                if (currentStep === 0) {
                    if (Math.abs(yaw) <= 12 && Math.abs(pitch) <= 12) {
                        matched = true;
                    } else {
                        if (yaw > 12) {
                            correctionHint = "Turn face left slightly";
                        } else if (yaw < -12) {
                            correctionHint = "Turn face right slightly";
                        } else if (pitch > 12) {
                            correctionHint = "Look down slightly";
                        } else if (pitch < -12) {
                            correctionHint = "Look up slightly";
                        }
                    }
                } else if (currentStep === 1) {
                    if (yaw >= 25 && yaw <= 45 && Math.abs(pitch) <= 12) {
                        matched = true;
                    } else {
                        if (yaw < 0) {
                            correctionHint = "Turn your head to the right";
                        } else if (yaw >= 0 && yaw < 25) {
                            correctionHint = "Turn further to the right";
                        } else if (yaw > 45) {
                            correctionHint = "Turn back left slightly";
                        } else if (pitch > 12) {
                            correctionHint = "Look down to level your face";
                        } else if (pitch < -12) {
                            correctionHint = "Look up to level your face";
                        }
                    }
                } else if (currentStep === 2) {
                    if (yaw <= -25 && yaw >= -45 && Math.abs(pitch) <= 12) {
                        matched = true;
                    } else {
                        if (yaw > 0) {
                            correctionHint = "Turn your head to the left";
                        } else if (yaw <= 0 && yaw > -25) {
                            correctionHint = "Turn further to the left";
                        } else if (yaw < -45) {
                            correctionHint = "Turn back right slightly";
                        } else if (pitch > 12) {
                            correctionHint = "Look down to level your face";
                        } else if (pitch < -12) {
                            correctionHint = "Look up to level your face";
                        }
                    }
                } else if (currentStep === 3) {
                    if (pitch >= 22 && pitch <= 40 && Math.abs(yaw) <= 12) {
                        matched = true;
                    } else {
                        if (pitch < 0) {
                            correctionHint = "Tilt your chin up";
                        } else if (pitch >= 0 && pitch < 22) {
                            correctionHint = "Tilt further up";
                        } else if (pitch > 40) {
                            correctionHint = "Tilt back down slightly";
                        } else if (yaw > 12) {
                            correctionHint = "Look further right to center";
                        } else if (yaw < -12) {
                            correctionHint = "Look further left to center";
                        }
                    }
                }

                if (matched) {
                    guideCircle.className.baseVal = "guide-circle captured";
                    captureInstructions.innerHTML = "<strong style='color:#22C55E; font-size: 1.1rem;'>Good! Please hold still...</strong>";

                    if (poseStableStartTime === null) {
                        poseStableStartTime = Date.now();
                    } else if (Date.now() - poseStableStartTime >= STABLE_DURATION_MS) {
                        poseStableStartTime = null;
                        captureAutoAngle();
                    }
                } else {
                    poseStableStartTime = null;
                    if (correctionHint) {
                        captureInstructions.innerHTML = `<span class="text-xs text-orange" style="font-weight: 600;">${correctionHint}</span>`;
                    } else {
                        captureInstructions.innerHTML = `<span class="text-xs text-muted">${steps[currentStep].desc}</span>`;
                    }
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
            const sliderContainer = document.querySelector('.tutorial-slider');
            let currentTutSlide = 0;
            let isAutoScrolling = false;

            function updateActiveState(index) {
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
                    tutNextBtn.innerHTML = 'I\'m Ready! <i class="fas fa-camera ml-6"></i>';
                } else {
                    tutNextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ml-6"></i>';
                }
            }

            function showSlide(index, smooth = true) {
                if (!sliderContainer) return;
                isAutoScrolling = true;
                const slideWidth = sliderContainer.clientWidth;
                sliderContainer.scrollTo({
                    left: index * slideWidth,
                    behavior: smooth ? 'smooth' : 'auto'
                });
                updateActiveState(index);

                setTimeout(() => {
                    isAutoScrolling = false;
                }, smooth ? 350 : 50);
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

            if (sliderContainer) {
                sliderContainer.addEventListener('scroll', () => {
                    if (isAutoScrolling) return;
                    const scrollLeft = sliderContainer.scrollLeft;
                    const slideWidth = sliderContainer.clientWidth;
                    if (slideWidth > 0) {
                        const index = Math.round(scrollLeft / slideWidth);
                        if (index !== currentTutSlide && index >= 0 && index < tutSlides.length) {
                            updateActiveState(index);
                        }
                    }
                }, { passive: true });
            }

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
                    scanningRing.classList.add('active');

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
                { title: "Turn Right", desc: "Rotate your face horizontally to the right." },
                { title: "Turn Left", desc: "Rotate your face horizontally to the left." },
                { title: "Tilt Up", desc: "Tilt your chin upwards." }
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
                    startCaptureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-4"></i> Verifying...';

                    const checkData = new FormData();
                    checkData.append('action', 'check_email_availability');
                    checkData.append('email', email);
                    checkData.append('token', <?= json_encode($token) ?>);

                    const res = await fetch('register_intern.php', {
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
                    startCaptureBtn.innerHTML = 'Next: Camera Capture <i class="fas fa-arrow-right ml-4"></i>';
                }

                tutorialModal.classList.add('open');
                setTimeout(() => showSlide(0, false), 50);
            });

            const startOverBtn = document.getElementById('startOverBtn');
            if (startOverBtn) {
                startOverBtn.addEventListener('click', () => {
                    currentStep = 0;
                    capturedImages.length = 0;

                    // Reset step indicators
                    dots.forEach(dot => {
                        dot.className = 'step-dot';
                    });
                    dots[0].classList.add('active');

                    // Reset warnings and instructions
                    faceWarningMessage.innerText = "";
                    guideCircle.className.baseVal = "guide-circle";
                    poseStableStartTime = null;
                    lastCaptureTime = 0;

                    updateStepUI();
                    console.log("[Capture] Reset capture flow. Starting over from Step 1.");
                });
            }

            function updateStepUI() {
                dots.forEach((dot, idx) => {
                    if (idx === currentStep) {
                        dot.classList.add('active');
                    } else if (idx > currentStep) {
                        dot.classList.remove('active', 'completed');
                    }
                });

                const captureTitle = document.getElementById('captureTitle');
                if (captureTitle) {
                    captureTitle.innerHTML = `Step ${currentStep + 1}: ${steps[currentStep].title}`;
                }
                captureInstructions.innerHTML = `<span class="text-xs text-muted">${steps[currentStep].desc}</span>`;
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
                scanningRing.classList.remove('active');
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

                fetch('register_intern.php', {
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
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(qrCode);

                qrOutput.src = qrUrl;

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

    <?php if (!$intern): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    title: <?= json_encode($expiredTitle) ?>,
                    text: <?= json_encode($expiredText) ?>,
                    icon: 'error',
                    confirmButtonColor: 'var(--orange)',
                    confirmButtonText: 'Understood',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>