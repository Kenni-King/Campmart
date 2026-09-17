<?php
include_once 'dashboard/lib/controller.php';

$store = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['store'] ?? '');
$size = (int)($_GET['size'] ?? 300);
$size = max(100, min(500, $size));

if ($store === '') {
    $storeId = $_GET['id'] ?? '';
    if ($storeId) {
        $user = $db->query("SELECT pubkey FROM users WHERE id='".$db->real_escape_string($storeId)."' LIMIT 1");
        if ($user && $user->num_rows) {
            $row = $user->fetch_assoc();
            $store = $row['pubkey'] ?? '';
        }
    }
}

if ($store === '') {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">
        <rect width="'.$size.'" height="'.$size.'" fill="#f3f4f6" rx="20"/>
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-size="14" font-family="sans-serif">No store ID</text>
    </svg>';
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname(dirname($_SERVER['SCRIPT_NAME']));
$storeUrl = rtrim($scheme.'://'.$host.$path, '/').'/qrstore/'.urlencode($store);

$qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs='.$size.'x'.$size.'&chl='.urlencode($storeUrl).'&choe=UTF-8';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$fetched = false;

if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $data = @file_get_contents($qrUrl, false, $ctx);
    if ($data !== false) {
        echo $data;
        $fetched = true;
    }
}

if (!$fetched && function_exists('curl_version')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $qrUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data !== false && $httpCode === 200) {
        echo $data;
        $fetched = true;
    }
}

if (!$fetched) {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">
        <defs>
            <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#f48c25;stop-opacity:1"/>
                <stop offset="100%" style="stop-color:#ea580c;stop-opacity:1"/>
            </linearGradient>
        </defs>
        <rect width="'.$size.'" height="'.$size.'" fill="url(#bg)" rx="20"/>
        <text x="50%" y="40%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="'.round($size/10).'" font-family="sans-serif" font-weight="bold">Scan Me</text>
        <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,0.8)" font-size="'.round($size/20).'" font-family="monospace">'.htmlspecialchars($store).'</text>
        <text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,0.6)" font-size="'.round($size/25).'" font-family="sans-serif">Visit store to view</text>
    </svg>';
}
