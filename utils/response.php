<?php

function generateResponse($status, $message, $data = null, $statusCode = 200)
{
    http_response_code($statusCode);

    $response = [
        "status" => $status,
        "statusCode" => $statusCode,
        "message" => $message
    ];

    if ($data !== null) {
        $response["data"] = $data;
    }

    return json_encode($response);
}
