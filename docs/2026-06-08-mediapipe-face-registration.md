# Google MediaPipe Face Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate Google MediaPipe Face Landmarker into the IMS web face registration page to support automated, seamless multi-angle capture matching the mobile hris-app.

**Architecture:** Load MediaPipe Task Vision via CDN, initialize it early with a full-screen loading screen overlay, calculate 3D head pose (yaw/pitch) in real-time, auto-capture frames on threshold match, and provide instant visual/audio feedback with robust manual fallbacks.

**Tech Stack:** PHP, HTML5, Vanilla CSS, Vanilla JavaScript, Google MediaPipe Face Landmarker (WebAssembly), Web Audio API.

---

## Decomposed Tasks

### Task 1: UI HTML & CSS Additions for Loader & SVG Guide

**Files:**
- Modify: `INTERN-MANAGEMENT-SYSTEM/register_face.php`

- [ ] **Step 1: Add CDN Script to Head**
  Add the jsDelivr CDN link inside the `<head>` block of `register_face.php` to fetch the MediaPipe Vision bundle.
  ```html
  <!-- MediaPipe Face Landmarker Library -->
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm/vision_bundle.js" crossorigin="anonymous"></script>
  ```

- [ ] **Step 2: Add Loader Overlay and Styling**
  Add the loading overlay container to the HTML body (above `.container`) and define CSS styling for the loading overlay, glassmorphism background, SVG guide circle, directional prompt cues, and white screen flash.
  
  *Add to CSS block in `<style>`:*
  ```css
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
  ```

  *Add HTML elements inside body:*
  ```html
  <!-- Early loading screen overlay -->
  <div id="modelLoadingOverlay" class="model-loading-overlay">
      <div class="loader-spinner"></div>
      <div style="font-weight: 600; font-size: 15px;">Initializing Face Camera...</div>
      <div style="font-size: 12px; color: var(--text-muted);">Downloading neural network model files (~6.6MB)</div>
  </div>
  ```
  
  *Add HTML element inside `#cameraSection` under `.camera-box`:*
  ```html
  <!-- Camera Flash Overlay -->
  <div id="cameraFlash" class="camera-flash"></div>
  
  <!-- Dynamic SVG face alignment guide overlay -->
  <svg class="svg-guide-overlay" viewBox="0 0 280 280">
      <circle class="guide-circle" id="guideCircle" cx="140" cy="140" r="95" />
  </svg>
  ```

- [ ] **Step 3: Verify Layout Rendering**
  Open the page in the browser (or run a local check) to confirm the full-screen loading screen appears correctly above the main card.

---

### Task 2: Initialize Google MediaPipe Face Landmarker

**Files:**
- Modify: `INTERN-MANAGEMENT-SYSTEM/register_face.php`

- [ ] **Step 1: Write MediaPipe Loader Logic**
  At the beginning of the script tag block in `register_face.php`, import/load the FaceLandmarker instance asynchronously.
  
  *Add to the script block:*
  ```javascript
  let faceLandmarker = null;
  const overlay = document.getElementById('modelLoadingOverlay');

  async function initializeFaceDetector() {
      try {
          const vision = await FilesetResolver.forVisionTasks(
              "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm"
          );
          faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
              baseOptions: {
                  modelAssetPath: "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
                  delegate: "GPU"
              },
              outputFacialTransformationMatrixes: true,
              runningMode: "VIDEO",
              numFaces: 1
          });
          
          // Hide loader overlay with fade out transition
          overlay.classList.add('fade-out');
          console.log("MediaPipe Face Landmarker initialized successfully.");
      } catch (err) {
          console.error("Failed to load MediaPipe. Falling back to manual capture mode:", err);
          overlay.classList.add('fade-out');
          alert("Face detection service is unavailable. Falling back to manual capture mode.");
      }
  }

  // Trigger loading immediately on page load
  window.addEventListener('DOMContentLoaded', initializeFaceDetector);
  ```

- [ ] **Step 2: Verify Successful Initialisation**
  Reload the page in a browser, verify the overlay fades out, and check the developer console for the output: `"MediaPipe Face Landmarker initialized successfully."`

---

### Task 3: Real-Time Detection Loop & Pose Calculation Math

**Files:**
- Modify: `INTERN-MANAGEMENT-SYSTEM/register_face.php`

- [ ] **Step 1: Implement Euler Angles Extraction & Face Checks**
  Write the animation frame processing loop to fetch camera frames, run detection, check if exactly one face is visible, and compute the Euler angles (yaw, pitch) in degrees. Add warning text elements to the UI if a face check fails.

  *Add inside `<div id="cameraSection">` above steps bar:*
  ```html
  <div id="faceWarningMessage" style="text-align: center; color: var(--danger); font-size: 12px; min-height: 18px; font-weight: 600; margin-bottom: 8px;"></div>
  ```

  *Add script variables and loop logic:*
  ```javascript
  let isCheckingFace = false;
  let lastVideoTime = -1;
  const faceWarningMessage = document.getElementById('faceWarningMessage');
  const guideCircle = document.getElementById('guideCircle');

  function runDetectionLoop() {
      if (!stream) return; // Stop if camera is stopped

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
      // Clear warning messages by default
      faceWarningMessage.innerText = "";
      guideCircle.className.baseVal = "guide-circle";

      if (!results.faceLandmarks || results.faceLandmarks.length === 0) {
          faceWarningMessage.innerText = "No face detected. Align your face in the circle.";
          guideCircle.className.baseVal = "guide-circle error-state";
          return;
      }

      if (results.faceLandmarks.length > 1) {
          faceWarningMessage.innerText = "Multiple faces detected. Keep only one person in frame.";
          guideCircle.className.baseVal = "guide-circle error-state";
          return;
      }

      // We have exactly 1 face. Make guide active
      guideCircle.className.baseVal = "guide-circle aligned";

      // Extract transformation matrix to get exact degrees
      if (results.facialTransformationMatrixes && results.facialTransformationMatrixes.length > 0) {
          const matrix = results.facialTransformationMatrixes[0].data;
          
          // Compute yaw, pitch, roll
          const yaw = Math.atan2(-matrix[8], matrix[10]) * (180 / Math.PI);
          const pitch = Math.asin(matrix[9]) * (180 / Math.PI);
          
          // Get bounding box size estimation
          const landmarks = results.faceLandmarks[0];
          const width = Math.abs(landmarks[263].x - landmarks[33].x); // Distance between eyes
          
          processCaptureAngles(yaw, pitch, width);
      }
  }
  ```

- [ ] **Step 2: Trigger Loop on Stream Start**
  Update the `startCaptureBtn` click listener to start the animation loop as soon as camera stream begins:
  ```javascript
  // Inside startCaptureBtn event listener:
  webcam.onloadedmetadata = () => {
      runDetectionLoop();
  };
  ```

- [ ] **Step 3: Verify Detection Loop Logs**
  Temporarily add `console.log("Yaw:", yaw, "Pitch:", pitch)` inside `processLandmarkResults`. Run the page in a browser, move your head left/right/up, and verify that the logs show corresponding changes in degree values.

---

### Task 4: Guided Auto-Capture Sequence & Web Audio Synthesizer

**Files:**
- Modify: `INTERN-MANAGEMENT-SYSTEM/register_face.php`

- [ ] **Step 1: Implement Web Audio Synth Shutter & Visual Flash**
  Write functions to trigger the synthetic click sound and flash effect.
  ```javascript
  function playShutterSound() {
      try {
          const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
          const osc = audioCtx.createOscillator();
          const gainNode = audioCtx.createGain();
          
          osc.connect(gainNode);
          gainNode.connect(audioCtx.destination);
          
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(600, audioCtx.currentTime);
          osc.frequency.exponentialRampToValueAtTime(100, audioCtx.currentTime + 0.08);
          
          gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
          gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
          
          osc.start();
          osc.stop(audioCtx.currentTime + 0.08);
      } catch (err) {
          console.error("Audio synth error:", err);
      }
  }

  function triggerScreenFlash() {
      const flash = document.getElementById('cameraFlash');
      flash.classList.add('flash-active');
      setTimeout(() => {
          flash.classList.remove('flash-active');
      }, 150);
  }
  ```

- [ ] **Step 2: Write Angle Checks & Multi-stage Auto-Capture**
  Implement the state machine matching the mobile thresholds to trigger snaps and advance steps automatically.
  ```javascript
  let initialFaceSize = null;
  let lastCaptureTime = 0;
  const CAPTURE_COOLDOWN_MS = 1500; // time to wait before next step to allow user to realign

  function processCaptureAngles(yaw, pitch, faceWidth) {
      // Prevent capturing during cooldown
      if (Date.now() - lastCaptureTime < CAPTURE_COOLDOWN_MS) return;

      const currentStepObj = steps[currentStep];
      let isMatch = false;

      switch(currentStep) {
          case 0: // Frontal
              if (Math.abs(yaw) <= 15 && Math.abs(pitch) <= 15) {
                  initialFaceSize = faceWidth;
                  isMatch = true;
              }
              break;
          case 1: // Frontal Far
              if (Math.abs(yaw) <= 15 && Math.abs(pitch) <= 15) {
                  // Face size must be smaller than baseline frontal (further away)
                  if (initialFaceSize && faceWidth <= initialFaceSize * 0.82) {
                      isMatch = true;
                  }
              }
              break;
          case 2: // Turn Left
              if (yaw >= 15) {
                  isMatch = true;
              }
              break;
          case 3: // Turn Right
              if (yaw <= -15) {
                  isMatch = true;
              }
              break;
          case 4: // Tilt Up
              if (pitch >= 12) {
                  isMatch = true;
              }
              break;
      }

      if (isMatch) {
          captureAutoAngle();
      }
  }

  function captureAutoAngle() {
      lastCaptureTime = Date.now();
      
      // Update UI state to green
      guideCircle.className.baseVal = "guide-circle captured";
      
      // Flash and play sound
      triggerScreenFlash();
      playShutterSound();

      // Capture frame
      ctx.drawImage(webcam, 0, 0, canvas.width, canvas.height);
      const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
      const base64Data = dataUrl.split(',')[1];
      capturedImages.push(base64Data);

      // Transition dots
      dots[currentStep].classList.remove('active');
      dots[currentStep].classList.add('completed');

      currentStep++;

      if (currentStep < 5) {
          updateStepUI();
      } else {
          submitFaceData();
      }
  }
  ```

- [ ] **Step 3: Modify UI Button to be Hidden and Add Override Button**
  Since it's automated, hide the manual capture button by default, but show an override button if the user stays on a step for more than 10 seconds.
  
  *Add inside `<div id="cameraSection">` script logic:*
  ```javascript
  let overrideTimer = null;
  const captureBtn = document.getElementById('captureBtn');
  
  // Hide the manual capture button by default
  captureBtn.classList.add('hidden');

  function startOverrideTimer() {
      clearTimeout(overrideTimer);
      
      // Remove any existing manual override button
      const existing = document.getElementById('manualOverrideBtn');
      if (existing) existing.remove();

      overrideTimer = setTimeout(() => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.id = 'manualOverrideBtn';
          btn.className = 'btn btn-override';
          btn.innerHTML = '<i class="fas fa-hand-pointer"></i> Capture Manually (Stuck)';
          btn.onclick = () => {
              btn.remove();
              captureAutoAngle();
          };
          // Insert above cancel button
          cancelCameraBtn.before(btn);
      }, 10000); // 10 seconds of inactivity
  }

  // Hook into updateStepUI to reset override timer on step changes
  const originalUpdateStepUI = updateStepUI;
  updateStepUI = function() {
      originalUpdateStepUI();
      startOverrideTimer();
  }
  ```

- [ ] **Step 4: Clean Up on stopCamera**
  Clear any running timers and loops when stopping the camera.
  ```javascript
  // Add to stopCamera() function:
  clearTimeout(overrideTimer);
  const existing = document.getElementById('manualOverrideBtn');
  if (existing) existing.remove();
  ```

---

### Task 5: Testing & Verification

- [ ] **Step 1: Test MediaPipe Auto-Capture Sequence**
  1. Open [register_face.php](file:///C:/Users/Keith/HRIS/INTERN-MANAGEMENT-SYSTEM/register_face.php).
  2. Confirm details and click **"Proceed to Camera"**.
  3. Align your face facing straight. The guide ring should turn orange, then green, flash, and beep.
  4. Move backward slightly. It should auto-capture Step 2.
  5. Rotate head left. It should auto-capture Step 3.
  6. Rotate head right. It should auto-capture Step 4.
  7. Tilt head up. It should auto-capture Step 5, shut off the camera, and submit to the backend.

- [ ] **Step 2: Test Manual Override Button**
  1. Reload the page and enter the camera view.
  2. Remain completely still or look off-camera for 10 seconds.
  3. Verify that the **"Capture Manually (Stuck)"** button appears.
  4. Click the button and check that it captures the frame, advances the step, and resets the 10-second timer.
