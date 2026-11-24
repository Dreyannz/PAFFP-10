<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';

function upload_response($status, $payload)
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_response(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

$uid = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '',
    (string) ($_POST['uid'] ?? '')
);
$requestedActor = trim((string) ($_POST['actor'] ?? ''));
$actor = paffp_auth_effective_viewer($requestedActor);

if (paffp_auth_enabled() && $actor === '') {
    upload_response(401, [
        'success' => false,
        'auth_required' => true,
        'message' => 'Login is required to upload verification photos.'
    ]);
}
$folder = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '',
    (string) ($_POST['folder'] ?? '')
);

if ($uid === '' || $actor === '' || $folder === '' || !isset($_FILES['file'])) {
    upload_response(400, [
        'success' => false,
        'message' => 'Invalid upload request.'
    ]);
}

/*
 * Resolve the payout on the server. Do not trust a client-supplied payout
 * title or beneficiary JSON filename.
 */
$listPath = __DIR__ . '/data/_list.json';
$rawList = @file_get_contents($listPath);
$payouts = json_decode((string) $rawList, true);

if ($rawList === false || !is_array($payouts)) {
    upload_response(500, [
        'success' => false,
        'message' => 'Unable to read payout access metadata.'
    ]);
}

$payout = null;

foreach ($payouts as $item) {
    if (is_array($item) && (string) ($item['uid'] ?? '') === $uid) {
        $payout = $item;
        break;
    }
}

if (!is_array($payout)) {
    upload_response(404, [
        'success' => false,
        'message' => 'Payout event not found.'
    ]);
}

$payoutFile = paffp_sync_safe_filename(
    (string) ($payout['file'] ?? '')
);

if ($payoutFile === '') {
    upload_response(500, [
        'success' => false,
        'message' => 'Invalid payout configuration.'
    ]);
}

$payoutState = paffp_sync_payout_state($payoutFile, $actor);

if (empty($payoutState['open']) || empty($payoutState['writable'])) {
    upload_response(423, [
        'success' => false,
        'message' => empty($payoutState['open'])
            ? 'Access Restricted'
            : 'This payout is open for viewing only. Photo upload is disabled.',
        'payout_open' => !empty($payoutState['open']),
        'payout_writable' => !empty($payoutState['writable']),
        'payout_lock_reason' => (string) ($payoutState['reason'] ?? '')
    ]);
}

$file = $_FILES['file'];
$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($uploadError !== UPLOAD_ERR_OK) {
    upload_response(400, [
        'success' => false,
        'message' => 'The photo upload was incomplete.'
    ]);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$fileSize = max(0, (int) ($file['size'] ?? 0));
$originalName = basename((string) ($file['name'] ?? ''));

if (!is_uploaded_file($tmpName)) {
    upload_response(400, [
        'success' => false,
        'message' => 'Invalid uploaded file.'
    ]);
}

if ($fileSize <= 0 || $fileSize > 12 * 1024 * 1024) {
    upload_response(413, [
        'success' => false,
        'message' => 'Photo must be between 1 byte and 12 MB.'
    ]);
}

/*
 * Camera capture always sends JPEG. Validate the actual image content, not
 * only the extension, and only permit the four expected PAFFP photo names.
 */
$imageInfo = @getimagesize($tmpName);

if (!is_array($imageInfo)
    || !isset($imageInfo[2])
    || (int) $imageInfo[2] !== IMAGETYPE_JPEG) {

    upload_response(415, [
        'success' => false,
        'message' => 'Only valid JPEG photos are accepted.'
    ]);
}

$allowedNames = [
    $folder . '_Farmer.jpg',
    $folder . '_FarmerwithID.jpg',
    $folder . '_IDFront.jpg',
    $folder . '_IDBack.jpg'
];

if (!in_array($originalName, $allowedNames, true)) {
    upload_response(400, [
        'success' => false,
        'message' => 'Unexpected PAFFP photo filename.'
    ]);
}

$tableTitle = preg_replace(
    '/[^A-Za-z0-9 _-]/',
    '',
    trim((string) ($payout['title'] ?? ''))
);

if ($tableTitle === '') {
    $tableTitle = $uid;
}

$baseDir = __DIR__ . '/uploadID';
$tableDir = $baseDir . '/' . $tableTitle;
$targetDir = $tableDir . '/' . $folder;

foreach ([$baseDir, $tableDir, $targetDir] as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        upload_response(500, [
            'success' => false,
            'message' => 'Unable to prepare the photo upload folder.'
        ]);
    }
}

/*
 * Apache defense-in-depth for the photo tree. Uploaded files are already
 * restricted to valid JPEGs, but this prevents script execution/indexing if
 * other files are ever placed here manually.
 */
$uploadHtaccess = $baseDir . '/.htaccess';

if (!is_file($uploadHtaccess)) {
    @file_put_contents(
        $uploadHtaccess,
        "Options -Indexes\n"
        . "<IfModule mod_authz_core.c>\n"
        . "  <FilesMatch \"\\.(?:php|phtml|phar|cgi|pl|py|sh)$\">\n"
        . "    Require all denied\n"
        . "  </FilesMatch>\n"
        . "</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n"
        . "  <FilesMatch \"\\.(?:php|phtml|phar|cgi|pl|py|sh)$\">\n"
        . "    Order allow,deny\n"
        . "    Deny from all\n"
        . "  </FilesMatch>\n"
        . "</IfModule>\n",
        LOCK_EX
    );
}

$targetFile = $targetDir . '/' . $originalName;

if (is_file($targetFile) && !@unlink($targetFile)) {
    upload_response(500, [
        'success' => false,
        'message' => 'Unable to replace the existing photo.'
    ]);
}

if (!@move_uploaded_file($tmpName, $targetFile)) {
    upload_response(500, [
        'success' => false,
        'message' => 'Failed to save the photo.'
    ]);
}

@chmod($targetFile, 0644);

upload_response(200, [
    'success' => true,
    'message' => 'Photo uploaded successfully.',
    'path' => 'uploadID/'
        . rawurlencode($tableTitle)
        . '/'
        . rawurlencode($folder)
        . '/'
        . rawurlencode($originalName)
]);
