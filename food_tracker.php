<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Composition Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(to bottom, #1f2937, #2d3748);
            color: #d1d5db;
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        header {
            background-color: #1c2833;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        header h1 {
            font-size: 1.8rem;
            margin: 0;
            color: #34a853;
            font-weight: 700;
        }

        .home-btn {
            background-color: #16a085;
            color: #fff;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s, transform 0.2s;
        }

        .home-btn:hover {
            background-color: #1abc9c;
            transform: scale(1.05);
            color: #fff;
        }

        .main-container {
            max-width: 1100px;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            grid-gap: 25px;
            padding: 25px;
            background-color: #2d3748;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border-radius: 15px;
        }

        /* Food Selection */
        .food-select-section {
            padding: 20px;
        }

        .food-select-section h3 {
            color: #34a853;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }

        .form-label {
            color: #a0aec0;
            font-weight: 500;
        }

        .form-control,
        .form-select {
            background-color: #1a202c;
            color: #d1d5db;
            border: 1px solid #4a5568;
            border-radius: 8px;
            padding: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #34a853;
            box-shadow: 0 0 8px rgba(52, 168, 83, 0.6);
            outline: none;
            background-color: #1a202c;
            color: #d1d5db;
        }

        /* Nutrition Display */
        .nutrition-card {
            background-color: #1c2833;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }

        .nutrition-card:hover {
            transform: translateX(5px);
        }

        .nutrition-label {
            color: #a0aec0;
            font-weight: 500;
        }

        .nutrition-value {
            color: #34a853;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Chart Section */
        .chart-section {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .chart-section h3 {
            color: #34a853;
            margin-bottom: 20px;
            font-size: 1.4rem;
            text-align: center;
        }

        .chart-container canvas {
            max-width: 350px;
            max-height: 350px;
        }

        /* Food Description */
        .food-desc {
            background: linear-gradient(135deg, #16a085, #1abc9c);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 15px;
            text-align: center;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                margin: 15px;
                padding: 15px;
            }
        }

        /* ========== LIVE MEAL ANALYSIS STYLES ========== */
        .ai-upload-btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .ai-upload-btn:hover {
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #1e293b;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            padding: 30px;
            max-width: 520px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-box h3 {
            color: #a78bfa;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.6rem;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #fff;
        }

        /* Drop Zone */
        .drop-zone {
            border: 2px dashed rgba(139, 92, 246, 0.4);
            border-radius: 14px;
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: #8b5cf6;
            background: rgba(139, 92, 246, 0.08);
        }

        .drop-zone .drop-icon {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }

        .drop-zone input[type=file] {
            display: none;
        }

        .drop-zone .preview-img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
            object-fit: cover;
            display: none;
        }

        /* Analyze Button */
        .btn-analyze {
            width: 100%;
            padding: 14px;
            margin-top: 15px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-analyze:hover {
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
        }

        .btn-analyze:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Loading spinner */
        .analyze-loading {
            text-align: center;
            padding: 30px;
            display: none;
        }

        .analyze-loading .spinner-ring {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(139, 92, 246, 0.2);
            border-top: 4px solid #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        .analyze-loading p {
            color: #a78bfa;
            font-weight: 500;
        }

        /* Error */
        .analyze-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            border-radius: 12px;
            padding: 14px;
            margin-top: 15px;
            text-align: center;
            display: none;
            font-size: 0.95rem;
        }

        /* Result Card */
        .meal-result {
            margin-top: 30px;
            display: none;
        }

        .meal-result-card {
            background: #1e293b;
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            padding: 25px;
            margin-top: 15px;
        }

        .meal-result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .meal-result-header h4 {
            color: #a78bfa;
            font-size: 1.3rem;
            margin: 0;
        }

        .confidence-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .confidence-high {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .confidence-mid {
            background: rgba(250, 204, 21, 0.2);
            color: #facc15;
        }

        .confidence-low {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9rem;
        }

        .items-table th {
            color: #94a3b8;
            font-weight: 600;
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        .items-table td {
            color: #d1d5db;
            padding: 10px 8px;
            border-bottom: 1px solid #1e293b;
        }

        .items-table tr:hover td {
            background: rgba(139, 92, 246, 0.05);
        }

        /* Totals row */
        .totals-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .total-item {
            background: #0f172a;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .total-item .total-label {
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .total-item .total-value {
            color: #34a853;
            font-size: 1.3rem;
            font-weight: 800;
            margin-top: 4px;
        }

        /* Warnings */
        .meal-warnings {
            margin-top: 12px;
            padding: 10px 14px;
            background: rgba(250, 204, 21, 0.1);
            border: 1px solid rgba(250, 204, 21, 0.3);
            border-radius: 10px;
            color: #fde68a;
            font-size: 0.85rem;
        }

        /* Separator */
        .section-divider {
            border: none;
            border-top: 1px solid #334155;
            margin: 25px 0;
        }

        @media (max-width: 768px) {
            .totals-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <header>
        <h1>🍽️ Food Composition Calculator</h1>
        <a href="HOME.php" class="home-btn">Home</a>
    </header>

    <div class="main-container">
        <!-- Left Section: Food Selection & Nutrition -->
        <div class="food-select-section">
            <h3>Select Food Item</h3>

            <div class="mb-3">
                <label for="food-select" class="form-label">Food Item:</label>
                <select id="food-select" class="form-select" onchange="selectFood()">
                    <option value="" selected disabled>-- Choose a food --</option>
                    <option value="apple">🍎 Apple</option>
                    <option value="banana">🍌 Banana</option>
                    <option value="chapathi">🫓 Chapathi</option>
                    <option value="chicken gravy">🍗 Chicken Gravy</option>
                    <option value="fries">🍟 Fries</option>
                    <option value="idli">🫕 Idli</option>
                    <option value="pizza">🍕 Pizza</option>
                    <option value="rice">🍚 Rice</option>
                    <option value="soda">🥤 Soda</option>
                    <option value="tomato">🍅 Tomato</option>
                    <option value="vada">🍩 Vada</option>
                    <option value="burger">🍔 Burger</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="weight" class="form-label">Weight (grams):</label>
                <input type="number" id="weight" class="form-control" placeholder="Enter weight in grams" min="1"
                    value="100" oninput="updateNutrition()">
            </div>

            <div id="food-desc" class="food-desc" style="display:none;">
                <strong id="food-name"></strong>
                <p id="food-description" style="margin: 5px 0 0; font-size: 0.9rem;"></p>
            </div>

            <div id="nutrition-cards" style="margin-top: 20px; display: none;">
                <div class="nutrition-card">
                    <span class="nutrition-label">🔥 Calories</span>
                    <span class="nutrition-value" id="calories">0</span>
                </div>
                <div class="nutrition-card">
                    <span class="nutrition-label">🥖 Carbohydrates</span>
                    <span class="nutrition-value" id="carbs">0g</span>
                </div>
                <div class="nutrition-card">
                    <span class="nutrition-label">💪 Proteins</span>
                    <span class="nutrition-value" id="proteins">0g</span>
                </div>
                <div class="nutrition-card">
                    <span class="nutrition-label">🧈 Fats</span>
                    <span class="nutrition-value" id="fats">0g</span>
                </div>
                <div class="nutrition-card">
                    <span class="nutrition-label">💧 Lipids</span>
                    <span class="nutrition-value" id="lipids">0g</span>
                </div>
                <div class="nutrition-card">
                    <span class="nutrition-label">🫀 Cholesterol</span>
                    <span class="nutrition-value" id="cholesterol">0mg</span>
                </div>
            </div>

            <!-- Live Meal Calories Button -->
            <hr class="section-divider">
            <button class="ai-upload-btn" id="btn-open-meal-modal" onclick="openMealModal()">
                📸 Live Meal Calories (Upload Image)
            </button>
        </div>

        <!-- Right Section: Chart -->
        <div class="chart-section">
            <h3>Nutritional Breakdown</h3>
            <div class="chart-container">
                <canvas id="nutrition-chart"></canvas>
            </div>
            <p id="chart-placeholder" style="color: #64748b; text-align: center; margin-top: 40px; font-size: 1.1rem;">
                👆 Select a food item to see the breakdown
            </p>
        </div>
    </div>

    <script>
        const foodData = {
            "apple": { name: "Apple", description: "A sweet and crunchy fruit, rich in fiber and vitamin C.", carbs: 14, proteins: 0.3, fats: 0.2, lipids: 0.1, cholesterol: 0, calories: 52 },
            "banana": { name: "Banana", description: "A soft and sweet fruit rich in potassium and energy.", carbs: 23, proteins: 1.1, fats: 0.3, lipids: 0.2, cholesterol: 0, calories: 96 },
            "chapathi": { name: "Chapathi", description: "A soft flatbread made from wheat flour, staple in South Asian cuisine.", carbs: 20, proteins: 4.2, fats: 1.5, lipids: 1.2, cholesterol: 0, calories: 104 },
            "chicken gravy": { name: "Chicken Gravy", description: "A savory chicken dish with a rich, spiced gravy.", carbs: 3, proteins: 12, fats: 10, lipids: 7, cholesterol: 50, calories: 150 },
            "fries": { name: "Fries", description: "Crispy deep-fried potato strips, popular fast food snack.", carbs: 35, proteins: 3.5, fats: 17, lipids: 13, cholesterol: 0, calories: 312 },
            "idli": { name: "Idli", description: "A steamed rice cake, popular healthy breakfast in South India.", carbs: 12, proteins: 2.5, fats: 0.3, lipids: 0.1, cholesterol: 0, calories: 39 },
            "pizza": { name: "Pizza", description: "A savory dish with cheese and various toppings on a dough base.", carbs: 26, proteins: 11, fats: 10, lipids: 5, cholesterol: 20, calories: 285 },
            "rice": { name: "Rice", description: "A staple grain commonly served with meals, high in carbohydrates.", carbs: 28, proteins: 2.7, fats: 0.3, lipids: 0.2, cholesterol: 0, calories: 130 },
            "soda": { name: "Soda", description: "A sweetened carbonated beverage, high in sugar.", carbs: 10, proteins: 0, fats: 0, lipids: 0, cholesterol: 0, calories: 40 },
            "tomato": { name: "Tomato", description: "A juicy fruit often used in salads and sauces, rich in lycopene.", carbs: 3.9, proteins: 0.9, fats: 0.2, lipids: 0.1, cholesterol: 0, calories: 18 },
            "vada": { name: "Vada", description: "A fried savory doughnut-shaped snack made from lentils.", carbs: 18, proteins: 5, fats: 12, lipids: 9, cholesterol: 5, calories: 230 },
            "burger": { name: "Burger", description: "A sandwich with a beef patty, lettuce, and other toppings.", carbs: 30, proteins: 12, fats: 10, lipids: 8, cholesterol: 40, calories: 295 }
        };

        let chart = null;
        let selectedFood = null;

        function selectFood() {
            const foodKey = document.getElementById('food-select').value;
            if (!foodKey) return;
            selectedFood = foodKey;
            updateNutrition();
        }

        function updateNutrition() {
            if (!selectedFood) return;

            const food = foodData[selectedFood];
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            if (!food || weight <= 0) return;

            const multiplier = weight / 100;

            const carbs = (food.carbs * multiplier).toFixed(1);
            const proteins = (food.proteins * multiplier).toFixed(1);
            const fats = (food.fats * multiplier).toFixed(1);
            const lipids = (food.lipids * multiplier).toFixed(1);
            const cholesterol = (food.cholesterol * multiplier).toFixed(1);
            const calories = (food.calories * multiplier).toFixed(0);

            // Update display
            document.getElementById('food-name').textContent = food.name;
            document.getElementById('food-description').textContent = food.description;
            document.getElementById('food-desc').style.display = 'block';

            document.getElementById('calories').textContent = calories + ' kcal';
            document.getElementById('carbs').textContent = carbs + 'g';
            document.getElementById('proteins').textContent = proteins + 'g';
            document.getElementById('fats').textContent = fats + 'g';
            document.getElementById('lipids').textContent = lipids + 'g';
            document.getElementById('cholesterol').textContent = cholesterol + 'mg';
            document.getElementById('nutrition-cards').style.display = 'block';
            document.getElementById('chart-placeholder').style.display = 'none';

            // Update chart
            if (chart) chart.destroy();

            const ctx = document.getElementById('nutrition-chart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Carbs', 'Proteins', 'Fats', 'Lipids'],
                    datasets: [{
                        data: [parseFloat(carbs), parseFloat(proteins), parseFloat(fats), parseFloat(lipids)],
                        backgroundColor: ['#3b82f6', '#34a853', '#f59e0b', '#ef4444'],
                        borderColor: '#2d3748',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#d1d5db',
                                font: { size: 13, weight: '600' },
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + context.parsed + 'g';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>

    <!-- ============ MEAL IMAGE UPLOAD MODAL ============ -->
    <div class="modal-overlay" id="meal-modal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeMealModal()">&times;</button>
            <h3>📸 Analyze Meal from Image</h3>

            <div id="meal-upload-form">
                <div class="drop-zone" id="drop-zone">
                    <div class="drop-icon">🖼️</div>
                    <p>Drag & drop a meal photo here<br><small>or click to browse (JPG, PNG, WebP)</small></p>
                    <input type="file" id="meal-image-input" accept="image/jpeg,image/png,image/webp">
                    <img class="preview-img" id="meal-preview">
                </div>

                <div class="mb-3" style="margin-top:15px;">
                    <label class="form-label">Meal Name / Notes (optional)</label>
                    <input type="text" class="form-control" id="meal-notes"
                        placeholder="e.g. Chicken biryani with raita">
                </div>

                <button class="btn-analyze" id="btn-analyze" onclick="analyzeMealImage()" disabled>
                    🔍 Analyze Meal
                </button>

                <div class="analyze-error" id="analyze-error"></div>
            </div>

            <div class="analyze-loading" id="analyze-loading">
                <div class="spinner-ring"></div>
                <p>🤖 Analyzing with AI...</p>
                <p id="loading-timer" style="color:#a78bfa; font-size:0.9rem; font-weight:600;"></p>
                <p style="color:#64748b; font-size:0.85rem;">Trying multiple AI models for fastest response</p>
            </div>
        </div>
    </div>

    <!-- ============ MEAL RESULT SECTION (below main container) ============ -->
    <div class="meal-result" id="meal-result" style="max-width:1100px; margin:0 auto 30px; padding:0 25px;">
        <div class="meal-result-card">
            <div class="meal-result-header">
                <h4 id="result-dish-name">—</h4>
                <span class="confidence-badge" id="result-confidence">—</span>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Portion</th>
                        <th>Calories</th>
                        <th>Protein</th>
                        <th>Carbs</th>
                        <th>Fat</th>
                    </tr>
                </thead>
                <tbody id="result-items-body"></tbody>
            </table>

            <div class="totals-row" id="result-totals">
                <div class="total-item">
                    <div class="total-label">🔥 Calories</div>
                    <div class="total-value" id="result-total-cal">0</div>
                </div>
                <div class="total-item">
                    <div class="total-label">💪 Protein</div>
                    <div class="total-value" id="result-total-prot">0g</div>
                </div>
                <div class="total-item">
                    <div class="total-label">🥖 Carbs</div>
                    <div class="total-value" id="result-total-carbs">0g</div>
                </div>
                <div class="total-item">
                    <div class="total-label">🧈 Fat</div>
                    <div class="total-value" id="result-total-fat">0g</div>
                </div>
            </div>

            <div class="meal-warnings" id="result-warnings" style="display:none;"></div>
        </div>
    </div>

    <script>
        // ============ MEAL IMAGE ANALYSIS JS ============
        let selectedFile = null;

        function openMealModal() {
            document.getElementById('meal-modal').classList.add('active');
            resetMealForm();
        }
        function closeMealModal() {
            document.getElementById('meal-modal').classList.remove('active');
        }
        function resetMealForm() {
            document.getElementById('meal-upload-form').style.display = 'block';
            document.getElementById('analyze-loading').style.display = 'none';
            document.getElementById('analyze-error').style.display = 'none';
            document.getElementById('meal-preview').style.display = 'none';
            document.getElementById('meal-image-input').value = '';
            document.getElementById('meal-notes').value = '';
            document.getElementById('btn-analyze').disabled = true;
            selectedFile = null;
            // Reset drop zone text
            const dz = document.getElementById('drop-zone');
            dz.querySelector('.drop-icon').style.display = 'block';
            dz.querySelector('p').style.display = 'block';
        }

        // Drop zone
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('meal-image-input');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) handleFile(fileInput.files[0]);
        });

        function handleFile(file) {
            const valid = ['image/jpeg', 'image/png', 'image/webp'];
            if (!valid.includes(file.type)) {
                showError('Please select a JPG, PNG, or WebP image.');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                showError('Image must be under 10MB.');
                return;
            }
            selectedFile = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.getElementById('meal-preview');
                preview.src = e.target.result;
                preview.style.display = 'block';
                dropZone.querySelector('.drop-icon').style.display = 'none';
                dropZone.querySelector('p').style.display = 'none';
            };
            reader.readAsDataURL(file);
            document.getElementById('btn-analyze').disabled = false;
            document.getElementById('analyze-error').style.display = 'none';
        }

        function showError(msg) {
            const el = document.getElementById('analyze-error');
            el.textContent = '⚠️ ' + msg;
            el.style.display = 'block';
        }

        let analyzeTimer = null;
        let analyzeSeconds = 0;

        async function analyzeMealImage() {
            if (!selectedFile) return;

            // Show loading
            document.getElementById('meal-upload-form').style.display = 'none';
            document.getElementById('analyze-loading').style.display = 'block';
            document.getElementById('analyze-error').style.display = 'none';

            // Start countdown timer
            analyzeSeconds = 0;
            const timerEl = document.getElementById('loading-timer');
            timerEl.textContent = '0s elapsed...';
            analyzeTimer = setInterval(() => {
                analyzeSeconds++;
                timerEl.textContent = analyzeSeconds + 's elapsed...';
            }, 1000);

            const formData = new FormData();
            formData.append('meal_image', selectedFile);
            formData.append('meal_notes', document.getElementById('meal-notes').value);

            // AbortController for 90s timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 90000);

            try {
                const resp = await fetch('api/analyze_meal_image.php', {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                clearInterval(analyzeTimer);

                const data = await resp.json();

                if (!resp.ok) {
                    document.getElementById('meal-upload-form').style.display = 'block';
                    document.getElementById('analyze-loading').style.display = 'none';
                    showError(data.error || 'Analysis failed. Please try again.');
                    return;
                }

                // Success! Close modal and show results
                closeMealModal();
                displayMealResult(data);

            } catch (err) {
                clearTimeout(timeoutId);
                clearInterval(analyzeTimer);
                document.getElementById('meal-upload-form').style.display = 'block';
                document.getElementById('analyze-loading').style.display = 'none';

                if (err.name === 'AbortError') {
                    showError('Request timed out (90s). The API may be busy. Click Analyze Meal to retry.');
                } else {
                    showError('Network error. Please check your connection and try again.');
                }
            }
        }

        function displayMealResult(data) {
            const resultSection = document.getElementById('meal-result');
            resultSection.style.display = 'block';

            // Dish name
            document.getElementById('result-dish-name').textContent = '🍽️ ' + (data.dish_name || 'Analyzed Meal');

            // Confidence badge
            const conf = data.confidence_score || 0;
            const badge = document.getElementById('result-confidence');
            badge.textContent = (conf * 100).toFixed(0) + '% confidence';
            badge.className = 'confidence-badge ' + (conf >= 0.7 ? 'confidence-high' : conf >= 0.4 ? 'confidence-mid' : 'confidence-low');

            // Items table
            const tbody = document.getElementById('result-items-body');
            tbody.innerHTML = '';
            (data.detected_items || []).forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${item.name}</td><td>${item.portion || '—'}</td><td>${item.calories} kcal</td><td>${item.protein_g}g</td><td>${item.carbs_g}g</td><td>${item.fat_g}g</td>`;
                tbody.appendChild(tr);
            });

            // Totals
            const t = data.totals || {};
            document.getElementById('result-total-cal').textContent = (t.total_calories || 0) + ' kcal';
            document.getElementById('result-total-prot').textContent = (t.total_protein_g || 0) + 'g';
            document.getElementById('result-total-carbs').textContent = (t.total_carbs_g || 0) + 'g';
            document.getElementById('result-total-fat').textContent = (t.total_fat_g || 0) + 'g';

            // Warnings
            const warnEl = document.getElementById('result-warnings');
            if (data.warnings && data.warnings.length > 0) {
                warnEl.innerHTML = '⚠️ ' + data.warnings.join('<br>⚠️ ');
                warnEl.style.display = 'block';
            } else {
                warnEl.style.display = 'none';
            }

            // Update existing doughnut chart with analyzed meal data
            if (t.total_carbs_g || t.total_protein_g || t.total_fat_g) {
                updateChartWithMealData(t);
            }

            // Scroll to result
            resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function updateChartWithMealData(totals) {
            const carbs = parseFloat(totals.total_carbs_g) || 0;
            const proteins = parseFloat(totals.total_protein_g) || 0;
            const fats = parseFloat(totals.total_fat_g) || 0;

            // Update the nutrition cards too
            document.getElementById('calories').textContent = (totals.total_calories || 0) + ' kcal';
            document.getElementById('carbs').textContent = carbs.toFixed(1) + 'g';
            document.getElementById('proteins').textContent = proteins.toFixed(1) + 'g';
            document.getElementById('fats').textContent = fats.toFixed(1) + 'g';
            document.getElementById('lipids').textContent = '—';
            document.getElementById('cholesterol').textContent = '—';
            document.getElementById('nutrition-cards').style.display = 'block';
            document.getElementById('chart-placeholder').style.display = 'none';

            // Update/create chart
            if (chart) chart.destroy();
            const ctx = document.getElementById('nutrition-chart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Carbs', 'Proteins', 'Fats'],
                    datasets: [{
                        data: [carbs, proteins, fats],
                        backgroundColor: ['#3b82f6', '#34a853', '#f59e0b'],
                        borderColor: '#2d3748',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#d1d5db', font: { size: 13, weight: '600' }, padding: 15 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) { return ctx.label + ': ' + ctx.parsed + 'g'; }
                            }
                        }
                    }
                }
            });

            // Update food description
            document.getElementById('food-name').textContent = '🤖 AI-Analyzed Meal';
            document.getElementById('food-description').textContent = 'Nutrition data from uploaded image analysis';
            document.getElementById('food-desc').style.display = 'block';
        }
    </script>
</body>

</html>