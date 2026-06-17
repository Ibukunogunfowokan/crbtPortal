<?php

function setCorsHeaders()
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json");
}

function getHeaders()
{
    if (function_exists('apache_request_headers')) {
        return apache_request_headers();
    } elseif (function_exists('getallheaders')) {
        return getallheaders();
    } else {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

function getRequestBody()
{
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        $data = $_REQUEST;
    }
    return $data;
}

function authenticateRequest($conn)
{
    $headers = getHeaders();

    if (!isset($headers['Authorization'])) {
        log_action("Auth blocked: Authorization header missing");
        echo generateResponse(false, "Authorization header is required", null, 400);
        closeConnection($conn);
        exit;
    }

    $authParts = explode(' ', $headers['Authorization']);

    if (count($authParts) !== 2 || strcasecmp($authParts[0], 'Bearer') !== 0) {
        log_action("Auth blocked: invalid Authorization header format");
        echo generateResponse(false, "Invalid Authorization header. Expected format: 'Bearer <token>'", null, 400);
        closeConnection($conn);
        exit;
    }

    $decoded = decodeJWT($authParts[1]);

    if (!$decoded || !isset($decoded['id']) || !isset($decoded['role'])) {
        log_action("Auth blocked: invalid or expired token");
        echo generateResponse(false, "Invalid or expired token.", null, 401);
        closeConnection($conn);
        exit;
    }

    log_action("Authorized caller: id={$decoded['id']} role={$decoded['role']}");
    return $decoded;
}

function requireRole($decoded, $role)
{
    if ($decoded['role'] !== $role) {
        log_action("Auth blocked: caller id={$decoded['id']} role={$decoded['role']} is not authorized");
        echo generateResponse(false, "You are not authorized to perform this action.", null, 403);
        exit;
    }
}

function getLogMessage($key, $context = [])
{
    $name = isset($context['name']) ? ' ' . $context['name'] : '';
    $messages = [
        "createdAdmin" => "Created admin{$name}",
        "updatedAdmin" => "Updated admin{$name}",
        "deletedAdmin" => "Deleted admin{$name}",
        "createdOrganisation" => "Created organisation{$name}",
        "updatedOrganisation" => "Updated organisation{$name}",
        "deletedOrganisation" => "Deleted organisation{$name}",
        "createdCategory" => "Created category{$name}",
        "updatedCategory" => "Updated category{$name}",
        "deletedCategory" => "Deleted category{$name}",
        "login" => "Logged in"
    ];
    return $messages[$key] ?? $key;
}
