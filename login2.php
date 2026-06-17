<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

require './conn.php';
require './log.php';
require './jwt.php';

$conn = getConnection();
log_action("=== LOGIN ATTEMPT START ===");

// Read input (JSON or URL-encoded)
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_REQUEST;
}
log_action("Raw Input Data: " . json_encode($data));

// Sanitize input
$email = trim($conn->real_escape_string($data['email'] ?? ''));
$password = $data['password'] ?? '';

// Validation: Check if email and password are provided
if (!$email || !$password) {
    log_action("Validation failed: Missing email or password.");
    echo json_encode(["status" => false, "message" => "Request cannot be processed"]);
    closeConnection($conn);
    exit;
}

$sql = "SELECT id, full_name,phoneNumber, email, password, role FROM users WHERE email = '$email'";
log_action("Executing SQL query: $sql");

$result = $conn->query($sql);

if (!$result) {
    log_action("Query failed: " . $conn->error);
    echo json_encode(["status" => false, "message" => "Database error occurred."]);
    closeConnection($conn);
    exit;
}

log_action("Query executed. Rows found: " . $result->num_rows);

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    log_action("User record fetched for: $email");

    if (password_verify($password, $user['password'])) {
        log_action("Password verified successfully for user ID: {$user['id']}");

	$phoneNo = '234'. substr($user['phoneNumber'], -10);
	$otp = generateRandomNumbersString();
	$otptext = " Use this code to complete your login on the official platform. $otp - This OTP expires in 10 minutes.";
	//$emailSent = sendMails($otp, $user['email']);
	$request_id = time();
	$emailSent = sendMadApiSMS($phoneNo, $request_id, $otptext);
	//$emailSent = sendSms($phoneNo, $otptext);

    log_action("OTP generated: $otp, sending sms to: {$user['phoneNumber']}");
	//log_action("OTP generated: $otp, sending email to: {$user['email']}");

        if ($emailSent) {
            $expiryAt = date('Y-m-d H:i:s', time() + 300); // 120 seconds = 2 minutes    
	
	    //log_action("OTP email sent successfully to: {$user['email']}");
            log_action("OTP email sent successfully to: {$user['phoneNumber']}");
            $query = "UPDATE users SET otp = '$otp', otp_expires_at = '$expiryAt' WHERE email = '$email'";
            //            $result = $conn->query($sql);
            $updateResult = $conn->query($query); // ✅ Correct one
            if ($updateResult) {
                log_action("OTP updated successfully for: {$user['email']}");
            } else {
                log_action("Failed to update OTP for: {$user['email']} | Error: " . $conn->error);
            }
        } else {
            log_action("Failed to send OTP email to: {$user['email']}");
        }

        $payload = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 1800
        ];
        $jwt = generateJWT($payload);
        log_action("JWT generated for user ID: {$user['id']}");
        echo json_encode([
            "status" => true,
            "message" => "Login successful.",
            "token" => $jwt
        ]);
    } else {
        log_action("Password mismatch for email: $email");
        echo json_encode(["status" => false, "message" => "Request cannot be processed"]);
    }
} else {
    log_action("Login failed: No matching user for email: $email");
    echo json_encode(["status" => false, "message" => "Request cannot be processed."]);
}

closeConnection($conn);
log_action("=== LOGIN ATTEMPT END ===");


