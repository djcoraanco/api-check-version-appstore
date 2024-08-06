<?php

/*
{
  "version": "1.5.3",
  "packageName": "com.domain.name",
  "bundleId": "com.domain.name"
}
*/


function getPlayStoreVersion($packageName) {
    $url = "https://play.google.com/store/apps/details?id=" . urlencode($packageName) . "&hl=en&gl=US";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cache-Control: no-cache, no-store, must-revalidate',
        'Pragma: no-cache',
        'Expires: 0'
    ]);
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    error_log("Play Store request URL: $url");
    error_log("Play Store HTTP Code: $httpCode");
    error_log("Play Store curl error: $error");
    error_log("Play Store raw output (first 1000 chars): " . substr($output, 0, 1000));

    if ($httpCode != 200) {
        error_log("Error en getPlayStoreVersion: HTTP Code $httpCode, Error: $error, URL: $url");
        return null;
    }

    if (preg_match('/"softwareVersion":\s*"([\d.]+)"/', $output, $matches)) {
        error_log("Play Store version encontrada (softwareVersion): " . $matches[1]);
        return $matches[1];
    }

    if (preg_match('/Current Version<\/div><span class="htlgb"><div><span class="htlgb">([\d.]+)<\/span>/', $output, $matches)) {
        error_log("Play Store version encontrada (Current Version): " . $matches[1]);
        return $matches[1];
    }

    error_log("No se pudo encontrar la versión en getPlayStoreVersion.");
    return null;
}

function getAppStoreVersion($bundleId) {
    $url = "https://itunes.apple.com/lookup?bundleId=" . urlencode($bundleId) . "&t=" . time();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cache-Control: no-cache, no-store, must-revalidate',
        'Pragma: no-cache',
        'Expires: 0'
    ]);
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    error_log("App Store request URL: $url");
    error_log("App Store HTTP Code: $httpCode");
    error_log("App Store curl error: $error");
    error_log("App Store raw output: $output");

    if ($httpCode != 200) {
        error_log("Error en getAppStoreVersion: HTTP Code $httpCode, Error: $error, URL: $url");
        return null;
    }

    $data = json_decode($output, true);
    if (isset($data['results'][0]['version'])) {
        error_log("App Store version encontrada: " . $data['results'][0]['version']);
        return $data['results'][0]['version'];
    }

    error_log("No se pudo encontrar la versión en getAppStoreVersion.");
    return null;
}

function compareVersions($currentVersion, $latestVersion) {
    return version_compare($currentVersion, $latestVersion) < 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentVersion = $input['version'] ?? null;
    $packageName = $input['packageName'] ?? null;
    $bundleId = $input['bundleId'] ?? null;

    error_log("Received input: " . json_encode($input));

    if ($currentVersion && $packageName && $bundleId) {
        $playStoreVersion = getPlayStoreVersion($packageName);
        $appStoreVersion = getAppStoreVersion($bundleId);
        
        $isPlayStoreUpdateAvailable = $playStoreVersion ? compareVersions($currentVersion, $playStoreVersion) : false;
        $isAppStoreUpdateAvailable = $appStoreVersion ? compareVersions($currentVersion, $appStoreVersion) : false;

        $response = [
            'playStore' => [
                'latestVersion' => $playStoreVersion,
                'updateAvailable' => $isPlayStoreUpdateAvailable
            ],
            'appStore' => [
                'latestVersion' => $appStoreVersion,
                'updateAvailable' => $isAppStoreUpdateAvailable
            ]
        ];

        error_log("Final response: " . json_encode($response));

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($response);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
