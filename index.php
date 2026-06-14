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

/**
 * Send JSON data with the provided HTTP status and terminate execution.
 */
function respond_json(mixed $data, int $httpCode = 200): void
{
    $response = json_encode($data);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode JSON response']);
        exit;
    }

    http_response_code($httpCode);
    echo $response;
    exit;
}

/**
 * Execute an upstream request and return the raw JSON response with its status code.
 *
 * @param string      $apiUrl  Full upstream URL.
 * @param string|null $payload JSON-encoded POST body, or null for a GET request.
 *
 * @return array{response: string, http_code: int}
 */
function execute_request(string $apiUrl, ?string $payload = null): array
{
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        respond_json(['error' => 'Failed to fetch data', 'details' => $curlError], 502);
    }

    json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond_json(['error' => 'Upstream response is not valid JSON'], 502);
    }

    return ['response' => $response, 'http_code' => $httpCode];
}

/**
 * Decode a validated JSON response into an array.
 *
 * @return array<mixed>
 */
function decode_json_response(string $response): array
{
    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        respond_json(['error' => 'Upstream response has an unexpected structure'], 502);
    }

    return $decoded;
}

/**
 * Encode a payload for upstream POST requests.
 */
function encode_payload(array $payload): string
{
    $encoded = json_encode($payload);

    if ($encoded === false) {
        respond_json(['error' => 'Failed to encode upstream request payload'], 500);
    }

    return $encoded;
}

/**
 * Proxy a request to the upstream API and stream the response.
 * Accepts an optional JSON payload; when provided the request is sent as POST.
 * Terminates execution after sending the response.
 *
 * @param string      $apiUrl  Full upstream URL.
 * @param string|null $payload JSON-encoded POST body, or null for a GET request.
 */
function proxy_request(string $apiUrl, ?string $payload = null): void
{
    $result = execute_request($apiUrl, $payload);
    http_response_code($result['http_code']);
    echo $result['response'];
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim($path, '/');

if ($path === '/') {
    echo json_encode(['api' => 'Swiss Tablesoccer Federation']);
    exit;
}

if ($path === '/tournaments') {
    proxy_request('https://api.tablesoccer.org/cms.tournaments?tour=78');
}

if (preg_match('#^/rankings/([^/]+)$#', $path, $matches)) {
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $categoryMap = [
        'OS' => 308, 'JS' => 309, 'WS' => 310, 'SS' => 311,
        'OD' => 312, 'JD' => 313, 'WD' => 314, 'SD' => 315,
        'MX' => 316, 'OC' => 322,
    ];
    $categoryKey = strtoupper($matches[1]);
    $category = isset($categoryMap[$categoryKey]) ? $categoryMap[$categoryKey] : null;
    $payload = json_encode(array_filter(
        ['tour' => 78, 'page' => $page, 'category' => $category],
        fn($v) => $v !== null
    ));
    proxy_request('https://api.tablesoccer.org/cms.rankings', $payload);
}

if ($path === '/committees') {
    proxy_request('https://api.tablesoccer.org/cms.committees?organization=STF');
}

if ($path === '/hall-of-fame') {
    $searchResult = execute_request(
        'https://api.tablesoccer.org/search.tournament',
        encode_payload(['q' => 'Swiss Tablesoccer Finals', 'page' => 1])
    );

    if ($searchResult['http_code'] >= 400) {
        http_response_code($searchResult['http_code']);
        echo $searchResult['response'];
        exit;
    }

    $searchData = decode_json_response($searchResult['response']);
    $codes = [];

    foreach ($searchData['results'] ?? [] as $result) {
        if (is_array($result) && isset($result['code']) && is_string($result['code'])) {
            $code = trim($result['code']);

            if ($code !== '') {
                $codes[] = $code;
            }
        }
    }

    $hallOfFame = [];

    foreach ($codes as $code) {
        $moduleResult = execute_request(
            'https://api.tablesoccer.org/tournament.fetch_module',
            encode_payload([
                'attr' => ['tournament', 'competitions'],
                'args' => ['code' => $code],
            ])
        );

        if ($moduleResult['http_code'] >= 400) {
            http_response_code($moduleResult['http_code']);
            echo $moduleResult['response'];
            exit;
        }

        $hallOfFame[] = decode_json_response($moduleResult['response']);
    }

    respond_json($hallOfFame);
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
