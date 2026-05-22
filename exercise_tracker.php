<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Exercise Tracker - Stride Sync</title>

    <!-- MediaPipe Pose JS -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: rgba(15, 15, 30, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(26, 188, 156, 0.2);
            padding: 12px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff !important;
        }

        .navbar-brand img {
            max-width: 42px;
            border-radius: 8px;
        }

        .navbar-brand span {
            color: #1abc9c;
        }

        /* Title */
        .page-header {
            text-align: center;
            padding: 30px 20px 10px;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1abc9c, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 2px;
        }

        .page-header p {
            color: #8e9aaf;
            font-size: 1.05rem;
            margin-top: 8px;
        }

        /* Main Layout */
        .tracker-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Exercise Selection Panel */
        .exercise-panel {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .exercise-list-section {}

        .exercise-preview-section {}

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1abc9c;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Exercise Buttons */
        .exercise-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-exercise {
            width: 100%;
            padding: 14px 18px;
            background: rgba(28, 40, 51, 0.8);
            color: #ecf0f1;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid rgba(26, 188, 156, 0.15);
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
        }

        .btn-exercise:hover {
            background: rgba(22, 160, 133, 0.25);
            border-color: rgba(26, 188, 156, 0.5);
            transform: translateX(5px);
        }

        .btn-exercise.selected {
            background: linear-gradient(135deg, rgba(22, 160, 133, 0.4), rgba(26, 188, 156, 0.2));
            border-color: #1abc9c;
            box-shadow: 0 0 20px rgba(26, 188, 156, 0.2);
        }

        .btn-exercise .ex-icon {
            font-size: 1.4rem;
        }

        .btn-exercise .ex-name {
            flex: 1;
            text-align: left;
        }

        /* Preview Card */
        .preview-card {
            background: rgba(28, 40, 51, 0.6);
            border: 1px solid rgba(26, 188, 156, 0.2);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            display: none;
        }

        .preview-card.visible {
            display: block;
        }

        .preview-card h3 {
            color: #1abc9c;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        .preview-card img {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid rgba(26, 188, 156, 0.3);
            margin-bottom: 15px;
        }

        .preview-card .desc {
            color: #8e9aaf;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .preview-placeholder {
            background: rgba(28, 40, 51, 0.6);
            border: 2px dashed rgba(26, 188, 156, 0.2);
            border-radius: 16px;
            padding: 60px 25px;
            text-align: center;
            color: #5d6d7e;
            font-size: 1.1rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 12px 35px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn-action:hover {
            transform: scale(1.05);
        }

        .btn-start-ex {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: #fff;
        }

        .btn-stop-ex {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            display: none;
        }

        .btn-reset-ex {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff;
        }

        /* ============ TRACKING SECTION ============ */
        .tracking-section {
            display: none;
            margin-top: 20px;
        }

        .tracking-section.active {
            display: block;
        }

        .tracking-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
            align-items: start;
        }

        /* Camera / Canvas */
        .camera-container {
            position: relative;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid rgba(26, 188, 156, 0.3);
            aspect-ratio: 16/10;
        }

        .camera-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .camera-container canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .camera-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1abc9c;
            font-size: 1.2rem;
            text-align: center;
        }

        .camera-loading .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(26, 188, 156, 0.3);
            border-top: 4px solid #1abc9c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Stats Panel */
        .stats-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .stat-card {
            background: rgba(28, 40, 51, 0.8);
            border: 1px solid rgba(26, 188, 156, 0.2);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #8e9aaf;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .stat-card .stat-value {
            font-size: 2.8rem;
            font-weight: 800;
            color: #1abc9c;
            font-family: 'Courier New', monospace;
        }

        .stat-card.exercise-name-card .stat-value {
            font-size: 1.4rem;
            font-family: 'Roboto', sans-serif;
        }

        /* Posture Feedback */
        .posture-card {
            padding: 18px;
            border-radius: 14px;
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .posture-good {
            background: rgba(39, 174, 96, 0.2);
            border: 2px solid #27ae60;
            color: #2ecc71;
        }

        .posture-bad {
            background: rgba(231, 76, 60, 0.2);
            border: 2px solid #e74c3c;
            color: #e74c3c;
            animation: pulse-bad 1s ease-in-out infinite alternate;
        }

        @keyframes pulse-bad {
            from {
                box-shadow: 0 0 5px rgba(231, 76, 60, 0.3);
            }

            to {
                box-shadow: 0 0 20px rgba(231, 76, 60, 0.6);
            }
        }

        /* Progress Bar */
        .progress-container {
            background: rgba(28, 40, 51, 0.8);
            border: 1px solid rgba(26, 188, 156, 0.2);
            border-radius: 14px;
            padding: 18px;
        }

        .progress-bar-wrapper {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #1abc9c, #2ecc71);
            border-radius: 10px;
            transition: width 0.3s ease;
            width: 0%;
        }

        /* FPS */
        .fps-display {
            font-size: 0.8rem;
            color: #5d6d7e;
            text-align: center;
        }

        /* Back to Home */
        .btn-home {
            display: block;
            max-width: 220px;
            margin: 30px auto;
            background: linear-gradient(135deg, #16a085, #1abc9c);
            color: #fff;
            padding: 12px 30px;
            border-radius: 12px;
            text-align: center;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-home:hover {
            transform: scale(1.05);
            color: #fff;
            box-shadow: 0 5px 20px rgba(26, 188, 156, 0.3);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .exercise-panel {
                grid-template-columns: 1fr;
            }

            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .stats-panel {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.6rem;
            }

            .stats-panel {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-action {
                text-align: center;
            }

            .exercise-grid {
                gap: 8px;
            }

            .btn-exercise {
                padding: 12px 14px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="HOME.php">
                <img src="images/icon.jpg" alt="Logo">
                <span>Stride Sync</span>
            </a>
        </div>
    </nav>

    <!-- Header -->
    <div class="page-header">
        <h1>ðŸ‹ï¸ LIVE EXERCISE TRACKER</h1>
        <p>Select an exercise, start the camera, and let AI track your reps & posture in real-time!</p>
    </div>

    <div class="tracker-container">

        <!-- Exercise Selection Panel -->
        <div class="exercise-panel" id="exercise-panel">
            <div class="exercise-list-section">
                <div class="section-title">Choose Exercise</div>
                <div class="exercise-grid" id="exercise-grid">
                    <!-- Filled by JS -->
                </div>
            </div>
            <div class="exercise-preview-section">
                <div class="section-title">Preview</div>
                <div class="preview-placeholder" id="preview-placeholder">
                    ðŸ‘ˆ Select an exercise to see the preview
                </div>
                <div class="preview-card" id="preview-card">
                    <h3 id="preview-title"></h3>
                    <img id="preview-gif" src="" alt="Exercise Demo">
                    <p class="desc" id="preview-desc"></p>
                    <div class="action-buttons">
                        <button class="btn-action btn-start-ex" id="btn-start">â–¶ Start Exercise</button>
                        <button class="btn-action btn-stop-ex" id="btn-stop">â¹ Stop</button>
                        <button class="btn-action btn-reset-ex" id="btn-reset">â†º Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Tracking Section (hidden until started) -->
        <div class="tracking-section" id="tracking-section">
            <div class="tracking-layout">
                <!-- Camera Feed -->
                <div class="camera-container" id="camera-container">
                    <video id="camera-video" playsinline></video>
                    <canvas id="pose-canvas"></canvas>
                    <div class="camera-loading" id="camera-loading">
                        <div class="spinner"></div>
                        Initializing Camera & Pose Detection...
                    </div>
                </div>

                <!-- Stats Panel -->
                <div class="stats-panel">
                    <div class="stat-card exercise-name-card">
                        <div class="stat-label">Exercise</div>
                        <div class="stat-value" id="stat-exercise">â€”</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Reps</div>
                        <div class="stat-value" id="stat-reps">0</div>
                    </div>
                    <!-- Phase Indicator -->
                    <div class="stat-card phase-card">
                        <div class="stat-label">Movement Phase</div>
                        <div class="stat-value phase-value" id="phase-indicator">â¸ Ready</div>
                    </div>
                    <div class="posture-card posture-good" id="posture-feedback">
                        âœ… Good Posture
                    </div>
                    <!-- Mismatch Warning -->
                    <div class="mismatch-warning" id="mismatch-warning" style="display:none;">
                        ðŸš« Incorrect Exercise Detected
                    </div>
                    <div class="progress-container">
                        <div class="stat-label">Rep Progress</div>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar-fill" id="progress-bar"></div>
                        </div>
                    </div>
                    <div class="fps-display" id="fps-display">FPS: â€”</div>
                    <div class="action-buttons" style="margin-top:5px;">
                        <button class="btn-action btn-stop-ex" id="btn-stop-tracking" style="display:inline-block;">â¹
                            Stop Exercise</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Back to Home -->
    <a href="HOME.php" class="btn-home">â¬… Back to Home</a>

    <script src="js/exercise_engine.js"></script>
    <script src="js/tracking_controller.js"></script>

    <style>
        /* Phase Indicator */
        .phase-card .phase-value {
            font-size: 1.1rem !important;
            font-family: 'Roboto', sans-serif !important;
            color: #3498db;
        }

        /* Mismatch Warning */
        .mismatch-warning {
            padding: 14px;
            border-radius: 14px;
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            background: rgba(231, 76, 60, 0.2);
            border: 2px solid #e74c3c;
            color: #e74c3c;
            animation: pulse-bad 1s ease-in-out infinite alternate;
        }
    </style>
</body>

</html>
