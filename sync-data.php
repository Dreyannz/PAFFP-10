<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Accept-Encoding');

require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';

function sync_response($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$file = paffp_sync_safe_filename($_GET['file'] ?? '');
$requestedViewer = trim((string) ($_GET['viewer'] ?? ''));
$viewer = paffp_auth_effective_viewer($requestedViewer);

if (paffp_auth_enabled() && $viewer === '') {
    sync_response(401, [
        'success' => false,
        'auth_required' => true,
        'message' => 'Login is required to access payout verification.',
        'login_url' => paffp_auth_login_url(
            'verify.php?'
            . http_build_query([
                'id' => (string) ($_GET['uid'] ?? '')
            ])
        )
    ]);
}
$clientEpoch = trim((string) ($_GET['epoch'] ?? ''));
$since = isset($_GET['since']) ? max(0, (int) $_GET['since']) : 0;
$forceFull = isset($_GET['full']) && (string) $_GET['full'] === '1';
$stateOnly = isset($_GET['state_only']) && (string) $_GET['state_only'] === '1';
$uid = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '',
    (string) ($_GET['uid'] ?? '')
);

/*
 * Lightweight access preflight.
 *
 * This deliberately reads only _list.json and never opens the beneficiary
 * JSON file. Verify uses it before localStorage/table initialization so a
 * restricted payout does not load cached beneficiary records or fetch the
 * payout dataset.
 */
if ($stateOnly) {
    if ($uid === '') {
        sync_response(400, [
            'success' => false,
            'message' => 'Invalid payout identifier.'
        ]);
    }

    $listPath = __DIR__ . '/data/_list.json';
    $rawList = @file_get_contents($listPath);
    $payouts = json_decode((string) $rawList, true);

    if ($rawList === false || !is_array($payouts)) {
        sync_response(500, [
            'success' => false,
            'message' => 'Unable to read payout access metadata.'
        ]);
    }

    $matchedPayout = null;

    foreach ($payouts as $payout) {
        if ((string) ($payout['uid'] ?? '') === $uid) {
            $matchedPayout = $payout;
            break;
        }
    }

    if (!is_array($matchedPayout)) {
        sync_response(404, [
            'success' => false,
            'message' => 'Payout event not found.'
        ]);
    }

    $matchedFile = paffp_sync_safe_filename(
        (string) ($matchedPayout['file'] ?? '')
    );

    if ($matchedFile === '') {
        sync_response(500, [
            'success' => false,
            'message' => 'Invalid payout configuration.'
        ]);
    }

    $state = paffp_sync_payout_state($matchedFile, $viewer);
    $isOpen = !empty($state['open']);
    $isWritable = $isOpen && !empty($state['writable']);
    $accessRequestState = paffp_sync_access_request_state(
        $uid,
        $viewer
    );

    $accessRevisionPayload = [
        'enabled' => $matchedPayout['enabled'] ?? null,
        'accomplished_access_mode' =>
            $matchedPayout['accomplished_access_mode'] ?? null,
        'accomplished_access_all_permission' =>
            $matchedPayout['accomplished_access_all_permission'] ?? 'edit',
        'accomplished_access_users' =>
            $matchedPayout['accomplished_access_users'] ?? [],
        'accomplished_access_permissions' =>
            $matchedPayout['accomplished_access_permissions'] ?? [],
        'state_reason' => (string) ($state['reason'] ?? ''),
        'state_access_mode' => (string) ($state['access_mode'] ?? ''),
        'state_writable' => $isWritable,
        'temporary_expires_at' =>
            (int) ($state['temporary_expires_at'] ?? 0),
        'access_request_id' =>
            (string) ($accessRequestState['id'] ?? ''),
        'access_request_status' =>
            (string) ($accessRequestState['status'] ?? 'none'),
        'access_request_permission' =>
            (string) ($accessRequestState['permission'] ?? ''),
        'access_request_expires_at' =>
            (int) ($accessRequestState['expires_at'] ?? 0),
        'access_request_updated_ts' =>
            (int) ($accessRequestState['updated_ts'] ?? 0)
    ];

    $accessRevision = sha1(
        json_encode(
            $accessRevisionPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
    );

    $response = [
        'success' => true,
        'mode' => 'state',
        'payout_open' => $isOpen,
        'payout_writable' => $isWritable,
        'payout_lock_reason' => (string) ($state['reason'] ?? ''),
        'payout_access_mode' => (string) ($state['access_mode'] ?? 'normal'),
        'all_permission' => (string) ($state['all_permission'] ?? ''),
        'individual_permission' => (string) ($state['individual_permission'] ?? ''),
        'access_revision' => $accessRevision,
        'temporary_expires_at' => (int) ($state['temporary_expires_at'] ?? 0),
        'temporary_audience' => (string) ($state['temporary_audience'] ?? ''),
        'temporary_audience_value' => (string) ($state['temporary_audience_value'] ?? ''),
        'access_request_id' => (string) ($accessRequestState['id'] ?? ''),
        'access_request_status' => (string) ($accessRequestState['status'] ?? 'none'),
        'access_request_permission' => (string) ($accessRequestState['permission'] ?? ''),
        'access_request_expires_at' => (int) ($accessRequestState['expires_at'] ?? 0)
    ];

    /*
     * Only disclose the dataset filename/metadata after the viewer passes
     * the access check. Restricted responses contain no beneficiary file
     * location and no beneficiary records.
     */
    if ($isOpen) {
        $response['uid'] = $uid;
        $response['title'] = (string) ($matchedPayout['title'] ?? '');
        $response['file'] = $matchedFile;
        $response['date'] = (string) ($matchedPayout['date'] ?? '');
    }

    sync_response(200, $response);
}

if ($file === '') {
    sync_response(400, [
        'success' => false,
        'message' => 'Invalid payout JSON filename.'
    ]);
}

$jsonFile = paffp_sync_source_path($file);
$payoutState = paffp_sync_payout_state($file, $viewer);
$payoutOpen = !empty($payoutState['open']);
$payoutWritable = $payoutOpen && !empty($payoutState['writable']);
$payoutLockReason = (string) ($payoutState['reason'] ?? '');
$payoutAccessMode = (string) ($payoutState['access_mode'] ?? 'normal');
$temporaryExpiresAt = (int) ($payoutState['temporary_expires_at'] ?? 0);

/*
 * Hard server-side privacy gate:
 * never open/read the beneficiary JSON when the payout is restricted.
 */
if (!$payoutOpen) {
    sync_response(423, [
        'success' => false,
        'access_restricted' => true,
        'payout_open' => false,
        'payout_writable' => false,
        'payout_lock_reason' => $payoutLockReason,
        'payout_access_mode' => $payoutAccessMode,
        'message' => 'Access Restricted'
    ]);
}

if (!is_file($jsonFile)) {
    sync_response(404, [
        'success' => false,
        'message' => 'Payout JSON not found.'
    ]);
}

$lock = paffp_sync_open_lock($file, true);

if (!$lock) {
    sync_response(503, [
        'success' => false,
        'message' => 'Unable to initialize payout synchronization. Ensure Apache can write to data/_sync.'
    ]);
}

try {
    $meta = paffp_sync_prepare_meta($file, $jsonFile);

    $serverEpoch = (string) $meta['epoch'];
    $serverVersion = (int) $meta['version'];
    $floorVersion = (int) $meta['floor_version'];

    $needsFull = $forceFull
        || $clientEpoch === ''
        || !hash_equals($serverEpoch, $clientEpoch)
        || $since < $floorVersion
        || $since > $serverVersion;

    if ($needsFull) {
        $raw = @file_get_contents($jsonFile);
        $data = json_decode((string) $raw, true);

        if ($raw === false || !is_array($data)) {
            paffp_sync_close_lock($lock);
            sync_response(500, [
                'success' => false,
                'message' => 'Invalid payout JSON.'
            ]);
        }

        paffp_sync_close_lock($lock);

        sync_response(200, [
            'success' => true,
            'mode' => 'full',
            'epoch' => $serverEpoch,
            'version' => $serverVersion,
            'payout_open' => $payoutOpen,
            'payout_writable' => $payoutWritable,
            'payout_lock_reason' => $payoutLockReason,
            'payout_access_mode' => $payoutAccessMode,
            'temporary_expires_at' => $temporaryExpiresAt,
            'data' => $data
        ]);
    }

    $changes = [];

    foreach ($meta['changes'] as $change) {
        if ((int) ($change['version'] ?? 0) > $since) {
            $changes[] = $change;
        }
    }

    paffp_sync_close_lock($lock);

    sync_response(200, [
        'success' => true,
        'mode' => empty($changes) ? 'none' : 'delta',
        'epoch' => $serverEpoch,
        'version' => $serverVersion,
        'payout_open' => $payoutOpen,
        'payout_writable' => $payoutWritable,
        'payout_lock_reason' => $payoutLockReason,
        'payout_access_mode' => $payoutAccessMode,
        'temporary_expires_at' => $temporaryExpiresAt,
        'changes' => $changes
    ]);
} catch (Throwable $e) {
    paffp_sync_close_lock($lock);

    sync_response(500, [
        'success' => false,
        'message' => 'Unexpected server error.'
    ]);
}
