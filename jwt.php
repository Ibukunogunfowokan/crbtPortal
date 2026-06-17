<?php
require_once 'vendor/autoload.php';  // If you're using Composer for Firebase JWT

use \Firebase\JWT\JWT;
use Firebase\JWT\Key;


function generateJWT($payload)
{
    $secretKey = "28e1a9ef31061caa6316126baee01aebb1270e3e3c58eddbeb4528ebbc4fb77ed666724ff6c157236dd82e9270e3ddc102da4971085bd2cc07bbd499df4c74a";  // Set your secret key
    $algorithm = 'HS256';           // Specify the JWT algorithm

    // Encode the payload to create the token
    return JWT::encode($payload, $secretKey, $algorithm);
}

function decodeJWT($jwt)
{
    $secretKey = "28e1a9ef31061caa6316126baee01aebb1270e3e3c58eddbeb4528ebbc4fb77ed666724ff6c157236dd82e9270e3ddc102da4971085bd2cc07bbd499df4c74a";  // Set your secret key

    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        return null;
    }
}
