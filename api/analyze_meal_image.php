<?php
// ============================================================
// API: Analyze Meal Image via Google Gemini Vision
// POST /api/analyze_meal_image.php
// - Auto-retry with fallback models on 503/overload errors
// - Image compression for speed
// ============================================================
session_start();
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config/gemini_config.php';
require_once __DIR__ . '/../db_connect.php';

// ---- Rate Limiting ----
if (!isset($_SESSION['gemini_requests'])) {
    $_SESSION['gemini_requests'] = [];
}
$now = time();
$_SESSION['gemini_requests'] = array_filter($_SESSION['gemini_requests'], fn($t) => ($now - $t) < GEMINI_RATE_WINDOW);
if (count($_SESSION['gemini_requests']) >= GEMINI_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait before trying again.']);
    exit;
}

// ---- Validate Upload ----
if (!isset($_FILES['meal_image']) || $_FILES['meal_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded or upload error.']);
    exit;
}

$file = $_FILES['meal_image'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.']);
    exit;
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 10MB.']);
    exit;
}

// ---- Check API Key ----
if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => 'Gemini API key not configured. Please set it in config/gemini_config.php']);
    exit;
}

// ---- Compress & Convert Image for Speed ----
$imgResource = null;
$sendMime = $mimeType;

if (function_exists('imagecreatefromjpeg')) {
    // Resize large images to max 800px for faster API response
    list($origW, $origH) = @getimagesize($file['tmp_name']);
    $maxDim = 800;

    if ($origW && $origH && ($origW > $maxDim || $origH > $maxDim)) {
        if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
            $imgResource = imagecreatefromjpeg($file['tmp_name']);
        } elseif ($mimeType === 'image/png') {
            $imgResource = imagecreatefrompng($file['tmp_name']);
        } elseif ($mimeType === 'image/webp') {
            $imgResource = imagecreatefromwebp($file['tmp_name']);
        }

        if ($imgResource) {
            $ratio = min($maxDim / $origW, $maxDim / $origH);
            $newW = (int) ($origW * $ratio);
            $newH = (int) ($origH * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $imgResource, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($imgResource);

            // Save as JPEG for smallest size
            ob_start();
            imagejpeg($resized, null, 75);
            $compressedData = ob_get_clean();
            imagedestroy($resized);

            $imageData = base64_encode($compressedData);
            $sendMime = 'image/jpeg';
        } else {
            $imageData = base64_encode(file_get_contents($file['tmp_name']));
        }
    } else {
        $imageData = base64_encode(file_get_contents($file['tmp_name']));
    }
} else {
    $imageData = base64_encode(file_get_contents($file['tmp_name']));
}

$mealNotes = isset($_POST['meal_notes']) ? trim(strip_tags($_POST['meal_notes'])) : '';

// ---- Build Prompt ----
$prompt = "Analyze this meal/food image. Provide a nutritional breakdown.";
if ($mealNotes) {
    $prompt .= " User says: \"$mealNotes\".";
}
$prompt .= '

Return JSON:
{
  "dish_name": "string",
  "detected_items": [{"name":"string","portion":"string","calories":0,"protein_g":0,"carbs_g":0,"fat_g":0}],
  "totals": {"total_calories":0,"total_protein_g":0,"total_carbs_g":0,"total_fat_g":0},
  "confidence_score": 0.85,
  "warnings": []
}

If NOT food: {"dish_name":"Not a food image","detected_items":[],"totals":{"total_calories":0,"total_protein_g":0,"total_carbs_g":0,"total_fat_g":0},"confidence_score":0,"warnings":["No food detected."]}

Return ONLY valid JSON.';

// ---- Models to try (in order of preference) ----
$models = [
    'gemini-2.5-flash',
    'gemini-2.5-flash-lite',
    'gemini-3-flash-preview',
];

$requestBody = [
    'contents' => [
        [
            'parts' => [
                [
                    'inlineData' => [
                        'mimeType' => $sendMime,
                        'data' => $imageData
                    ]
                ],
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.2,
        'maxOutputTokens' => 4096,
        'responseMimeType' => 'application/json'
    ],
    'systemInstruction' => [
        'parts' => [
            ['text' => 'You are a nutrition analyst. Analyze food images and output only valid JSON with calorie/macro estimates. Be concise.']
        ]
    ]
];

// ---- Try each model with retry ----
$response = null;
$httpCode = 0;
$lastError = '';
$usedModel = '';

foreach ($models as $model) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";

    // Try up to 2 times per model
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . GEMINI_API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $lastError = "Connection error: $curlError";
            continue;
        }

        if ($httpCode === 200) {
            $usedModel = $model;
            break 2; // Success! Exit both loops
        }

        // Parse error
        $errBody = json_decode($response, true);
        $errMsg = $errBody['error']['message'] ?? 'Unknown error';
        $lastError = $errMsg;

        // If 503 (overloaded) or 429 (rate limited), wait briefly then retry
        if ($httpCode === 503 || $httpCode === 429) {
            if ($attempt === 1) {
                usleep(1500000); // Wait 1.5 seconds before retry
            }
            continue;
        }

        // For other errors (400, 401, etc.), skip to next model
        break;
    }
}

// If all models failed
if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Gemini API error: ' . $lastError]);
    exit;
}

// ---- Parse Response ----
$geminiResponse = json_decode($response, true);

// Find the text part (Gemini 3 has thinking + text parts)
$textContent = null;
$parts = $geminiResponse['candidates'][0]['content']['parts'] ?? [];
foreach ($parts as $part) {
    if (isset($part['text'])) {
        $textContent = $part['text'];
    }
}

if (!$textContent) {
    http_response_code(500);
    echo json_encode(['error' => 'Empty response from API']);
    exit;
}

// Clean markdown wrapping
$textContent = preg_replace('/^```json\s*/i', '', $textContent);
$textContent = preg_replace('/\s*```$/i', '', $textContent);
$textContent = trim($textContent);

$mealData = json_decode($textContent, true);
if (!$mealData || !isset($mealData['totals'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse response', 'raw' => substr($textContent, 0, 500)]);
    exit;
}

// Add model info
$mealData['model_used'] = $usedModel;

// ---- Record rate limit ----
$_SESSION['gemini_requests'][] = $now;

// ---- Save to Database ----
$userId = $_SESSION['user_id'];
$dishName = $conn->real_escape_string($mealData['dish_name'] ?? 'Analyzed Meal');
$totalCal = floatval($mealData['totals']['total_calories'] ?? 0);
$totalProt = floatval($mealData['totals']['total_protein_g'] ?? 0);
$totalCarbs = floatval($mealData['totals']['total_carbs_g'] ?? 0);
$totalFat = floatval($mealData['totals']['total_fat_g'] ?? 0);
$today = date('Y-m-d');

if ($totalCal > 0) {
    $stmt = $conn->prepare("INSERT INTO foodcomposition (user_id, food_name, calories, proteins, carbs, fats, date_logged) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddds", $userId, $dishName, $totalCal, $totalProt, $totalCarbs, $totalFat, $today);
    $stmt->execute();
    $mealData['saved_to_db'] = true;
    $mealData['db_id'] = $conn->insert_id;
    $stmt->close();
} else {
    $mealData['saved_to_db'] = false;
}

$conn->close();
echo json_encode($mealData);
?>