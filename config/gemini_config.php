<?php
// ============================================================
// Gemini API Configuration
// ============================================================
// Get your free API key from: https://aistudio.google.com/apikey
// Paste it below between the quotes.

define('GEMINI_API_KEY', 'AIzaSyDRAUYvANKd3bOnbDYsfd8I6xTDTqvI8yk');

// Rate limiting: max requests per session per hour
define('GEMINI_RATE_LIMIT', 10);
define('GEMINI_RATE_WINDOW', 3600); // seconds

// Max upload file size (10MB)
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Allowed image types
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/jpg']);
?>