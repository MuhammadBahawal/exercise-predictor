<?php
session_start();
require 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return JSON error for AJAX requests
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User is not logged in.']);
    exit();
}

// Retrieve user_id from session
$user_id = $_SESSION['user_id'];

// Check if POST data is received
if (!isset($_POST['weight']) || !isset($_POST['bmi']) || !isset($_POST['body_fat_percentage'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing POST data.']);
    exit();
}

// Get the BMI data sent via POST
$weight = $_POST['weight'];
$bmi = $_POST['bmi'];
$body_fat_percentage = $_POST['body_fat_percentage'];

// Insert the data into the progresstracking table
$sql = "INSERT INTO progresstracking (user_id, body_weight, bmi, body_fat_percentage) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iddd", $user_id, $weight, $bmi, $body_fat_percentage);

// Execute the statement and return JSON response
header('Content-Type: application/json');
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'BMI data stored successfully!']);
} else {
    echo json_encode(['error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>