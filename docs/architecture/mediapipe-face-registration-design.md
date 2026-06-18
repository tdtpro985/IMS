# Specification: Seamless Face Registration via Google MediaPipe

* **Date:** 2026-06-08
* **Target File:** [register_face.php](file:///C:/Users/Keith/HRIS/INTERN-MANAGEMENT-SYSTEM/register_face.php)
* **Goal:** Replicate the seamless, automatic camera registration of the mobile `hris-app` in the web-based registration process.

---

## 1. Architecture & Library Loading

### A. Library CDN Inclusion
The script will load the following client-side packages from the public jsDelivr CDN in the `<head>` of the page:
```html
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm/vision_bundle.js" crossorigin="anonymous"></script>
```

### B. Initialization & Early Loader Overlay
1. As soon as the page is opened, a full-screen glassmorphic loading screen overlay appears showing:
   * *"Loading Face Recognition Engine..."* with a sleek spinner.
2. The JavaScript script calls the MediaPipe `FilesetResolver.forVisionTasks` and instantiates the `FaceLandmarker`.
3. If successful, the loading overlay is replaced by the standard Email Confirmation panel, and the camera is warmed up in the background.
4. If initialization fails (network/CDN timeout), the page automatically degrades to **Manual Capture Mode**, hiding the loading overlay and notifying the user.

---

## 2. Face Landmark Processing & Math

### A. Real-Time Detection Loop
* When the webcam stream is active, a `requestAnimationFrame` loop feeds each frame to the `FaceLandmarker` instance.
* We configure the detector with `outputFacialTransformationMatrixes: true` and `runningMode: "VIDEO"`.

### B. Head Pose Estimation (Degrees)
* From `facialTransformationMatrixes`, we extract the rotation data and convert it directly to Euler angles (in degrees):
  ```javascript
  const matrix = result.facialTransformationMatrixes[0].data;
  const yaw = Math.atan2(-matrix[8], matrix[10]) * (180 / Math.PI);
  const pitch = Math.asin(matrix[9]) * (180 / Math.PI);
  ```

### C. 5-Stage Guided Auto-Capture Thresholds
The capture sequence progresses automatically as thresholds matching the mobile app are met:

| Step | Instruction | Target Angle Threshold | Bounding Box Constraints |
|---|---|---|---|
| **1. Frontal** | Look Straight | Yaw: $-15^\circ \text{ to } +15^\circ$<br>Pitch: $-15^\circ \text{ to } +15^\circ$ | Area: $\ge 15\%$ of frame |
| **2. Frontal (Far)** | Look Straight (Far) | Yaw: $-15^\circ \text{ to } +15^\circ$<br>Pitch: $-15^\circ \text{ to } +15^\circ$ | Area: $\le 80\%$ of Step 1 area |
| **3. Turn Left** | Turn Left | Yaw: $> 15^\circ$ | Area: $\ge 15\%$ of frame |
| **4. Turn Right** | Turn Right | Yaw: $< -15^\circ$ | Area: $\ge 15\%$ of frame |
| **5. Tilt Up** | Tilt Chin Up | Pitch: $> 12^\circ$ | Area: $\ge 15\%$ of frame |

---

## 3. UI Overlay & Feedback

### A. Dynamic SVG Guide Overlay
* A circular SVG indicator centered on the video box displays alignment states:
  * **Neutral (White):** Scanning/No face.
  * **Aligned (Orange):** Face in frame, checking target angle.
  * **Captured (Green):** Perfect angle detected, snapping photo!
* Inside the circle, a dynamic flashing arrow instructs the user which direction to turn (Left, Right, Up, or Center).

### B. Capture Feedback
* **Flash Effect:** A quick fade transition of a white overlay on the camera box.
* **Audio Shutter Sound:** Synthesized client-side via the browser **Web Audio API** to keep it functional offline/CDN-independent without external assets:
  ```javascript
  const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  const osc = audioCtx.createOscillator();
  const gain = audioCtx.createGain();
  osc.connect(gain);
  gain.connect(audioCtx.destination);
  osc.frequency.setValueAtTime(800, audioCtx.currentTime);
  gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
  osc.start();
  osc.stop(audioCtx.currentTime + 0.08);
  ```

---

## 4. Robust Safety Guards & Fallbacks

1. **Webcam Blocking:** If browser webcam permissions are denied, show a clean, responsive modal explaining how to allow permissions.
2. **Face Lost Warn:** If the face leaves the camera circle during the process, show a warning: *"Face not detected. Please center your face in the circle."*
3. **Multi-face Guard:** If multiple faces are detected, display: *"Multiple faces detected. Please make sure only one person is in the frame."*
4. **Manual Capture Override:** If a user is stuck on a step for more than **10 seconds** (due to severe lighting issues, thick glasses, etc.), a button appears: *"Capture Manually"*, enabling them to click and proceed to the next step.
