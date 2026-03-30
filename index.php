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
    $shareUrl = 'https://1drv.ms/f/c/753cbab9de4f01b4/IgA0lMh6_4xeTKD4BOpLF1fUAXQyejtyXdNVOIGVzOBwNVc';
    $browserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $apiBase = 'https://my.microsoftpersonalcontent.com/_api/v2.0';

    // Sharing token: u! + URL-safe base64 of the share URL (no padding)
    $shareToken = 'u!' . rtrim(strtr(base64_encode($shareUrl), '+/', '-_'), '=');

    // Cookie jar shared across all requests, exactly as a browser session would do
    $cookieFile = tempnam(sys_get_temp_dir(), 'odcookie_');
    chmod($cookieFile, 0600);

    $doPost = function (string $url, array $params) use ($browserAgent, $cookieFile): array {
        $fields = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, $browserAgent);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => $body, 'error' => $error, 'code' => $code];
    };

    // Step 1: POST to the share URL to establish session cookies (mirrors the browser's
    //         initial request to 1drv.ms which sets auth cookies for subsequent API calls)
    $step1Params = [
        '$expand' => 'thumbnails',
        '$select' => '*,containingDrivePolicyScenarioViewpoint,ocr,webDavUrl,sharepointIds',
        'ump' => '1',
    ];
    $r1 = $doPost($shareUrl, $step1Params);
    if ($r1['body'] === false || $r1['error'] !== '') {
        @unlink($cookieFile);
        http_response_code(502);
        echo json_encode(['error' => 'Failed to redeem OneDrive share', 'details' => $r1['error']]);
        exit;
    }

    // Step 2: Resolve the shared item to obtain its drive ID and item ID
    $step2Params = ['$select' => 'id,parentReference,folder,bundle,remoteItem'];
    $step2Fields = http_build_query($step2Params, '', '&', PHP_QUERY_RFC3986);
    $r2 = $doPost($apiBase . '/shares/' . $shareToken . '/driveitem?' . $step2Fields, $step2Params);
    if ($r2['body'] === false || $r2['error'] !== '') {
        @unlink($cookieFile);
        http_response_code(502);
        echo json_encode(['error' => 'Failed to resolve shared drive item', 'details' => $r2['error']]);
        exit;
    }

    $itemInfo = json_decode($r2['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        @unlink($cookieFile);
        http_response_code(502);
        echo json_encode(['error' => 'Failed to parse drive item info']);
        exit;
    }

    $itemId = $itemInfo['id'] ?? '';
    $driveId = strtolower($itemInfo['parentReference']['driveId'] ?? '');
    if ($itemId === '' || $driveId === '') {
        @unlink($cookieFile);
        http_response_code(502);
        echo json_encode(['error' => 'Missing drive/item ID in share response', 'data' => $itemInfo]);
        exit;
    }

    // Step 3: List the folder children (params duplicated in URL and body, as the browser does)
    $step3Params = [
        '$top' => '100',
        'orderby' => 'folder,name asc',
        '$expand' => 'thumbnails,tags',
        'select' => '*,ocr,webDavUrl,sharepointIds,isRestricted,commentSettings,specialFolder,containingDrivePolicyScenarioViewpoint',
        'ump' => '1',
    ];
    $step3Fields = http_build_query($step3Params, '', '&', PHP_QUERY_RFC3986);
    $step3Url = $apiBase . '/drives/' . rawurlencode($driveId)
        . '/items/' . rawurlencode($itemId)
        . '/children?' . $step3Fields;
    $r3 = $doPost($step3Url, $step3Params);
    @unlink($cookieFile);

    if ($r3['body'] === false || $r3['error'] !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch docs listing', 'details' => $r3['error']]);
        exit;
    }

    $data = json_decode($r3['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(['error' => 'Upstream response is not valid JSON']);
        exit;
    }

    http_response_code($r3['code']);
    echo json_encode($data);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
