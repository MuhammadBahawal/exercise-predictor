 // ====================================================================
// TRACKING CONTROLLER — Phase detection, posture-gated reps, mismatch
// Requires: exercise_engine.js loaded first
// ====================================================================

// ---- STATE ----
let selectedExercise = null;
let isTracking = false;
let repCount = 0;
let poseInstance = null;
let cameraInstance = null;
let frameCount = 0;
let fpsTime = performance.now();
let currentFps = 0;

// Phase & rep state
let repPhase = 'idle';          // 'idle' | 'down' | 'up'
let lastSmoothedPer = 50;
const SMOOTH_WINDOW = 6;
let perHistory = [];

// Alternating curl state
let altCurlStage = { left: 'idle', right: 'idle' };

// Jumping jacks state
let jjStage = 'closed';

// Mismatch tracking
let mismatchFrames = 0;
const MISMATCH_THRESHOLD = 12;   // require 12 consecutive bad frames
let isMismatch = false;
let mismatchReason = '';

// Posture-gated rep counting
let goodPostureFrames = 0;
const POSTURE_GATE_FRAMES = 3;   // require 3 good frames before counting
let postureGateOpen = true;

// Bad-posture streak for rep rejection
let badPostureStreak = 0;
const BAD_POSTURE_REP_BLOCK = 5; // if 5+ consecutive bad frames, block rep

// ---- BUILD EXERCISE LIST ----
const grid = document.getElementById('exercise-grid');
Object.entries(EXERCISES).forEach(([key, ex]) => {
    const btn = document.createElement('button');
    btn.className = 'btn-exercise';
    btn.dataset.key = key;
    btn.innerHTML = `<span class="ex-icon">${ex.icon}</span><span class="ex-name">${ex.name}</span>`;
    btn.addEventListener('click', () => selectExercise(key));
    grid.appendChild(btn);
});

function selectExercise(key) {
    if (isTracking) return;
    selectedExercise = key;
    const ex = EXERCISES[key];
    document.querySelectorAll('.btn-exercise').forEach(b => b.classList.remove('selected'));
    document.querySelector(`.btn-exercise[data-key="${key}"]`).classList.add('selected');
    document.getElementById('preview-placeholder').style.display = 'none';
    const card = document.getElementById('preview-card');
    card.classList.add('visible');
    document.getElementById('preview-title').textContent = `${ex.icon} ${ex.name}`;
    document.getElementById('preview-gif').src = ex.gif;
    document.getElementById('preview-desc').textContent = ex.desc;
}

// ---- START / STOP / RESET ----
document.getElementById('btn-start').addEventListener('click', startTracking);
document.getElementById('btn-stop').addEventListener('click', stopTracking);
document.getElementById('btn-stop-tracking').addEventListener('click', stopTracking);
document.getElementById('btn-reset').addEventListener('click', resetAll);

async function startTracking() {
    if (!selectedExercise) { alert('Please select an exercise first!'); return; }
    isTracking = true;
    repCount = 0;
    repPhase = 'idle';
    perHistory = [];
    altCurlStage = { left: 'idle', right: 'idle' };
    jjStage = 'closed';
    mismatchFrames = 0;
    isMismatch = false;
    mismatchReason = '';
    goodPostureFrames = 0;
    postureGateOpen = true;
    badPostureStreak = 0;

    // UI
    document.getElementById('exercise-panel').style.display = 'none';
    document.getElementById('tracking-section').classList.add('active');
    document.getElementById('stat-exercise').textContent = EXERCISES[selectedExercise].name;
    document.getElementById('stat-reps').textContent = '0';
    document.getElementById('progress-bar').style.width = '0%';
    document.getElementById('phase-indicator').textContent = '⏸ Ready';
    updatePostureFeedback(true, '');
    updateMismatchWarning(false, '');
    document.getElementById('camera-loading').style.display = 'block';

    const videoEl = document.getElementById('camera-video');
    poseInstance = new Pose({ locateFile: (f) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${f}` });
    poseInstance.setOptions({
        modelComplexity: 1, smoothLandmarks: true,
        enableSegmentation: false, smoothSegmentation: false,
        minDetectionConfidence: 0.5, minTrackingConfidence: 0.5
    });
    poseInstance.onResults(onPoseResults);
    cameraInstance = new Camera(videoEl, {
        onFrame: async () => { if (poseInstance && isTracking) await poseInstance.send({ image: videoEl }); },
        width: 1280, height: 720
    });
    try { await cameraInstance.start(); }
    catch (e) { alert('Camera access denied. Grant permission and retry.'); stopTracking(); }
}

function stopTracking() {
    isTracking = false;
    if (cameraInstance) { cameraInstance.stop(); cameraInstance = null; }
    if (poseInstance) { poseInstance.close(); poseInstance = null; }
    document.getElementById('tracking-section').classList.remove('active');
    document.getElementById('exercise-panel').style.display = 'grid';
}

function resetAll() {
    stopTracking();
    selectedExercise = null;
    repCount = 0;
    document.querySelectorAll('.btn-exercise').forEach(b => b.classList.remove('selected'));
    document.getElementById('preview-card').classList.remove('visible');
    document.getElementById('preview-placeholder').style.display = 'block';
}

// ---- POSE RESULTS ----
function onPoseResults(results) {
    document.getElementById('camera-loading').style.display = 'none';
    const canvasEl = document.getElementById('pose-canvas');
    const ctx = canvasEl.getContext('2d');
    const videoEl = document.getElementById('camera-video');
    canvasEl.width = videoEl.videoWidth || 1280;
    canvasEl.height = videoEl.videoHeight || 720;
    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

    // FPS
    frameCount++;
    const now = performance.now();
    if (now - fpsTime >= 1000) { currentFps = frameCount; frameCount = 0; fpsTime = now; document.getElementById('fps-display').textContent = `FPS: ${currentFps}`; }

    if (!results.poseLandmarks || !selectedExercise) return;
    const lm = results.poseLandmarks;
    const ex = EXERCISES[selectedExercise];

    // ---- 1. EXERCISE MISMATCH DETECTION ----
    const sig = computeMotionSignature(lm);
    const validator = EXERCISE_VALIDATORS[selectedExercise];
    const matchResult = validator ? validator(sig) : { matched: true, reason: '' };

    if (!matchResult.matched) {
        mismatchFrames++;
        if (mismatchFrames >= MISMATCH_THRESHOLD) { isMismatch = true; mismatchReason = matchResult.reason; }
    } else {
        mismatchFrames = Math.max(0, mismatchFrames - 2); // decay
        if (mismatchFrames === 0) { isMismatch = false; mismatchReason = ''; }
    }
    updateMismatchWarning(isMismatch, mismatchReason);

    // ---- 2. EXERCISE DETECTION ----
    const detection = ex.detect(lm);

    // ---- 3. SMOOTH PERCENTAGE ----
    perHistory.push(detection.percentage);
    if (perHistory.length > SMOOTH_WINDOW) perHistory.shift();
    const smoothPer = perHistory.reduce((a, b) => a + b, 0) / perHistory.length;
    lastSmoothedPer = smoothPer;

    // ---- 4. POSTURE GATE ----
    if (detection.goodPosture) {
        goodPostureFrames++;
        badPostureStreak = 0;
        if (goodPostureFrames >= POSTURE_GATE_FRAMES) postureGateOpen = true;
    } else {
        goodPostureFrames = 0;
        badPostureStreak++;
        if (badPostureStreak >= BAD_POSTURE_REP_BLOCK) postureGateOpen = false;
    }

    // ---- 5. PHASE DETECTION & REP COUNTING ----
    const canCountRep = postureGateOpen && !isMismatch;
    const downT = ex.downThreshold || 20;
    const upT = ex.upThreshold || 80;
    let currentPhaseLabel = '';

    if (ex.isJumpingJack) {
        // Jumping jack special logic
        if (ex._openScore >= 100 && jjStage === 'closed') { jjStage = 'open'; }
        if (ex._closedScore >= 100 && jjStage === 'open') {
            jjStage = 'closed';
            if (canCountRep) repCount++;
        }
        currentPhaseLabel = jjStage === 'open' ? ex.phaseLabels.up : ex.phaseLabels.down;
    } else if (ex.isAlternating) {
        // Alternating curls
        const lA = ex._lAngle, rA = ex._rAngle;
        if (lA > 150) altCurlStage.left = 'down';
        if (lA < 50 && altCurlStage.left === 'down') { altCurlStage.left = 'up'; if (canCountRep) repCount++; }
        if (rA > 150) altCurlStage.right = 'down';
        if (rA < 50 && altCurlStage.right === 'down') { altCurlStage.right = 'up'; if (canCountRep) repCount++; }
        currentPhaseLabel = (lA < rA) ? `Left ${ex.phaseLabels.up}` : `Right ${ex.phaseLabels.up}`;
    } else if (ex.invertPhase) {
        // Lateral raise: up=high percentage, counts on come-down
        if (smoothPer >= upT && repPhase !== 'up') { repPhase = 'up'; }
        if (smoothPer <= downT && repPhase === 'up') { repPhase = 'down'; if (canCountRep) repCount++; repPhase = 'idle'; }
        currentPhaseLabel = repPhase === 'up' ? ex.phaseLabels.up : (repPhase === 'down' ? ex.phaseLabels.down : ex.phaseLabels.hold);
    } else {
        // Standard phase machine: down→up = 1 rep (only if posture OK)
        if (smoothPer <= downT && repPhase !== 'down') { repPhase = 'down'; }
        if (smoothPer >= upT && repPhase === 'down') {
            repPhase = 'up';
            if (canCountRep) repCount++;
            repPhase = 'idle';
        }
        if (smoothPer <= downT) currentPhaseLabel = ex.phaseLabels.down;
        else if (smoothPer >= upT) currentPhaseLabel = ex.phaseLabels.up;
        else currentPhaseLabel = ex.phaseLabels.hold || '⏸ Mid-Range';
    }

    // ---- 6. UI UPDATES ----
    document.getElementById('stat-reps').textContent = repCount;
    document.getElementById('progress-bar').style.width = smoothPer.toFixed(0) + '%';
    document.getElementById('phase-indicator').textContent = currentPhaseLabel || '⏸ Ready';
    updatePostureFeedback(detection.goodPosture, detection.postureMsg);

    // ---- 7. DRAW SKELETON ----
    drawSkeleton(ctx, lm, detection, canvasEl.width, canvasEl.height, currentPhaseLabel);
}

// ---- UI HELPERS ----
function updatePostureFeedback(isGood, message) {
    const el = document.getElementById('posture-feedback');
    if (isGood) { el.className = 'posture-card posture-good'; el.textContent = '✅ Good Posture'; }
    else { el.className = 'posture-card posture-bad'; el.textContent = `⚠️ Fix Your Form${message ? ': ' + message : ''}`; }
}

function updateMismatchWarning(show, reason) {
    const el = document.getElementById('mismatch-warning');
    if (show) { el.style.display = 'block'; el.textContent = `🚫 ${reason || 'Incorrect Exercise Detected'}`; }
    else { el.style.display = 'none'; }
}

// ---- SKELETON DRAWING ----
const POSE_CONNECTIONS = [
    [11, 13], [13, 15], [12, 14], [14, 16], [11, 12], [11, 23], [12, 24],
    [23, 24], [23, 25], [25, 27], [24, 26], [26, 28], [27, 29], [29, 31], [28, 30], [30, 32], [15, 17], [15, 19], [16, 18], [16, 20]
];

function drawSkeleton(ctx, lm, detection, w, h, phaseLabel) {
    const badSet = new Set(detection.badJoints || []);

    // Connections
    ctx.lineWidth = 3;
    POSE_CONNECTIONS.forEach(([i, j]) => {
        const a = lm[i], b = lm[j];
        if (!a || !b || a.visibility < 0.3 || b.visibility < 0.3) return;
        const bad = badSet.has(i) || badSet.has(j);
        ctx.strokeStyle = bad ? 'rgba(231,76,60,0.9)' : (isMismatch ? 'rgba(241,196,15,0.7)' : 'rgba(26,188,156,0.8)');
        ctx.beginPath(); ctx.moveTo(a.x * w, a.y * h); ctx.lineTo(b.x * w, b.y * h); ctx.stroke();
    });

    // Landmarks
    lm.forEach((pt, idx) => {
        if (pt.visibility < 0.3) return;
        const bad = badSet.has(idx);
        const x = pt.x * w, y = pt.y * h;
        if (bad) { ctx.beginPath(); ctx.arc(x, y, 14, 0, Math.PI * 2); ctx.fillStyle = 'rgba(231,76,60,0.35)'; ctx.fill(); }
        ctx.beginPath(); ctx.arc(x, y, bad ? 7 : 5, 0, Math.PI * 2);
        ctx.fillStyle = bad ? '#e74c3c' : (isMismatch ? '#f1c40f' : '#1abc9c');
        ctx.fill(); ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
    });

    // Angle text
    if (detection.angle !== undefined) {
        const pjMap = {
            'squats': 25, 'push-up': 13, 'dumbbell-curls': 13, 'alt-dumbbell-curls': 13, 'lunges': 25,
            'shoulder-press': 13, 'jumping-jacks': 11, 'lateral-rise': 11, 'barbell-row': 13, 'tricep-dips': 13
        };
        const jIdx = pjMap[selectedExercise] || 13;
        const jt = lm[jIdx];
        if (jt && jt.visibility > 0.3) {
            ctx.font = 'bold 16px Roboto,sans-serif'; ctx.fillStyle = '#fff'; ctx.strokeStyle = 'rgba(0,0,0,0.7)'; ctx.lineWidth = 3;
            const t = `${detection.angle.toFixed(0)}°`; ctx.strokeText(t, jt.x * w + 15, jt.y * h - 10); ctx.fillText(t, jt.x * w + 15, jt.y * h - 10);
        }
    }

    // HUD overlay on canvas
    ctx.textAlign = 'left';
    // Rep count
    ctx.font = 'bold 28px Roboto,sans-serif'; ctx.fillStyle = '#1abc9c'; ctx.strokeStyle = 'rgba(0,0,0,0.8)'; ctx.lineWidth = 4;
    ctx.strokeText(`Reps: ${repCount}`, 20, 40); ctx.fillText(`Reps: ${repCount}`, 20, 40);
    // Exercise name
    ctx.font = 'bold 20px Roboto,sans-serif'; ctx.fillStyle = '#ecf0f1';
    ctx.strokeText(EXERCISES[selectedExercise]?.name || '', 20, 70); ctx.fillText(EXERCISES[selectedExercise]?.name || '', 20, 70);
    // Phase
    ctx.font = 'bold 16px Roboto,sans-serif'; ctx.fillStyle = '#3498db';
    ctx.strokeText(phaseLabel || '', 20, 95); ctx.fillText(phaseLabel || '', 20, 95);
    // Posture
    const pText = detection.goodPosture ? '✅ Good Posture' : '⚠️ Fix Your Form';
    ctx.font = 'bold 16px Roboto,sans-serif'; ctx.fillStyle = detection.goodPosture ? '#2ecc71' : '#e74c3c';
    ctx.strokeText(pText, 20, 120); ctx.fillText(pText, 20, 120);
    // Mismatch warning on canvas
    if (isMismatch) {
        ctx.font = 'bold 22px Roboto,sans-serif'; ctx.fillStyle = '#e74c3c'; ctx.strokeStyle = 'rgba(0,0,0,0.9)'; ctx.lineWidth = 4;
        const wt = `🚫 ${mismatchReason || 'Wrong Exercise!'}`;
        const tw = ctx.measureText(wt).width;
        ctx.strokeText(wt, (w - tw) / 2, h - 30); ctx.fillText(wt, (w - tw) / 2, h - 30);
        // Flash border
        ctx.strokeStyle = 'rgba(231,76,60,0.7)'; ctx.lineWidth = 6;
        ctx.strokeRect(3, 3, w - 6, h - 6);
    }

    // Progress bar (right)
    const barX = w - 50, barTop = 80, barBot = h - 80, barH = barBot - barTop;
    const fill = (lastSmoothedPer / 100) * barH;
    ctx.fillStyle = 'rgba(0,0,0,0.4)'; ctx.fillRect(barX, barTop, 30, barH);
    ctx.fillStyle = isMismatch ? '#f1c40f' : (detection.goodPosture ? '#1abc9c' : '#e74c3c');
    ctx.fillRect(barX, barBot - fill, 30, fill);
    ctx.strokeStyle = '#fff'; ctx.lineWidth = 1; ctx.strokeRect(barX, barTop, 30, barH);
    ctx.font = '14px Roboto,sans-serif'; ctx.fillStyle = '#fff'; ctx.textAlign = 'center';
    ctx.fillText(`${lastSmoothedPer.toFixed(0)}%`, barX + 15, barTop - 8);
    ctx.textAlign = 'left';
}
