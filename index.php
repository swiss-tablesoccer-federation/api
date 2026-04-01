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

if (preg_match('#^/rankings/([^/]+)$#', $path, $matches)) {
    $apiUrl = 'https://api.tablesoccer.org/cms.rankings';
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

if ($path === '/docs') {
    $shareUrl   = 'https://1drv.ms/f/c/753cbab9de4f01b4/IgA0lMh6_4xeTKD4BOpLF1fUAXQyejtyXdNVOIGVzOBwNVc';
    $browserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $apiBase    = 'https://my.microsoftpersonalcontent.com/_api/v2.0';

    // Drive and item IDs are stable — they are embedded in the permanent share URL
    // and confirmed via the onedrive.live.com redirect (cid + resid params).
    $driveId = '753cbab9de4f01b4';
    $itemId  = '753CBAB9DE4F01B4!s7ac894348cff4c5ea0f804ea4b1757d4';

    // Cookie jar shared across both requests so session cookies set by 1drv.ms
    // (which redirects through my.microsoftpersonalcontent.com) are forwarded.
    $cookieFile = tempnam(sys_get_temp_dir(), 'odcookie_');
    chmod($cookieFile, 0600);

    // Step 1: POST to the share URL (urlencoded) to establish session cookies.
    // 1drv.ms redirects through onedrive.live.com → my.microsoftpersonalcontent.com,
    // setting auth cookies needed by the /children call below.
    $step1Params = http_build_query([
        '$expand' => 'thumbnails',
        '$select' => '*,containingDrivePolicyScenarioViewpoint,ocr,webDavUrl,sharepointIds',
        'ump' => '1',
    ], '', '&', PHP_QUERY_RFC3986);
    $ch1 = curl_init($shareUrl);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch1, CURLOPT_USERAGENT, $browserAgent);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, $step1Params);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch1, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch1, CURLOPT_COOKIEJAR, $cookieFile);
    $r1Body = curl_exec($ch1);
    $r1Error = curl_error($ch1);
    curl_close($ch1);
    if ($r1Body === false || $r1Error !== '') {
        @unlink($cookieFile);
        http_response_code(502);
        echo json_encode(['error' => 'Failed to redeem OneDrive share', 'details' => $r1Error]);
        exit;
    }

    // Step 2: List the folder children.
    // The browser sends this as multipart/form-data (ump=1 means "use multipart").
    // The Origin and Referer headers are required — the server validates them as a
    // CORS/CSRF check to ensure the request originates from onedrive.live.com.
    $step2Params = [
        '$top' => '100',
        'orderby' => 'folder,name asc',
        '$expand' => 'thumbnails,tags',
        'select' => '*,ocr,webDavUrl,sharepointIds,isRestricted,commentSettings,specialFolder,containingDrivePolicyScenarioViewpoint',
        'ump' => '1',
    ];
    $step2Fields = http_build_query($step2Params, '', '&', PHP_QUERY_RFC3986);
    $step2Url = $apiBase . '/drives/' . rawurlencode($driveId)
        . '/items/' . rawurlencode($itemId)
        . '/children?' . $step2Fields;
    $ch2 = curl_init($step2Url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch2, CURLOPT_USERAGENT, $browserAgent);
    curl_setopt($ch2, CURLOPT_POST, true);
    // Passing an array (not a string) makes curl use multipart/form-data with a boundary,
    // exactly matching what the browser sends for ump=1 requests.
    curl_setopt($ch2, CURLOPT_POSTFIELDS, $step2Params);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Origin: https://onedrive.live.com',
        'Referer: https://onedrive.live.com/',
    ]);
    curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieFile);
    $r2Body  = curl_exec($ch2);
    $r2Error = curl_error($ch2);
    $r2Code  = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    @unlink($cookieFile);
    $r2 = ['body' => $r2Body, 'error' => $r2Error, 'code' => $r2Code];

    if ($r2['body'] === false || $r2['error'] !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch docs listing', 'details' => $r2['error']]);
        exit;
    }

    $data = json_decode($r2['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(['error' => 'Upstream response is not valid JSON']);
        exit;
    }

    http_response_code($r2['code']);
    echo json_encode($data);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
