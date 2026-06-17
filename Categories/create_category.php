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
log_action("=== CREATE CATEGORY ATTEMPT START ===");

try {
    $decoded = authenticateRequest($conn);

    $data = getRequestBody();
    log_action("Raw Input Data: " . json_encode($data));

    $name = trim($conn->real_escape_string($data['name'] ?? ''));

    if (!$name) {
        echo generateResponse(false, "Name is required.", null, 400);
        closeConnection($conn);
        exit;
    }

    // Check if name already exists
    $checkResult = $conn->query("SELECT id FROM categories WHERE name='$name'");

    if (!$checkResult) {
        log_action("Name check query failed: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    if ($checkResult->num_rows > 0) {
        log_action("Create category failed: name already exists - $name");
        echo generateResponse(false, "A category with that name already exists.", null, 409);
        closeConnection($conn);
        exit;
    }

    if (!$conn->query("INSERT INTO categories (name) VALUES ('$name')")) {
        log_action("Failed to create category: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    $categoryId = $conn->insert_id;
    log_action("Category created successfully: id=$categoryId, name=$name");

    $callerId = (int) $decoded['id'];
    $callerResult = $conn->query("SELECT firstname, lastname FROM admins WHERE id=$callerId");
    if ($callerResult && $callerResult->num_rows > 0) {
        $caller = $callerResult->fetch_assoc();
        $callerFullName = $caller['firstname'] . ' ' . $caller['lastname'];
        try {
            audit_log($conn, $callerId, $callerFullName, getLogMessage('createdCategory', ['name' => $name]), 1);
        } catch (\Throwable $e) {
            log_action("Audit log call failed: " . $e->getMessage());
        }
    }

    echo generateResponse(true, "Category created successfully.", [
        "category" => [
            "id"   => $categoryId,
            "name" => $name
        ]
    ], 201);
} catch (\Throwable $e) {
    log_action("Create category exception: " . $e->getMessage());
    echo generateResponse(false, "An error occured", null, 500);
} finally {
    closeConnection($conn);
    log_action("=== CREATE CATEGORY ATTEMPT END ===");
}
