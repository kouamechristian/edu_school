<?php
// Test script to check API endpoint
$url = 'http://127.0.0.1/GETACI_DEV/public/api/statistics';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo "HTTP Status: $httpCode\n";
    echo "Response: " . $response;
}

curl_close($ch);
?>
