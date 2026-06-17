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
log_action("=== READ ACTIVITY LOGS ATTEMPT START ===");

$statusLabels = [0 => 'pending', 1 => 'successful', 2 => 'failed'];

try {
    $decoded = authenticateRequest($conn);

    $id = $_GET['id'] ?? null;

    if ($id !== null) {
        if (!ctype_digit((string) $id)) {
            log_action("Validation failed: invalid id - $id");
            echo generateResponse(false, "Invalid log id.", null, 400);
            closeConnection($conn);
            exit;
        }

        $id = (int) $id;
        $result = $conn->query(
            "SELECT full_name, activity, status, created_at FROM activity_logs WHERE id=$id"
        );

        if (!$result) {
            log_action("Read activity log query failed: " . $conn->error);
            echo generateResponse(false, "An error occured", null, 500);
            closeConnection($conn);
            exit;
        }

        if ($result->num_rows === 0) {
            log_action("Read activity log failed: id=$id not found");
            echo generateResponse(false, "Log not found.", null, 404);
            closeConnection($conn);
            exit;
        }

        $log = $result->fetch_assoc();
        $log['status'] = $statusLabels[(int) $log['status']] ?? 'unknown';

        log_action("Activity log retrieved successfully: id=$id");
        echo generateResponse(true, "Activity log retrieved successfully.", ["log" => $log], 200);
        closeConnection($conn);
        exit;
    }

    $result = $conn->query(
        "SELECT full_name, activity, status, created_at FROM activity_logs ORDER BY created_at DESC"
    );

    if (!$result) {
        log_action("Read activity logs query failed: " . $conn->error);
        echo generateResponse(false, "An error occured", null, 500);
        closeConnection($conn);
        exit;
    }

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $row['status'] = $statusLabels[(int) $row['status']] ?? 'unknown';
        $logs[] = $row;
    }

    log_action("Activity logs retrieved successfully: count=" . count($logs));
    echo generateResponse(true, "Activity logs retrieved successfully.", ["logs" => $logs], 200);
} catch (\Throwable $e) {
    log_action("Read activity logs exception: " . $e->getMessage());
    echo generateResponse(false, "An error occured", null, 500);
} finally {
    closeConnection($conn);
    log_action("=== READ ACTIVITY LOGS ATTEMPT END ===");
}
