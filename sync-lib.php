<?php

if (!defined('PAFFP_SYNC_MAX_CHANGES')) {
    define('PAFFP_SYNC_MAX_CHANGES', 3000);
}

function paffp_sync_data_dir()
{
    return __DIR__ . '/data';
}

function paffp_sync_dir()
{
    return paffp_sync_data_dir() . '/_sync';
}

function paffp_sync_ensure_dir()
{
    $dir = paffp_sync_dir();

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    if (is_dir($dir)) {
        @chmod($dir, 0777);
    }

    return is_dir($dir) && is_writable($dir);
}

function paffp_sync_safe_filename($file)
{
    $file = basename((string) $file);

    if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json') {
        return '';
    }

    if (strpos($file, '_') === 0) {
        return '';
    }

    return $file;
}

function paffp_sync_key($file)
{
    return sha1((string) $file);
}

function paffp_sync_meta_path($file)
{
    return paffp_sync_dir() . '/' . paffp_sync_key($file) . '.json';
}

function paffp_sync_lock_path($file)
{
    return paffp_sync_dir() . '/' . paffp_sync_key($file) . '.lock';
}

function paffp_sync_source_path($file)
{
    return paffp_sync_data_dir() . '/' . $file;
}

function paffp_sync_signature($path)
{
    clearstatcache(true, $path);

    return [
        'mtime' => is_file($path) ? (int) @filemtime($path) : 0,
        'size' => is_file($path) ? (int) @filesize($path) : 0
    ];
}

function paffp_sync_new_epoch()
{
    try {
        return bin2hex(random_bytes(12));
    } catch (Exception $e) {
        return sha1(uniqid('', true) . mt_rand());
    }
}

function paffp_sync_new_meta($file, $sourcePath)
{
    $signature = paffp_sync_signature($sourcePath);

    return [
        'file' => $file,
        'epoch' => paffp_sync_new_epoch(),
        'version' => 1,
        'floor_version' => 1,
        'changes' => [],
        'source_mtime' => $signature['mtime'],
        'source_size' => $signature['size'],
        'updated_ts' => time()
    ];
}

function paffp_sync_read_meta($file)
{
    $path = paffp_sync_meta_path($file);

    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode((string) $raw, true);

    if (!is_array($decoded)
        || empty($decoded['epoch'])
        || !isset($decoded['version'])
        || !isset($decoded['floor_version'])
        || !isset($decoded['changes'])
        || !is_array($decoded['changes'])) {
        return null;
    }

    return $decoded;
}

function paffp_sync_json_text_is_valid($content)
{
    if (!is_string($content) || $content === '') {
        return false;
    }

    json_decode($content, true);

    return json_last_error() === JSON_ERROR_NONE;
}

function paffp_sync_atomic_write($path, $content, $validateJson = false)
{
    $path = (string) $path;
    $content = (string) $content;
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return false;
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $mode = 0777;

    if (is_file($path)) {
        $existingMode = @fileperms($path);

        if ($existingMode !== false) {
            $mode = $existingMode & 0777;
        }
    }

    /*
     * Keep the temporary file in the same directory as the live file so the
     * final rename is atomic on the normal Debian/Apache filesystem.
     */
    $tmp = @tempnam(
        $dir,
        '.' . basename($path) . '.tmp.'
    );

    if ($tmp === false) {
        return false;
    }

    $handle = @fopen($tmp, 'wb');

    if (!$handle) {
        @unlink($tmp);
        return false;
    }

    $length = strlen($content);
    $offset = 0;
    $ok = true;

    /*
     * Write in bounded chunks and verify the total byte count. This protects
     * against short/partial writes caused by disk-full or I/O failures.
     */
    while ($offset < $length) {
        $chunk = substr(
            $content,
            $offset,
            min(1048576, $length - $offset)
        );

        $written = @fwrite($handle, $chunk);

        if ($written === false || $written <= 0) {
            $ok = false;
            break;
        }

        $offset += $written;
    }

    if ($ok && $offset !== $length) {
        $ok = false;
    }

    if ($ok && !@fflush($handle)) {
        $ok = false;
    }

    /*
     * fsync() is unavailable on PHP 7.4, but use it automatically on newer
     * PHP versions while keeping full PHP 7.4 compatibility.
     */
    if ($ok && function_exists('fsync')) {
        if (!@fsync($handle)) {
            $ok = false;
        }
    }

    @fclose($handle);

    if (!$ok) {
        @unlink($tmp);
        return false;
    }

    clearstatcache(true, $tmp);

    if ((int) @filesize($tmp) !== $length) {
        @unlink($tmp);
        return false;
    }

    /*
     * Validate the completed temporary JSON BEFORE touching the live file.
     */
    if ($validateJson) {
        $check = @file_get_contents($tmp);

        if ($check === false
            || strlen($check) !== $length
            || !paffp_sync_json_text_is_valid($check)) {

            @unlink($tmp);
            return false;
        }
    }

    @chmod($tmp, $mode);

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    @chmod($path, $mode);
    clearstatcache(true, $path);

    if ((int) @filesize($path) !== $length) {
        return false;
    }

    if ($validateJson) {
        $finalCheck = @file_get_contents($path);

        if ($finalCheck === false
            || strlen($finalCheck) !== $length
            || !paffp_sync_json_text_is_valid($finalCheck)) {

            return false;
        }
    }

    return true;
}

function paffp_sync_write_validated_json_text($path, $content)
{
    $content = (string) $content;

    if (!paffp_sync_json_text_is_valid($content)) {
        return false;
    }

    return paffp_sync_atomic_write(
        $path,
        $content,
        true
    );
}

function paffp_sync_write_json_file($path, $value, $pretty)
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if ($pretty) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $encoded = json_encode($value, $flags);

    if ($encoded === false) {
        return false;
    }

    return paffp_sync_atomic_write(
        $path,
        $encoded,
        true
    );
}

function paffp_sync_write_meta($file, $meta)
{
    return paffp_sync_write_json_file(
        paffp_sync_meta_path($file),
        $meta,
        false
    );
}

function paffp_sync_meta_matches_source($meta, $sourcePath)
{
    if (!is_array($meta)) {
        return false;
    }

    $signature = paffp_sync_signature($sourcePath);

    return (int) ($meta['source_mtime'] ?? -1) === $signature['mtime']
        && (int) ($meta['source_size'] ?? -1) === $signature['size'];
}

function paffp_sync_reset_meta($file, $sourcePath)
{
    $meta = paffp_sync_new_meta($file, $sourcePath);
    paffp_sync_write_meta($file, $meta);
    return $meta;
}

function paffp_sync_invalidate($file)
{
    $file = paffp_sync_safe_filename($file);

    if ($file === '') {
        return false;
    }

    if (!paffp_sync_ensure_dir()) {
        return false;
    }

    $metaPath = paffp_sync_meta_path($file);

    if (is_file($metaPath)) {
        return @unlink($metaPath);
    }

    return true;
}

function paffp_sync_open_lock($file, $exclusive)
{
    if (!paffp_sync_ensure_dir()) {
        return false;
    }

    $handle = @fopen(paffp_sync_lock_path($file), 'c+');

    if (!$handle) {
        return false;
    }

    if (!@flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
        @fclose($handle);
        return false;
    }

    return $handle;
}

function paffp_sync_close_lock($handle)
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function paffp_sync_prepare_meta($file, $sourcePath)
{
    $meta = paffp_sync_read_meta($file);

    if (!$meta || !paffp_sync_meta_matches_source($meta, $sourcePath)) {
        $meta = paffp_sync_reset_meta($file, $sourcePath);
    }

    return $meta;
}

function paffp_sync_change_fields_from_row($row)
{
    $fields = [];

    foreach (['VerifyStatus', 'VerifyEncoder', 'VerifyTimeStamp'] as $field) {
        $fields[$field] = array_key_exists($field, $row)
            ? $row[$field]
            : null;
    }

    return $fields;
}

function paffp_sync_append_change(&$meta, $sysGen, $fields, $sourcePath)
{
    $nextVersion = (int) ($meta['version'] ?? 1) + 1;

    $change = [
        'version' => $nextVersion,
        'SysGen' => (string) $sysGen,
        'fields' => $fields
    ];

    $changes = isset($meta['changes']) && is_array($meta['changes'])
        ? $meta['changes']
        : [];

    $changes[] = $change;

    $max = PAFFP_SYNC_MAX_CHANGES;

    if (count($changes) > $max) {
        $dropCount = count($changes) - $max;
        $dropped = array_splice($changes, 0, $dropCount);
        $lastDropped = end($dropped);

        if (is_array($lastDropped) && isset($lastDropped['version'])) {
            $meta['floor_version'] = (int) $lastDropped['version'];
        }
    }

    $signature = paffp_sync_signature($sourcePath);

    $meta['version'] = $nextVersion;
    $meta['changes'] = array_values($changes);
    $meta['source_mtime'] = $signature['mtime'];
    $meta['source_size'] = $signature['size'];
    $meta['updated_ts'] = time();

    return $change;
}

function paffp_sync_normalize_access_users($value)
{
    if (!is_array($value)) {
        $value = preg_split('/[\r\n,;]+/', (string) $value);
    }

    $users = [];
    $seen = [];

    foreach ($value as $item) {
        $name = trim((string) $item);

        if ($name === '') {
            continue;
        }

        $key = strtolower($name);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $users[] = $name;
    }

    return $users;
}

function paffp_sync_temporary_access_state()
{
    $state = [
        'active' => false,
        'expires_at' => 0,
        'mode' => 'edit',
        'writable' => false,
        'audience' => 'all',
        'audience_value' => ''
    ];

    $file = __DIR__ . '/data/_temporaryAccess.json';

    if (!is_file($file) || !is_readable($file)) {
        return $state;
    }

    $raw = @file_get_contents($file);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return $state;
    }

    $enabled = !empty($decoded['enabled']);
    $expiresAt = max(0, (int) ($decoded['expires_at'] ?? 0));

    $mode = strtolower(trim((string) ($decoded['mode'] ?? 'edit')));

    if (!in_array($mode, ['view', 'edit'], true)) {
        $mode = 'edit';
    }

    $audience = strtolower(trim((string) ($decoded['audience'] ?? 'all')));

    if (!in_array($audience, ['all', 'user'], true)) {
        $audience = 'all';
    }

    $audienceValue = trim((string) ($decoded['audience_value'] ?? ''));

    if ($audience === 'all') {
        $audienceValue = '';
    }

    $state['active'] = $enabled && $expiresAt > time();
    $state['expires_at'] = $expiresAt;
    $state['mode'] = $mode;
    $state['writable'] = $state['active'] && $mode === 'edit';
    $state['audience'] = $audience;
    $state['audience_value'] = $audienceValue;

    return $state;
}

function paffp_sync_normalize_access_permissions($raw, $users = [])
{
    $permissions = [];

    if (is_array($raw)) {
        foreach ($raw as $name => $permission) {
            $cleanName = trim((string) $name);
            $cleanPermission = strtolower(trim((string) $permission));

            if ($cleanName === '') {
                continue;
            }

            if (!in_array($cleanPermission, ['view', 'edit'], true)) {
                $cleanPermission = 'edit';
            }

            $permissions[strtolower($cleanName)] = $cleanPermission;
        }
    }

    foreach ((array) $users as $name) {
        $key = strtolower(trim((string) $name));

        if ($key === '') {
            continue;
        }

        if (!array_key_exists($key, $permissions)) {
            // Old individual-access entries were fully editable.
            $permissions[$key] = 'edit';
        }
    }

    return $permissions;
}


function paffp_sync_access_requests_path()
{
    return paffp_sync_data_dir() . '/_accessRequests.json';
}

function paffp_sync_access_requests_lock_path()
{
    return paffp_sync_dir() . '/_accessRequests.lock';
}

function paffp_sync_access_requests_open_lock($exclusive = true)
{
    if (!paffp_sync_ensure_dir()) {
        return false;
    }

    $handle = @fopen(
        paffp_sync_access_requests_lock_path(),
        'c+'
    );

    if (!$handle) {
        return false;
    }

    if (!@flock(
        $handle,
        $exclusive ? LOCK_EX : LOCK_SH
    )) {
        @fclose($handle);
        return false;
    }

    return $handle;
}

function paffp_sync_access_requests_load()
{
    $path = paffp_sync_access_requests_path();

    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $items = json_decode((string) $raw, true);

    return is_array($items)
        ? array_values($items)
        : [];
}

function paffp_sync_access_requests_save($items)
{
    return paffp_sync_write_json_file(
        paffp_sync_access_requests_path(),
        array_values((array) $items),
        true
    );
}

function paffp_sync_access_request_clean_viewer($viewer)
{
    $viewer = preg_replace(
        '/[\x00-\x1F\x7F]/u',
        '',
        (string) $viewer
    );

    $viewer = trim((string) $viewer);

    if (strlen($viewer) > 120) {
        $viewer = substr($viewer, 0, 120);
    }

    return $viewer;
}

function paffp_sync_find_payout_by_uid($uid)
{
    $uid = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string) $uid
    );

    if ($uid === '') {
        return null;
    }

    $listFile = paffp_sync_data_dir() . '/_list.json';

    if (!is_file($listFile) || !is_readable($listFile)) {
        return null;
    }

    $raw = @file_get_contents($listFile);
    $items = json_decode((string) $raw, true);

    if ($raw === false || !is_array($items)) {
        return null;
    }

    foreach ($items as $item) {
        if ((string) ($item['uid'] ?? '') === $uid) {
            return is_array($item) ? $item : null;
        }
    }

    return null;
}

function paffp_sync_access_request_state_from_items(
    $items,
    $uid,
    $viewer,
    $now = null
) {
    $uid = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string) $uid
    );
    $viewer = paffp_sync_access_request_clean_viewer($viewer);
    $now = $now === null ? time() : (int) $now;

    $empty = [
        'exists' => false,
        'active' => false,
        'id' => '',
        'status' => 'none',
        'permission' => '',
        'reason' => '',
        'expires_at' => 0,
        'expiry_mode' => '',
        'requested_at' => 0,
        'decision_at' => 0,
        'updated_ts' => 0
    ];

    if ($uid === '' || $viewer === '') {
        return $empty;
    }

    $latest = null;
    $latestScore = -1;

    foreach ((array) $items as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string) ($item['payout_uid'] ?? '') !== $uid) {
            continue;
        }

        if (strcasecmp(
            trim((string) ($item['username'] ?? '')),
            $viewer
        ) !== 0) {
            continue;
        }

        $score = max(
            (int) ($item['updated_ts'] ?? 0),
            (int) ($item['decision_at'] ?? 0),
            (int) ($item['requested_at'] ?? 0)
        );

        if ($latest === null || $score >= $latestScore) {
            $latest = $item;
            $latestScore = $score;
        }
    }

    if (!is_array($latest)) {
        return $empty;
    }

    $status = strtolower(
        trim((string) ($latest['status'] ?? 'pending'))
    );

    if (!in_array(
        $status,
        ['pending', 'approved', 'rejected', 'revoked'],
        true
    )) {
        $status = 'pending';
    }

    $permission = strtolower(
        trim((string) ($latest['permission'] ?? ''))
    );

    if (!in_array($permission, ['view', 'edit'], true)) {
        $permission = '';
    }

    $expiresAt = (int) ($latest['expires_at'] ?? 0);
    $active =
        $status === 'approved' &&
        $expiresAt > $now &&
        in_array($permission, ['view', 'edit'], true);

    if ($status === 'approved' && !$active) {
        $status = 'expired';
    }

    return [
        'exists' => true,
        'active' => $active,
        'id' => (string) ($latest['id'] ?? ''),
        'status' => $status,
        'permission' => $permission,
        'reason' => (string) ($latest['reason'] ?? ''),
        'expires_at' => $expiresAt,
        'expiry_mode' => (string) ($latest['expiry_mode'] ?? ''),
        'requested_at' => (int) ($latest['requested_at'] ?? 0),
        'decision_at' => (int) ($latest['decision_at'] ?? 0),
        'updated_ts' => (int) ($latest['updated_ts'] ?? 0),
        'payout_title' => (string) ($latest['payout_title'] ?? ''),
        'username' => (string) ($latest['username'] ?? $viewer)
    ];
}

function paffp_sync_access_request_state(
    $uid,
    $viewer,
    $now = null
) {
    return paffp_sync_access_request_state_from_items(
        paffp_sync_access_requests_load(),
        $uid,
        $viewer,
        $now
    );
}

function paffp_sync_create_access_request($uid, $viewer, $reason = '')
{
    $uid = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string) $uid
    );
    $viewer = paffp_sync_access_request_clean_viewer($viewer);

    $reason = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        (string) $reason
    );
    $reason = trim((string) $reason);

    if (strlen($reason) > 500) {
        $reason = substr($reason, 0, 500);
    }

    if ($uid === '' || $viewer === '') {
        return [
            'success' => false,
            'message' => 'Invalid payout or username.'
        ];
    }

    $payout = paffp_sync_find_payout_by_uid($uid);

    if (!is_array($payout)) {
        return [
            'success' => false,
            'message' => 'Payout event not found.'
        ];
    }

    $lock = paffp_sync_access_requests_open_lock(true);

    if (!$lock) {
        return [
            'success' => false,
            'message' => 'Unable to save the access request.'
        ];
    }

    try {
        $items = paffp_sync_access_requests_load();
        $current = paffp_sync_access_request_state_from_items(
            $items,
            $uid,
            $viewer
        );

        if (
            $current['status'] === 'pending' ||
            !empty($current['active'])
        ) {
            return [
                'success' => true,
                'created' => false,
                'request' => $current
            ];
        }

        try {
            $requestId = 'req_' . bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $requestId = 'req_' . substr(
                sha1(uniqid('', true) . mt_rand()),
                0,
                16
            );
        }

        $now = time();

        $record = [
            'id' => $requestId,
            'payout_uid' => $uid,
            'payout_title' => (string) ($payout['title'] ?? ''),
            'username' => $viewer,
            'status' => 'pending',
            'permission' => '',
            'reason' => $reason,
            'requested_at' => $now,
            'decision_at' => 0,
            'duration_minutes' => 0,
            'expires_at' => 0,
            'updated_ts' => $now
        ];

        $items[] = $record;

        /*
         * Keep request history bounded. Recent/current records are at the end
         * because requests and decisions update their timestamps.
         */
        if (count($items) > 1000) {
            usort($items, function ($a, $b) {
                return max(
                    (int) ($a['updated_ts'] ?? 0),
                    (int) ($a['requested_at'] ?? 0)
                ) <=> max(
                    (int) ($b['updated_ts'] ?? 0),
                    (int) ($b['requested_at'] ?? 0)
                );
            });

            $items = array_slice($items, -1000);
        }

        if (!paffp_sync_access_requests_save($items)) {
            return [
                'success' => false,
                'message' => 'Unable to save the access request.'
            ];
        }

        return [
            'success' => true,
            'created' => true,
            'request' =>
                paffp_sync_access_request_state_from_items(
                    $items,
                    $uid,
                    $viewer,
                    $now
                )
        ];
    } finally {
        paffp_sync_close_lock($lock);
    }
}

function paffp_sync_decide_access_request(
    $requestId,
    $decision,
    $permission = 'view',
    $durationMinutes = 60,
    $expiresAtOverride = 0,
    $expiryMode = 'preset'
) {
    $requestId = trim((string) $requestId);
    $decision = strtolower(trim((string) $decision));
    $permission = strtolower(trim((string) $permission));
    $durationMinutes = (int) $durationMinutes;
    $expiresAtOverride = max(0, (int) $expiresAtOverride);
    $expiryMode = strtolower(trim((string) $expiryMode));

    if (!in_array(
        $expiryMode,
        ['preset', 'today_5pm', 'end_of_day', 'payout_end', 'custom'],
        true
    )) {
        $expiryMode = 'preset';
    }

    if ($requestId === '') {
        return [
            'success' => false,
            'message' => 'Invalid access request.'
        ];
    }

    if (!in_array(
        $decision,
        ['approve', 'reject', 'revoke'],
        true
    )) {
        return [
            'success' => false,
            'message' => 'Invalid request decision.'
        ];
    }

    if (!in_array($permission, ['view', 'edit'], true)) {
        $permission = 'view';
    }

    $allowedDurations = [
        15,
        30,
        60,
        120,
        240,
        480,
        720,
        1440,
        4320,
        10080
    ];

    if (!in_array($durationMinutes, $allowedDurations, true)) {
        $durationMinutes = 60;
    }

    $lock = paffp_sync_access_requests_open_lock(true);

    if (!$lock) {
        return [
            'success' => false,
            'message' => 'Unable to update the access request.'
        ];
    }

    try {
        $items = paffp_sync_access_requests_load();
        $found = false;
        $updated = null;
        $now = time();

        foreach ($items as &$item) {
            if (!is_array($item)
                || (string) ($item['id'] ?? '') !== $requestId) {
                continue;
            }

            $found = true;

            $currentStatus = strtolower(
                trim((string) ($item['status'] ?? 'pending'))
            );
            $currentExpiresAt =
                (int) ($item['expires_at'] ?? 0);
            $currentActive =
                $currentStatus === 'approved'
                && $currentExpiresAt > $now;

            /*
             * Keep rejected/revoked requests terminal. A verifier can submit
             * a NEW request later, but Admin cannot accidentally re-approve
             * the old rejected/revoked record.
             */
            if ($decision === 'approve') {
                /*
                 * Pending requests may be approved.
                 * Only an ACTIVE approved request may be updated.
                 * Expired, rejected and revoked records are terminal.
                 */
                if (
                    $currentStatus !== 'pending'
                    && !$currentActive
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            'Expired, rejected, or revoked requests cannot be re-approved. '
                            . 'The verifier must submit a new access request.'
                    ];
                }

                $finalExpiresAt =
                    $expiresAtOverride > $now
                        ? $expiresAtOverride
                        : $now + ($durationMinutes * 60);

                /*
                 * Guard against accidental excessively long grants.
                 * Custom access may be granted for up to 30 days.
                 */
                $maxExpiresAt = $now + (30 * 24 * 60 * 60);

                if ($finalExpiresAt > $maxExpiresAt) {
                    return [
                        'success' => false,
                        'message' =>
                            'Access expiry cannot be more than 30 days from now.'
                    ];
                }

                if ($finalExpiresAt <= $now) {
                    return [
                        'success' => false,
                        'message' => 'Access expiry must be in the future.'
                    ];
                }

                $item['status'] = 'approved';
                $item['permission'] = $permission;
                $item['decision_at'] = $now;
                $item['duration_minutes'] = max(
                    1,
                    (int) ceil(($finalExpiresAt - $now) / 60)
                );
                $item['expires_at'] = $finalExpiresAt;
                $item['expiry_mode'] = $expiryMode;
            } elseif ($decision === 'reject') {
                if ($currentStatus !== 'pending') {
                    return [
                        'success' => false,
                        'message' =>
                            'Only pending access requests can be rejected.'
                    ];
                }

                $item['status'] = 'rejected';
                $item['permission'] = '';
                $item['decision_at'] = $now;
                $item['duration_minutes'] = 0;
                $item['expires_at'] = 0;
                $item['expiry_mode'] = '';
            } else {
                if (!$currentActive) {
                    return [
                        'success' => false,
                        'message' =>
                            'Only an active approved access request can be revoked.'
                    ];
                }

                $item['status'] = 'revoked';
                $item['decision_at'] = $now;
                $item['duration_minutes'] = 0;
                $item['expires_at'] = 0;
                $item['expiry_mode'] = '';
            }

            $item['updated_ts'] = $now;
            $updated = $item;
            break;
        }
        unset($item);

        if (!$found) {
            return [
                'success' => false,
                'message' => 'Access request not found.'
            ];
        }

        if (!paffp_sync_access_requests_save($items)) {
            return [
                'success' => false,
                'message' => 'Unable to update the access request.'
            ];
        }

        return [
            'success' => true,
            'request' => $updated
        ];
    } finally {
        paffp_sync_close_lock($lock);
    }
}


function paffp_sync_is_edit_whitelisted_viewer($viewer)
{
    $viewer = trim((string) $viewer);

    if ($viewer === '') {
        return false;
    }

    /*
     * Explicit PAFFP verifier bypass accounts.
     *
     * Matching is case-insensitive so differences in URL/query casing do not
     * unexpectedly block an authorized bypass account.
     */
    return in_array(
        strtolower($viewer),
        [
            'handaeho',
            'bypass'
        ],
        true
    );
}

function paffp_sync_payout_state($file, $viewer = '')
{
    $safeFile = paffp_sync_safe_filename($file);
    $viewer = trim((string) $viewer);

    /*
     * Fail closed. A payout becomes readable/writable only after its
     * beneficiary filename is found in data/_list.json.
     */
    $state = [
        'open' => false,
        'writable' => false,
        'reason' => 'unconfigured',
        'access_mode' => 'unconfigured'
    ];

    if ($safeFile === '') {
        return $state;
    }

    $listFile = __DIR__ . '/data/_list.json';
    if (!is_file($listFile) || !is_readable($listFile)) {
        return $state;
    }

    $raw = @file_get_contents($listFile);
    $items = json_decode((string) $raw, true);

    if ($raw === false || !is_array($items)) {
        return $state;
    }

    foreach ($items as $item) {
        if ((string) ($item['file'] ?? '') !== $safeFile) {
            continue;
        }

        /*
         * Explicit edit whitelist.
         *
         * This runs only AFTER the requested beneficiary file has been found
         * in _list.json, preserving the existing fail-closed behavior for
         * unknown/unconfigured filenames.
         *
         * Whitelisted users always receive full edit access, including when
         * the payout is accomplished/closed, View Only, individually
         * restricted, or covered by a temporary View Only mode.
         */
        if (paffp_sync_is_edit_whitelisted_viewer($viewer)) {
            return [
                'open' => true,
                'writable' => true,
                'reason' => 'whitelist_edit',
                'access_mode' => 'whitelist_edit',
                'whitelisted' => true
            ];
        }

        /*
         * Global temporary access applies only to configured payout files.
         * It never rewrites the payout's saved access settings and expires
         * automatically based on the server clock.
         */
        $temporaryAccess = paffp_sync_temporary_access_state();

        if (!empty($temporaryAccess['active'])) {
            $temporaryAudience = (string) ($temporaryAccess['audience'] ?? 'all');
            $temporaryAudienceValue = trim(
                (string) ($temporaryAccess['audience_value'] ?? '')
            );

            $temporaryAudienceMatches =
                $temporaryAudience === 'all' ||
                (
                    $temporaryAudience === 'user' &&
                    $viewer !== '' &&
                    $temporaryAudienceValue !== '' &&
                    strcasecmp($viewer, $temporaryAudienceValue) === 0
                );

            if ($temporaryAudienceMatches) {
                $temporaryMode = (string) ($temporaryAccess['mode'] ?? 'edit');

                return [
                    'open' => true,
                    'writable' => !empty($temporaryAccess['writable']),
                    'reason' => $temporaryMode === 'edit'
                        ? 'temporary_global_edit'
                        : 'temporary_global_view',
                    'access_mode' => $temporaryMode === 'edit'
                        ? 'temporary_edit'
                        : 'temporary_view',
                    'temporary_mode' => $temporaryMode,
                    'temporary_audience' => $temporaryAudience,
                    'temporary_audience_value' => $temporaryAudienceValue,
                    'temporary_expires_at' => (int) ($temporaryAccess['expires_at'] ?? 0)
                ];
            }
        }

        /*
         * Admin-approved per-user access request.
         *
         * This is checked after global temporary access so an active global
         * temporary mode remains the highest-priority operational override.
         * It is checked before normal payout date/manual-close rules so an
         * explicit approved request can open an otherwise restricted payout.
         */
        $requestState = paffp_sync_access_request_state(
            (string) ($item['uid'] ?? ''),
            $viewer
        );

        if (!empty($requestState['active'])) {
            $requestPermission =
                (string) ($requestState['permission'] ?? 'view');

            return [
                'open' => true,
                'writable' => $requestPermission === 'edit',
                'reason' => $requestPermission === 'edit'
                    ? 'approved_request_edit'
                    : 'approved_request_view',
                'access_mode' => $requestPermission === 'edit'
                    ? 'request_edit'
                    : 'request_view',
                'request_id' =>
                    (string) ($requestState['id'] ?? ''),
                'request_permission' => $requestPermission,
                'request_expires_at' =>
                    (int) ($requestState['expires_at'] ?? 0)
            ];
        }

        $date = trim((string) ($item['date'] ?? ''));

        if ($date !== '') {
            try {
                $timezone = new DateTimeZone('Asia/Manila');
                $payoutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
                $errors = DateTimeImmutable::getLastErrors();

                if ($payoutDate
                    && !(is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {

                    $today = new DateTimeImmutable('today', $timezone);

                    if ($payoutDate < $today) {
                        $mode = strtolower(trim((string) ($item['accomplished_access_mode'] ?? 'closed')));

                        if (!in_array($mode, ['closed', 'all', 'users'], true)) {
                            $mode = 'closed';
                        }

                        if ($mode === 'all') {
                            $allPermission = strtolower(
                                trim(
                                    (string) (
                                        $item['accomplished_access_all_permission']
                                        ?? 'edit'
                                    )
                                )
                            );

                            if (!in_array($allPermission, ['view', 'edit'], true)) {
                                $allPermission = 'edit';
                            }

                            return [
                                'open' => true,
                                'writable' => $allPermission === 'edit',
                                'reason' => 'accomplished_override_all',
                                'access_mode' => 'all',
                                'all_permission' => $allPermission
                            ];
                        }

                        if ($mode === 'users') {
                            $users = paffp_sync_normalize_access_users(
                                $item['accomplished_access_users'] ?? []
                            );

                            $permissions =
                                paffp_sync_normalize_access_permissions(
                                    $item['accomplished_access_permissions'] ?? [],
                                    $users
                                );

                            if ($viewer !== '') {
                                foreach ($users as $allowedUser) {
                                    if (strcasecmp($viewer, $allowedUser) === 0) {
                                        $permission =
                                            $permissions[
                                                strtolower($allowedUser)
                                            ] ?? 'edit';

                                        return [
                                            'open' => true,
                                            'writable' => $permission === 'edit',
                                            'reason' => 'accomplished_override_user',
                                            'access_mode' => 'users',
                                            'individual_permission' => $permission
                                        ];
                                    }
                                }
                            }

                            return [
                                'open' => false,
                                'writable' => false,
                                'reason' => 'accomplished_restricted',
                                'access_mode' => 'users'
                            ];
                        }

                        return [
                            'open' => false,
                            'writable' => false,
                            'reason' => 'accomplished',
                            'access_mode' => 'closed'
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Invalid date metadata must not break synchronization.
            }
        }

        if (array_key_exists('enabled', $item) && empty($item['enabled'])) {
            return [
                'open' => false,
                'writable' => false,
                'reason' => 'manual',
                'access_mode' => 'manual'
            ];
        }

        return [
            'open' => true,
            'writable' => true,
            'reason' => '',
            'access_mode' => 'normal'
        ];
    }

    return $state;
}
