<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/utilityFunctions.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo generateResponse(false, "Method not allowed. Use GET.", null, 405);
    exit;
}

require_once __DIR__ . '/../utils/conn.php';
require_once __DIR__ . '/../log.php';
require_once __DIR__ . '/../jwt.php';

$conn = getConnection();
log_action("=== READ ORGANISATION ATTEMPT START ===");

try {
    $decoded = authenticateRequest($conn);

    $id = $_GET['id'] ?? null;

    if ($id !== null) {
        if (!ctype_digit((string) $id)) {
            log_action("Validation failed: invalid id - $id");
            echo generateResponse(false, "Invalid organisation id.", null, 400);
            closeConnection($conn);
            exit;
        }

        $id = (int) $id;
        $result = $conn->query(
            "SELECT id, name, email, url, created_at, updated_at FROM organisations WHERE id=$id"
        );

        if (!$result) {
            log_action("Read organisation query failed: " . $conn->error);
            echo generateResponse(false, "An error occured", null, 500);
            closeConnection($conn);
            exit;
        }

        if ($result->num_rows === 0) {
            log_action("Read organisation failed: id=$id not found");
            echo generateResponse(false, "Organisation not found.", null, 404);
            closeConnection($conn);
            exit;
        }

        $organisation = $result->fetch_assoc();
        log_action("Organisation retrieved successfully: id=$id");
        echo generateResponse(true, "Organisation retrieved successfully.", ["organisation" => $organisation], 200);
        closeConnection($conn);
        exit;
    }

    $result = $conn->query(
        "SELECT id, name, email, url, created_at, updated_at FROM organisations"
    );

    if (!$result) {
        log_action("Read organisations query failed: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    $organisations = [];
    while ($row = $result->fetch_assoc()) {
        $organisations[] = $row;
    }

    log_action("Organisations retrieved successfully: count=" . count($organisations));
    echo generateResponse(true, "Organisations retrieved successfully.", ["organisations" => $organisations], 200);
} catch (\Throwable $e) {
    log_action("Read organisation exception: " . $e->getMessage());
    echo generateResponse(false, "An error occured", null, 500);
} finally {
    closeConnection($conn);
    log_action("=== READ ORGANISATION ATTEMPT END ===");
}
