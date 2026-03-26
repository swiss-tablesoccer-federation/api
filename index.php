<?php

declare(strict_types=1);

header('Content-Type: application/json');

$allowed_origins = [
    'https://tournaments.swisstablesoccer.ch',
    'http://localhost:5500',
    'http://127.0.0.1:5500'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: https://tournaments.swisstablesoccer.ch');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim($path, '/');

if ($path === '/') {
    echo json_encode(['api' => 'Swiss Tablesoccer Federation']);
    exit;
}

if ($path === '/tournaments') {
    $apiUrl = 'https://api.tablesoccer.org/cms.tournaments?tour=78';

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch tournament data', 'details' => $curlError]);
        exit;
    }

    json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(['error' => 'Upstream response is not valid JSON']);
        exit;
    }

    http_response_code($httpCode);
    echo $response;
    exit;
}

if ($path === '/rankings') {
    $apiUrl = 'https://api.tablesoccer.org/cms.rankings';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $category = isset($_GET['category']) ? (string) $_GET['category'] : '';
    $payload = json_encode(['tour' => 78, 'page' => $page, 'category' => $category]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch rankings data', 'details' => $curlError]);
        exit;
    }

    json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(['error' => 'Upstream response is not valid JSON']);
        exit;
    }

    http_response_code($httpCode);
    echo $response;
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
