<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/utilityFunctions.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo generateResponse(false, "Method not allowed. Use POST.", null, 405);
    exit;
}

require_once __DIR__ . '/../utils/conn.php';
require_once __DIR__ . '/../log.php';
require_once __DIR__ . '/../jwt.php';

$conn = getConnection();
log_action("=== DELETE CATEGORY ATTEMPT START ===");

try {
    $decoded = authenticateRequest($conn);

    $data = getRequestBody();
    log_action("Raw Input Data: " . json_encode($data));

    $targetId = isset($data['id']) ? (int) $data['id'] : 0;

    if (!$targetId) {
        echo generateResponse(false, "Category id is required.", null, 400);
        closeConnection($conn);
        exit;
    }

    $checkResult = $conn->query("SELECT name FROM categories WHERE id=$targetId");

    if (!$checkResult) {
        log_action("Delete category check query failed: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    if ($checkResult->num_rows === 0) {
        log_action("Delete category failed: id=$targetId not found");
        echo generateResponse(false, "Category not found.", null, 404);
        closeConnection($conn);
        exit;
    }

    $category = $checkResult->fetch_assoc();
    $categoryName = $category['name'];

    if (!$conn->query("DELETE FROM categories WHERE id=$targetId")) {
        log_action("Delete category query failed: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    log_action("Category deleted successfully: id=$targetId ($categoryName) by caller id={$decoded['id']}");

    $callerId = (int) $decoded['id'];
    $callerResult = $conn->query("SELECT firstname, lastname FROM admins WHERE id=$callerId");
    if ($callerResult && $callerResult->num_rows > 0) {
        $caller = $callerResult->fetch_assoc();
        $callerFullName = $caller['firstname'] . ' ' . $caller['lastname'];
        try {
            audit_log($conn, $callerId, $callerFullName, getLogMessage('deletedCategory', ['name' => $categoryName]), 1);
        } catch (\Throwable $e) {
            log_action("Audit log call failed: " . $e->getMessage());
        }
    }

    echo generateResponse(true, "Category deleted successfully.", null, 200);
} catch (\Throwable $e) {
    log_action("Delete category exception: " . $e->getMessage());
    echo generateResponse(false, "An error occured", null, 500);
} finally {
    closeConnection($conn);
    log_action("=== DELETE CATEGORY ATTEMPT END ===");
}
