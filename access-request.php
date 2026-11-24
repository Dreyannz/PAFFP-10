<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';

function access_request_response($status, $payload)
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    access_request_response(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

$uid = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '',
    (string) ($_POST['uid'] ?? '')
);

$viewer = paffp_sync_access_request_clean_viewer(
    paffp_auth_effective_viewer(
        $_POST['viewer'] ?? ''
    )
);

if (paffp_auth_enabled() && $viewer === '') {
    access_request_response(401, [
        'success' => false,
        'auth_required' => true,
        'message' => 'Login is required to request payout access.'
    ]);
}

$reason = preg_replace(
    '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
    '',
    (string) ($_POST['reason'] ?? '')
);
$reason = trim((string) $reason);

if (strlen($reason) > 500) {
    $reason = substr($reason, 0, 500);
}

if ($uid === '' || $viewer === '') {
    access_request_response(400, [
        'success' => false,
        'message' => 'Missing payout or verifier username.'
    ]);
}

$payout = paffp_sync_find_payout_by_uid($uid);

if (!is_array($payout)) {
    access_request_response(404, [
        'success' => false,
        'message' => 'Payout event not found.'
    ]);
}

$file = paffp_sync_safe_filename(
    (string) ($payout['file'] ?? '')
);

if ($file === '') {
    access_request_response(500, [
        'success' => false,
        'message' => 'Invalid payout configuration.'
    ]);
}

/*
 * Do not create a request when this username is already authorized by the
 * current payout rules. This also detects an already-approved active request.
 */
$state = paffp_sync_payout_state($file, $viewer);

if (!empty($state['open'])) {
    $requestState = paffp_sync_access_request_state(
        $uid,
        $viewer
    );

    access_request_response(200, [
        'success' => true,
        'already_open' => true,
        'message' => 'Access is already available for this payout.',
        'request' => $requestState
    ]);
}

$result = paffp_sync_create_access_request(
    $uid,
    $viewer,
    $reason
);

if (empty($result['success'])) {
    access_request_response(500, [
        'success' => false,
        'message' =>
            (string) ($result['message']
                ?? 'Unable to submit the access request.')
    ]);
}

$request = is_array($result['request'] ?? null)
    ? $result['request']
    : [];

$message = !empty($result['created'])
    ? 'Access request submitted. Please wait for Admin approval.'
    : (
        ($request['status'] ?? '') === 'pending'
            ? 'Your access request is already pending.'
            : 'Access request is already active.'
    );

access_request_response(200, [
    'success' => true,
    'created' => !empty($result['created']),
    'message' => $message,
    'request' => $request
]);
