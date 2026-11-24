<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';

function respond_json($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)
    || empty($input['SysGen'])
    || empty($input['file'])) {
    respond_json(400, [
        'success' => false,
        'message' => 'Invalid request.'
    ]);
}

$file = paffp_sync_safe_filename($input['file']);

if ($file === '') {
    respond_json(400, [
        'success' => false,
        'message' => 'Invalid payout JSON filename.'
    ]);
}

$jsonFile = paffp_sync_source_path($file);

$requestedActor = trim(
    (string) ($input['Actor'] ?? ($input['VerifyEncoder'] ?? ''))
);
$actor = paffp_auth_effective_viewer($requestedActor);

if (paffp_auth_enabled() && $actor === '') {
    respond_json(401, [
        'success' => false,
        'auth_required' => true,
        'message' => 'Login is required to update verification records.'
    ]);
}

/*
 * In account mode, the authenticated session is authoritative for
 * VerifyEncoder. The browser cannot write another verifier username.
 */
if (
    paffp_auth_enabled()
    && array_key_exists('VerifyStatus', $input)
    && trim((string) $input['VerifyStatus']) !== ''
) {
    $input['VerifyEncoder'] = $actor;
    $input['Actor'] = $actor;
}

$payoutState = paffp_sync_payout_state($file, $actor);

if (empty($payoutState['open'])) {
    $reason = (string) ($payoutState['reason'] ?? 'manual');

    $message = 'Payout verification is currently closed.';

    if ($reason === 'accomplished') {
        $message = 'Payout is accomplished and automatically closed.';
    } elseif ($reason === 'accomplished_restricted') {
        $message = 'This accomplished payout is open only to selected verifiers.';
    }

    respond_json(423, [
        'success' => false,
        'message' => $message,
        'payout_open' => false,
        'payout_lock_reason' => $reason,
        'payout_access_mode' => (string) ($payoutState['access_mode'] ?? '')
    ]);
}

if (empty($payoutState['writable'])) {
    respond_json(423, [
        'success' => false,
        'message' => 'This payout is open for viewing only. Editing is disabled.',
        'payout_open' => true,
        'payout_writable' => false,
        'payout_lock_reason' => (string) ($payoutState['reason'] ?? 'temporary_global_view'),
        'payout_access_mode' => (string) ($payoutState['access_mode'] ?? 'temporary_view'),
        'temporary_expires_at' => (int) ($payoutState['temporary_expires_at'] ?? 0)
    ]);
}

if (!is_file($jsonFile)) {
    respond_json(404, [
        'success' => false,
        'message' => 'JSON file not found.'
    ]);
}

$lock = paffp_sync_open_lock($file, true);

if (!$lock) {
    respond_json(503, [
        'success' => false,
        'message' => 'Unable to initialize payout update storage. Ensure Apache can write to data/_sync and the payout JSON files.'
    ]);
}

try {
    $meta = paffp_sync_prepare_meta($file, $jsonFile);

    $raw = @file_get_contents($jsonFile);
    $data = json_decode((string) $raw, true);

    if ($raw === false || !is_array($data)) {
        paffp_sync_close_lock($lock);
        respond_json(500, [
            'success' => false,
            'message' => 'Invalid payout JSON.'
        ]);
    }

    $updated = false;
    $updatedRow = null;
    $targetSysGen = trim((string) $input['SysGen']);

    foreach ($data as &$row) {
        if (!isset($row['SysGen'])
            || trim((string) $row['SysGen']) !== $targetSysGen) {
            continue;
        }

        foreach (['VerifyStatus', 'VerifyEncoder', 'VerifyTimeStamp'] as $field) {
            if (array_key_exists($field, $input)) {
                $row[$field] = $input[$field];
            } else {
                unset($row[$field]);
            }
        }

        $updated = true;
        $updatedRow = $row;
        break;
    }
    unset($row);

    if (!$updated) {
        paffp_sync_close_lock($lock);
        respond_json(404, [
            'success' => false,
            'message' => 'SysGen not found.'
        ]);
    }

    if (!paffp_sync_write_json_file($jsonFile, $data, true)) {
        paffp_sync_close_lock($lock);
        respond_json(500, [
            'success' => false,
            'message' => 'Unable to save payout JSON.'
        ]);
    }

    $change = paffp_sync_append_change(
        $meta,
        $targetSysGen,
        paffp_sync_change_fields_from_row($updatedRow),
        $jsonFile
    );

    $journalSaved = paffp_sync_write_meta($file, $meta);

    paffp_sync_close_lock($lock);

    respond_json(200, [
        'success' => true,
        'message' => 'Updated successfully.',
        'file' => $file,
        'epoch' => $journalSaved ? $meta['epoch'] : null,
        'version' => $journalSaved ? (int) $meta['version'] : null,
        'journal' => $journalSaved,
        'change' => $journalSaved ? $change : null
    ]);
} catch (Throwable $e) {
    paffp_sync_close_lock($lock);

    respond_json(500, [
        'success' => false,
        'message' => 'Unexpected server error.'
    ]);
}
