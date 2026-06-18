# Developer Guide: MediaPipe Face Registration

This document details the automated face registration system implemented in the Intern Management System.

---

## 1. Overview & Flow

The face registration process is fully automated on the client-side using **Google MediaPipe Face Landmarker**. It tracks the user's face landmarks and head angles in real-time, automatically snapping photos when correct angles are reached.

```
[Webcam Feed] ──> [MediaPipe Landmarker] ──> [Pose Calculation (Yaw/Pitch)]
                                                   │
                                                   ├──> [Auto-Capture on Match]
                                                   │
                                                   └──> [AJAX Submit to /register]
```

---

## 2. Technical Stack & Dependencies

* **Library:** MediaPipe Vision Tasks-Vision (`@mediapipe/tasks-vision` v0.10.8) loaded dynamically in the browser.
* **Wasm Runtime:** Loaded from jsDelivr CDN:
  * `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm`
* **Model File:** Google's pre-trained face landmarker model:
  * `https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task`

---

## 3. Pose Calculation Math

The head angles are computed in degrees from the MediaPipe 3D transformation matrix:

```javascript
const matrix = results.facialTransformationMatrixes[0].data;
const yaw = Math.atan2(-matrix[8], matrix[10]) * (180 / Math.PI);
const pitch = Math.asin(Math.max(-1, Math.min(1, matrix[9]))) * (180 / Math.PI);
```

### Auto-Capture Thresholds (Degrees)

| Step | target Angle | Criteria | Bounding Box Width |
| :--- | :--- | :--- | :--- |
| **0. Frontal** | Look Straight | Yaw: $\pm 15^\circ$, Pitch: $\pm 15^\circ$ | Baseline size |
| **1. Frontal (Far)** | Look Straight (Far) | Yaw: $\pm 15^\circ$, Pitch: $\pm 15^\circ$ | $\le 82\%$ of baseline size |
| **2. Turn Left** | Turn Left | Yaw: $\ge 15^\circ$ | N/A |
| **3. Turn Right** | Turn Right | Yaw: $\le -15^\circ$ | N/A |
| **4. Tilt Up** | Look Up | Pitch: $\ge 12^\circ$ | N/A |

---

## 4. User Feedback Mechanisms

### A. Dynamic SVG Guide Overlay
A centered guide circle changes color based on the face alignment state:
* **Dashed White:** No face detected.
* **Solid Orange:** Face detected, aligning.
* **Solid Green:** Perfect angle match, capturing frame.

### B. Synthesized Audio Shutter
A click sound is synthesized client-side on-the-fly using the **Web Audio API** to prevent loading external audio assets:
```javascript
const audioCtx = new AudioContext();
const osc = audioCtx.createOscillator();
const gain = audioCtx.createGain();
osc.type = 'triangle';
osc.frequency.setValueAtTime(600, audioCtx.currentTime);
osc.frequency.exponentialRampToValueAtTime(100, audioCtx.currentTime + 0.08);
gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
osc.connect(gain);
gain.connect(audioCtx.destination);
osc.start();
osc.stop(audioCtx.currentTime + 0.08);
```

### C. Visual Flash
A full-screen camera box overlay flashes white briefly (`opacity` animation) during capture.

---

## 5. Resiliency & Fallback Guards

1. **MediaPipe CDN Failure:** If the CDN model files fail to load within `initializeFaceDetector()`, the system alert displays a warning and automatically degrades to **Manual Click-to-Capture Mode** (re-enabling manual buttons).
2. **Face Tracking Warnings:**
   * *No face detected:* Shows a prompt to center the face and resets guide colors.
   * *Multiple faces detected:* Pauses detection and warns the user to ensure only one face is in the frame.
3. **Manual Capture Override:** If a user gets stuck on a specific capture step for more than **10 seconds** (due to poor lighting, glasses, etc.), a button appears: `"Capture Manually (Stuck)"` enabling them to bypass the auto-capture and proceed.

---

## 6. Backend Integration

The captures are sent to the local endpoint:
* **AJAX URL:** `register` (relative rewrite from `register_face.php`)
* **Payload:** 5 base64 JPEG strings inside `images` array.
* **Face Embeddings:** Backend forwards the base64 captures to the Python ONNX service (`http://localhost:5001/embed`), generating a `512-dimension` vector array saved as `face_embedding` inside the database.

---

## 7. Onboarding Tutorial & Guide Overlay

To prepare the user before using the camera scanner, the portal implements a smartphone-mockup styled **Onboarding Tutorial Modal** with the following details:
1. **Interactive Slideshow**: Uses CSS scroll snap mandatory (`scroll-snap-type: x mandatory`) to support native touch swiping and scroll-snapping on mobile and desktop devices.
2. **Dynamic Indicators**: A scroll event listener calculates the scroll offset dynamically, updating the active dot indicator and action buttons in real-time.
3. **Gender-Specific Illustrative Guides**: The system queries the database token to detect the intern's gender, loading female (`_f.png`) or male (`_m.png` / default) visual illustration guides for the 4 capture poses (Straight, Left, Right, Up).
4. **Optimized Spacers**: Slide cards utilize invisible vertical spacer elements to eliminate height-shifting jitter during transitions.
5. **Ergonomic Footer**: Dot indicators and navigation buttons are pinned inside a static footer at the bottom of the card, prioritizing thumb-zone reachability on mobile devices.

---

## 8. User Validation & Security Guards

1. **SweetAlert2 Manual Capture Guard**:
   When the user activates the manual capture override (triggered after a 10-second auto-scan delay), the process is intercepted by a SweetAlert2 confirmation modal. This modal displays the active step's required position and requests verification that the face is aligned before capturing the photo.
2. **Real-Time Email Uniqueness Check**:
   During initial details entry, entering the email triggers a real-time AJAX check. If the email is already registered to another active intern, a shake animation is triggered on the form, and a warning appears: *"This email is already registered."*
3. **Link Expiration Alert**:
   If an intern attempts to access the page with an expired or invalid token, the scanner wizard is hidden. The system triggers an immediate SweetAlert2 error popup informing them that the registration link is invalid.

---

## 9. UI Layout & Mirroring Feedback

1. **Natural Camera Preview Mirroring**:
   To prevent cognitive load, the active camera stream is horizontally mirrored (`transform: scaleX(-1)`) so it behaves like a standard bathroom mirror. The profile avatar images inside `interns.php` and `intern_workspace.php` also utilize horizontal mirroring for a natural preview, while the raw base64 data sent to the face embedding server remains unmirrored for mathematical accuracy.
2. **Smooth Info Accordions**:
   The "Why do we need to register?" info container features a CSS accordion animation, dynamically transition-animating `max-height`, `opacity`, and margins when toggled.
