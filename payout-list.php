<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);

$listFile = __DIR__ . '/data/_list.json';
$raw = @file_get_contents($listFile);
$items = json_decode((string) $raw, true);

if ($raw === false || !is_array($items)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Payout schedules are unavailable.'
    ]);
    exit;
}

$publicItems = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $uid = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string) ($item['uid'] ?? '')
    );

    $title = trim((string) ($item['title'] ?? ''));
    $date = trim((string) ($item['date'] ?? ''));

    if ($uid === '' || $title === '' || $date === '') {
        continue;
    }

    $publicItems[] = [
        'uid' => $uid,
        'title' => $title,
        'date' => $date,
        'count' => max(0, (int) ($item['count'] ?? 0))
    ];
}

echo json_encode(
    $publicItems,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
