<?php
require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';
require_once __DIR__ . '/google-sheets-import.php';
require_once __DIR__ . '/admin-ops.php';
require_once __DIR__ . '/data-quality.php';

session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$dataFolder = __DIR__ . "/data";
$backupFolder = $dataFolder . "/backups";
$fullBackupFolder = $backupFolder . "/full";
$announcementFile = $dataFolder . "/_announcements.json";
$adSettingsFile = $dataFolder . "/_adSettings.json";
$temporaryAccessFile = $dataFolder . "/_temporaryAccess.json";
$accessRequestFile = $dataFolder . "/_accessRequests.json";
$dataQualityCacheFile = $dataFolder . "/_dataQualityCache.json";
$chatFolder = $dataFolder . "/_chat";
$chatFile = $chatFolder . "/global.json";
$chatLockFile = $chatFolder . "/global.lock";
$payoutFile = $dataFolder . "/_list.json";
$defaultFile = "_list.json";
$self = basename($_SERVER['PHP_SELF']);

/*
 * Admin password configuration
 * ----------------------------
 * The password itself is not stored in this file.
 * This value was generated with PHP password_hash().
 *
 * To change the password later, generate a new hash with:
 *   php -r 'echo password_hash("YOUR_NEW_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
 *
 * Then replace only the hash below.
 */
$adminPasswordHash = '$2y$12$1qUCuddTJaHgZtkT0XXw4.HopjCZp9NzxSSZP.buaER.jeEtohrCS';
$sessionTimeoutSeconds = 8 * 60 * 60;

if (!is_dir($dataFolder)) {
    @mkdir($dataFolder, 0777, true);
}

if (!is_dir($backupFolder)) {
    @mkdir($backupFolder, 0777, true);
}

if (!is_dir($fullBackupFolder)) {
    @mkdir($fullBackupFolder, 0777, true);
}

if (!file_exists($announcementFile)) {
    paffp_sync_write_json_file(
        $announcementFile,
        [],
        true
    );
}

if (!file_exists($adSettingsFile)) {
    $defaultAdSettings = [
        'index' => true,
        'verify' => true,
        'monitor' => true,
        'slots' => [
            'index' => [
                'banner_after_ongoing' => true,
                'banner_after_upcoming' => true,
                'native_accomplished' => true,
                'popunder' => true
            ],
            'verify' => [
                'analytics_native' => true,
                'container_local_banner' => true,
                'footer_banner' => true
            ],
            'monitor' => [
                'summary_banner' => true,
                'status_banner' => true
            ]
        ],
        'updated_ts' => 0
    ];

    paffp_sync_write_json_file(
        $adSettingsFile,
        $defaultAdSettings,
        true
    );
}


if (!file_exists($temporaryAccessFile)) {
    $defaultTemporaryAccess = [
        'enabled' => false,
        'started_at' => 0,
        'expires_at' => 0,
        'duration_minutes' => 0,
        'mode' => 'edit',
        'audience' => 'all',
        'audience_value' => '',
        'updated_ts' => 0
    ];

    paffp_sync_write_json_file(
        $temporaryAccessFile,
        $defaultTemporaryAccess,
        true
    );
}

if (!file_exists($accessRequestFile)) {
    paffp_sync_write_json_file(
        $accessRequestFile,
        [],
        true
    );
}

function verifyAdminPassword($password, $passwordHash)
{
    if (!is_string($passwordHash) || $passwordHash === '') {
        return false;
    }

    return password_verify($password, $passwordHash);
}

function loadJsonArray($file)
{
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);

    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function saveJsonArray($file, $items)
{
    return paffp_sync_write_json_file(
        $file,
        array_values($items),
        true
    );
}

function paffpAdminChatLoad($file)
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return [];
    }

    $messages = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = trim((string) ($item['id'] ?? ''));

        if ($id === '') {
            continue;
        }

        $messages[] = [
            'id' => $id,
            'username' => (string) ($item['username'] ?? ''),
            'message' => (string) ($item['message'] ?? ''),
            'payout_uid' => (string) ($item['payout_uid'] ?? ''),
            'payout_title' => (string) ($item['payout_title'] ?? ''),
            'created_at' => (int) ($item['created_at'] ?? 0)
        ];
    }

    return $messages;
}

function paffpAdminChatMutate(
    $chatFile,
    $lockFile,
    $mutator,
    &$changed
) {
    $changed = false;
    $chatDir = dirname($chatFile);

    if (!is_dir($chatDir)) {
        if (!@mkdir($chatDir, 0775, true) && !is_dir($chatDir)) {
            return false;
        }

        @chmod($chatDir, 02775);
    }

    $lockHandle = @fopen($lockFile, 'c+');

    if (is_file($lockFile)) {
        @chmod($lockFile, 0664);
    }

    if (!$lockHandle || !@flock($lockHandle, LOCK_EX)) {
        if (is_resource($lockHandle)) {
            @fclose($lockHandle);
        }

        return false;
    }

    $messages = paffpAdminChatLoad($chatFile);
    $result = call_user_func($mutator, $messages);

    if (
        !is_array($result)
        || !array_key_exists('messages', $result)
        || !is_array($result['messages'])
    ) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
        return false;
    }

    $changed = !empty($result['changed']);

    if (!$changed) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
        return true;
    }

    $writeOk = paffp_sync_write_json_file(
        $chatFile,
        array_values($result['messages']),
        false
    );

    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);

    if ($writeOk && is_file($chatFile)) {
        @chmod($chatFile, 0664);
    }

    return $writeOk;
}

function loadAdSettings($file)
{
    $defaults = [
        'index' => true,
        'verify' => true,
        'monitor' => true,
        'slots' => [
            'index' => [
                'banner_after_ongoing' => true,
                'banner_after_upcoming' => true,
                'native_accomplished' => true,
                'popunder' => true
            ],
            'verify' => [
                'analytics_native' => true,
                'container_local_banner' => true,
                'footer_banner' => true
            ],
            'monitor' => [
                'summary_banner' => true,
                'status_banner' => true
            ]
        ],
        'updated_ts' => 0
    ];

    if (!is_file($file)) {
        return $defaults;
    }

    $raw = @file_get_contents($file);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return $defaults;
    }

    foreach (['index', 'verify', 'monitor'] as $page) {
        if (array_key_exists($page, $decoded)) {
            $defaults[$page] = (bool) $decoded[$page];
        }
    }

    if (isset($decoded['slots']) && is_array($decoded['slots'])) {
        foreach ($defaults['slots'] as $page => $slots) {
            if (!isset($decoded['slots'][$page]) || !is_array($decoded['slots'][$page])) {
                continue;
            }

            foreach ($slots as $slot => $enabled) {
                if (array_key_exists($slot, $decoded['slots'][$page])) {
                    $defaults['slots'][$page][$slot] =
                        (bool) $decoded['slots'][$page][$slot];
                }
            }
        }
    }

    if (isset($decoded['updated_ts'])) {
        $defaults['updated_ts'] = (int) $decoded['updated_ts'];
    }

    return $defaults;
}

function adSlotEnabled($settings, $page, $slot)
{
    return !empty($settings[$page])
        && !empty($settings['slots'][$page][$slot]);
}

function adPageSlotCounts($settings, $page)
{
    $slots = isset($settings['slots'][$page]) && is_array($settings['slots'][$page])
        ? $settings['slots'][$page]
        : [];

    $enabled = 0;

    if (!empty($settings[$page])) {
        foreach ($slots as $value) {
            if (!empty($value)) {
                $enabled++;
            }
        }
    }

    return [
        'enabled' => $enabled,
        'total' => count($slots)
    ];
}

function saveAdSettings($file, $settings)
{
    return paffp_sync_write_json_file(
        $file,
        $settings,
        true
    );
}

function loadTemporaryAccess($file)
{
    $defaults = [
        'enabled' => false,
        'started_at' => 0,
        'expires_at' => 0,
        'duration_minutes' => 0,
        'mode' => 'edit',
        'audience' => 'all',
        'audience_value' => '',
        'updated_ts' => 0
    ];

    if (!is_file($file)) {
        return $defaults;
    }

    $raw = @file_get_contents($file);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return $defaults;
    }

    foreach (array_keys($defaults) as $key) {
        if (array_key_exists($key, $decoded)) {
            $defaults[$key] = $decoded[$key];
        }
    }

    $defaults['enabled'] = !empty($defaults['enabled']);
    $defaults['started_at'] = max(0, (int) $defaults['started_at']);
    $defaults['expires_at'] = max(0, (int) $defaults['expires_at']);
    $defaults['duration_minutes'] = max(0, (int) $defaults['duration_minutes']);

    $mode = strtolower(trim((string) ($defaults['mode'] ?? 'edit')));
    $defaults['mode'] = in_array($mode, ['view', 'edit'], true)
        ? $mode
        : 'edit';

    $audience = strtolower(trim((string) ($defaults['audience'] ?? 'all')));
    $defaults['audience'] = in_array($audience, ['all', 'user'], true)
        ? $audience
        : 'all';
    $defaults['audience_value'] = trim(
        (string) ($defaults['audience_value'] ?? '')
    );

    if ($defaults['audience'] === 'all') {
        $defaults['audience_value'] = '';
    }

    $defaults['updated_ts'] = max(0, (int) $defaults['updated_ts']);

    return $defaults;
}

function saveTemporaryAccess($file, $state)
{
    return paffp_sync_write_json_file(
        $file,
        $state,
        true
    );
}

function temporaryAccessIsActive($state, $now = null)
{
    $now = $now === null ? time() : (int) $now;

    return !empty($state['enabled'])
        && (int) ($state['expires_at'] ?? 0) > $now;
}

function isSequentialJsonArray($value)
{
    if (!is_array($value)) {
        return false;
    }

    if ($value === []) {
        return true;
    }

    return array_keys($value) === range(0, count($value) - 1);
}

function validateBeneficiaryDataset($records)
{
    if (!isSequentialJsonArray($records)) {
        return 'Beneficiary data must be a top-level JSON array.';
    }

    if (count($records) === 0) {
        return 'Beneficiary data cannot be empty.';
    }

    if (count($records) > 25000) {
        return 'Beneficiary data exceeds the 25,000-record limit.';
    }

    $seenSysGen = [];

    foreach ($records as $index => $record) {
        $rowNumber = $index + 1;

        if (!is_array($record)) {
            return 'Beneficiary record ' . $rowNumber . ' must be a JSON object.';
        }

        foreach (['SysGen', 'LastName', 'FirstName'] as $requiredField) {
            $value = trim((string) ($record[$requiredField] ?? ''));

            if ($value === '') {
                return 'Beneficiary record '
                    . $rowNumber
                    . ' is missing required field '
                    . $requiredField
                    . '.';
            }
        }

        $sysGen = trim((string) $record['SysGen']);

        if (isset($seenSysGen[$sysGen])) {
            return 'Duplicate SysGen '
                . $sysGen
                . ' found at beneficiary record '
                . $rowNumber
                . '.';
        }

        $seenSysGen[$sysGen] = true;
    }

    return null;
}

function payoutFilenameFromTitle($title)
{
    $filename = preg_replace(
        '/[^A-Za-z0-9_-]+/',
        '_',
        trim((string) $title)
    );

    $filename = trim((string) $filename, '_-');

    if ($filename === '') {
        $filename = 'PAFFP_Payout';
    }

    return $filename . '.json';
}

function generatePayoutUid($items)
{
    do {
        $uid = 'paffp' . substr(bin2hex(random_bytes(8)), 0, 8);
        $exists = false;

        foreach ($items as $item) {
            if ((string) ($item['uid'] ?? '') === $uid) {
                $exists = true;
                break;
            }
        }
    } while ($exists);

    return $uid;
}

function safeJsonFiles($folder)
{
    $files = glob($folder . "/*.json");

    if ($files === false) {
        return [];
    }

    $names = array_values(array_filter(
        array_map('basename', $files),
        function ($name) {
            return !in_array(
                $name,
                [
                    '_adminStatsCache.json',
                    '_authSettings.json',
                    '_users.json'
                ],
                true
            );
        }
    ));

    natcasesort($names);

    return array_values($names);
}

function formatBytes($bytes)
{
    $bytes = (int) $bytes;

    if ($bytes < 1024) {
        return $bytes . " B";
    }

    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . " KB";
    }

    return number_format($bytes / 1048576, 1) . " MB";
}

function countTopLevelRecords($content)
{
    $decoded = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    if (is_array($decoded)) {
        return count($decoded);
    }

    return null;
}

function createBackup($sourceFile, $backupFolder, $selectedFile, $reason)
{
    if (!is_file($sourceFile)) {
        return false;
    }

    $base = pathinfo($selectedFile, PATHINFO_FILENAME);
    $extension = pathinfo($selectedFile, PATHINFO_EXTENSION);
    $timestamp = date('Ymd_His');
    $suffix = substr(bin2hex(random_bytes(3)), 0, 6);

    $backupName = $base . "__" . $timestamp . "__" . $reason . "__" . $suffix;

    if ($extension !== '') {
        $backupName .= "." . $extension;
    }

    $backupPath = $backupFolder . "/" . $backupName;

    if (!@copy($sourceFile, $backupPath)) {
        return false;
    }

    clearstatcache(true, $sourceFile);
    clearstatcache(true, $backupPath);

    if ((int) @filesize($sourceFile) !== (int) @filesize($backupPath)) {
        @unlink($backupPath);
        return false;
    }

    if (strtolower($extension) === 'json') {
        $backupContent = @file_get_contents($backupPath);

        if ($backupContent === false
            || !paffp_sync_json_text_is_valid($backupContent)) {

            @unlink($backupPath);
            return false;
        }
    }

    @chmod($backupPath, 0777);

    return $backupName;
}

function backupListForFile($backupFolder, $selectedFile)
{
    if ($selectedFile === '') {
        return [];
    }

    $base = pathinfo($selectedFile, PATHINFO_FILENAME);
    $extension = pathinfo($selectedFile, PATHINFO_EXTENSION);

    $pattern = $backupFolder . "/" . $base . "__*";

    if ($extension !== '') {
        $pattern .= "." . $extension;
    }

    $files = glob($pattern);

    if ($files === false) {
        return [];
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return array_map('basename', $files);
}

function announcementState($item, $now)
{
    return empty($item['enabled']) ? 'inactive' : 'active';
}

function setFlash($type, $message)
{
    $_SESSION['paffp_admin_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function pullFlash()
{
    if (empty($_SESSION['paffp_admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['paffp_admin_flash'];
    unset($_SESSION['paffp_admin_flash']);

    return $flash;
}

function redirectAdmin($self, $query)
{
    header("Location: " . $self . ($query !== '' ? "?" . $query : ""));
    exit;
}

/* --------------------------------------------------------------
   Public read-only announcement API used by verify.php.
   This endpoint intentionally does not expose admin controls.
---------------------------------------------------------------- */
if (isset($_GET['api']) && $_GET['api'] === 'announcements') {
    header('Content-Type: application/json; charset=utf-8');

    $scope = isset($_GET['scope'])
        ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $_GET['scope'])
        : '';

    $viewer = trim((string) ($_GET['user'] ?? ''));

    $now = time();
    $items = loadJsonArray($announcementFile);

    $active = array_values(array_filter($items, function ($item) use ($scope, $viewer, $now) {
        if (empty($item['enabled'])) {
            return false;
        }

        $target = isset($item['scope']) ? (string) $item['scope'] : 'all';

        if ($target !== 'all' && $scope !== '' && $target !== $scope) {
            return false;
        }

        if ($target !== 'all' && $scope === '') {
            return false;
        }

        $audience = (string) ($item['audience'] ?? 'all');
        $audienceValue = trim((string) ($item['audience_value'] ?? ''));

        if ($audience === 'verifier') {
            if ($viewer === '' || $audienceValue === ''
                || strcasecmp($viewer, $audienceValue) !== 0) {
                return false;
            }
        }

        return true;
    }));

    usort($active, function ($a, $b) {
        $announcementPriority = [
            'urgent' => 3,
            'high' => 2,
            'normal' => 1
        ];

        $typePriority = [
            'danger' => 4,
            'warning' => 3,
            'success' => 2,
            'info' => 1
        ];

        $aAnnouncementPriority = $announcementPriority[$a['priority'] ?? 'normal'] ?? 1;
        $bAnnouncementPriority = $announcementPriority[$b['priority'] ?? 'normal'] ?? 1;

        if ($aAnnouncementPriority !== $bAnnouncementPriority) {
            return $bAnnouncementPriority <=> $aAnnouncementPriority;
        }

        $aTypePriority = $typePriority[$a['type'] ?? 'info'] ?? 1;
        $bTypePriority = $typePriority[$b['type'] ?? 'info'] ?? 1;

        if ($aTypePriority !== $bTypePriority) {
            return $bTypePriority <=> $aTypePriority;
        }

        return (int) ($b['updated_ts'] ?? 0)
            <=> (int) ($a['updated_ts'] ?? 0);
    });

    $response = array_map(function ($item) {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'message' => (string) ($item['message'] ?? ''),
            'type' => (string) ($item['type'] ?? 'info'),
            'priority' => (string) ($item['priority'] ?? 'normal'),
            'audience' => (string) ($item['audience'] ?? 'all'),
            'dismissible' => !empty($item['dismissible']),
            'home_action' => empty($item['dismissible']),
            'scope' => (string) ($item['scope'] ?? 'all'),
            'updated_ts' => (int) ($item['updated_ts'] ?? 0)
        ];
    }, $active);

    echo json_encode([
        'success' => true,
        'server_time' => date(DATE_ATOM),
        'announcements' => $response
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

/* --------------------------------------------------------------
   Authentication
---------------------------------------------------------------- */
$loginError = '';

if (!empty($_SESSION['paffp_admin_authenticated'])) {
    $lastActivity = isset($_SESSION['paffp_admin_last_activity'])
        ? (int) $_SESSION['paffp_admin_last_activity']
        : 0;

    if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeoutSeconds) {
        unset(
            $_SESSION['paffp_admin_authenticated'],
            $_SESSION['paffp_admin_last_activity'],
            $_SESSION['paffp_admin_csrf']
        );
    }
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    unset(
        $_SESSION['paffp_admin_authenticated'],
        $_SESSION['paffp_admin_last_activity'],
        $_SESSION['paffp_admin_csrf']
    );

    session_regenerate_id(true);
    redirectAdmin($self, '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['admin_action'])
    && $_POST['admin_action'] === 'login') {

    $password = isset($_POST['password'])
        ? (string) $_POST['password']
        : '';

    if (verifyAdminPassword($password, $adminPasswordHash)) {
        session_regenerate_id(true);

        $_SESSION['paffp_admin_authenticated'] = true;
        $_SESSION['paffp_admin_last_activity'] = time();
        $_SESSION['paffp_admin_csrf'] = bin2hex(random_bytes(24));

        redirectAdmin($self, 'section=announcements');
    }

    $loginError = 'Incorrect admin password.';
}

$authenticated = !empty($_SESSION['paffp_admin_authenticated']);

if (!$authenticated) {
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DA RFO X | PAFFP Admin</title>

    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
    <link rel="icon" href="img/DA_transparent.png" type="image/png">

    <script>
        (function () {
            const dark = localStorage.getItem("darkMode") === "true";
            document.documentElement.setAttribute("data-bs-theme", dark ? "dark" : "light");
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --da-green: #198754;
            --body-bg: #f5f7f6;
            --surface: #ffffff;
            --text-main: #1f2923;
            --text-soft: #6b756f;
            --border: #e2e7e4;
        }

        [data-bs-theme="dark"] {
            --body-bg: #111714;
            --surface: #18201c;
            --text-main: #eef2ef;
            --text-soft: #a7b0aa;
            --border: #2a352e;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background: var(--body-bg);
            color: var(--text-main);
            font-family: "Montserrat", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .admin-login-backdrop {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background:
                linear-gradient(rgba(7,12,9,.45), rgba(7,12,9,.45)),
                radial-gradient(circle at 20% 20%, rgba(25,135,84,.18), transparent 32%),
                var(--body-bg);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
        }

        .admin-login-card {
            width: min(92vw, 430px);
            padding: 1.4rem;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--surface);
            box-shadow: 0 1.5rem 4rem rgba(0,0,0,.28);
            text-align: center;
        }

        .admin-login-icon {
            display: grid;
            place-items: center;
            width: 78px;
            height: 78px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: rgba(25,135,84,.12);
            color: var(--da-green);
            font-size: 1.8rem;
            animation: adminPulse 1.8s ease-in-out infinite;
        }

        @keyframes adminPulse {
            0%,100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(25,135,84,0);
            }
            50% {
                transform: scale(1.07);
                box-shadow: 0 0 0 11px rgba(25,135,84,.10);
            }
        }

        .admin-login-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .admin-login-subtitle {
            margin: .45rem auto 1rem;
            max-width: 330px;
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.45;
        }

        .admin-password-wrap {
            position: relative;
        }

        .admin-password-wrap .form-control {
            min-height: 46px;
            padding-right: 46px;
            border-radius: 12px;
            border-color: var(--border);
            background: var(--surface);
            color: var(--text-main);
        }

        .admin-password-toggle {
            position: absolute;
            top: 50%;
            right: 5px;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--text-soft);
            transform: translateY(-50%);
        }

        .admin-password-toggle:hover {
            background: rgba(127,127,127,.08);
        }

        .btn-login {
            min-height: 44px;
            border-radius: 11px;
        }

        .login-error {
            margin-bottom: .75rem;
            padding: .55rem .7rem;
            border-radius: 10px;
            background: rgba(220,53,69,.10);
            color: #dc3545;
            font-size: .75rem;
            text-align: left;
        }


        /* Login typography aligned with verify.php */
        body {
            font-size: 1.05rem;
        }

        .admin-login-title {
            font-size: 1.2rem;
        }

        .admin-login-subtitle {
            font-size: .86rem;
        }

        .admin-password-wrap .form-control {
            font-size: .9rem;
        }

        .btn-login {
            font-size: .92rem;
            font-weight: 400;
        }

        .login-error {
            font-size: .85rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .admin-login-icon {
                animation: none;
            }
        }

        /* Match typography scale used by verify.php */
        body {
            font-size: 1.05rem;
        }

        .brand-kicker {
            font-size: 1.05rem;
        }

        .brand-subtitle {
            font-size: .9rem;
            line-height: 1.35;
        }

        .page-title {
            font-size: clamp(1.15rem, 2vw, 1.48rem);
            letter-spacing: -.02em;
        }

        .page-subtitle {
            font-size: .86rem;
        }

        .btn {
            font-weight: 400 !important;
        }

        .topbar .btn,
        .admin-nav-link,
        .composer-card .btn,
        .announcement-actions .btn,
        .backup-toolbar .btn,
        .editor-toolbar .btn,
        .editor-footer .btn {
            font-size: .92rem;
        }

        .admin-nav-link {
            font-size: .85rem;
        }

        .admin-nav-count {
            font-size: .7rem;
        }

        .form-label {
            font-size: .86rem;
        }

        .form-control,
        .form-select {
            font-size: .9rem;
        }

        .form-check-label {
            font-size: .85rem !important;
        }

        .flash-alert {
            font-size: .85rem;
        }

        .announcement-preview-label {
            font-size: .75rem;
        }

        .preview-icon {
            font-size: 2rem;
        }

        .preview-title {
            font-size: 1.2rem;
        }

        .preview-message {
            font-size: .88rem;
            line-height: 1.55;
        }

        .announcement-title {
            font-size: .92rem;
        }

        .announcement-message {
            font-size: .82rem;
            line-height: 1.45;
        }

        .meta-chip {
            font-size: .72rem;
        }

        .system-badge {
            font-size: .7rem;
        }

        .file-picker-item {
            font-size: .85rem;
        }

        .file-meta-chip {
            font-size: .78rem;
        }

        .backup-title {
            font-size: .9rem;
        }

        .backup-subtitle {
            font-size: .76rem;
        }

        .editor-toolbar-title {
            font-size: .9rem;
        }

        .editor-status {
            font-size: .8rem;
        }

        .json-action-btn {
            font-size: .82rem;
        }

        .CodeMirror {
            font-size: .93rem;
        }

        .footer {
            font-size: .84rem;
        }

        @media (max-width: 575.98px) {
            .brand-kicker {
                font-size: 1rem;
            }

            .brand-subtitle {
                font-size: .84rem;
            }

            .admin-nav-link {
                font-size: .82rem;
            }

            .page-title {
                font-size: clamp(1.1rem, 5vw, 1.3rem);
            }

            .page-subtitle {
                font-size: .82rem;
            }

            .form-label {
                font-size: .82rem;
            }

            .form-control,
            .form-select {
                font-size: .88rem;
            }

            .preview-title {
                font-size: 1.05rem;
            }

            .preview-message {
                font-size: .8rem;
            }

            .announcement-title {
                font-size: .88rem;
            }

            .announcement-message {
                font-size: .8rem;
            }

            .CodeMirror {
                font-size: .88rem;
            }
        }

    </style>
    <link rel="stylesheet" href="css/paffp-modal-system.css?v=20261011b">
</head>
<body>

<div class="admin-login-backdrop">
    <section class="admin-login-card" role="dialog" aria-modal="true" aria-labelledby="adminLoginTitle">
        <div class="admin-login-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <h1 class="admin-login-title" id="adminLoginTitle">PAFFP Administration</h1>
        <div class="admin-login-subtitle">
            Enter the administrator password to access PAFFP administration and recovery tools.
        </div>

        <?php if ($loginError !== '') { ?>
            <div class="login-error">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php } ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="admin_action" value="login">

            <div class="admin-password-wrap mb-3">
                <input type="password"
                       class="form-control"
                       id="adminLoginPassword"
                       name="password"
                       placeholder="Admin password"
                       autocomplete="current-password"
                       autofocus
                       required>

                <button type="button"
                        class="admin-password-toggle"
                        id="toggleLoginPassword"
                        title="Show password"
                        aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>

            <button class="btn btn-success btn-login w-100" type="submit">
                <i class="fa-solid fa-lock-open me-1"></i>
                Unlock Admin
            </button>
        </form>
    </section>
</div>

<script>
(function () {
    const input = document.getElementById("adminLoginPassword");
    const toggle = document.getElementById("toggleLoginPassword");

    toggle.addEventListener("click", function () {
        const showing = input.type === "text";

        input.type = showing ? "password" : "text";
        toggle.querySelector("i").className =
            showing ? "fa-regular fa-eye" : "fa-regular fa-eye-slash";

        toggle.title = showing ? "Show password" : "Hide password";
        toggle.setAttribute("aria-label", toggle.title);
    });
})();
</script>

</body>
</html>
<?php
    exit;
}

$isPassiveAdminPoll =
    isset($_GET['api'])
    && $_GET['api'] === 'access_request_status';

if (!$isPassiveAdminPoll) {
    $_SESSION['paffp_admin_last_activity'] = time();
}

if (empty($_SESSION['paffp_admin_csrf'])) {
    $_SESSION['paffp_admin_csrf'] = bin2hex(random_bytes(24));
}

$csrfToken = $_SESSION['paffp_admin_csrf'];

/* --------------------------------------------------------------
   Protected live access-request status API for Admin notifications.
---------------------------------------------------------------- */
if (
    isset($_GET['api'])
    && $_GET['api'] === 'access_request_status'
) {
    header('Content-Type: application/json; charset=utf-8');

    $items = paffp_sync_access_requests_load();
    $apiNow = time();
    $pending = [];
    $activeCount = 0;
    $latestTs = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $status = strtolower(
            trim((string) ($item['status'] ?? 'pending'))
        );
        $expiresAt = (int) ($item['expires_at'] ?? 0);
        $updatedTs = max(
            (int) ($item['updated_ts'] ?? 0),
            (int) ($item['requested_at'] ?? 0),
            (int) ($item['decision_at'] ?? 0)
        );

        $latestTs = max($latestTs, $updatedTs);

        if ($status === 'pending') {
            $pending[] = [
                'id' => (string) ($item['id'] ?? ''),
                'username' => (string) ($item['username'] ?? ''),
                'payout_uid' => (string) ($item['payout_uid'] ?? ''),
                'payout_title' => (string) ($item['payout_title'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
                'requested_at' => (int) ($item['requested_at'] ?? 0),
                'updated_ts' => $updatedTs
            ];
        } elseif (
            $status === 'approved'
            && $expiresAt > $apiNow
        ) {
            $activeCount++;
        }
    }

    usort($pending, function ($a, $b) {
        return (int) ($b['requested_at'] ?? 0)
            <=> (int) ($a['requested_at'] ?? 0);
    });

    echo json_encode([
        'success' => true,
        'server_time' => $apiNow,
        'pending_count' => count($pending),
        'active_count' => $activeCount,
        'latest_ts' => $latestTs,
        'pending' => $pending
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

if (isset($_GET['download_backup'])) {
    $backup = paffp_admin_find_full_backup(
        $fullBackupFolder,
        (string) $_GET['download_backup']
    );

    if (!$backup || !is_file($backup['path'])) {
        http_response_code(404);
        exit('Backup not found.');
    }

    header('Content-Type: application/gzip');
    header(
        'Content-Disposition: attachment; filename="' .
        str_replace('"', '', $backup['name']) .
        '"'
    );
    header('Content-Length: ' . filesize($backup['path']));
    header('Cache-Control: private, no-store, max-age=0');
    readfile($backup['path']);
    exit;
}

$section = isset($_GET['section'])
    ? (string) $_GET['section']
    : 'dashboard';

if (!in_array($section, ['dashboard', 'activity', 'payouts', 'auth', 'access_requests', 'data_quality', 'chat', 'announcements', 'backups', 'json', 'ads'], true)) {
    $section = 'dashboard';
}

/* --------------------------------------------------------------
   Authenticated mutations
---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['admin_action'])
    && $_POST['admin_action'] !== 'login') {

    $postedCsrf = isset($_POST['csrfToken'])
        ? (string) $_POST['csrfToken']
        : '';

    if (!hash_equals($csrfToken, $postedCsrf)) {
        setFlash('danger', 'The admin session form expired. Please try again.');
        redirectAdmin($self, 'section=' . urlencode($section));
    }

    $action = (string) $_POST['admin_action'];

    if ($action === 'auth_toggle') {
        $enabled =
            isset($_POST['auth_enabled'])
            && (string) $_POST['auth_enabled'] === '1';

        if (paffp_auth_save_settings([
            'enabled' => $enabled
        ])) {
            setFlash(
                'success',
                $enabled
                    ? 'Verifier login/signup is now enabled. Users opening Verify must sign in or create an account.'
                    : 'Verifier login/signup is now disabled. Verify returned to generated usernames.'
            );
        } else {
            setFlash(
                'danger',
                'Unable to save verifier authentication settings.'
            );
        }

        redirectAdmin($self, 'section=auth');
    }

    if ($action === 'chat_message_delete') {
        $messageId = trim(
            (string) ($_POST['message_id'] ?? '')
        );

        if ($messageId === '' || strlen($messageId) > 128) {
            setFlash('warning', 'Invalid chat message selected.');
            redirectAdmin($self, 'section=chat');
        }

        $chatChanged = false;

        $chatWriteOk = paffpAdminChatMutate(
            $chatFile,
            $chatLockFile,
            function ($messages) use ($messageId) {
                $remaining = [];
                $removed = false;

                foreach ($messages as $messageItem) {
                    if (
                        !$removed
                        && (string) ($messageItem['id'] ?? '')
                            === $messageId
                    ) {
                        $removed = true;
                        continue;
                    }

                    $remaining[] = $messageItem;
                }

                return [
                    'messages' => $remaining,
                    'changed' => $removed
                ];
            },
            $chatChanged
        );

        if (!$chatWriteOk) {
            setFlash(
                'danger',
                'Unable to delete the chat message. Please try again.'
            );
        } elseif (!$chatChanged) {
            setFlash(
                'warning',
                'The chat message was not found or was already deleted.'
            );
        } else {
            setFlash('success', 'Chat message deleted.');
        }

        redirectAdmin($self, 'section=chat');
    }

    if ($action === 'chat_clear_all') {
        $chatChanged = false;

        $chatWriteOk = paffpAdminChatMutate(
            $chatFile,
            $chatLockFile,
            function ($messages) {
                return [
                    'messages' => [],
                    'changed' => !empty($messages)
                ];
            },
            $chatChanged
        );

        if (!$chatWriteOk) {
            setFlash(
                'danger',
                'Unable to clear verifier chat. Please try again.'
            );
        } elseif (!$chatChanged) {
            setFlash('info', 'Verifier chat is already empty.');
        } else {
            setFlash('success', 'All verifier chat messages were deleted.');
        }

        redirectAdmin($self, 'section=chat');
    }

    if (in_array(
        $action,
        [
            'access_request_approve',
            'access_request_reject',
            'access_request_revoke'
        ],
        true
    )) {
        $requestId = trim(
            (string) ($_POST['request_id'] ?? '')
        );

        if ($action === 'access_request_approve') {
            $permission = strtolower(
                trim((string) ($_POST['permission'] ?? 'view'))
            );

            $expiryChoice = strtolower(
                trim((string) ($_POST['expiry_choice'] ?? '60'))
            );

            $durationMinutes = 60;
            $expiresAtOverride = 0;
            $expiryMode = 'preset';
            $expiryError = '';

            if ($expiryChoice === 'today_5pm') {
                $expiryMode = 'today_5pm';

                $expiryDate = new DateTimeImmutable(
                    'today',
                    new DateTimeZone('Asia/Manila')
                );

                $expiresAtOverride = $expiryDate
                    ->setTime(17, 0, 0)
                    ->getTimestamp();

                if ($expiresAtOverride <= time()) {
                    $expiryError =
                        'The 5:00 PM expiry time has already passed today.';
                }
            } elseif ($expiryChoice === 'end_of_day') {
                $expiryMode = 'end_of_day';

                $expiryDate = new DateTimeImmutable(
                    'today',
                    new DateTimeZone('Asia/Manila')
                );

                $expiresAtOverride = $expiryDate
                    ->setTime(23, 59, 59)
                    ->getTimestamp();

                if ($expiresAtOverride <= time()) {
                    $expiryError =
                        'End-of-day expiry is no longer available for today.';
                }
            } elseif ($expiryChoice === 'payout_end') {
                $expiryMode = 'payout_end';

                $requestForExpiry = null;

                foreach (
                    paffp_sync_access_requests_load()
                    as $requestCandidate
                ) {
                    if (
                        is_array($requestCandidate)
                        && (string) ($requestCandidate['id'] ?? '')
                            === $requestId
                    ) {
                        $requestForExpiry = $requestCandidate;
                        break;
                    }
                }

                $requestPayoutUid = is_array($requestForExpiry)
                    ? (string) ($requestForExpiry['payout_uid'] ?? '')
                    : '';

                $requestPayout =
                    $requestPayoutUid !== ''
                        ? paffp_sync_find_payout_by_uid(
                            $requestPayoutUid
                        )
                        : null;

                $requestPayoutDate = is_array($requestPayout)
                    ? trim((string) ($requestPayout['date'] ?? ''))
                    : '';

                $payoutEndDate =
                    DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        $requestPayoutDate,
                        new DateTimeZone('Asia/Manila')
                    );

                $payoutEndErrors =
                    DateTimeImmutable::getLastErrors();

                if (
                    !$payoutEndDate
                    || (
                        is_array($payoutEndErrors)
                        && (
                            $payoutEndErrors['warning_count'] > 0
                            || $payoutEndErrors['error_count'] > 0
                        )
                    )
                ) {
                    $expiryError =
                        'The payout does not have a valid end date.';
                } else {
                    $expiresAtOverride = $payoutEndDate
                        ->setTime(23, 59, 59)
                        ->getTimestamp();

                    if ($expiresAtOverride <= time()) {
                        $expiryError =
                            'The payout end date has already passed. '
                            . 'Use another expiration option.';
                    }
                }
            } elseif ($expiryChoice === 'custom') {
                $expiryMode = 'custom';
                $customExpiry = trim(
                    (string) ($_POST['custom_expires_at'] ?? '')
                );

                $customDate = DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    $customExpiry,
                    new DateTimeZone('Asia/Manila')
                );

                $customErrors = DateTimeImmutable::getLastErrors();

                if (
                    !$customDate
                    || (
                        is_array($customErrors)
                        && (
                            $customErrors['warning_count'] > 0
                            || $customErrors['error_count'] > 0
                        )
                    )
                ) {
                    $expiryError =
                        'Select a valid custom access expiration date and time.';
                } else {
                    $expiresAtOverride = $customDate->getTimestamp();

                    if ($expiresAtOverride <= time()) {
                        $expiryError =
                            'Custom access expiration must be in the future.';
                    } elseif (
                        $expiresAtOverride
                        > time() + (30 * 24 * 60 * 60)
                    ) {
                        $expiryError =
                            'Custom access expiration cannot exceed 30 days.';
                    }
                }
            } else {
                $durationMinutes = (int) $expiryChoice;

                if (!in_array(
                    $durationMinutes,
                    [
                        15, 30, 60, 120, 240,
                        480, 720, 1440, 4320, 10080
                    ],
                    true
                )) {
                    $durationMinutes = 60;
                }
            }

            if ($expiryError !== '') {
                setFlash('danger', $expiryError);
                redirectAdmin(
                    $self,
                    'section=access_requests'
                );
            }

            $result = paffp_sync_decide_access_request(
                $requestId,
                'approve',
                $permission,
                $durationMinutes,
                $expiresAtOverride,
                $expiryMode
            );

            if (!empty($result['success'])) {
                $request = (array) ($result['request'] ?? []);
                $username = (string) ($request['username'] ?? 'User');
                $accessLabel =
                    (string) ($request['permission'] ?? '') === 'edit'
                        ? 'Can Edit'
                        : 'View Only';
                $expiresAt = (int) ($request['expires_at'] ?? 0);

                setFlash(
                    'success',
                    'Access approved for '
                    . $username
                    . ' ('
                    . $accessLabel
                    . ') until '
                    . ($expiresAt > 0
                        ? date('M. j, Y g:i A', $expiresAt)
                        : 'expiry')
                    . '.'
                );
            } else {
                setFlash(
                    'danger',
                    (string) (
                        $result['message']
                        ?? 'Unable to approve the access request.'
                    )
                );
            }

            redirectAdmin(
                $self,
                'section=access_requests'
            );
        }

        $decision =
            $action === 'access_request_reject'
                ? 'reject'
                : 'revoke';

        $result = paffp_sync_decide_access_request(
            $requestId,
            $decision
        );

        if (!empty($result['success'])) {
            setFlash(
                'success',
                $decision === 'reject'
                    ? 'Access request rejected.'
                    : 'Approved access revoked.'
            );
        } else {
            setFlash(
                'danger',
                (string) (
                    $result['message']
                    ?? 'Unable to update the access request.'
                )
            );
        }

        redirectAdmin(
            $self,
            'section=access_requests'
        );
    }

    if ($action === 'payout_google_preview') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        try {
            $result = paffp_google_import(
                (string) ($_POST['payout_google_url'] ?? ''),
                (string) ($_POST['payout_google_gid'] ?? '')
            );

            $samples = array_slice($result['records'], 0, 3);
            // Limit sample data to 6 fields so the preview is compact.
            $sampleColumns = array_slice($result['headers'], 0, 6);
            $previewRows = [];
            foreach ($samples as $row) {
                $previewRow = [];
                foreach ($sampleColumns as $key) {
                    $previewRow[$key] = (string) ($row[$key] ?? '');
                }
                $previewRows[] = $previewRow;
            }

            echo json_encode([
                'success' => true,
                'count' => $result['count'],
                'headers' => $result['headers'],
                'sample_columns' => $sampleColumns,
                'sample_rows' => $previewRows,
                'worksheet' => $result['worksheet'],
                'gid' => $result['gid'],
                'source' => $result['source']
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    if ($action === 'payout_accomplished_access') {
        $uid = trim((string) ($_POST['payout_uid'] ?? ''));
        $mode = strtolower(trim((string) ($_POST['access_mode'] ?? 'closed')));
        $allPermission = strtolower(
            trim((string) ($_POST['all_permission'] ?? 'edit'))
        );
        $rawUsers = (string) ($_POST['access_users'] ?? '');
        $rawPermissionsJson = (string) ($_POST['access_permissions_json'] ?? '');

        if (!in_array($mode, ['closed', 'all', 'users'], true)) {
            $mode = 'closed';
        }

        if (!in_array($allPermission, ['view', 'edit'], true)) {
            $allPermission = 'edit';
        }

        $users = [];
        $permissions = [];
        $seenUsers = [];

        $permissionEntries = json_decode($rawPermissionsJson, true);

        if (is_array($permissionEntries)) {
            foreach ($permissionEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $name = trim((string) ($entry['name'] ?? ''));
                $permission = strtolower(
                    trim((string) ($entry['permission'] ?? 'edit'))
                );

                if ($name === '') {
                    continue;
                }

                if (!in_array($permission, ['view', 'edit'], true)) {
                    $permission = 'edit';
                }

                $name = mb_substr($name, 0, 120);
                $key = strtolower($name);

                if (isset($seenUsers[$key])) {
                    continue;
                }

                $seenUsers[$key] = true;
                $users[] = $name;
                $permissions[$name] = $permission;

                if (count($users) >= 100) {
                    break;
                }
            }
        }

        /*
         * Backward-compatible fallback if an older browser/form submits only
         * the textarea value.
         */
        if (empty($users)) {
            foreach (preg_split('/[\r\n,;]+/', $rawUsers) as $rawUser) {
                $name = trim((string) $rawUser);

                if ($name === '') {
                    continue;
                }

                $name = mb_substr($name, 0, 120);
                $key = strtolower($name);

                if (isset($seenUsers[$key])) {
                    continue;
                }

                $seenUsers[$key] = true;
                $users[] = $name;
                $permissions[$name] = 'edit';

                if (count($users) >= 100) {
                    break;
                }
            }
        }

        if ($mode === 'users' && empty($users)) {
            setFlash(
                'warning',
                'Enter at least one verifier username for specific-user access.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $items = loadJsonArray($payoutFile);
        $found = false;
        $isAccomplished = false;

        foreach ($items as &$item) {
            if ((string) ($item['uid'] ?? '') !== $uid) {
                continue;
            }

            $found = true;
            $isAccomplished = paffp_admin_payout_accomplished($item);

            if (!$isAccomplished) {
                break;
            }

            $item['accomplished_access_mode'] = $mode;

            /*
             * Remember the Open-to-All permission even while another access
             * mode is selected. Older entries without this field are treated
             * as "edit" for backward compatibility.
             */
            $item['accomplished_access_all_permission'] = $allPermission;

            $item['accomplished_access_users'] = $mode === 'users'
                ? array_values($users)
                : [];
            $item['accomplished_access_permissions'] = $mode === 'users'
                ? $permissions
                : [];

            if ($mode !== 'closed') {
                // An explicit access override means the payout is intended to
                // accept verification updates even if it had previously been
                // manually closed before becoming accomplished.
                $item['enabled'] = true;
            }

            break;
        }
        unset($item);

        if (!$found) {
            setFlash('warning', 'Payout event not found.');
        } elseif (!$isAccomplished) {
            setFlash(
                'warning',
                'Access Override is available only for accomplished payouts.'
            );
        } else {
            $backupName = createBackup(
                $payoutFile,
                $backupFolder,
                '_list.json',
                'before_accomplished_access'
            );

            if ($backupName === false && is_file($payoutFile)) {
                setFlash(
                    'danger',
                    'Could not create a safety backup. Access settings were not changed.'
                );
            } elseif (saveJsonArray($payoutFile, $items)) {
                if ($mode === 'all') {
                    setFlash(
                        'success',
                        'Accomplished payout reopened for all verifiers ('
                        . ($allPermission === 'view'
                            ? 'View Only'
                            : 'Can Edit')
                        . ').'
                    );
                } elseif ($mode === 'users') {
                    $viewCount = count(
                        array_filter(
                            $permissions,
                            function ($permission) {
                                return $permission === 'view';
                            }
                        )
                    );
                    $editCount = count($permissions) - $viewCount;

                    setFlash(
                        'success',
                        'Individual access saved for '
                        . number_format(count($users))
                        . ' verifier'
                        . (count($users) === 1 ? '' : 's')
                        . ' ('
                        . number_format($viewCount)
                        . ' view only, '
                        . number_format($editCount)
                        . ' can edit).'
                    );
                } else {
                    setFlash(
                        'success',
                        'Accomplished payout returned to Auto Closed.'
                    );
                }
            } else {
                setFlash(
                    'danger',
                    'Unable to save accomplished payout access settings.'
                );
            }
        }

        redirectAdmin($self, 'section=payouts');
    }

    if ($action === 'payout_toggle') {
        $uid = trim((string) ($_POST['payout_uid'] ?? ''));
        $items = loadJsonArray($payoutFile);
        $found = false;
        $newState = true;
        $autoClosed = false;

        foreach ($items as &$item) {
            if ((string) ($item['uid'] ?? '') !== $uid) {
                continue;
            }

            if (paffp_admin_payout_accomplished($item)) {
                $found = true;
                $newState = false;
                $autoClosed = true;
                break;
            }

            $current = !array_key_exists('enabled', $item) || !empty($item['enabled']);
            $newState = !$current;
            $item['enabled'] = $newState;
            $found = true;
            break;
        }
        unset($item);

        if (!$found) {
            setFlash('warning', 'Payout event not found.');
        } elseif ($autoClosed) {
            setFlash(
                'info',
                'Accomplished payouts are automatically closed. Use the Access button to reopen them for all verifiers or selected verifier usernames.'
            );
        } else {
            $backupName = createBackup(
                $payoutFile,
                $backupFolder,
                '_list.json',
                'before_payout_control'
            );

            if ($backupName === false && is_file($payoutFile)) {
                setFlash('danger', 'Could not create a safety backup. Payout status was not changed.');
            } elseif (saveJsonArray($payoutFile, $items)) {
                setFlash(
                    'success',
                    $newState
                        ? 'Payout reopened for verification.'
                        : 'Payout closed. Verification updates are now blocked.'
                );
            } else {
                setFlash('danger', 'Unable to update payout status.');
            }
        }

        redirectAdmin($self, 'section=payouts');
    }

    if ($action === 'payout_delete') {
        $uid = trim((string) ($_POST['payout_uid'] ?? ''));

        if ($uid === '') {
            setFlash('danger', 'Invalid payout event selected.');
            redirectAdmin($self, 'section=payouts');
        }

        $items = loadJsonArray($payoutFile);
        $foundIndex = null;

        foreach ($items as $index => $item) {
            if ((string) ($item['uid'] ?? '') === $uid) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === null) {
            setFlash('warning', 'Payout event not found.');
            redirectAdmin($self, 'section=payouts');
        }

        $payoutItem = $items[$foundIndex];
        $payoutTitle = trim((string) ($payoutItem['title'] ?? 'Payout event'));
        $payoutJsonFile = paffp_sync_safe_filename(
            (string) ($payoutItem['file'] ?? '')
        );

        if ($payoutJsonFile === '') {
            setFlash(
                'danger',
                'The payout uses an invalid beneficiary JSON filename. It was not deleted.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $payoutJsonPath = $dataFolder . '/' . $payoutJsonFile;

        /*
         * Safety backups are mandatory before deletion.
         * _list.json is always backed up. The beneficiary JSON is backed up
         * when it still exists on disk.
         */
        $listBackup = createBackup(
            $payoutFile,
            $backupFolder,
            '_list.json',
            'before_delete_payout'
        );

        if ($listBackup === false && is_file($payoutFile)) {
            setFlash(
                'danger',
                'Could not create a safety backup of _list.json. The payout was not deleted.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $dataBackup = null;

        if (is_file($payoutJsonPath)) {
            $dataBackup = createBackup(
                $payoutJsonPath,
                $backupFolder,
                $payoutJsonFile,
                'before_delete_payout'
            );

            if ($dataBackup === false) {
                setFlash(
                    'danger',
                    'Could not create a safety backup of the beneficiary JSON. The payout was not deleted.'
                );
                redirectAdmin($self, 'section=payouts');
            }
        }

        $updatedItems = $items;
        array_splice($updatedItems, $foundIndex, 1);

        if (!saveJsonArray($payoutFile, $updatedItems)) {
            setFlash(
                'danger',
                'Unable to remove the payout from _list.json. Nothing was deleted.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        /*
         * Remove the beneficiary JSON only after the list update succeeds.
         * If deletion fails, restore _list.json from its just-created backup
         * so the payout remains usable and consistent.
         */
        if (is_file($payoutJsonPath) && !@unlink($payoutJsonPath)) {
            $listBackupPath = $backupFolder . '/' . basename((string) $listBackup);

            if (is_file($listBackupPath)) {
                $listBackupContent = @file_get_contents($listBackupPath);

                if ($listBackupContent !== false) {
                    paffp_sync_write_validated_json_text(
                        $payoutFile,
                        $listBackupContent
                    );
                }
            }

            setFlash(
                'danger',
                'The beneficiary JSON could not be deleted. The payout list was restored and no deletion was completed.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        /*
         * Remove stale delta-sync metadata/lock artifacts for this payout.
         * Failure here is non-fatal because the active payout and data file
         * have already been removed safely.
         */
        paffp_sync_invalidate($payoutJsonFile);

        if (function_exists('paffp_sync_lock_path')) {
            $syncLockPath = paffp_sync_lock_path($payoutJsonFile);

            if (is_file($syncLockPath)) {
                @unlink($syncLockPath);
            }
        }

        setFlash(
            'success',
            'Payout "' . $payoutTitle . '" deleted successfully. Safety backups were retained.'
        );

        redirectAdmin($self, 'section=payouts');
    }

    if ($action === 'payout_edit') {
        $uid = trim((string) ($_POST['payout_uid'] ?? ''));
        $title = trim((string) ($_POST['payout_title'] ?? ''));
        $date = trim((string) ($_POST['payout_date'] ?? ''));

        if ($uid === '') {
            setFlash('danger', 'Invalid payout event selected.');
            redirectAdmin($self, 'section=payouts');
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);
        $dateIsValid = $dateObject
            && $dateObject->format('Y-m-d') === $date;

        if ($title === '') {
            setFlash('warning', 'Payout title is required.');
            redirectAdmin($self, 'section=payouts');
        }

        if (!$dateIsValid) {
            setFlash('warning', 'Please select a valid payout date.');
            redirectAdmin($self, 'section=payouts');
        }

        $items = loadJsonArray($payoutFile);
        $foundIndex = null;

        foreach ($items as $index => $item) {
            if ((string) ($item['uid'] ?? '') === $uid) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === null) {
            setFlash('danger', 'The selected payout event could not be found.');
            redirectAdmin($self, 'section=payouts');
        }

        foreach ($items as $index => $item) {
            if ($index === $foundIndex) {
                continue;
            }

            if (strcasecmp(
                (string) ($item['title'] ?? ''),
                $title
            ) === 0) {
                setFlash('warning', 'Another payout event already uses this title.');
                redirectAdmin($self, 'section=payouts');
            }
        }

        $oldTitle = (string) ($items[$foundIndex]['title'] ?? '');
        $oldDate = (string) ($items[$foundIndex]['date'] ?? '');

        if ($oldTitle === $title && $oldDate === $date) {
            setFlash('info', 'No changes were made to the payout event.');
            redirectAdmin($self, 'section=payouts');
        }

        $listBackup = createBackup(
            $payoutFile,
            $backupFolder,
            '_list.json',
            'before_edit_payout'
        );

        if ($listBackup === false && is_file($payoutFile)) {
            setFlash(
                'danger',
                'Could not create a safety backup of _list.json. The payout was not changed.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $items[$foundIndex]['title'] = $title;
        $items[$foundIndex]['date'] = $date;

        if (!saveJsonArray($payoutFile, $items)) {
            setFlash('danger', 'Unable to save the payout changes.');
            redirectAdmin($self, 'section=payouts');
        }

        setFlash('success', 'Payout title and date updated successfully.');

        redirectAdmin(
            $self,
            'section=payouts&edited=' . urlencode($uid)
        );
    }

    if ($action === 'payout_create') {
        $title = trim((string) ($_POST['payout_title'] ?? ''));
        $date = trim((string) ($_POST['payout_date'] ?? ''));
        $sourceMode = (string) ($_POST['payout_source_mode'] ?? 'file');

        if (!in_array($sourceMode, ['file', 'text', 'google'], true)) {
            $sourceMode = 'file';
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);
        $dateIsValid = $dateObject
            && $dateObject->format('Y-m-d') === $date;

        if ($title === '') {
            setFlash('warning', 'Please enter a payout title.');
            redirectAdmin($self, 'section=payouts');
        }

        if (!$dateIsValid) {
            setFlash('warning', 'Please select a valid payout date.');
            redirectAdmin($self, 'section=payouts');
        }

        $existingPayouts = loadJsonArray($payoutFile);
        $filename = payoutFilenameFromTitle($title);
        $destination = $dataFolder . '/' . $filename;

        foreach ($existingPayouts as $existingPayout) {
            if (strcasecmp(
                (string) ($existingPayout['title'] ?? ''),
                $title
            ) === 0) {
                setFlash('warning', 'A payout with this title already exists.');
                redirectAdmin($self, 'section=payouts');
            }

            if (strcasecmp(
                (string) ($existingPayout['file'] ?? ''),
                $filename
            ) === 0) {
                setFlash(
                    'warning',
                    'The generated JSON filename already exists in the payout list.'
                );
                redirectAdmin($self, 'section=payouts');
            }
        }

        if (is_file($destination)) {
            setFlash(
                'warning',
                'A JSON file named ' . $filename . ' already exists in the data folder.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $beneficiaries = null;

        if ($sourceMode === 'google') {
            try {
                $googleResult = paffp_google_import(
                    (string) ($_POST['payout_google_url'] ?? ''),
                    (string) ($_POST['payout_google_gid'] ?? '')
                );
                $beneficiaries = $googleResult['records'];
            } catch (Exception $e) {
                setFlash('danger', 'Unable to import Google Sheet: ' . $e->getMessage());
                redirectAdmin($self, 'section=payouts');
            }
        } elseif ($sourceMode === 'text') {
            $jsonText = trim((string) ($_POST['payout_json_text'] ?? ''));

            if ($jsonText === '') {
                setFlash('warning', 'Please paste the beneficiary JSON array.');
                redirectAdmin($self, 'section=payouts');
            }

            $beneficiaries = json_decode($jsonText, true);

            if (json_last_error() !== JSON_ERROR_NONE
                || !isSequentialJsonArray($beneficiaries)) {

                setFlash(
                    'warning',
                    'The pasted beneficiary data must be a valid top-level JSON array.'
                );
                redirectAdmin($self, 'section=payouts');
            }
        } else {
            if (!isset($_FILES['payout_json_file'])) {
                setFlash('warning', 'Please select a beneficiary JSON file.');
                redirectAdmin($self, 'section=payouts');
            }

            $uploadedFile = $_FILES['payout_json_file'];
            $uploadError = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $uploadMessages = [
                    UPLOAD_ERR_INI_SIZE => 'The JSON file exceeds the server upload limit.',
                    UPLOAD_ERR_FORM_SIZE => 'The JSON file exceeds the allowed form upload size.',
                    UPLOAD_ERR_PARTIAL => 'The JSON file was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE => 'Please select a beneficiary JSON file.',
                    UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is unavailable.',
                    UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
                    UPLOAD_ERR_EXTENSION => 'The upload was stopped by a server extension.'
                ];

                setFlash(
                    'warning',
                    $uploadMessages[$uploadError]
                        ?? 'The JSON file could not be uploaded.'
                );
                redirectAdmin($self, 'section=payouts');
            }

            $originalName = (string) ($uploadedFile['name'] ?? '');

            if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'json') {
                setFlash('warning', 'Only .json beneficiary files are accepted.');
                redirectAdmin($self, 'section=payouts');
            }

            $rawJson = @file_get_contents(
                (string) ($uploadedFile['tmp_name'] ?? '')
            );

            if ($rawJson === false) {
                setFlash('danger', 'Unable to read the uploaded JSON file.');
                redirectAdmin($self, 'section=payouts');
            }

            $beneficiaries = json_decode($rawJson, true);

            if (json_last_error() !== JSON_ERROR_NONE
                || !isSequentialJsonArray($beneficiaries)) {

                setFlash(
                    'warning',
                    'The uploaded file must contain a valid top-level JSON array.'
                );
                redirectAdmin($self, 'section=payouts');
            }
        }

        $beneficiaryValidationError =
            validateBeneficiaryDataset($beneficiaries);

        if ($beneficiaryValidationError !== null) {
            setFlash('warning', $beneficiaryValidationError);
            redirectAdmin($self, 'section=payouts');
        }

        $encodedBeneficiaries = json_encode(
            $beneficiaries,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encodedBeneficiaries === false) {
            setFlash('danger', 'Unable to prepare the beneficiary JSON.');
            redirectAdmin($self, 'section=payouts');
        }

        $listBackup = createBackup(
            $payoutFile,
            $backupFolder,
            '_list.json',
            'before_add_payout'
        );

        if ($listBackup === false && is_file($payoutFile)) {
            setFlash(
                'danger',
                'Could not create a safety backup of _list.json. The payout was not created.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        if (!paffp_sync_write_json_file(
            $destination,
            $beneficiaries,
            true
        )) {

            setFlash(
                'danger',
                'Unable to save and validate the beneficiary JSON file in the data folder.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        $uid = generatePayoutUid($existingPayouts);

        $existingPayouts[] = [
            'uid' => $uid,
            'title' => $title,
            'file' => $filename,
            'date' => $date,
            'count' => count($beneficiaries),
            'enabled' => true
        ];

        if (!saveJsonArray($payoutFile, $existingPayouts)) {
            @unlink($destination);

            setFlash(
                'danger',
                'The beneficiary file was prepared, but _list.json could not be updated. No payout was created.'
            );
            redirectAdmin($self, 'section=payouts');
        }

        setFlash(
            'success',
            'Payout created successfully with '
            . number_format(count($beneficiaries))
            . ' beneficiar'
            . (count($beneficiaries) === 1 ? 'y.' : 'ies.')
        );

        redirectAdmin(
            $self,
            'section=payouts&created=' . urlencode($uid)
        );
    }

    if ($action === 'temporary_access_start') {
        $minutes = (int) ($_POST['temporary_access_minutes'] ?? 60);
        $accessMode = strtolower(trim((string) ($_POST['temporary_access_mode'] ?? 'view')));
        $accessAudience = strtolower(trim((string) ($_POST['temporary_access_audience'] ?? 'all')));
        $accessAudienceValue = trim((string) ($_POST['temporary_access_user'] ?? ''));
        $allowedDurations = [15, 30, 60, 120, 240, 480, 720, 1440];

        if (!in_array($minutes, $allowedDurations, true)) {
            setFlash('warning', 'Select a valid temporary access duration.');
            redirectAdmin($self, 'section=payouts');
        }

        if (!in_array($accessMode, ['view', 'edit'], true)) {
            setFlash('warning', 'Select either Open for Viewing or Open for Editing.');
            redirectAdmin($self, 'section=payouts');
        }

        if (!in_array($accessAudience, ['all', 'user'], true)) {
            setFlash('warning', 'Select All Users or Specific User.');
            redirectAdmin($self, 'section=payouts');
        }

        if ($accessAudience === 'user' && $accessAudienceValue === '') {
            setFlash('warning', 'Enter the verifier username for Specific User access.');
            redirectAdmin($self, 'section=payouts');
        }

        if ($accessAudience === 'all') {
            $accessAudienceValue = '';
        }

        $startedAt = time();
        $expiresAt = $startedAt + ($minutes * 60);
        $state = [
            'enabled' => true,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'duration_minutes' => $minutes,
            'mode' => $accessMode,
            'audience' => $accessAudience,
            'audience_value' => $accessAudienceValue,
            'updated_ts' => $startedAt
        ];

        if (!saveTemporaryAccess($temporaryAccessFile, $state)) {
            setFlash('danger', 'Unable to enable temporary access. Check data-folder permissions.');
            redirectAdmin($self, 'section=payouts');
        }

        $modeLabel = $accessMode === 'edit'
            ? 'viewing and editing'
            : 'viewing only';

        $audienceLabel = $accessAudience === 'user'
            ? $accessAudienceValue
            : 'all users';

        setFlash(
            'success',
            'All payouts are temporarily open for '
            . $modeLabel
            . ' to '
            . $audienceLabel
            . ' until '
            . date('M. j, Y g:i A', $expiresAt)
            . '.'
        );

        redirectAdmin($self, 'section=payouts');
    }

    if ($action === 'temporary_access_stop') {
        $nowTs = time();
        $state = loadTemporaryAccess($temporaryAccessFile);
        $state['enabled'] = false;
        $state['expires_at'] = $nowTs;
        $state['updated_ts'] = $nowTs;

        if (!saveTemporaryAccess($temporaryAccessFile, $state)) {
            setFlash('danger', 'Unable to stop temporary access. Check data-folder permissions.');
            redirectAdmin($self, 'section=payouts');
        }

        setFlash(
            'success',
            'Temporary all-payout access stopped. Normal payout restrictions are active again.'
        );

        redirectAdmin($self, 'section=payouts');
    }

    if ($action === 'backup_all') {
        try {
            $backupName = paffp_admin_full_backup(
                $dataFolder,
                $fullBackupFolder,
                'manual'
            );
            setFlash('success', 'Full PAFFP data backup created: ' . $backupName);
        } catch (Throwable $e) {
            setFlash('danger', 'Unable to create full backup: ' . $e->getMessage());
        }

        redirectAdmin($self, 'section=backups');
    }

    if ($action === 'backup_restore') {
        $backupName = basename((string) ($_POST['backup_name'] ?? ''));

        try {
            paffp_admin_restore_full_backup(
                $dataFolder,
                $fullBackupFolder,
                $backupName
            );
            setFlash(
                'success',
                'Backup restored successfully. A pre-restore safety backup was created automatically.'
            );
        } catch (Throwable $e) {
            setFlash('danger', 'Restore failed: ' . $e->getMessage());
        }

        redirectAdmin($self, 'section=backups');
    }

    if ($action === 'backup_delete') {
        $backupName = basename((string) ($_POST['backup_name'] ?? ''));

        if ($backupName === '') {
            setFlash('warning', 'No backup was selected for deletion.');
            redirectAdmin($self, 'section=backups');
        }

        $backup = paffp_admin_find_full_backup(
            $fullBackupFolder,
            $backupName
        );

        if (!$backup || !is_file($backup['path'])) {
            setFlash('warning', 'Backup not found. It may have already been deleted.');
            redirectAdmin($self, 'section=backups');
        }

        /*
         * Full-backup deletion is intentionally permanent.
         * The path comes only from paffp_admin_find_full_backup(), which
         * restricts deletion to a valid backup inside the configured full
         * backup directory.
         */
        if (!@unlink($backup['path'])) {
            setFlash(
                'danger',
                'Unable to delete the selected backup. Check server file permissions.'
            );
            redirectAdmin($self, 'section=backups');
        }

        setFlash(
            'success',
            'Backup deleted successfully: ' . $backupName
        );

        redirectAdmin($self, 'section=backups');
    }

    if ($action === 'announcement_save') {
        $items = loadJsonArray($announcementFile);

        $id = trim((string) ($_POST['id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['message'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'info');
        $scope = trim((string) ($_POST['scope'] ?? 'all'));
        $priority = (string) ($_POST['priority'] ?? 'normal');
        $audience = (string) ($_POST['audience'] ?? 'all');
        $audienceValue = trim((string) ($_POST['audience_value'] ?? ''));
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $dismissible = isset($_POST['dismissible']) ? 1 : 0;

        $allowedTypes = ['info', 'success', 'warning', 'danger'];
        $allowedPriorities = ['normal', 'high', 'urgent'];
        $allowedAudiences = ['all', 'verifier'];

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        if (!in_array($audience, $allowedAudiences, true)) {
            $audience = 'all';
        }

        if ($audience === 'verifier' && $audienceValue === '') {
            setFlash('warning', 'Enter the verifier username for the selected announcement audience.');
            redirectAdmin($self, 'section=announcements');
        }

        if ($audience === 'all') {
            $audienceValue = '';
        }

        if ($scope !== 'all') {
            $scope = preg_replace('/[^A-Za-z0-9_-]/', '', $scope);
        }

        if ($title === '' || $body === '') {
            setFlash('warning', 'Announcement title and message are required.');
        } else {
            $now = time();

            $record = [
                'id' => $id !== '' ? $id : 'ann_' . bin2hex(random_bytes(6)),
                'title' => $title,
                'message' => $body,
                'type' => $type,
                'scope' => $scope !== '' ? $scope : 'all',
                'priority' => $priority,
                'audience' => $audience,
                'audience_value' => $audienceValue,
                'dismissible' => $dismissible,
                'enabled' => $enabled,
                'updated_ts' => $now
            ];

            $updated = false;

            if ($id !== '') {
                foreach ($items as $index => $item) {
                    if ((string) ($item['id'] ?? '') === $id) {
                        $record['created_ts'] = (int) ($item['created_ts'] ?? $now);
                        $items[$index] = $record;
                        $updated = true;
                        break;
                    }
                }
            }

            if (!$updated) {
                $record['created_ts'] = $now;
                $items[] = $record;
            }

            if (saveJsonArray($announcementFile, $items)) {
                setFlash(
                    'success',
                    $updated ? 'Announcement updated.' : 'Announcement published.'
                );
            } else {
                setFlash('danger', 'Unable to save the announcement.');
            }
        }

        redirectAdmin($self, 'section=announcements');
    }

    if ($action === 'announcement_toggle' || $action === 'announcement_delete') {
        $items = loadJsonArray($announcementFile);
        $id = (string) ($_POST['id'] ?? '');

        if ($action === 'announcement_delete') {
            $before = count($items);

            $items = array_values(array_filter($items, function ($item) use ($id) {
                return (string) ($item['id'] ?? '') !== $id;
            }));

            if (count($items) === $before) {
                setFlash('warning', 'Announcement not found.');
            } elseif (saveJsonArray($announcementFile, $items)) {
                setFlash('success', 'Announcement deleted.');
            } else {
                setFlash('danger', 'Unable to delete the announcement.');
            }
        } else {
            $found = false;

            foreach ($items as &$item) {
                if ((string) ($item['id'] ?? '') === $id) {
                    $item['enabled'] = empty($item['enabled']);
                    $item['updated_ts'] = time();
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                setFlash('warning', 'Announcement not found.');
            } elseif (saveJsonArray($announcementFile, $items)) {
                setFlash('success', 'Announcement status updated.');
            } else {
                setFlash('danger', 'Unable to update announcement status.');
            }
        }

        redirectAdmin($self, 'section=announcements');
    }

    if ($action === 'ad_settings_save') {
        $settings = [
            'index' => isset($_POST['ads_index']),
            'verify' => isset($_POST['ads_verify']),
            'monitor' => isset($_POST['ads_monitor']),
            'slots' => [
                'index' => [
                    'banner_after_ongoing' => isset($_POST['ads_index_banner_after_ongoing']),
                    'banner_after_upcoming' => isset($_POST['ads_index_banner_after_upcoming']),
                    'native_accomplished' => isset($_POST['ads_index_native_accomplished']),
                    'popunder' => isset($_POST['ads_index_popunder'])
                ],
                'verify' => [
                    'analytics_native' => isset($_POST['ads_verify_analytics_native']),
                    'container_local_banner' => isset($_POST['ads_verify_container_local_banner']),
                    'footer_banner' => isset($_POST['ads_verify_footer_banner'])
                ],
                'monitor' => [
                    'summary_banner' => isset($_POST['ads_monitor_summary_banner']),
                    'status_banner' => isset($_POST['ads_monitor_status_banner'])
                ]
            ],
            'updated_ts' => time()
        ];

        if (saveAdSettings($adSettingsFile, $settings)) {
            setFlash('success', 'Advertisement settings updated.');
        } else {
            setFlash('danger', 'Unable to save advertisement settings.');
        }

        redirectAdmin($self, 'section=ads');
    }

    if ($action === 'json_save' || $action === 'json_restore') {
        $jsonFilesForAction = safeJsonFiles($dataFolder);
        $selectedFileForAction = isset($_POST['selectedFile'])
            ? basename((string) $_POST['selectedFile'])
            : '';

        if ($selectedFileForAction === ''
            || !in_array($selectedFileForAction, $jsonFilesForAction, true)) {

            setFlash('danger', 'Invalid JSON file selected.');
            redirectAdmin($self, 'section=json');
        }

        $targetFile = $dataFolder . "/" . $selectedFileForAction;

        if ($action === 'json_save') {
            $content = isset($_POST['jsonContent'])
                ? (string) $_POST['jsonContent']
                : '';

            json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                setFlash('warning', 'Invalid JSON: ' . json_last_error_msg());
            } else {
                $existingContent = is_file($targetFile)
                    ? @file_get_contents($targetFile)
                    : '';

                if ($existingContent !== false && $existingContent === $content) {
                    setFlash('info', 'No changes to save.');
                } else {
                    $backupName = createBackup(
                        $targetFile,
                        $backupFolder,
                        $selectedFileForAction,
                        'before_save'
                    );

                    if ($backupName === false && is_file($targetFile)) {
                        setFlash(
                            'danger',
                            'Could not create a safety backup. The file was not overwritten.'
                        );
                    } elseif (!paffp_sync_write_validated_json_text(
                        $targetFile,
                        $content
                    )) {
                        setFlash(
                            'danger',
                            'Unable to write and validate the selected JSON file. The previous file was left unchanged when possible.'
                        );
                    } else {
                        paffp_sync_invalidate($selectedFileForAction);
                        setFlash('success', 'Saved successfully. Previous version backed up.');
                    }
                }
            }
        } else {
            $backupName = isset($_POST['backupFile'])
                ? basename((string) $_POST['backupFile'])
                : '';

            $availableBackups = backupListForFile(
                $backupFolder,
                $selectedFileForAction
            );

            if ($backupName === ''
                || !in_array($backupName, $availableBackups, true)) {

                setFlash('danger', 'Invalid backup selected.');
            } else {
                $backupPath = $backupFolder . "/" . $backupName;
                $backupContent = @file_get_contents($backupPath);

                json_decode((string) $backupContent, true);

                if ($backupContent === false
                    || json_last_error() !== JSON_ERROR_NONE) {

                    setFlash('danger', 'The selected backup is not valid JSON.');
                } else {
                    $preRestoreBackup = createBackup(
                        $targetFile,
                        $backupFolder,
                        $selectedFileForAction,
                        'before_restore'
                    );

                    if ($preRestoreBackup === false && is_file($targetFile)) {
                        setFlash(
                            'danger',
                            'Could not create a safety backup before restore.'
                        );
                    } elseif (!paffp_sync_write_validated_json_text(
                        $targetFile,
                        $backupContent
                    )) {

                        setFlash(
                            'danger',
                            'Unable to restore and validate the selected backup. The previous live JSON was left unchanged when possible.'
                        );
                    } else {
                        paffp_sync_invalidate($selectedFileForAction);
                        setFlash('success', 'Backup restored successfully.');
                    }
                }
            }
        }

        redirectAdmin(
            $self,
            'section=json&file=' . urlencode($selectedFileForAction)
        );
    }
}

$flash = pullFlash();

/* --------------------------------------------------------------
   Shared admin data
---------------------------------------------------------------- */
$payouts = loadJsonArray($payoutFile);
$payoutMap = [];

foreach ($payouts as $payout) {
    if (!isset($payout['uid'])) {
        continue;
    }

    $payoutMap[(string) $payout['uid']] =
        (string) ($payout['title'] ?? $payout['uid']);
}

$payoutDisplay = $payouts;

usort($payoutDisplay, function ($a, $b) {
    $dateCompare = strcmp(
        (string) ($b['date'] ?? ''),
        (string) ($a['date'] ?? '')
    );

    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    return strcasecmp(
        (string) ($a['title'] ?? ''),
        (string) ($b['title'] ?? '')
    );
});

$payoutToday = date('Y-m-d');
$payoutBeneficiaryTotal = 0;
$payoutOngoingCount = 0;
$payoutUpcomingCount = 0;
$payoutAccomplishedCount = 0;

foreach ($payouts as $payoutSummaryItem) {
    $payoutBeneficiaryTotal += (int) ($payoutSummaryItem['count'] ?? 0);
    $payoutSummaryDate = (string) ($payoutSummaryItem['date'] ?? '');

    if ($payoutSummaryDate === $payoutToday) {
        $payoutOngoingCount++;
    } elseif ($payoutSummaryDate > $payoutToday) {
        $payoutUpcomingCount++;
    } elseif ($payoutSummaryDate !== '') {
        $payoutAccomplishedCount++;
    }
}

$announcements = loadJsonArray($announcementFile);
$adSettings = loadAdSettings($adSettingsFile);
$authSettings = paffp_auth_load_settings();
$authUsers = paffp_auth_load_users();

usort($authUsers, function ($a, $b) {
    return (int) ($b['created_at'] ?? 0)
        <=> (int) ($a['created_at'] ?? 0);
});

$authUserCount = count($authUsers);
$chatMessages = paffpAdminChatLoad($chatFile);

usort($chatMessages, function ($a, $b) {
    $aTime = (int) ($a['created_at'] ?? 0);
    $bTime = (int) ($b['created_at'] ?? 0);

    if ($aTime !== $bTime) {
        return $bTime <=> $aTime;
    }

    return strcasecmp(
        (string) ($b['id'] ?? ''),
        (string) ($a['id'] ?? '')
    );
});

$chatMessageCount = count($chatMessages);
$now = time();

$accessRequests = paffp_sync_access_requests_load();
$pendingAccessRequestCount = 0;
$activeAccessRequestCount = 0;
$pendingAccessRequestIds = [];

foreach ($accessRequests as $accessRequestItem) {
    $requestStatus = strtolower(
        (string) ($accessRequestItem['status'] ?? 'pending')
    );
    $requestExpiresAt = (int) (
        $accessRequestItem['expires_at'] ?? 0
    );

    if ($requestStatus === 'pending') {
        $pendingAccessRequestCount++;

        $pendingRequestId = trim(
            (string) ($accessRequestItem['id'] ?? '')
        );

        if ($pendingRequestId !== '') {
            $pendingAccessRequestIds[] = $pendingRequestId;
        }
    } elseif (
        $requestStatus === 'approved' &&
        $requestExpiresAt > $now
    ) {
        $activeAccessRequestCount++;
    }
}

usort($accessRequests, function ($a, $b) use ($now) {
    $stateWeight = function ($item) use ($now) {
        $status = strtolower(
            (string) ($item['status'] ?? 'pending')
        );
        $expiresAt = (int) ($item['expires_at'] ?? 0);

        if ($status === 'pending') {
            return 0;
        }

        if ($status === 'approved' && $expiresAt > $now) {
            return 1;
        }

        if ($status === 'approved') {
            return 2;
        }

        if ($status === 'rejected') {
            return 3;
        }

        if ($status === 'revoked') {
            return 4;
        }

        return 5;
    };

    $aWeight = $stateWeight($a);
    $bWeight = $stateWeight($b);

    if ($aWeight !== $bWeight) {
        return $aWeight <=> $bWeight;
    }

    $aUpdated = max(
        (int) ($a['updated_ts'] ?? 0),
        (int) ($a['requested_at'] ?? 0)
    );
    $bUpdated = max(
        (int) ($b['updated_ts'] ?? 0),
        (int) ($b['requested_at'] ?? 0)
    );

    return $bUpdated <=> $aUpdated;
});


$dataQualityReport = null;
$dataQualitySummary = [
    'payout_files' => 0,
    'records' => 0,
    'files_with_issues' => 0,
    'issues' => 0,
    'cross_file_duplicate_sysgen' => 0,
    'cross_file_duplicate_name' => 0
];

if ($section === 'data_quality') {
    $forceQualityScan =
        isset($_GET['scan'])
        && $_GET['scan'] === '1';

    $dataQualityReport = paffp_quality_get_report(
        $dataQualityCacheFile,
        $dataFolder,
        $payouts,
        $forceQualityScan,
        300
    );
} else {
    $dataQualityReport =
        paffp_quality_load_cache($dataQualityCacheFile);
}

if (
    is_array($dataQualityReport)
    && isset($dataQualityReport['summary'])
    && is_array($dataQualityReport['summary'])
) {
    $dataQualitySummary = array_merge(
        $dataQualitySummary,
        $dataQualityReport['summary']
    );
}

$temporaryAccess = loadTemporaryAccess($temporaryAccessFile);
$temporaryAccessActive = temporaryAccessIsActive($temporaryAccess, $now);
$temporaryAccessExpiresAt = (int) ($temporaryAccess['expires_at'] ?? 0);
$temporaryAccessMode = in_array(
    (string) ($temporaryAccess['mode'] ?? 'edit'),
    ['view', 'edit'],
    true
)
    ? (string) $temporaryAccess['mode']
    : 'edit';

$temporaryAccessAudience = in_array(
    (string) ($temporaryAccess['audience'] ?? 'all'),
    ['all', 'user'],
    true
)
    ? (string) $temporaryAccess['audience']
    : 'all';

$temporaryAccessAudienceValue = trim(
    (string) ($temporaryAccess['audience_value'] ?? '')
);

$temporaryAccessRemaining = $temporaryAccessActive
    ? max(0, $temporaryAccessExpiresAt - $now)
    : 0;

usort($announcements, function ($a, $b) {
    // Active announcements always appear first in Admin.
    $aActive = !empty($a['enabled']) ? 1 : 0;
    $bActive = !empty($b['enabled']) ? 1 : 0;

    if ($aActive !== $bActive) {
        return $bActive <=> $aActive;
    }

    // Within Active/Inactive groups, preserve priority ordering:
    // Urgent > High > Normal.
    $priorityWeight = [
        'urgent' => 3,
        'high' => 2,
        'normal' => 1
    ];

    $aPriority = strtolower((string) ($a['priority'] ?? 'normal'));
    $bPriority = strtolower((string) ($b['priority'] ?? 'normal'));

    $aWeight = $priorityWeight[$aPriority] ?? 1;
    $bWeight = $priorityWeight[$bPriority] ?? 1;

    if ($aWeight !== $bWeight) {
        return $bWeight <=> $aWeight;
    }

    // Newest modified announcement first as the final tie-breaker.
    $aUpdated = (int) ($a['updated_ts'] ?? ($a['created_ts'] ?? 0));
    $bUpdated = (int) ($b['updated_ts'] ?? ($b['created_ts'] ?? 0));

    return $bUpdated <=> $aUpdated;
});

$activeAnnouncementCount = 0;

foreach ($announcements as $announcementItem) {
    if (announcementState($announcementItem, $now) === 'active') {
        $activeAnnouncementCount++;
    }
}

$jsonFiles = safeJsonFiles($dataFolder);

$adminOverview = paffp_admin_collect_stats_cached(
    $dataFolder,
    $payouts,
    $dataFolder . '/_adminStatsCache.json'
);
$adminSummary = $adminOverview['summary'];
$verifierActivity = $adminOverview['verifiers'];
$recentVerifierActivity = $adminOverview['recent'];
$verifierNames = array_map(function ($item) {
    return (string) ($item['name'] ?? '');
}, $verifierActivity);
$fullBackups = paffp_admin_full_backup_list($fullBackupFolder);

/* --------------------------------------------------------------
   JSON manager page data
---------------------------------------------------------------- */
$selectedFile = isset($_GET['file'])
    ? basename((string) $_GET['file'])
    : '';

if ($selectedFile === '' || !in_array($selectedFile, $jsonFiles, true)) {
    if (in_array($defaultFile, $jsonFiles, true)) {
        $selectedFile = $defaultFile;
    } elseif (!empty($jsonFiles)) {
        $selectedFile = $jsonFiles[0];
    } else {
        $selectedFile = '';
    }
}

$file = $selectedFile !== ''
    ? $dataFolder . "/" . $selectedFile
    : '';

$jsonContent = '';

if ($file !== '' && is_file($file)) {
    $loaded = @file_get_contents($file);

    if ($loaded !== false) {
        $jsonContent = $loaded;
    }
}

$fileSize = ($file !== '' && is_file($file))
    ? filesize($file)
    : 0;

$fileModified = ($file !== '' && is_file($file))
    ? filemtime($file)
    : null;

$recordCount = countTopLevelRecords($jsonContent);
$isSystemFile = $selectedFile !== ''
    && substr($selectedFile, 0, 1) === '_';

$backups = backupListForFile($backupFolder, $selectedFile);

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DA RFO X | PAFFP Admin</title>

    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
    <link rel="icon" href="img/DA_transparent.png" type="image/png">

    <script>
        (function () {
            const darkMode = localStorage.getItem("darkMode") === "true";
            document.documentElement.setAttribute(
                "data-bs-theme",
                darkMode ? "dark" : "light"
            );
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php if ($section === 'json') { ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
    <?php } ?>

    <style>
        :root {
            --da-green: #198754;
            --da-green-dark: #146c43;
            --da-green-soft: rgba(25,135,84,.10);
            --body-bg: #f5f7f6;
            --surface: #ffffff;
            --surface-muted: #f7f9f8;
            --text-main: #1f2923;
            --text-soft: #6b756f;
            --border: #e2e7e4;
            --shadow: 0 .35rem 1.25rem rgba(30,48,38,.07);
        }

        [data-bs-theme="dark"] {
            --body-bg: #111714;
            --surface: #18201c;
            --surface-muted: #141b17;
            --text-main: #eef2ef;
            --text-soft: #a7b0aa;
            --border: #2a352e;
            --shadow: 0 .35rem 1.25rem rgba(0,0,0,.22);
            --da-green-soft: rgba(25,135,84,.16);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background: var(--body-bg);
            color: var(--text-main);
            font-family: "Montserrat", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .min-w-0 {
            min-width: 0;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 .12rem .5rem rgba(0,0,0,.035);
        }

        .brand-link,
        .brand-link:hover,
        .brand-link:focus,
        .brand-link:active,
        .brand-link:visited {
            color: inherit !important;
            text-decoration: none !important;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            flex: 0 0 50px;
            object-fit: contain;
        }

        .brand-kicker {
            color: var(--da-green);
            font-weight: 800;
            letter-spacing: .02em;
        }

        .brand-subtitle {
            color: var(--text-soft);
            font-size: .82rem;
        }

        .square-mobile {
            min-height: 38px;
            border-radius: 10px;
        }

        .admin-nav-wrap {
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .admin-nav {
            display: flex;
            gap: .35rem;
            overflow-x: auto;
            padding-top: .55rem;
            padding-bottom: .55rem;
            scrollbar-width: none;
        }

        .admin-nav::-webkit-scrollbar {
            display: none;
        }

        .admin-nav-link {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 38px;
            padding: .45rem .7rem;
            border: 1px solid transparent;
            border-radius: 10px;
            color: var(--text-soft);
            font-size: .76rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .admin-nav-link:hover {
            background: var(--surface-muted);
            color: var(--text-main);
        }

        .admin-nav-link.active {
            border-color: rgba(25,135,84,.2);
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .admin-nav-count {
            display: inline-grid;
            place-items: center;
            min-width: 22px;
            height: 22px;
            padding: 0 .35rem;
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .62rem;
        }

        .admin-nav-link.active .admin-nav-count {
            background: rgba(25,135,84,.12);
            color: var(--da-green);
        }

        .page-shell {
            padding-top: 1rem;
            padding-bottom: 2rem;
        }

        .surface-card {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .page-title {
            margin: 0;
            color: var(--text-main);
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .page-subtitle {
            margin-top: .18rem;
            color: var(--text-soft);
            font-size: .78rem;
        }

        .form-label {
            color: var(--text-main);
            font-size: .78rem;
            font-weight: 700;
        }

        .form-control,
        .form-select {
            border-color: var(--border);
            background-color: var(--surface);
            color: var(--text-main);
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(25,135,84,.55);
            box-shadow: 0 0 0 .2rem rgba(25,135,84,.12);
        }

        .flash-alert {
            border-radius: 12px;
            font-size: .8rem;
        }

        /* ---------------- Announcement manager ---------------- */

        .composer-card {
            padding: 1rem;
        }

        .announcement-preview-wrap {
            margin-top: 1rem;
        }

        .announcement-preview-label {
            margin-bottom: .45rem;
            color: var(--text-soft);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .announcement-preview {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 245px;
            padding: 1.45rem 1.2rem;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface-muted);
            text-align: center;
            overflow: hidden;
        }

        .preview-icon {
            --preview-pulse: 13,202,240;
            display: grid;
            place-items: center;
            width: 74px;
            height: 74px;
            flex: 0 0 74px;
            margin: 0 auto .9rem;
            border-radius: 50%;
            background: rgba(var(--preview-pulse),.12);
            color: rgb(var(--preview-pulse));
            font-size: 1.9rem;
            animation: announcementPreviewPulse 1.8s ease-in-out infinite;
            transform-origin: center;
        }

        .announcement-preview.info .preview-icon {
            --preview-pulse: 13,202,240;
        }

        .announcement-preview.success .preview-icon {
            --preview-pulse: 25,135,84;
        }

        .announcement-preview.warning .preview-icon {
            --preview-pulse: 181,129,0;
        }

        .announcement-preview.danger .preview-icon {
            --preview-pulse: 220,53,69;
        }

        .preview-title {
            max-width: 100%;
            color: var(--text-main);
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .preview-message {
            max-width: 430px;
            margin-top: .5rem;
            color: var(--text-soft);
            font-size: .82rem;
            line-height: 1.5;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .preview-home-action {
            margin-top: .9rem;
            pointer-events: none;
        }

        @keyframes announcementPreviewPulse {
            0%,100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(var(--preview-pulse),0);
            }
            50% {
                transform: scale(1.08);
                box-shadow: 0 0 0 11px rgba(var(--preview-pulse),.10);
            }
        }

        .announcement-list-card {
            overflow: hidden;
        }

        .list-header {
            padding: .8rem .9rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .announcement-item {
            padding: .85rem .9rem;
            border-bottom: 1px solid var(--border);
        }

        .announcement-item:last-child {
            border-bottom: 0;
        }

        .announcement-title {
            font-size: .82rem;
            font-weight: 800;
        }

        .announcement-message {
            margin-top: .2rem;
            color: var(--text-soft);
            font-size: .72rem;
            white-space: pre-line;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .55rem;
        }

        .meta-chip {
            padding: .25rem .45rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .64rem;
        }

        .announcement-actions {
            display: flex;
            gap: .35rem;
            flex-wrap: wrap;
        }

        /* ---------------- JSON manager ---------------- */

        .json-header-card {
            padding: .9rem 1rem;
            margin-bottom: 1rem;
        }

        .system-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            margin-left: .4rem;
            padding: .22rem .45rem;
            border-radius: 999px;
            background: rgba(220,53,69,.1);
            color: #dc3545;
            font-size: .63rem;
            font-weight: 750;
            vertical-align: middle;
        }

        .file-picker {
            position: relative;
            width: min(360px,100%);
        }

        .file-search-input {
            min-height: 42px;
            padding-left: 2.15rem;
            border-radius: 10px;
        }

        .file-search-icon {
            position: absolute;
            top: 50%;
            left: .75rem;
            z-index: 2;
            color: var(--text-soft);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .file-picker-menu {
            position: absolute;
            top: calc(100% + .35rem);
            left: 0;
            right: 0;
            z-index: 1050;
            max-height: 300px;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 .75rem 2rem rgba(0,0,0,.14);
        }

        .file-picker-item {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .6rem .7rem;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--text-main);
            font-size: .76rem;
            text-align: left;
        }

        .file-picker-item:last-child {
            border-bottom: 0;
        }

        .file-picker-item:hover,
        .file-picker-item.is-current {
            background: var(--surface-muted);
        }

        .file-picker-item-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-top: .65rem;
        }

        .file-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 30px;
            padding: .3rem .55rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .7rem;
            white-space: nowrap;
        }

        .file-meta-chip i {
            color: var(--da-green);
        }

        .file-meta-chip.system i {
            color: #dc3545;
        }

        .backup-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .7rem;
            margin-bottom: 1rem;
            padding: .75rem .85rem;
        }

        .backup-title {
            color: var(--text-main);
            font-size: .8rem;
            font-weight: 750;
        }

        .backup-subtitle {
            margin-top: .12rem;
            color: var(--text-soft);
            font-size: .68rem;
        }

        .backup-select {
            min-width: 260px;
            max-width: 360px;
        }

        .editor-card {
            overflow: hidden;
        }

        .editor-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .85rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .editor-toolbar-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 750;
        }

        .editor-toolbar-title i {
            color: var(--da-green);
        }

        .selected-file-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .editor-status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            color: var(--text-soft);
            font-size: .7rem;
            white-space: nowrap;
        }

        .editor-status.is-valid {
            color: var(--da-green);
        }

        .editor-status.is-invalid {
            color: #dc3545;
        }

        .editor-toolbar-actions {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex: 0 0 auto;
        }

        .json-action-btn {
            min-height: 34px;
            border-radius: 9px;
            font-size: .72rem;
        }

        .CodeMirror {
            height: 58vh;
            min-height: 420px;
            border: 0;
            background: var(--surface);
            color: var(--text-main);
            font-family: Consolas,Monaco,"Courier New",monospace;
            font-size: .86rem;
            line-height: 1.5;
        }

        [data-bs-theme="light"] .CodeMirror {
            background: #fff;
            color: #1f2923;
        }

        [data-bs-theme="light"] .CodeMirror-gutters {
            border-right: 1px solid var(--border);
            background: #f8faf9;
        }

        [data-bs-theme="light"] .CodeMirror-linenumber {
            color: #8a958e;
        }

        .cm-error-line {
            background: rgba(220,53,69,.12) !important;
        }

        .editor-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .8rem .85rem;
            border-top: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .footer {
            padding: 1rem 0 1.5rem;
            color: var(--text-soft);
            font-size: .72rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .brand-subtitle {
                display: none;
            }

            .json-header-layout,
            .backup-toolbar {
                flex-direction: column;
                align-items: stretch !important;
            }

            .file-picker,
            .backup-select {
                min-width: 0;
                max-width: none;
                width: 100%;
            }

            .editor-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .CodeMirror {
                min-height: 360px;
                height: 55vh;
                font-size: .8rem;
            }
        }

        @media (max-width: 575.98px) {
            .square-mobile {
                width: 38px;
                min-width: 38px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .square-mobile .btn-label {
                display: none;
            }

            .square-mobile i {
                margin: 0 !important;
            }

            .composer-card {
                padding: .8rem;
            }

            .announcement-preview {
                min-height: 215px;
                padding: 1.2rem .9rem;
                border-radius: 15px;
            }

            .preview-icon {
                width: 64px;
                height: 64px;
                flex-basis: 64px;
                margin-bottom: .75rem;
                font-size: 1.55rem;
            }

            .preview-title {
                font-size: .98rem;
            }

            .preview-message {
                font-size: .76rem;
            }

            .editor-toolbar {
                align-items: flex-start;
                padding: .6rem .7rem;
            }

            .editor-toolbar-actions {
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .json-action-btn span {
                display: none;
            }

            .json-action-btn {
                width: 34px;
                min-width: 34px;
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .preview-icon {
                animation: none !important;
            }
        }
    
        /* =========================================================
           FINAL TYPOGRAPHY OVERRIDE
           Matches the readable scale used by verify.php.
           Kept at the end so earlier admin component CSS cannot
           override it.
        ========================================================== */

        body {
            font-size: 1.05rem !important;
        }

        .brand-kicker {
            font-size: 1.05rem !important;
        }

        .brand-subtitle {
            font-size: .9rem !important;
            line-height: 1.35 !important;
        }

        .page-title {
            font-size: clamp(1.15rem, 2vw, 1.48rem) !important;
        }

        .page-subtitle {
            font-size: .86rem !important;
        }

        .admin-nav-link {
            font-size: .9rem !important;
        }

        .admin-nav-count {
            font-size: .76rem !important;
        }

        .form-label {
            font-size: .9rem !important;
        }

        .form-control,
        .form-select {
            font-size: .95rem !important;
        }

        .form-check-label {
            font-size: .9rem !important;
        }

        .btn,
        .topbar .btn,
        .composer-card .btn,
        .announcement-actions .btn,
        .backup-toolbar .btn,
        .editor-toolbar .btn,
        .editor-footer .btn {
            font-size: .92rem !important;
            font-weight: 400 !important;
        }

        .flash-alert {
            font-size: .9rem !important;
        }

        /* Announcement manager */
        .announcement-preview-label {
            font-size: .8rem !important;
        }

        .preview-title {
            font-size: 1.2rem !important;
        }

        .preview-message {
            font-size: .88rem !important;
            line-height: 1.55 !important;
        }

        .announcement-title {
            font-size: .95rem !important;
        }

        .announcement-message {
            font-size: .86rem !important;
            line-height: 1.5 !important;
        }

        .meta-chip {
            font-size: .76rem !important;
        }

        /* JSON manager */
        .system-badge {
            font-size: .74rem !important;
        }

        .file-picker-item {
            font-size: .9rem !important;
        }

        .file-meta-chip {
            font-size: .82rem !important;
        }

        .backup-title {
            font-size: .92rem !important;
        }

        .backup-subtitle {
            font-size: .8rem !important;
        }

        .editor-toolbar-title {
            font-size: .92rem !important;
        }

        .editor-status {
            font-size: .84rem !important;
        }

        .json-action-btn {
            font-size: .85rem !important;
        }

        .CodeMirror {
            font-size: .95rem !important;
            line-height: 1.55 !important;
        }

        .footer {
            font-size: .84rem !important;
        }

        /* Bootstrap modal copy used by JSON save/restore */
        .modal-title {
            font-size: 1rem !important;
        }

        .modal-body,
        .modal-footer {
            font-size: .9rem !important;
        }

        @media (max-width: 575.98px) {
            body {
                font-size: 1rem !important;
            }

            .brand-kicker {
                font-size: 1rem !important;
            }

            .brand-subtitle {
                font-size: .84rem !important;
            }

            .page-title {
                font-size: clamp(1.1rem, 5vw, 1.3rem) !important;
            }

            .page-subtitle {
                font-size: .82rem !important;
            }

            .admin-nav-link {
                font-size: .85rem !important;
            }

            .form-label {
                font-size: .86rem !important;
            }

            .form-control,
            .form-select {
                font-size: .92rem !important;
            }

            .preview-title {
                font-size: 1.05rem !important;
            }

            .preview-message {
                font-size: .82rem !important;
            }

            .announcement-title {
                font-size: .9rem !important;
            }

            .announcement-message {
                font-size: .82rem !important;
            }

            .file-picker-item {
                font-size: .86rem !important;
            }

            .file-meta-chip {
                font-size: .78rem !important;
            }

            .editor-toolbar-title {
                font-size: .88rem !important;
            }

            .editor-status {
                font-size: .8rem !important;
            }

            .CodeMirror {
                font-size: .9rem !important;
            }
        }

    
        /* ---------------- Advertisement controls ---------------- */

        .ad-control-page {
            padding: 1rem;
        }

        .ad-control-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .ad-control-summary {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            flex: 0 0 auto;
            min-height: 38px;
            padding: .45rem .7rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .84rem;
        }

        .ad-control-summary i {
            color: var(--da-green);
        }

        .ad-control-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .ad-control-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: .8rem;
            min-height: 128px;
            padding: .9rem;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface-muted);
            cursor: pointer;
            transition: border-color .15s ease, transform .15s ease, background .15s ease;
        }

        .ad-control-card:hover {
            border-color: rgba(25,135,84,.4);
            background: var(--surface);
            transform: translateY(-1px);
        }

        .ad-control-icon {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1rem;
        }

        .ad-control-copy {
            min-width: 0;
        }

        .ad-control-title {
            color: var(--text-main);
            font-size: .95rem;
            font-weight: 750;
        }

        .ad-control-description {
            margin-top: .2rem;
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.45;
        }

        .ad-control-switch {
            display: flex;
            align-items: center;
            margin: 0;
        }

        .ad-control-switch .form-check-input {
            width: 2.6rem;
            height: 1.35rem;
            margin: 0;
            cursor: pointer;
        }


        .ad-page-groups {
            display: grid;
            gap: .9rem;
        }

        .ad-page-group {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--surface-muted);
        }

        .ad-page-master {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: .8rem;
            padding: .9rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            cursor: pointer;
        }

        .ad-page-master-title {
            color: var(--text-main);
            font-size: .96rem;
            font-weight: 800;
        }

        .ad-page-master-meta {
            margin-top: .18rem;
            color: var(--text-soft);
            font-size: .78rem;
        }

        .ad-slot-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .6rem;
            padding: .75rem;
        }

        .ad-slot-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: .65rem;
            min-height: 78px;
            padding: .7rem .75rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface);
            cursor: pointer;
        }

        .ad-slot-card:hover {
            border-color: rgba(25,135,84,.4);
        }

        .ad-slot-title {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 750;
        }

        .ad-slot-description {
            margin-top: .16rem;
            color: var(--text-soft);
            font-size: .73rem;
            line-height: 1.4;
        }

        .ad-page-group.is-page-disabled .ad-slot-list {
            opacity: .5;
        }

        .ad-page-group.is-page-disabled .ad-slot-list::before {
            content: "Page master is OFF — slot preferences are preserved.";
            grid-column: 1 / -1;
            color: var(--text-soft);
            font-size: .72rem;
        }

        @media (max-width: 767.98px) {
            .ad-slot-list {
                grid-template-columns: 1fr;
            }
        }

        .ad-control-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: .85rem;
            border-top: 1px solid var(--border);
        }

        .ad-control-note {
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.4;
        }

        .ad-control-note i {
            margin-right: .25rem;
            color: var(--da-green);
        }

        @media (max-width: 991.98px) {
            .ad-control-grid {
                grid-template-columns: 1fr;
            }

            .ad-control-card {
                min-height: 0;
            }
        }

        @media (max-width: 575.98px) {
            .ad-control-heading,
            .ad-control-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .ad-control-summary {
                align-self: flex-start;
            }

            .ad-control-card {
                grid-template-columns: auto minmax(0, 1fr) auto;
                padding: .8rem;
            }

            .ad-control-description {
                font-size: .78rem;
            }

            .ad-control-footer .btn {
                width: 100%;
            }
        }

    
        /* Saved announcement visual previews */
        .saved-announcement-preview {
            --saved-preview-color: 13, 202, 240;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 215px;
            padding: 1.35rem 1.15rem 1.15rem;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--surface-muted);
            text-align: center;
            overflow: hidden;
        }

        .saved-announcement-preview.info {
            --saved-preview-color: 13, 202, 240;
        }

        .saved-announcement-preview.success {
            --saved-preview-color: 25, 135, 84;
        }

        .saved-announcement-preview.warning {
            --saved-preview-color: 181, 129, 0;
        }

        .saved-announcement-preview.danger {
            --saved-preview-color: 220, 53, 69;
        }

        .saved-announcement-state {
            position: absolute;
            top: .65rem;
            right: .65rem;
            z-index: 2;
            font-size: .7rem !important;
        }

        .saved-preview-icon {
            display: grid;
            place-items: center;
            width: 66px;
            height: 66px;
            flex: 0 0 66px;
            margin: 0 auto .8rem;
            border-radius: 50%;
            background: rgba(var(--saved-preview-color), .12);
            color: rgb(var(--saved-preview-color));
            font-size: 1.7rem;
            animation: savedAnnouncementPulse 1.8s ease-in-out infinite;
            transform-origin: center;
        }

        .saved-preview-title {
            max-width: 100%;
            color: var(--text-main);
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .saved-preview-message {
            max-width: 520px;
            margin-top: .45rem;
            color: var(--text-soft);
            font-size: .82rem;
            line-height: 1.5;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        @keyframes savedAnnouncementPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(var(--saved-preview-color), 0);
            }

            50% {
                transform: scale(1.07);
                box-shadow: 0 0 0 9px rgba(var(--saved-preview-color), .09);
            }
        }

        .announcement-item .meta-row {
            margin-top: .7rem;
        }

        .announcement-item .announcement-actions {
            margin-top: .55rem !important;
        }

        @media (max-width: 575.98px) {
            .saved-announcement-preview {
                min-height: 190px;
                padding: 1.2rem .9rem 1rem;
                border-radius: 13px;
            }

            .saved-preview-icon {
                width: 58px;
                height: 58px;
                flex-basis: 58px;
                margin-bottom: .7rem;
                font-size: 1.45rem;
            }

            .saved-preview-title {
                font-size: .98rem;
            }

            .saved-preview-message {
                font-size: .8rem;
            }

            .saved-announcement-state {
                top: .5rem;
                right: .5rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .saved-preview-icon {
                animation: none !important;
            }
        }

    
        .payout-list-tools {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .6rem;
            flex-wrap: wrap;
        }

        .temporary-access-btn {
            white-space: nowrap;
        }

        .temporary-access-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 0 0 .9rem;
            padding: .7rem .85rem;
            border: 1px solid rgba(255, 193, 7, .35);
            border-radius: 11px;
            background: rgba(255, 193, 7, .09);
        }

        .temporary-access-status-main {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-width: 0;
        }

        .temporary-access-status-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: rgba(255, 193, 7, .18);
            color: #a56b00;
        }

        [data-bs-theme="dark"] .temporary-access-status-icon {
            color: #ffd970;
        }

        .temporary-access-status-title {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 600;
        }

        .temporary-access-status-subtitle {
            color: var(--text-soft);
            font-size: .75rem;
            line-height: 1.4;
        }

        .temporary-access-note {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            padding: .8rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.5;
        }

        @media (max-width: 767.98px) {
            .payout-list-tools {
                width: 100%;
                justify-content: stretch;
            }

            .payout-list-tools .temporary-access-btn,
            .payout-list-tools .payout-admin-search {
                width: 100%;
            }

            .temporary-access-status {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        /* ---------------- Payout Events ---------------- */

        .payout-admin-stat {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-height: 92px;
            padding: .85rem;
        }

        .payout-admin-stat-icon {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 12px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1rem;
        }

        .payout-admin-stat-icon.is-live {
            background: rgba(25,135,84,.14);
        }

        .payout-admin-stat-value {
            color: var(--text-main);
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .payout-admin-stat-label {
            margin-top: .15rem;
            color: var(--text-soft);
            font-size: .78rem;
        }

        .payout-create-card {
            padding: 1rem;
        }

        .payout-create-heading {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .payout-create-heading-icon {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            flex: 0 0 46px;
            border-radius: 13px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1rem;
        }

        .payout-create-card .form-text {
            color: var(--text-soft);
            font-size: .72rem;
        }

        .payout-source-switch {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .4rem;
            padding: .28rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        .payout-source-option {
            min-height: 40px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--text-soft);
            font-size: .84rem;
        }

        .payout-source-option.active {
            background: var(--surface);
            color: var(--da-green);
            box-shadow: 0 .15rem .5rem rgba(0,0,0,.06);
            font-weight: 700;
        }

        .payout-source-option i {
            margin-right: .35rem;
        }

        .payout-file-drop {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 165px;
            padding: 1rem;
            border: 1px dashed rgba(25,135,84,.45);
            border-radius: 14px;
            background: var(--surface-muted);
            text-align: center;
            cursor: pointer;
        }

        .payout-file-drop:hover {
            border-color: var(--da-green);
            background: var(--surface);
        }

        .payout-file-drop-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            margin-bottom: .65rem;
            border-radius: 50%;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1.15rem;
        }

        .payout-file-drop-title {
            color: var(--text-main);
            font-size: .9rem;
            font-weight: 750;
        }

        .payout-file-drop-subtitle {
            max-width: 100%;
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .75rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payout-json-text {
            min-height: 220px;
            resize: vertical;
            font-family: Consolas, Monaco, "Courier New", monospace;
            font-size: .82rem !important;
            line-height: 1.45;
        }

        .payout-data-status {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 64px;
            margin-top: .7rem;
            padding: .65rem .75rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        .payout-data-status-icon {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            background: rgba(108,117,125,.10);
            color: var(--text-soft);
        }

        .payout-data-status-title {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 750;
        }

        .payout-data-status-text {
            margin-top: .08rem;
            color: var(--text-soft);
            font-size: .73rem;
            line-height: 1.35;
        }

        .payout-data-status.is-valid {
            border-color: rgba(25,135,84,.25);
            background: rgba(25,135,84,.06);
        }

        .payout-data-status.is-valid .payout-data-status-icon {
            background: rgba(25,135,84,.12);
            color: var(--da-green);
        }

        .payout-data-status.is-invalid {
            border-color: rgba(220,53,69,.25);
            background: rgba(220,53,69,.05);
        }

        .payout-data-status.is-invalid .payout-data-status-icon {
            background: rgba(220,53,69,.10);
            color: #dc3545;
        }

        .payout-create-notice {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            padding: .65rem .75rem;
            border-radius: 11px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.45;
        }

        .payout-create-notice i {
            margin-top: .18rem;
            color: var(--da-green);
        }

        .payout-list-card {
            overflow: hidden;
        }

        .payout-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .payout-admin-search {
            position: relative;
            width: min(270px, 100%);
        }

        .payout-admin-search > i {
            position: absolute;
            top: 50%;
            left: .75rem;
            z-index: 2;
            color: var(--text-soft);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .payout-admin-search .form-control {
            padding-left: 2.1rem;
        }

        .payout-event-list {
            max-height: 710px;
            overflow-y: auto;
        }

        .payout-event-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .85rem;
            padding: .85rem .9rem;
            border-bottom: 1px solid var(--border);
        }

        .payout-event-item:last-child {
            border-bottom: 0;
        }

        .payout-event-main {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .payout-event-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 11px;
            background: var(--surface-muted);
            color: var(--text-soft);
        }

        .payout-event-icon.ongoing {
            background: rgba(25,135,84,.12);
            color: var(--da-green);
        }

        .payout-event-icon.upcoming {
            background: rgba(13,110,253,.10);
            color: #0d6efd;
        }

        .payout-event-copy {
            flex: 1 1 auto;
        }

        .payout-event-title {
            color: var(--text-main);
            font-size: .9rem;
            font-weight: 800;
        }

        .payout-event-status {
            flex: 0 0 auto;
            font-size: .66rem !important;
        }

        .payout-event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .75rem;
            margin-top: .32rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .payout-event-meta span {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            min-width: 0;
        }

        .payout-event-actions {
            display: flex;
            align-items: center;
            gap: .35rem;
            flex: 0 0 auto;
        }

        .payout-event-actions .payout-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0;
            line-height: 1;
        }

        .payout-event-actions .payout-action-icon i {
            margin: 0 !important;
        }

        @media (max-width: 767.98px) {
            .payout-list-header {
                flex-direction: column;
                align-items: stretch;
            }

            .payout-admin-search {
                width: 100%;
                max-width: none;
            }

            .payout-event-item {
                align-items: stretch;
                flex-direction: column;
            }

            .payout-event-actions {
                justify-content: flex-end;
            }
        }

        @media (max-width: 575.98px) {
            .payout-admin-stat {
                min-height: 82px;
                padding: .7rem;
            }

            .payout-admin-stat-icon {
                width: 40px;
                height: 40px;
                flex-basis: 40px;
            }

            .payout-admin-stat-value {
                font-size: 1.05rem;
            }

            .payout-source-switch {
                grid-template-columns: 1fr;
            }

            .payout-event-main {
                align-items: flex-start;
            }

            .payout-event-meta {
                display: grid;
                gap: .25rem;
            }

            .payout-event-actions .btn {
                flex: 0 0 38px;
            }

            .payout-event-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }

    
        /* Edit payout event */
        .payout-edit-modal {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            overflow: hidden;
        }

        .payout-edit-info {
            display: grid;
            gap: .4rem;
            padding: .7rem .75rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-muted);
        }

        .payout-edit-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-size: .8rem;
        }

        .payout-edit-info-row span {
            color: var(--text-soft);
        }

        .payout-edit-info-row strong {
            min-width: 0;
            color: var(--text-main);
            text-align: right;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payout-edit-note {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            padding: .65rem .75rem;
            border-radius: 10px;
            background: rgba(13,110,253,.06);
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.45;
        }

        .payout-edit-note i {
            margin-top: .15rem;
            color: #0d6efd;
        }

        @media (max-width: 575.98px) {
            .payout-event-actions {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                width: 100%;
            }

            .payout-event-actions .btn {
                width: 100%;
            }

            .payout-edit-info-row {
                align-items: flex-start;
                flex-direction: column;
                gap: .15rem;
            }

            .payout-edit-info-row strong {
                width: 100%;
                text-align: left;
            }
        }

    
        /* Google Sheet import: compact responsive preview */
        .payout-google-hint,
        .payout-google-privacy {
            display: flex;
            gap: .5rem;
            align-items: flex-start;
            padding: .68rem .75rem;
            color: var(--text-soft);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: .76rem;
            line-height: 1.5;
            background: var(--surface-muted);
        }
        .payout-google-privacy i,
        .payout-google-hint i { margin-top: .14rem; color: var(--da-green); }
        .payout-google-preview {
            padding: .85rem;
            border: 1px solid rgba(25, 135, 84, .25);
            border-radius: 12px;
            background: var(--surface-muted);
        }
        .payout-google-preview-heading {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: .65rem;
        }
        .payout-google-preview-count {
            color: var(--da-green); font-size: 1.5rem;
            font-weight: 800; line-height: 1.1;
        }
        .payout-google-preview-label,
        .payout-google-preview-help {
            color: var(--text-soft); font-size: .73rem;
        }
        .payout-google-preview-columns {
            display: flex; gap: .3rem; flex-wrap: wrap; margin-top: .85rem;
        }
        .payout-google-preview-columns span {
            font-size: .7rem; padding: .25rem .45rem;
            border: 1px solid var(--border); border-radius: 7px;
            background: var(--surface);
            color: var(--text-main);
        }
        .payout-google-sample-table { font-size: .73rem; color: var(--text-main); }
        .payout-google-sample-table th,
        .payout-google-sample-table td {
            min-width: 75px; max-width: 160px;
            overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap; vertical-align: middle;
        }
        @media (min-width: 576px) and (max-width: 767.98px) {
            .payout-source-switch { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

    
        /* ============================================================
           Admin Operations Dashboard / Activity / Backups
        ============================================================ */
        .dashboard-hero,
        .dashboard-panel,
        .activity-page,
        .backup-create-card,
        .restore-center-card {
            padding: 1rem;
        }

        .dashboard-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .dashboard-kicker {
            margin-bottom: .22rem;
            color: var(--da-green);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .dashboard-completion {
            flex: 0 0 auto;
            min-width: 135px;
            padding: .7rem .85rem;
            border: 1px solid rgba(25,135,84,.2);
            border-radius: 13px;
            background: var(--da-green-soft);
            text-align: right;
        }

        .dashboard-completion-value {
            color: var(--da-green);
            font-size: 1.35rem;
            font-weight: 850;
            line-height: 1;
        }

        .dashboard-completion-label {
            margin-top: .22rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .dashboard-kpi {
            position: relative;
            min-height: 118px;
            padding: .9rem;
            overflow: hidden;
        }

        .dashboard-kpi-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            margin-bottom: .55rem;
            border-radius: 11px;
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .dashboard-kpi-icon.is-success {
            background: rgba(25,135,84,.12);
            color: #198754;
        }

        .dashboard-kpi-icon.is-warning {
            background: rgba(255,193,7,.16);
            color: #b58100;
        }

        .dashboard-kpi-icon.is-info {
            background: rgba(13,202,240,.12);
            color: #0aa2c0;
        }

        .dashboard-kpi-value {
            color: var(--text-main);
            font-size: 1.3rem;
            font-weight: 850;
            line-height: 1.05;
        }

        .dashboard-kpi-label {
            margin-top: .2rem;
            color: var(--text-soft);
            font-size: .76rem;
        }

        .dashboard-panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: .8rem;
        }

        .dashboard-table,
        .activity-table {
            color: var(--text-main);
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            --bs-table-border-color: var(--border);
        }

        .dashboard-table th,
        .activity-table th {
            color: var(--text-soft);
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .dashboard-table td,
        .activity-table td {
            font-size: .8rem;
        }

        .dashboard-status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .55rem;
        }

        .dashboard-status-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .7rem;
            min-height: 54px;
            padding: .6rem .7rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-muted);
        }

        .dashboard-status-card span {
            color: var(--text-soft);
            font-size: .75rem;
        }

        .dashboard-status-card strong {
            color: var(--text-main);
            font-size: .88rem;
        }

        .ad-status-list {
            display: grid;
            gap: .5rem;
        }

        .ad-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .65rem .7rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-muted);
        }

        .ad-status-row strong {
            color: var(--text-main);
            font-size: .82rem;
        }

        .activity-summary-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            flex: 0 0 auto;
            padding: .42rem .6rem;
            border: 1px solid rgba(25,135,84,.22);
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .74rem;
            font-weight: 750;
        }

        .activity-search-wrap {
            position: relative;
            max-width: 390px;
        }

        .activity-search-wrap > i {
            position: absolute;
            top: 50%;
            left: .75rem;
            z-index: 2;
            color: var(--text-soft);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .activity-search-wrap .form-control {
            padding-left: 2.15rem;
        }

        .activity-last-payout {
            max-width: 260px;
        }

        .backup-create-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            border-radius: 15px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1.25rem;
        }

        .backup-note {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            padding: .7rem .75rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.45;
        }

        .backup-note i {
            margin-top: .14rem;
            color: var(--da-green);
        }

        .backup-list {
            display: grid;
        }

        .backup-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .8rem 0;
            border-bottom: 1px solid var(--border);
        }

        .backup-item:last-child {
            border-bottom: 0;
        }

        .backup-item-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 11px;
            background: var(--surface-muted);
            color: var(--da-green);
        }

        .backup-item-copy {
            flex: 1 1 auto;
        }

        .backup-item-name {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 750;
        }

        .backup-item-meta {
            margin-top: .12rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .backup-item-actions {
            display: flex;
            align-items: center;
            gap: .35rem;
            flex: 0 0 auto;
        }

        .payout-control-form {
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .dashboard-hero,
            .dashboard-panel-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .dashboard-completion {
                width: 100%;
                text-align: left;
            }

            .backup-item {
                align-items: stretch;
                flex-direction: column;
            }

            .backup-item-actions {
                width: 100%;
            }

            .backup-item-actions .btn,
            .backup-item-actions form {
                flex: 1 1 0;
            }

            .backup-item-actions form .btn {
                width: 100%;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-status-grid {
                grid-template-columns: 1fr;
            }

            .activity-summary-chip {
                align-self: flex-start;
            }

            .payout-event-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .payout-event-actions form {
                width: 100%;
            }

            .payout-event-actions form .btn {
                width: 100%;
            }
        }

    
        /* Shared Admin confirmation / notice modal */
        .admin-action-modal {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            overflow: hidden;
        }

        .admin-action-icon {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 50%;
            background: var(--surface-muted);
            color: var(--text-soft);
        }

        .admin-action-icon.is-warning {
            background: rgba(255,193,7,.14);
            color: #b58100;
        }

        .admin-action-icon.is-danger {
            background: rgba(220,53,69,.11);
            color: #dc3545;
        }

        .admin-action-icon.is-success {
            background: rgba(25,135,84,.12);
            color: var(--da-green);
        }

        .admin-action-icon.is-info {
            background: rgba(13,110,253,.10);
            color: #0d6efd;
        }

        .admin-action-message {
            color: var(--text-main);
            font-size: .92rem;
            line-height: 1.55;
        }

        .admin-action-details {
            margin-top: .8rem;
            padding: .7rem .75rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .78rem;
            line-height: 1.45;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        @media (max-width: 575.98px) {
            .admin-action-modal .modal-footer {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .admin-action-modal .modal-footer .btn {
                width: 100%;
                margin: 0;
            }

            .admin-action-modal.is-notice .modal-footer {
                grid-template-columns: 1fr;
            }
        }

    
        /* Accomplished payout access override */
        .payout-access-modal {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            overflow: hidden;
        }

        .payout-access-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
        }

        .payout-access-option {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: start;
            gap: .65rem;
            min-height: 128px;
            padding: .8rem;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: var(--surface-muted);
            cursor: pointer;
        }

        .payout-access-option:hover {
            border-color: rgba(25,135,84,.35);
            background: var(--surface);
        }

        .payout-access-option > .form-check-input {
            position: absolute;
            top: .65rem;
            right: .65rem;
            margin: 0;
        }

        .payout-access-option:has(.form-check-input:checked) {
            border-color: rgba(25,135,84,.55);
            box-shadow: 0 0 0 2px rgba(25,135,84,.08);
            background: var(--surface);
        }

        .payout-access-option-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--surface);
            color: var(--text-soft);
        }

        .payout-access-option-icon.is-closed {
            color: #dc3545;
            background: rgba(220,53,69,.08);
        }

        .payout-access-option-icon.is-all {
            color: var(--da-green);
            background: rgba(25,135,84,.10);
        }

        .payout-access-option-icon.is-users {
            color: #b58100;
            background: rgba(255,193,7,.12);
        }

        .payout-access-option-title {
            padding-right: 1.3rem;
            color: var(--text-main);
            font-size: .88rem;
            font-weight: 800;
        }

        .payout-access-option-text {
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.45;
        }

        .payout-specific-users {
            margin-top: 1rem;
            padding: .8rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        
        .individual-access-list {
            display: grid;
            gap: .55rem;
            margin-bottom: .75rem;
        }

        .individual-access-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 150px 38px;
            gap: .5rem;
            align-items: center;
            padding: .6rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        .individual-access-name {
            min-width: 0;
            overflow: hidden;
            font-size: .82rem;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .individual-access-row .form-select {
            font-size: .78rem;
        }

        @media (max-width: 575.98px) {
            .individual-access-row {
                grid-template-columns: minmax(0, 1fr) 115px 36px;
            }
        }

.payout-user-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            max-height: 170px;
            overflow-y: auto;
        }

        .accomplished-user-chip {
            font-size: .72rem !important;
        }

        .payout-access-error {
            margin-top: .4rem;
            color: #dc3545;
            font-size: .74rem;
        }

        .payout-access-note {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            padding: .65rem .75rem;
            border-radius: 10px;
            background: rgba(13,110,253,.06);
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.45;
        }

        .payout-access-note i {
            margin-top: .15rem;
            color: #0d6efd;
        }

        @media (max-width: 767.98px) {
            .payout-access-options {
                grid-template-columns: 1fr;
            }

            .payout-access-option {
                min-height: 0;
            }
        }

    
        .access-request-action-cell {
            min-width: 330px;
        }

        .access-request-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: .45rem;
        }

        .access-request-approve-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: .45rem;
            margin: 0;
        }

        .access-request-secondary-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .45rem;
        }

        .access-request-actions .form-select {
            width: auto;
            min-width: 112px;
        }

        .access-request-actions .btn {
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .access-request-action-cell {
                min-width: 260px;
            }

            .access-request-actions,
            .access-request-approve-form,
            .access-request-secondary-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .access-request-action-cell {
                min-width: 230px;
            }

            .access-request-approve-form .form-select {
                flex: 1 1 100%;
                width: 100%;
            }

            .access-request-actions .btn {
                flex: 1 1 auto;
            }
        }


        .access-request-reason {
            max-width: 320px;
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.45;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .access-expiry-custom {
            min-width: 205px;
        }

        .access-request-toast-container {
            position: fixed;
            z-index: 10900;
            right: 1rem;
            bottom: 1rem;
            width: min(390px, calc(100vw - 2rem));
        }

        .access-request-toast {
            border: 1px solid var(--border);
            border-left: 4px solid var(--da-green);
            border-radius: 14px;
            background: var(--surface);
            color: var(--text-main);
            box-shadow: var(--shadow);
        }

        .access-request-toast .toast-header {
            border-bottom-color: var(--border);
            background: var(--surface-muted);
            color: var(--text-main);
        }

        .quality-kpi {
            height: 100%;
            padding: 1rem;
        }

        .quality-kpi-value {
            color: var(--text-main);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .quality-kpi-label {
            margin-top: .35rem;
            color: var(--text-soft);
            font-size: .76rem;
        }

        .quality-issue-list {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .quality-issue-chip {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .28rem .48rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-main);
            font-size: .7rem;
            line-height: 1.2;
        }

        .quality-issue-chip.is-danger {
            border-color: rgba(220,53,69,.28);
            background: rgba(220,53,69,.08);
            color: #dc3545;
        }

        .quality-issue-chip.is-warning {
            border-color: rgba(181,129,0,.28);
            background: rgba(255,193,7,.08);
            color: #9b7100;
        }

        .quality-samples {
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .68rem;
            line-height: 1.4;
        }

        .quality-ok {
            color: var(--da-green);
            font-size: .76rem;
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .access-expiry-custom {
                min-width: 100%;
                width: 100%;
            }

            .access-request-toast-container {
                right: .65rem;
                bottom: .65rem;
                width: calc(100vw - 1.3rem);
            }
        }


        /* ==========================================================
           PAFFP ADMIN UNIFIED DESIGN SYSTEM
           Applied across every authenticated Admin feature.
        ========================================================== */

        :root {
            --admin-radius-card: 16px;
            --admin-radius-inner: 12px;
            --admin-radius-control: 10px;
            --admin-space-card: 1rem;
            --admin-control-height: 40px;
            --admin-control-height-sm: 36px;
            --admin-icon-size: 40px;
            --admin-shadow-soft: 0 .28rem 1rem rgba(30,48,38,.055);
        }

        [data-bs-theme="dark"] {
            --admin-shadow-soft: 0 .28rem 1rem rgba(0,0,0,.18);
        }

        .page-shell {
            width: 100%;
            max-width: 1680px;
            margin-right: auto;
            margin-left: auto;
            padding-top: 1rem;
            padding-bottom: 2.25rem;
        }

        .page-shell .surface-card {
            border: 1px solid var(--border);
            border-radius: var(--admin-radius-card);
            background: var(--surface);
            box-shadow: var(--admin-shadow-soft);
        }

        .page-shell .page-title {
            margin: 0;
            color: var(--text-main);
            font-size: clamp(1.08rem, 1.5vw, 1.3rem);
            font-weight: 800;
            line-height: 1.28;
            letter-spacing: -.018em;
        }

        .page-shell .page-subtitle {
            margin-top: .22rem;
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.5;
        }

        .admin-feature-kicker,
        .page-shell .dashboard-kicker {
            margin-bottom: .28rem;
            color: var(--da-green);
            font-size: .68rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: .075em;
            text-transform: uppercase;
        }

        .admin-feature-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-height: 86px;
            padding: 1rem 1.1rem;
        }

        .admin-feature-heading,
        .dashboard-panel-heading,
        .payout-create-heading,
        .payout-list-header,
        .ad-control-heading,
        .admin-card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .85rem;
        }

        .admin-feature-heading {
            margin-bottom: 1rem !important;
            padding-bottom: .85rem;
            border-bottom: 1px solid var(--border);
        }

        .admin-card-heading,
        .payout-create-heading,
        .payout-list-header,
        .dashboard-panel-heading,
        .ad-control-heading {
            margin-bottom: .85rem;
        }

        .admin-feature-summary,
        .activity-summary-chip,
        .ad-control-summary,
        .dashboard-completion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 38px;
            padding: .45rem .7rem;
            border: 1px solid rgba(25,135,84,.2);
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .76rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .dashboard-completion {
            flex-direction: column;
            gap: .05rem;
            min-width: 116px;
            padding: .48rem .75rem;
            border-radius: var(--admin-radius-inner);
        }

        .dashboard-completion-value {
            color: var(--da-green);
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .dashboard-completion-label {
            color: var(--text-soft);
            font-size: .65rem;
            font-weight: 700;
        }

        .admin-content-card,
        .dashboard-panel,
        .activity-page,
        .payout-create-card,
        .payout-list-card,
        .composer-card,
        .announcement-list-card,
        .backup-create-card,
        .restore-center-card,
        .ad-control-page {
            padding: var(--admin-space-card);
        }

        /* Unified KPI cards */
        .dashboard-kpi,
        .payout-admin-stat,
        .quality-kpi {
            min-height: 96px;
            padding: .9rem;
            border-radius: var(--admin-radius-card);
        }

        .dashboard-kpi,
        .payout-admin-stat {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .dashboard-kpi-icon,
        .payout-admin-stat-icon {
            display: grid;
            place-items: center;
            width: var(--admin-icon-size);
            height: var(--admin-icon-size);
            flex: 0 0 var(--admin-icon-size);
            border-radius: var(--admin-radius-inner);
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .94rem;
        }

        .dashboard-kpi-value,
        .payout-admin-stat-value,
        .quality-kpi-value {
            color: var(--text-main);
            font-size: 1.22rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .dashboard-kpi-label,
        .payout-admin-stat-label,
        .quality-kpi-label {
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .72rem;
            font-weight: 650;
            line-height: 1.35;
        }

        /* Unified controls */
        .page-shell .form-control,
        .page-shell .form-select {
            min-height: var(--admin-control-height);
            border-color: var(--border);
            border-radius: var(--admin-radius-control);
            background-color: var(--surface);
            color: var(--text-main);
            font-size: .84rem;
        }

        .page-shell textarea.form-control {
            min-height: 92px;
        }

        .page-shell .form-control::placeholder {
            color: var(--text-soft);
            opacity: .72;
        }

        .page-shell .form-control:focus,
        .page-shell .form-select:focus {
            border-color: rgba(25,135,84,.52);
            box-shadow: 0 0 0 .18rem rgba(25,135,84,.11);
        }

        .page-shell .form-label {
            margin-bottom: .38rem;
            color: var(--text-main);
            font-size: .77rem;
            font-weight: 750;
        }

        .page-shell .form-text {
            color: var(--text-soft);
            font-size: .7rem;
            line-height: 1.45;
        }

        .page-shell .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: var(--admin-control-height);
            border-radius: var(--admin-radius-control);
            font-size: .82rem;
            line-height: 1.2;
        }

        .page-shell .btn-sm {
            min-height: var(--admin-control-height-sm);
            padding: .35rem .62rem;
            font-size: .77rem;
        }

        .page-shell .payout-action-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            min-height: 38px;
            padding: 0;
        }

        .page-shell .badge {
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 750;
            letter-spacing: .005em;
        }

        /* Unified tables */
        .page-shell .table {
            margin-bottom: 0;
            color: var(--text-main);
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            --bs-table-border-color: var(--border);
            vertical-align: middle;
        }

        .page-shell .table > :not(caption) > * > * {
            padding: .72rem .78rem;
            border-bottom-color: var(--border);
        }

        .page-shell .table thead th {
            border-bottom-width: 1px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .025em;
            white-space: nowrap;
        }

        .page-shell .table tbody td {
            color: var(--text-main);
            font-size: .78rem;
        }

        .page-shell .table tbody tr:last-child > * {
            border-bottom: 0;
        }

        .page-shell .table tbody tr {
            transition: background-color .15s ease;
        }

        .page-shell .table tbody tr:hover > * {
            background: rgba(127,127,127,.035);
        }

        .page-shell .table-responsive {
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: var(--admin-radius-inner);
        }

        /* Unified inner/list cards */
        .payout-event-item,
        .announcement-item,
        .backup-item,
        .ad-slot-card,
        .dashboard-status-card,
        .ad-status-row,
        .payout-access-option,
        .payout-edit-info,
        .payout-edit-note,
        .payout-data-status {
            border: 1px solid var(--border);
            border-radius: var(--admin-radius-inner);
            background: var(--surface-muted);
            box-shadow: none;
        }

        .payout-event-item,
        .announcement-item,
        .backup-item {
            transition:
                border-color .15s ease,
                background-color .15s ease,
                transform .15s ease;
        }

        .payout-event-item:hover,
        .announcement-item:hover,
        .backup-item:hover {
            border-color: rgba(25,135,84,.25);
            background: var(--surface);
        }

        .payout-event-list,
        .announcement-list,
        .backup-list,
        .ad-page-groups,
        .ad-slot-list {
            gap: .65rem;
        }

        .payout-event-actions,
        .announcement-actions,
        .backup-item-actions,
        .editor-toolbar-actions {
            gap: .4rem;
        }

        /* Payout Events */
        .payout-admin-summary {
            --bs-gutter-x: .75rem;
            --bs-gutter-y: .75rem;
        }

        .payout-event-item {
            padding: .78rem .82rem;
        }

        .payout-event-title {
            color: var(--text-main);
            font-size: .86rem;
            font-weight: 800;
        }

        .payout-event-meta {
            margin-top: .3rem;
            color: var(--text-soft);
            font-size: .71rem;
        }

        .payout-admin-search,
        .activity-search-wrap {
            border-radius: var(--admin-radius-control);
        }

        /* Access Requests */
        .access-request-actions {
            gap: .42rem;
        }

        .access-request-action-cell {
            min-width: 245px;
        }

        .access-request-reason {
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.45;
        }

        /* Data Quality */
        .quality-kpi {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .quality-issue-chip {
            min-height: 28px;
            padding: .3rem .5rem;
            border-radius: 999px;
            font-size: .67rem;
        }

        /* Data Quality section cards */
        .quality-section-card {
            padding: 1rem 1.05rem;
        }

        .quality-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .9rem;
            padding-bottom: .85rem;
            border-bottom: 1px solid var(--border);
        }

        .quality-section-heading {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            min-width: 0;
        }

        .quality-section-icon {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 11px;
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .quality-section-icon.is-info {
            background: rgba(13,110,253,.08);
            color: #0d6efd;
        }

        .quality-section-summary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .38rem;
            min-height: 34px;
            padding: .38rem .62rem;
            border: 1px solid rgba(25,135,84,.2);
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .71rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .quality-section-summary.is-info {
            border-color: rgba(13,110,253,.18);
            background: rgba(13,110,253,.07);
            color: #0d6efd;
        }

        .quality-section-table {
            margin-top: .15rem;
            border-radius: 12px;
        }

        .quality-section-empty {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-height: 82px;
            padding: .85rem;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
            color: var(--text-main);
        }

        .quality-section-empty-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 50%;
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .cross-payout-name-list {
            display: flex;
            flex-direction: column;
            gap: .28rem;
            min-width: 180px;
        }

        .cross-payout-name {
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            color: var(--text-main);
            font-size: .75rem;
            font-weight: 650;
            line-height: 1.35;
        }

        .cross-payout-name i {
            color: var(--text-soft);
            font-size: .7rem;
        }

        .cross-payout-compare-btn {
            white-space: nowrap;
        }

        .cross-compare-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .cross-compare-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
        }

        .cross-compare-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
            padding: .85rem .9rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .cross-compare-record-number {
            color: var(--da-green);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .cross-compare-payout-title {
            margin-top: .15rem;
            color: var(--text-main);
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .cross-compare-file {
            margin-top: .18rem;
            color: var(--text-soft);
            font-size: .7rem;
            overflow-wrap: anywhere;
        }

        .cross-compare-card-body {
            padding: .9rem;
        }

        .cross-compare-name {
            margin-bottom: .75rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--border);
        }

        .cross-compare-name-value {
            color: var(--text-main);
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .cross-compare-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .55rem .75rem;
        }

        .cross-compare-detail {
            min-width: 0;
            padding: .55rem .6rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-muted);
        }

        .cross-compare-detail-label {
            color: var(--text-soft);
            font-size: .64rem;
            font-weight: 750;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .cross-compare-detail-value {
            margin-top: .18rem;
            color: var(--text-main);
            font-size: .76rem;
            font-weight: 650;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .cross-compare-status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 29px;
            padding: .3rem .5rem;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
        }

        .cross-compare-status.is-verified {
            background: rgba(25,135,84,.10);
            color: #198754;
        }

        .cross-compare-status.is-corrections {
            background: rgba(255,193,7,.13);
            color: #9a7100;
        }

        .cross-compare-status.is-caution {
            background: rgba(220,53,69,.10);
            color: #dc3545;
        }

        .cross-compare-status.is-pending {
            background: rgba(108,117,125,.10);
            color: var(--text-soft);
        }

        .cross-compare-modal-summary {
            display: flex;
            align-items: center;
            gap: .55rem;
            flex-wrap: wrap;
        }

        .cross-compare-modal-summary .badge {
            min-height: 28px;
            display: inline-flex;
            align-items: center;
        }

        @media (max-width: 991.98px) {
            .cross-compare-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .cross-compare-details {
                grid-template-columns: 1fr;
            }

            .cross-payout-name-list {
                min-width: 150px;
            }
        }

        /* Verifier Chat Admin */
        .chat-admin-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: .8rem;
        }

        .chat-admin-storage {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            min-height: 34px;
            padding: .35rem .55rem;
            border: 1px solid var(--border);
            border-radius: var(--admin-radius-control);
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .69rem;
        }

        .chat-admin-table td {
            vertical-align: top;
        }

        .chat-admin-user {
            display: flex;
            align-items: center;
            gap: .45rem;
            min-width: 135px;
        }

        .chat-admin-avatar {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 9px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .72rem;
        }

        .chat-admin-user-name {
            max-width: 180px;
            overflow: hidden;
            color: var(--text-main);
            font-size: .75rem;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chat-admin-payout {
            max-width: 240px;
            color: var(--text-soft);
            font-size: .7rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .chat-admin-message {
            min-width: 240px;
            max-width: 560px;
            color: var(--text-main);
            font-size: .75rem;
            line-height: 1.45;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .chat-admin-time {
            min-width: 130px;
            color: var(--text-soft);
            font-size: .68rem;
            line-height: 1.35;
            white-space: nowrap;
        }

        .chat-admin-delete-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            min-width: 36px;
            padding: 0 !important;
            border-radius: var(--admin-radius-control);
        }

        .chat-admin-delete-btn i {
            margin: 0 !important;
            line-height: 1;
        }

        .chat-admin-empty {
            display: grid;
            place-items: center;
            min-height: 180px;
            padding: 1.25rem;
            border: 1px dashed var(--border);
            border-radius: var(--admin-radius-inner);
            background: var(--surface-muted);
            color: var(--text-soft);
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .chat-admin-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .chat-admin-toolbar .btn {
                width: 100%;
            }
        }

        /* Announcements */
        .announcement-item {
            padding: .8rem;
        }

        .announcement-title {
            color: var(--text-main);
            font-size: .86rem;
            font-weight: 800;
        }

        .announcement-message {
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.5;
        }

        .announcement-preview {
            border-radius: var(--admin-radius-inner);
            box-shadow: none;
        }

        /* Backup & Restore */
        .backup-create-icon,
        .backup-item-icon {
            border-radius: var(--admin-radius-inner);
        }

        .backup-item {
            padding: .75rem .8rem;
        }

        .backup-toolbar {
            margin-top: .75rem;
            margin-bottom: .75rem;
            padding: .85rem 1rem;
            border-radius: var(--admin-radius-card);
        }

        /* Advertisement Controls */
        .ad-page-group {
            border-radius: var(--admin-radius-card);
            box-shadow: none;
        }

        .ad-page-master {
            min-height: 70px;
            padding: .8rem .9rem;
        }

        .ad-slot-list {
            padding: .7rem;
        }

        .ad-slot-card {
            min-height: 74px;
            padding: .7rem .75rem;
        }

        .ad-control-footer {
            margin-top: .9rem;
            padding-top: .85rem;
            border-top: 1px solid var(--border);
        }

        /* JSON Manager */
        .json-header-card {
            margin-bottom: .75rem;
        }

        .json-header-layout {
            width: 100%;
        }

        .file-meta {
            margin-top: .8rem;
            padding-top: .75rem;
            border-top: 1px solid var(--border);
        }

        .file-meta-chip {
            min-height: 29px;
            border-radius: 999px;
        }

        .editor-card {
            overflow: hidden;
            border-radius: var(--admin-radius-card);
        }

        .editor-toolbar,
        .editor-footer {
            border-color: var(--border);
            background: var(--surface-muted);
        }

        /* Navigation also follows the same control geometry */
        .admin-nav {
            gap: .4rem;
        }

        .admin-nav-link {
            min-height: 38px;
            border-radius: var(--admin-radius-control);
            font-size: .8rem;
        }

        .admin-nav-link.active {
            border-color: rgba(25,135,84,.2);
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .admin-nav-count {
            min-width: 22px;
            border-radius: 999px;
            text-align: center;
        }

        /* Consistent low-emphasis empty/information blocks */
        .page-shell .text-secondary {
            color: var(--text-soft) !important;
        }

        .page-shell hr {
            border-color: var(--border);
            opacity: 1;
        }

        @media (max-width: 991.98px) {
            .admin-feature-header,
            .admin-feature-heading,
            .dashboard-panel-heading,
            .payout-list-header,
            .ad-control-heading {
                align-items: flex-start;
            }

            .admin-feature-header {
                flex-direction: column;
            }

            .admin-feature-summary,
            .activity-summary-chip,
            .ad-control-summary {
                align-self: flex-start;
            }

            .access-request-action-cell {
                min-width: 215px;
            }
        }

        @media (max-width: 767.98px) {
            .quality-section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .quality-section-summary {
                align-self: flex-start;
            }

            .quality-section-card {
                padding: .9rem;
            }

            .page-shell {
                padding-top: .75rem;
                padding-bottom: 1.5rem;
            }

            .admin-feature-header,
            .admin-content-card,
            .dashboard-panel,
            .activity-page,
            .payout-create-card,
            .payout-list-card,
            .composer-card,
            .announcement-list-card,
            .backup-create-card,
            .restore-center-card,
            .ad-control-page {
                padding: .85rem;
            }

            .admin-feature-heading,
            .dashboard-panel-heading,
            .payout-list-header,
            .ad-control-heading {
                flex-direction: column;
            }

            .dashboard-kpi,
            .payout-admin-stat,
            .quality-kpi {
                min-height: 88px;
                padding: .75rem;
            }

            .page-shell .table > :not(caption) > * > * {
                padding: .62rem .65rem;
            }
        }

        @media (max-width: 575.98px) {
            .admin-feature-header {
                min-height: 0;
            }

            .admin-feature-summary,
            .activity-summary-chip,
            .ad-control-summary {
                width: 100%;
                justify-content: flex-start;
                border-radius: var(--admin-radius-control);
            }

            .page-shell .btn:not(.payout-action-icon) {
                min-height: 38px;
            }

            .payout-event-actions {
                justify-content: flex-start;
            }

            .payout-event-actions .btn,
            .payout-event-actions .payout-action-icon {
                flex: 0 0 38px;
            }
        }


    </style>
</head>
<body>

<header class="topbar">
    <div class="container-fluid px-3 px-lg-4 py-2">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <a class="brand-link d-flex align-items-center gap-3 min-w-0"
               href="./"
               title="Return to Home">
                <img class="brand-logo"
                     src="img/DA_transparent.png"
                     alt="Department of Agriculture logo">

                <div class="min-w-0">
                    <div class="brand-kicker">DA RFO X</div>
                    <div class="brand-subtitle text-truncate">
                        Presidential Assistance to Farmers and Fisherfolks Program
                    </div>
                </div>
            </a>

            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <button class="btn btn-outline-secondary square-mobile"
                        id="toggleTheme"
                        type="button"
                        title="Switch theme">
                    <i class="fas fa-moon"></i>
                </button>

                <a class="btn btn-outline-secondary square-mobile"
                   href="./"
                   title="Home">
                    <i class="fa-solid fa-house me-md-2"></i>
                    <span class="btn-label">Home</span>
                </a>

                <a class="btn btn-outline-danger square-mobile"
                   href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?logout=1"
                   title="Lock Admin">
                    <i class="fa-solid fa-lock me-md-2"></i>
                    <span class="btn-label">Lock</span>
                </a>
            </div>
        </div>
    </div>
</header>

<nav class="admin-nav-wrap">
    <div class="container-fluid px-3 px-lg-4">
        <div class="admin-nav">
            <a class="admin-nav-link <?= $section === 'dashboard' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=dashboard">
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard
            </a>

            <a class="admin-nav-link <?= $section === 'activity' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=activity">
                <i class="fa-solid fa-user-clock"></i>
                Verifier Activity
                <span class="admin-nav-count"><?= number_format($adminSummary['verifier_count']) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'payouts' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=payouts">
                <i class="fa-solid fa-calendar-plus"></i>
                Payout Events
                <span class="admin-nav-count"><?= number_format(count($payouts)) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'auth' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=auth">
                <i class="fa-solid fa-user-lock"></i>
                User Login
                <span class="admin-nav-count">
                    <?= !empty($authSettings['enabled']) ? 'ON' : 'OFF' ?>
                </span>
            </a>

            <a class="admin-nav-link <?= $section === 'access_requests' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=access_requests">
                <i class="fa-solid fa-key"></i>
                Access Requests
                <span class="admin-nav-count"
                      id="AccessRequestNavCount"><?= number_format($pendingAccessRequestCount) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'data_quality' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=data_quality">
                <i class="fa-solid fa-shield-heart"></i>
                Data Quality
                <span class="admin-nav-count">
                    <?= number_format((int) ($dataQualitySummary['issues'] ?? 0)) ?>
                </span>
            </a>

            <a class="admin-nav-link <?= $section === 'chat' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=chat">
                <i class="fa-solid fa-comments"></i>
                Verifier Chat
                <span class="admin-nav-count">
                    <?= number_format($chatMessageCount) ?>
                </span>
            </a>

            <a class="admin-nav-link <?= $section === 'announcements' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=announcements">
                <i class="fa-solid fa-bullhorn"></i>
                Announcements
                <span class="admin-nav-count"><?= number_format($activeAnnouncementCount) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'backups' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=backups">
                <i class="fa-solid fa-database"></i>
                Backup & Restore
                <span class="admin-nav-count"><?= number_format(count($fullBackups)) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'json' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=json">
                <i class="fa-solid fa-code"></i>
                JSON Manager
                <span class="admin-nav-count"><?= number_format(count($jsonFiles)) ?></span>
            </a>

            <a class="admin-nav-link <?= $section === 'ads' ? 'active' : '' ?>"
               href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=ads">
                <i class="fa-solid fa-rectangle-ad"></i>
                Ad Controls
                <span class="admin-nav-count">
                    <?php
                        $navIndexAds = adPageSlotCounts($adSettings, 'index');
                        $navVerifyAds = adPageSlotCounts($adSettings, 'verify');
                        $navMonitorAds = adPageSlotCounts($adSettings, 'monitor');
                        echo number_format(
                            $navIndexAds['enabled']
                            + $navVerifyAds['enabled']
                            + $navMonitorAds['enabled']
                        );
                        echo '/';
                        echo number_format(
                            $navIndexAds['total']
                            + $navVerifyAds['total']
                            + $navMonitorAds['total']
                        );
                    ?>
                </span>
            </a>
        </div>
    </div>
</nav>

<main class="container-fluid px-3 px-lg-4 page-shell">

    <?php if ($section === 'dashboard') { ?>

        <?php
            $dashboardCompleted = (int) $adminSummary['completed'];
            $dashboardTotal = (int) $adminSummary['beneficiaries'];
            $dashboardCompletion = $dashboardTotal > 0
                ? ($dashboardCompleted / $dashboardTotal) * 100
                : 0;

            $dashboardIndexAds = adPageSlotCounts($adSettings, 'index');
            $dashboardVerifyAds = adPageSlotCounts($adSettings, 'verify');
            $dashboardMonitorAds = adPageSlotCounts($adSettings, 'monitor');

            $adsEnabledCount =
                $dashboardIndexAds['enabled']
                + $dashboardVerifyAds['enabled']
                + $dashboardMonitorAds['enabled'];
        ?>

        <section class="dashboard-hero surface-card admin-feature-header mb-3">
            <div>
                <div class="dashboard-kicker">PAFFP Control Center</div>
                <h1 class="page-title mb-1">Dashboard Overview</h1>
                <div class="page-subtitle">
                    Operational snapshot across <?= number_format(count($payouts)) ?> payout event<?= count($payouts) === 1 ? '' : 's' ?>.
                </div>
            </div>

            <div class="dashboard-completion">
                <div class="dashboard-completion-value">
                    <?= number_format($dashboardCompletion, 1) ?>%
                </div>
                <div class="dashboard-completion-label">Overall completion</div>
            </div>
        </section>

        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="surface-card dashboard-kpi">
                    <div class="dashboard-kpi-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="dashboard-kpi-value"><?= number_format($adminSummary['beneficiaries']) ?></div>
                    <div class="dashboard-kpi-label">Beneficiaries</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card dashboard-kpi">
                    <div class="dashboard-kpi-icon is-success"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="dashboard-kpi-value"><?= number_format($adminSummary['completed']) ?></div>
                    <div class="dashboard-kpi-label">Completed</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card dashboard-kpi">
                    <div class="dashboard-kpi-icon is-warning"><i class="fa-solid fa-flag"></i></div>
                    <div class="dashboard-kpi-value"><?= number_format($adminSummary['caution']) ?></div>
                    <div class="dashboard-kpi-label">Caution</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card dashboard-kpi">
                    <div class="dashboard-kpi-icon is-info"><i class="fa-solid fa-user-group"></i></div>
                    <div class="dashboard-kpi-value"><?= number_format($adminSummary['active_verifiers_today']) ?></div>
                    <div class="dashboard-kpi-label">Active Verifiers Today</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <section class="surface-card dashboard-panel">
                    <div class="dashboard-panel-heading">
                        <div>
                            <div class="page-title">Verifier Activity</div>
                            <div class="page-subtitle">Most active verifiers across all payout files.</div>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm"
                           href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=activity">
                            View Monitor
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Verifier</th>
                                    <th class="text-end">Verified</th>
                                    <th class="text-end">Corrections</th>
                                    <th class="text-end">Caution</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($verifierActivity, 0, 8) as $verifierItem) { ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($verifierItem['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="small text-secondary">
                                                <?= number_format($verifierItem['payout_count']) ?> payout<?= $verifierItem['payout_count'] === 1 ? '' : 's' ?>
                                            </div>
                                        </td>
                                        <td class="text-end"><?= number_format($verifierItem['verified']) ?></td>
                                        <td class="text-end"><?= number_format($verifierItem['corrected']) ?></td>
                                        <td class="text-end"><?= number_format($verifierItem['caution']) ?></td>
                                        <td class="text-end fw-semibold"><?= number_format($verifierItem['total_actions']) ?></td>
                                    </tr>
                                <?php } ?>

                                <?php if (empty($verifierActivity)) { ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-4">
                                            No verifier activity has been recorded yet.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="surface-card dashboard-panel mb-3">
                    <div class="dashboard-panel-heading">
                        <div>
                            <div class="page-title">System Status</div>
                            <div class="page-subtitle">Current admin-controlled services.</div>
                        </div>
                    </div>

                    <div class="dashboard-status-grid">
                        <div class="dashboard-status-card">
                            <span>Open payouts</span>
                            <strong><?= number_format($adminSummary['open_payouts']) ?>/<?= number_format(count($payouts)) ?></strong>
                        </div>
                        <div class="dashboard-status-card">
                            <span>Active announcements</span>
                            <strong><?= number_format($activeAnnouncementCount) ?></strong>
                        </div>
                        <div class="dashboard-status-card">
                            <span>Ads enabled</span>
                            <strong>
                                <?= $adsEnabledCount ?>/
                                <?= $dashboardIndexAds['total']
                                    + $dashboardVerifyAds['total']
                                    + $dashboardMonitorAds['total'] ?>
                            </strong>
                        </div>
                        <div class="dashboard-status-card">
                            <span>Full backups</span>
                            <strong><?= number_format(count($fullBackups)) ?></strong>
                        </div>
                    </div>
                </section>

                <section class="surface-card dashboard-panel">
                    <div class="dashboard-panel-heading">
                        <div>
                            <div class="page-title">Ad Status Overview</div>
                            <div class="page-subtitle">Page-level advertisement controls.</div>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm"
                           href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=ads">
                            Manage
                        </a>
                    </div>

                    <div class="ad-status-list">
                        <?php
                            $dashboardAds = [
                                ['Index', !empty($adSettings['index']),
                                    $dashboardIndexAds['enabled'] . '/' . $dashboardIndexAds['total'] . ' slots active'],
                                ['Verify', !empty($adSettings['verify']),
                                    $dashboardVerifyAds['enabled'] . '/' . $dashboardVerifyAds['total'] . ' slots active'],
                                ['Monitor', !empty($adSettings['monitor']),
                                    $dashboardMonitorAds['enabled'] . '/' . $dashboardMonitorAds['total'] . ' slots active']
                            ];
                        ?>
                        <?php foreach ($dashboardAds as $adRow) { ?>
                            <div class="ad-status-row">
                                <div>
                                    <strong><?= htmlspecialchars($adRow[0], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="small text-secondary">
                                        <?= htmlspecialchars($adRow[2], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                                <span class="badge <?= $adRow[1] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $adRow[1] ? 'ON' : 'OFF' ?>
                                </span>
                            </div>
                        <?php } ?>
                    </div>
                </section>
            </div>
        </div>

    <?php } elseif ($section === 'activity') { ?>

        <section class="surface-card activity-page admin-content-card">
            <div class="dashboard-panel-heading admin-feature-heading">
                <div>
                    <div class="admin-feature-kicker">Verification operations</div>
                    <h1 class="page-title">Verifier Activity Monitor</h1>
                    <div class="page-subtitle">
                        Aggregated verification activity across readable payout JSON files.
                    </div>
                </div>

                <div class="activity-summary-chip">
                    <i class="fa-solid fa-user-group"></i>
                    <?= number_format($adminSummary['active_verifiers_today']) ?> active today
                </div>
            </div>

            <div class="activity-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search"
                       class="form-control"
                       id="activitySearch"
                       placeholder="Search verifier or payout...">
            </div>

            <div class="table-responsive mt-3">
                <table class="table align-middle activity-table mb-0">
                    <thead>
                        <tr>
                            <th>Verifier</th>
                            <th class="text-end">Verified</th>
                            <th class="text-end">Corrections</th>
                            <th class="text-end">Caution</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Payouts</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody id="activityTableBody">
                        <?php foreach ($verifierActivity as $verifierItem) { ?>
                            <?php
                                $activitySearchText = strtolower(
                                    $verifierItem['name'] . ' ' .
                                    implode(' ', $verifierItem['payouts']) . ' ' .
                                    $verifierItem['last_payout']
                                );
                            ?>
                            <tr class="activity-row"
                                data-search="<?= htmlspecialchars($activitySearchText, ENT_QUOTES, 'UTF-8') ?>">
                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($verifierItem['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="small text-secondary text-truncate activity-last-payout">
                                        <?= htmlspecialchars($verifierItem['last_payout'] ?: 'No recent payout', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td class="text-end"><?= number_format($verifierItem['verified']) ?></td>
                                <td class="text-end"><?= number_format($verifierItem['corrected']) ?></td>
                                <td class="text-end"><?= number_format($verifierItem['caution']) ?></td>
                                <td class="text-end fw-semibold"><?= number_format($verifierItem['total_actions']) ?></td>
                                <td class="text-end"><?= number_format($verifierItem['payout_count']) ?></td>
                                <td>
                                    <?php if (!empty($verifierItem['last_ts'])) { ?>
                                        <div><?= date('M. j, Y g:i A', $verifierItem['last_ts']) ?></div>
                                        <div class="small text-secondary">
                                            <?= htmlspecialchars($verifierItem['last_status'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php } else { ?>
                                        <span class="text-secondary">—</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <div class="p-4 text-center text-secondary d-none"
                     id="activityNoResults">
                    No verifier activity matches your search.
                </div>
            </div>
        </section>

    <?php } elseif ($section === 'payouts') { ?>

        <section class="surface-card admin-feature-header mb-3">
            <div>
                <div class="admin-feature-kicker">Payout administration</div>
                <h1 class="page-title mb-1">Payout Events</h1>
                <div class="page-subtitle">
                    Create, manage, open, restrict, and maintain PAFFP payout events.
                </div>
            </div>

            <div class="admin-feature-summary">
                <i class="fa-solid fa-calendar-check"></i>
                <span><?= number_format(count($payouts)) ?> event<?= count($payouts) === 1 ? '' : 's' ?></span>
            </div>
        </section>

        <div class="payout-admin-summary row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="surface-card payout-admin-stat">
                    <div class="payout-admin-stat-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <div class="payout-admin-stat-value"><?= number_format(count($payouts)) ?></div>
                        <div class="payout-admin-stat-label">Payout Events</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card payout-admin-stat">
                    <div class="payout-admin-stat-icon is-live">
                        <i class="fa-solid fa-circle-play"></i>
                    </div>
                    <div>
                        <div class="payout-admin-stat-value"><?= number_format($payoutOngoingCount) ?></div>
                        <div class="payout-admin-stat-label">Ongoing Today</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card payout-admin-stat">
                    <div class="payout-admin-stat-icon">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="payout-admin-stat-value"><?= number_format($payoutUpcomingCount) ?></div>
                        <div class="payout-admin-stat-label">Upcoming</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card payout-admin-stat">
                    <div class="payout-admin-stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <div class="payout-admin-stat-value"><?= number_format($payoutBeneficiaryTotal) ?></div>
                        <div class="payout-admin-stat-label">Beneficiaries</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-5">
                <section class="surface-card payout-create-card admin-content-card">
                    <div class="payout-create-heading">
                        <div class="payout-create-heading-icon">
                            <i class="fa-solid fa-calendar-plus"></i>
                        </div>

                        <div class="min-w-0">
                            <h1 class="page-title">Create Payout Event</h1>
                            <div class="page-subtitle">
                                Add a payout schedule and its beneficiary dataset.
                            </div>
                        </div>
                    </div>

                    <form method="post"
                          enctype="multipart/form-data"
                          id="payoutCreateForm"
                          novalidate>
                        <input type="hidden"
                               name="csrfToken"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden"
                               name="admin_action"
                               value="payout_create">
                        <input type="hidden"
                               name="payout_source_mode"
                               id="payoutSourceMode"
                               value="file">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label"
                                       for="payoutTitle">
                                    Payout Title
                                </label>

                                <input type="text"
                                       class="form-control"
                                       id="payoutTitle"
                                       name="payout_title"
                                       maxlength="160"
                                       placeholder="e.g., Manolo Fortich (Day 1)"
                                       required>

                            </div>

                            <div class="col-12">
                                <label class="form-label"
                                       for="payoutDate">
                                    Payout Date
                                </label>

                                <input type="date"
                                       class="form-control"
                                       id="payoutDate"
                                       name="payout_date"
                                       required>
                            </div>
                        </div>

                        <div class="payout-source-block mt-3">
                            <div class="form-label mb-2">Beneficiary Data</div>

                            <div class="payout-source-switch"
                                 role="group"
                                 aria-label="Beneficiary data source">
                                <button type="button"
                                        class="payout-source-option active"
                                        id="payoutSourceFileButton"
                                        aria-pressed="true">
                                    <i class="fa-solid fa-file-arrow-up"></i>
                                    Upload JSON
                                </button>

                                <button type="button"
                                        class="payout-source-option"
                                        id="payoutSourceTextButton"
                                        aria-pressed="false">
                                    <i class="fa-solid fa-paste"></i>
                                    Paste JSON
                                </button>

                                <button type="button"
                                        class="payout-source-option"
                                        id="payoutSourceGoogleButton"
                                        aria-pressed="false">
                                    <i class="fa-brands fa-google-drive"></i>
                                    Google Sheets
                                </button>
                            </div>

                            <div id="payoutFilePanel"
                                 class="payout-source-panel mt-3">
                                <label class="payout-file-drop"
                                       for="payoutJsonFile">
                                    <div class="payout-file-drop-icon">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>

                                    <div class="payout-file-drop-title">
                                        Choose beneficiary JSON
                                    </div>

                                    <div class="payout-file-drop-subtitle"
                                         id="payoutFileName">
                                        No file selected
                                    </div>

                                    <input type="file"
                                           class="visually-hidden"
                                           id="payoutJsonFile"
                                           name="payout_json_file"
                                           accept=".json,application/json">
                                </label>
                            </div>

                            <div id="payoutTextPanel"
                                 class="payout-source-panel mt-3 d-none">
                                <textarea class="form-control payout-json-text"
                                          id="payoutJsonText"
                                          name="payout_json_text"
                                          rows="10"
                                          spellcheck="false"
                                          placeholder='Paste the complete JSON array here, e.g.&#10;[&#10;  {"SysGen":"10-...","LastName":"Dela Cruz"},&#10;  {"SysGen":"10-...","LastName":"Santos"}&#10;]'></textarea>
                            </div>

                            <div id="payoutGooglePanel"
                                 class="payout-source-panel mt-3 d-none">
                                <label class="form-label" for="payoutGoogleUrl">
                                    Google Sheets Link
                                </label>
                                <input type="url"
                                       class="form-control"
                                       id="payoutGoogleUrl"
                                       name="payout_google_url"
                                       maxlength="1000"
                                       placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0"
                                       inputmode="url"
                                       autocomplete="off">

                                <div class="mt-3">
                                    <label class="form-label" for="payoutGoogleGid">
                                        Worksheet GID <span class="text-secondary fw-normal">(optional)</span>
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="payoutGoogleGid"
                                           name="payout_google_gid"
                                           inputmode="numeric"
                                           pattern="[0-9]*"
                                           maxlength="15"
                                           placeholder="Detected from the Sheet link when available">
                                </div>


                                <button type="button"
                                        class="btn btn-outline-success w-100 mt-3"
                                        id="payoutGooglePreviewButton">
                                    <i class="fa-solid fa-magnifying-glass-chart me-1"></i>
                                    Fetch &amp; Preview Sheet
                                </button>

                                <div class="payout-google-preview d-none mt-3"
                                     id="payoutGooglePreview"
                                     aria-live="polite">
                                    <div class="payout-google-preview-heading">
                                        <div>
                                            <div class="payout-google-preview-count" id="payoutGooglePreviewCount">0</div>
                                            <div class="payout-google-preview-label">beneficiary rows ready</div>
                                        </div>
                                        <span class="badge text-bg-success" id="payoutGooglePreviewTab">Worksheet loaded</span>
                                    </div>
                                    <div class="payout-google-preview-columns" id="payoutGooglePreviewColumns"></div>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm payout-google-sample-table mb-0">
                                            <thead id="payoutGoogleSampleHead"></thead>
                                            <tbody id="payoutGoogleSampleBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="payout-google-preview-help mt-2">
                                        Preview only. Creating the event fetches a fresh snapshot of this worksheet.
                                    </div>
                                </div>
                            </div>

                            <div class="payout-data-status"
                                 id="payoutDataStatus">
                                <div class="payout-data-status-icon">
                                    <i class="fa-solid fa-circle-info"></i>
                                </div>

                                <div class="min-w-0">
                                    <div class="payout-data-status-title"
                                         id="payoutDataStatusTitle">
                                        Waiting for beneficiary data
                                    </div>

                                    <div class="payout-data-status-text"
                                         id="payoutDataStatusText">
                                        Upload JSON, paste JSON, or import a Google Sheet.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="btn btn-success w-100 mt-3"
                                id="payoutCreateButton">
                            <span class="payout-create-label">
                                <i class="fa-solid fa-plus me-1"></i>
                                Create Payout Event
                            </span>

                            <span class="payout-create-loading d-none">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Creating Payout…
                            </span>
                        </button>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-7">
                <section class="surface-card payout-list-card admin-content-card">
                    <div class="payout-list-header">
                        <div>
                            <div class="page-title">Payout Events</div>
                            <div class="page-subtitle">
                                <?= number_format(count($payouts)) ?> saved event<?= count($payouts) === 1 ? '' : 's' ?>
                                · <?= number_format($payoutAccomplishedCount) ?> accomplished
                            </div>
                        </div>

                        <div class="payout-list-tools">
                            <button type="button"
                                    class="btn <?= $temporaryAccessActive ? 'btn-warning' : 'btn-outline-warning' ?> btn-sm temporary-access-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#temporaryAccessModal">
                                <i class="fa-solid fa-clock me-1"></i>
                                <?= $temporaryAccessActive
                                    ? ($temporaryAccessMode === 'edit'
                                        ? 'Editing Access Active'
                                        : 'Viewing Access Active')
                                    : 'Open All Temporarily' ?>
                            </button>

                            <div class="payout-admin-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search"
                                       class="form-control"
                                       id="payoutAdminSearch"
                                       placeholder="Search payouts..."
                                       autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <?php if ($temporaryAccessActive) { ?>
                        <div class="temporary-access-status">
                            <div class="temporary-access-status-main">
                                <div class="temporary-access-status-icon">
                                    <i class="fa-solid fa-unlock-keyhole"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="temporary-access-status-title">
                                        All payouts are temporarily open for
                                        <?= $temporaryAccessMode === 'edit'
                                            ? 'viewing and editing'
                                            : 'viewing only' ?>
                                    </div>
                                    <div class="temporary-access-status-subtitle">
                                        <?= $temporaryAccessMode === 'edit'
                                            ? 'Users can view and save verification changes.'
                                            : 'Users can view records, but verification changes are blocked.' ?>
                                        · Expires <?= htmlspecialchars(date('M. j, Y g:i A', $temporaryAccessExpiresAt), ENT_QUOTES, 'UTF-8') ?>
                                        · Normal payout restrictions resume automatically after expiry.
                                    </div>
                                </div>
                            </div>

                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    id="stopTemporaryAccessButton">
                                <i class="fa-solid fa-stop me-1"></i>
                                Stop Early
                            </button>
                        </div>
                    <?php } ?>

                    <div class="payout-event-list"
                         id="payoutEventList">

                        <?php if (empty($payoutDisplay)) { ?>
                            <div class="p-4 text-center text-secondary">
                                No payout events have been created yet.
                            </div>
                        <?php } ?>

                        <?php foreach ($payoutDisplay as $payoutItem) { ?>
                            <?php
                                $itemDate = (string) ($payoutItem['date'] ?? '');
                                $payoutIsAccomplished = paffp_admin_payout_accomplished($payoutItem);
                                $payoutIsOpen = paffp_admin_payout_open($payoutItem);
                                $accomplishedAccessMode = paffp_admin_accomplished_access_mode($payoutItem);
                                $accomplishedAccessAllPermission =
                                    paffp_admin_accomplished_access_all_permission($payoutItem);
                                $accomplishedAccessUsers = paffp_admin_accomplished_access_users($payoutItem);
                                $accomplishedAccessPermissions = paffp_admin_accomplished_access_permissions($payoutItem);
                                $itemStatus = $itemDate === $payoutToday
                                    ? 'ongoing'
                                    : ($itemDate > $payoutToday ? 'upcoming' : 'accomplished');

                                $statusLabel = [
                                    'ongoing' => 'Ongoing',
                                    'upcoming' => 'Upcoming',
                                    'accomplished' => 'Accomplished'
                                ][$itemStatus];

                                $statusClass = [
                                    'ongoing' => 'text-bg-success',
                                    'upcoming' => 'text-bg-primary',
                                    'accomplished' => 'text-bg-secondary'
                                ][$itemStatus];

                                $displayDate = $itemDate !== ''
                                    ? date('M. j, Y', strtotime($itemDate))
                                    : 'No date';

                                $searchText = strtolower(
                                    (string) ($payoutItem['title'] ?? '')
                                    . ' '
                                    . (string) ($payoutItem['file'] ?? '')
                                    . ' '
                                    . $itemDate
                                );
                            ?>

                            <article class="payout-event-item"
                                     data-payout-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="payout-event-main">
                                    <div class="payout-event-icon <?= $itemStatus ?>">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </div>

                                    <div class="payout-event-copy min-w-0">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div class="payout-event-title text-truncate"
                                                 title="<?= htmlspecialchars((string) ($payoutItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string) ($payoutItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </div>

                                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                <?php
                                                    $accessBadgeClass = $temporaryAccessActive
                                                        ? 'text-bg-warning'
                                                        : ($payoutIsOpen
                                                            ? ($payoutIsAccomplished && $accomplishedAccessMode === 'users'
                                                                ? 'text-bg-warning'
                                                                : 'text-bg-success')
                                                            : 'text-bg-danger');

                                                    $accessBadgeLabel = $temporaryAccessActive
                                                        ? ($temporaryAccessMode === 'edit'
                                                            ? 'Temp Edit'
                                                            : 'Temp View')
                                                        : 'Closed';

                                                    if (!$temporaryAccessActive && $payoutIsAccomplished) {
                                                        if ($accomplishedAccessMode === 'all') {
                                                            $accessBadgeLabel =
                                                                'All · '
                                                                . ($accomplishedAccessAllPermission === 'view'
                                                                    ? 'View'
                                                                    : 'Edit');
                                                        } elseif ($accomplishedAccessMode === 'users') {
                                                            $accessBadgeLabel =
                                                                'Limited (' . count($accomplishedAccessUsers) . ')';
                                                        } else {
                                                            $accessBadgeLabel = 'Auto Closed';
                                                        }
                                                    } elseif (!$temporaryAccessActive) {
                                                        $accessBadgeLabel = $payoutIsOpen ? 'Open' : 'Closed';
                                                    }
                                                ?>
                                                <span class="badge <?= $accessBadgeClass ?> payout-event-status">
                                                    <?= htmlspecialchars($accessBadgeLabel, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="badge <?= $statusClass ?> payout-event-status">
                                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="payout-event-meta">
                                            <span>
                                                <i class="fa-regular fa-calendar"></i>
                                                <?= htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                            <span>
                                                <i class="fa-solid fa-users"></i>
                                                <?= number_format((int) ($payoutItem['count'] ?? 0)) ?> beneficiaries
                                            </span>

                                            <span class="text-truncate"
                                                  title="<?= htmlspecialchars((string) ($payoutItem['file'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-regular fa-file-code"></i>
                                                <?= htmlspecialchars((string) ($payoutItem['file'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="payout-event-actions">
                                    <?php if ($payoutIsAccomplished) { ?>
                                        <button type="button"
                                                class="btn <?= $accomplishedAccessMode === 'closed' ? 'btn-outline-secondary' : 'btn-outline-warning' ?> btn-sm payout-action-icon manage-accomplished-access"
                                                title="Manage access"
                                                aria-label="Manage access"
                                                data-payout-access="<?= htmlspecialchars(json_encode([
                                                    'uid' => (string) ($payoutItem['uid'] ?? ''),
                                                    'title' => (string) ($payoutItem['title'] ?? ''),
                                                    'mode' => $accomplishedAccessMode,
                                                    'all_permission' => $accomplishedAccessAllPermission,
                                                    'users' => $accomplishedAccessUsers,
                                                    'permissions' => $accomplishedAccessPermissions
                                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-user-lock"></i>
                                        </button>
                                    <?php } else { ?>
                                    <form method="post" class="d-inline payout-control-form">
                                        <input type="hidden"
                                               name="csrfToken"
                                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden"
                                               name="admin_action"
                                               value="payout_toggle">
                                        <input type="hidden"
                                               name="payout_uid"
                                               value="<?= htmlspecialchars((string) ($payoutItem['uid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                                        <button type="submit"
                                                class="btn <?= $payoutIsOpen ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm payout-action-icon"
                                                title="<?= $payoutIsOpen ? 'Close verification' : 'Reopen payout' ?>"
                                                aria-label="<?= $payoutIsOpen ? 'Close verification' : 'Reopen payout' ?>">
                                            <i class="fa-solid <?= $payoutIsOpen ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                                        </button>
                                    </form>
                                    <?php } ?>

                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm payout-action-icon edit-payout-event"
                                            title="Edit payout"
                                            aria-label="Edit payout"
                                            data-payout="<?= htmlspecialchars(json_encode([
                                                'uid' => (string) ($payoutItem['uid'] ?? ''),
                                                'title' => (string) ($payoutItem['title'] ?? ''),
                                                'date' => (string) ($payoutItem['date'] ?? ''),
                                                'file' => (string) ($payoutItem['file'] ?? ''),
                                                'count' => (int) ($payoutItem['count'] ?? 0)
                                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <form method="post"
                                          class="d-inline payout-delete-form"
                                          data-payout-title="<?= htmlspecialchars((string) ($payoutItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                          data-payout-file="<?= htmlspecialchars((string) ($payoutItem['file'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden"
                                               name="csrfToken"
                                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden"
                                               name="admin_action"
                                               value="payout_delete">
                                        <input type="hidden"
                                               name="payout_uid"
                                               value="<?= htmlspecialchars((string) ($payoutItem['uid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                                        <button type="submit"
                                                class="btn btn-outline-danger btn-sm payout-action-icon"
                                                title="Delete payout"
                                                aria-label="Delete payout">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>

                                    <a class="btn btn-outline-success btn-sm payout-action-icon"
                                       href="verify.php?id=<?= urlencode((string) ($payoutItem['uid'] ?? '')) ?>&location=<?= urlencode((string) ($payoutItem['title'] ?? '')) ?>"
                                       target="_blank"
                                       rel="noopener"
                                       title="Open payout"
                                       aria-label="Open payout">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>

                                    <a class="btn btn-outline-secondary btn-sm payout-action-icon"
                                       href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=json&file=<?= urlencode((string) ($payoutItem['file'] ?? '')) ?>"
                                       title="Open JSON"
                                       aria-label="Open JSON">
                                        <i class="fa-solid fa-code"></i>
                                    </a>
                                </div>
                            </article>
                        <?php } ?>

                        <div class="p-4 text-center text-secondary d-none"
                             id="payoutAdminNoResults">
                            No payout events match your search.
                        </div>
                    </div>
                </section>
            </div>
        </div>

    <?php } elseif ($section === 'auth') { ?>

        <section class="surface-card admin-feature-header mb-3">
            <div>
                <div class="admin-feature-kicker">Verifier authentication</div>
                <h1 class="page-title mb-1">User Login & Sign Up</h1>
                <div class="page-subtitle">
                    Control whether verifier accounts are required when users open a payout verification page.
                </div>
            </div>

            <span class="badge rounded-pill <?= !empty($authSettings['enabled']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= !empty($authSettings['enabled']) ? 'LOGIN REQUIRED' : 'GENERATED USERNAMES' ?>
            </span>
        </section>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <section class="surface-card admin-content-card h-100">
                    <div class="dashboard-panel-heading admin-feature-heading">
                        <div>
                            <div class="page-title">Verify Authentication</div>
                            <div class="page-subtitle">
                                This affects only <strong>verify.php</strong>. The payout list remains public.
                            </div>
                        </div>
                    </div>

                    <form method="post" class="mt-3">
                        <input type="hidden"
                               name="csrfToken"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden"
                               name="admin_action"
                               value="auth_toggle">

                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 border rounded-3">
                            <div>
                                <div class="fw-semibold">Require login or sign up on Verify</div>
                                <div class="small text-secondary mt-1">
                                    ON: users are redirected to Login/Sign Up before payout data is loaded.<br>
                                    OFF: the existing anonymous adjective-animal username generator is used.
                                </div>
                            </div>

                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="auth_enabled" value="0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="authEnabledSwitch"
                                       name="auth_enabled"
                                       value="1"
                                       <?= !empty($authSettings['enabled']) ? 'checked' : '' ?>
                                       style="width:3rem;height:1.5rem;">
                                <label class="form-check-label ms-2"
                                       for="authEnabledSwitch">
                                    <?= !empty($authSettings['enabled']) ? 'Enabled' : 'Disabled' ?>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-success" type="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                Save Authentication Setting
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="surface-card admin-content-card h-100">
                    <div class="page-title">Account Summary</div>
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-secondary">Registered Accounts</div>
                                <div class="fs-4 fw-bold mt-1"><?= number_format($authUserCount) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-secondary">Current Mode</div>
                                <div class="fw-bold mt-2">
                                    <?= !empty($authSettings['enabled']) ? 'Account Login' : 'Generated Username' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-secondary mt-3">
                        Existing generated usernames are not deleted when login is enabled. If authentication is disabled later, browsers can continue using their existing generated identity.
                    </div>
                </section>
            </div>
        </div>

        <section class="surface-card admin-content-card">
            <div class="dashboard-panel-heading admin-feature-heading">
                <div>
                    <div class="page-title">Registered Verifier Accounts</div>
                    <div class="page-subtitle">
                        Passwords are stored only as secure password hashes and are never displayed here.
                    </div>
                </div>
                <span class="admin-nav-count"><?= number_format($authUserCount) ?></span>
            </div>

            <?php if (empty($authUsers)) { ?>
                <div class="text-center text-secondary py-5">
                    <i class="fa-solid fa-user-plus fa-2x mb-3"></i>
                    <div class="fw-semibold">No verifier accounts yet</div>
                    <div class="small mt-1">
                        Accounts will appear here after users sign up from the Verify login screen.
                    </div>
                </div>
            <?php } else { ?>
                <div class="table-responsive mt-3">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($authUsers as $authUser) { ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <?= htmlspecialchars((string) ($authUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-success">Active</span>
                                    </td>
                                    <td>
                                        <?php
                                            $createdAt = (int) ($authUser['created_at'] ?? 0);
                                            echo $createdAt > 0
                                                ? htmlspecialchars(date('M. j, Y g:i A', $createdAt), ENT_QUOTES, 'UTF-8')
                                                : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                            $lastLoginAt = (int) ($authUser['last_login_at'] ?? 0);
                                            echo $lastLoginAt > 0
                                                ? htmlspecialchars(date('M. j, Y g:i A', $lastLoginAt), ENT_QUOTES, 'UTF-8')
                                                : 'Never';
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

    <?php } elseif ($section === 'access_requests') { ?>

        <section class="surface-card admin-content-card">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3 admin-feature-heading">
                <div>
                    <div class="admin-feature-kicker">Restricted access workflow</div>
                    <h1 class="page-title mb-1">Access Requests</h1>
                    <div class="page-subtitle">
                        Review restricted Verify access requests. Approved access expires automatically and is enforced server-side.
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-warning px-3 py-2"
                          id="AccessRequestPendingBadge">
                        <?= number_format($pendingAccessRequestCount) ?> Pending
                    </span>
                    <span class="badge text-bg-success px-3 py-2"
                          id="AccessRequestActiveBadge">
                        <?= number_format($activeAccessRequestCount) ?> Active
                    </span>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            id="toggleAccessRequestSound"
                            title="Toggle sound for new access requests">
                        <i class="fa-solid fa-bell-slash me-1"></i>
                        <span>Sound Off</span>
                    </button>

                    <a class="btn btn-outline-secondary btn-sm"
                       href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=access_requests">
                        <i class="fa-solid fa-rotate me-1"></i>
                        Refresh
                    </a>
                </div>
            </div>

            <?php if (empty($accessRequests)) { ?>
                <div class="p-4 text-center text-secondary border rounded-3">
                    No Verify access requests have been submitted.
                </div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Verifier</th>
                                <th>Payout</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Requested</th>
                                <th>Access / Expiry</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($accessRequests as $requestItem) { ?>
                            <?php
                                $requestId = (string) ($requestItem['id'] ?? '');
                                $requestUsername = (string) ($requestItem['username'] ?? '');
                                $requestPayoutTitle = (string) ($requestItem['payout_title'] ?? '');
                                $requestPayoutUid = (string) ($requestItem['payout_uid'] ?? '');
                                $requestStatus = strtolower(
                                    (string) ($requestItem['status'] ?? 'pending')
                                );
                                $requestPermission = strtolower(
                                    (string) ($requestItem['permission'] ?? '')
                                );
                                $requestReason = trim(
                                    (string) ($requestItem['reason'] ?? '')
                                );
                                $requestRequestedAt = (int) ($requestItem['requested_at'] ?? 0);
                                $requestExpiresAt = (int) ($requestItem['expires_at'] ?? 0);
                                $requestDurationMinutes = (int) ($requestItem['duration_minutes'] ?? 60);
                                $requestExpiryMode = strtolower(
                                    (string) ($requestItem['expiry_mode'] ?? 'preset')
                                );

                                if (!in_array(
                                    $requestExpiryMode,
                                    ['preset', 'today_5pm', 'end_of_day', 'payout_end', 'custom'],
                                    true
                                )) {
                                    $requestExpiryMode = 'preset';
                                }
                                $requestActive =
                                    $requestStatus === 'approved'
                                    && $requestExpiresAt > $now;

                                if ($requestStatus === 'approved' && !$requestActive) {
                                    $requestDisplayStatus = 'expired';
                                } else {
                                    $requestDisplayStatus = $requestStatus;
                                }

                                $requestStatusClass = [
                                    'pending' => 'text-bg-warning',
                                    'approved' => 'text-bg-success',
                                    'expired' => 'text-bg-secondary',
                                    'rejected' => 'text-bg-danger',
                                    'revoked' => 'text-bg-dark'
                                ][$requestDisplayStatus] ?? 'text-bg-secondary';

                                $requestStatusLabel = [
                                    'pending' => 'Pending',
                                    'approved' => 'Approved',
                                    'expired' => 'Expired',
                                    'rejected' => 'Rejected',
                                    'revoked' => 'Revoked'
                                ][$requestDisplayStatus] ?? ucfirst($requestDisplayStatus);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($requestUsername, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="small text-secondary">
                                        <?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars(
                                            $requestPayoutTitle !== ''
                                                ? $requestPayoutTitle
                                                : $requestPayoutUid,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                    <div class="small text-secondary">
                                        <?= htmlspecialchars($requestPayoutUid, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge <?= $requestStatusClass ?>">
                                        <?= htmlspecialchars($requestStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($requestReason !== '') { ?>
                                        <div class="access-request-reason">
                                            <?= nl2br(htmlspecialchars(
                                                $requestReason,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )) ?>
                                        </div>
                                    <?php } else { ?>
                                        <span class="text-secondary small">No reason provided.</span>
                                    <?php } ?>
                                </td>

                                <td class="small">
                                    <?= $requestRequestedAt > 0
                                        ? htmlspecialchars(
                                            date('M. j, Y g:i A', $requestRequestedAt),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : '—' ?>
                                </td>

                                <td class="small">
                                    <?php if ($requestActive) { ?>
                                        <div class="fw-semibold">
                                            <?= $requestPermission === 'edit'
                                                ? 'Can Edit'
                                                : 'View Only' ?>
                                        </div>
                                        <div class="text-secondary">
                                            Expires
                                            <?= htmlspecialchars(
                                                date('M. j, Y g:i A', $requestExpiresAt),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    <?php } elseif ($requestDisplayStatus === 'expired') { ?>
                                        <span class="text-secondary">
                                            Expired
                                            <?= $requestExpiresAt > 0
                                                ? htmlspecialchars(
                                                    date('M. j, Y g:i A', $requestExpiresAt),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                                : '' ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-secondary">—</span>
                                    <?php } ?>
                                </td>

                                <td class="access-request-action-cell">
                                    <div class="access-request-actions">
                                        <?php if ($requestDisplayStatus === 'pending'
                                            || $requestActive) { ?>

                                            <form method="post"
                                                  class="access-request-approve-form access-request-action-form"
                                                  data-access-action="<?= $requestActive ? 'update' : 'approve' ?>"
                                                  data-access-user="<?= htmlspecialchars($requestUsername, ENT_QUOTES, 'UTF-8') ?>"
                                                  data-access-payout="<?= htmlspecialchars(
                                                      $requestPayoutTitle !== ''
                                                          ? $requestPayoutTitle
                                                          : $requestPayoutUid,
                                                      ENT_QUOTES,
                                                      'UTF-8'
                                                  ) ?>">
                                                <input type="hidden"
                                                       name="csrfToken"
                                                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden"
                                                       name="admin_action"
                                                       value="access_request_approve">
                                                <input type="hidden"
                                                       name="request_id"
                                                       value="<?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?>">

                                                <select class="form-select form-select-sm"
                                                        name="permission">
                                                    <option value="view"
                                                        <?= $requestPermission !== 'edit' ? 'selected' : '' ?>>
                                                        View Only
                                                    </option>
                                                    <option value="edit"
                                                        <?= $requestPermission === 'edit' ? 'selected' : '' ?>>
                                                        Can Edit
                                                    </option>
                                                </select>

                                                <select class="form-select form-select-sm access-expiry-choice"
                                                        name="expiry_choice">
                                                    <option value="15" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 15 ? 'selected' : '' ?>>15 minutes</option>
                                                    <option value="30" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 30 ? 'selected' : '' ?>>30 minutes</option>
                                                    <option value="60" <?= $requestExpiryMode === 'preset' && (!in_array($requestDurationMinutes, [15, 30, 120, 240, 480, 720, 1440, 4320, 10080], true) || $requestDurationMinutes === 60) ? 'selected' : '' ?>>1 hour</option>
                                                    <option value="120" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 120 ? 'selected' : '' ?>>2 hours</option>
                                                    <option value="240" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 240 ? 'selected' : '' ?>>4 hours</option>
                                                    <option value="480" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 480 ? 'selected' : '' ?>>8 hours</option>
                                                    <option value="720" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 720 ? 'selected' : '' ?>>12 hours</option>
                                                    <option value="1440" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 1440 ? 'selected' : '' ?>>24 hours</option>
                                                    <option value="4320" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 4320 ? 'selected' : '' ?>>3 days</option>
                                                    <option value="10080" <?= $requestExpiryMode === 'preset' && $requestDurationMinutes === 10080 ? 'selected' : '' ?>>7 days</option>
                                                    <option value="today_5pm" <?= $requestExpiryMode === 'today_5pm' ? 'selected' : '' ?>>Until 5:00 PM Today</option>
                                                    <option value="end_of_day" <?= $requestExpiryMode === 'end_of_day' ? 'selected' : '' ?>>Until End of Day</option>
                                                    <option value="payout_end" <?= $requestExpiryMode === 'payout_end' ? 'selected' : '' ?>>Until Payout End</option>
                                                    <option value="custom" <?= $requestExpiryMode === 'custom' ? 'selected' : '' ?>>Custom Date / Time</option>
                                                </select>

                                                <input type="datetime-local"
                                                       class="form-control form-control-sm access-expiry-custom <?= $requestExpiryMode === 'custom' ? '' : 'd-none' ?>"
                                                       name="custom_expires_at"
                                                       value="<?= $requestExpiryMode === 'custom' && $requestExpiresAt > 0
                                                           ? htmlspecialchars(
                                                               date('Y-m-d\\TH:i', $requestExpiresAt),
                                                               ENT_QUOTES,
                                                               'UTF-8'
                                                           )
                                                           : '' ?>"
                                                       min="<?= htmlspecialchars(
                                                           date('Y-m-d\\TH:i'),
                                                           ENT_QUOTES,
                                                           'UTF-8'
                                                       ) ?>"
                                                       max="<?= htmlspecialchars(
                                                           date('Y-m-d\\TH:i', time() + (30 * 24 * 60 * 60)),
                                                           ENT_QUOTES,
                                                           'UTF-8'
                                                       ) ?>">

                                                <button type="submit"
                                                        class="btn btn-success btn-sm">
                                                    <i class="fa-solid fa-check me-1"></i>
                                                    <?= $requestActive ? 'Update Access' : 'Approve' ?>
                                                </button>
                                            </form>
                                        <?php } ?>

                                        <?php if (
                                            $requestDisplayStatus === 'expired'
                                            || $requestDisplayStatus === 'rejected'
                                            || $requestDisplayStatus === 'revoked'
                                        ) { ?>
                                            <span class="small text-secondary">
                                                <?php if ($requestDisplayStatus === 'expired') { ?>
                                                    Expired — verifier must submit a new request
                                                <?php } else { ?>
                                                    No further action
                                                <?php } ?>
                                            </span>
                                        <?php } ?>

                                        <div class="access-request-secondary-actions">
                                            <?php if ($requestDisplayStatus === 'pending') { ?>
                                                <form method="post"
                                                      class="access-request-action-form"
                                                      data-access-action="reject"
                                                      data-access-user="<?= htmlspecialchars($requestUsername, ENT_QUOTES, 'UTF-8') ?>"
                                                      data-access-payout="<?= htmlspecialchars(
                                                          $requestPayoutTitle !== ''
                                                              ? $requestPayoutTitle
                                                              : $requestPayoutUid,
                                                          ENT_QUOTES,
                                                          'UTF-8'
                                                      ) ?>">
                                                    <input type="hidden"
                                                           name="csrfToken"
                                                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden"
                                                           name="admin_action"
                                                           value="access_request_reject">
                                                    <input type="hidden"
                                                           name="request_id"
                                                           value="<?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?>">

                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm">
                                                        <i class="fa-solid fa-xmark me-1"></i>
                                                        Reject
                                                    </button>
                                                </form>
                                            <?php } ?>

                                            <?php if ($requestActive) { ?>
                                                <form method="post"
                                                      class="access-request-action-form"
                                                      data-access-action="revoke"
                                                      data-access-user="<?= htmlspecialchars($requestUsername, ENT_QUOTES, 'UTF-8') ?>"
                                                      data-access-payout="<?= htmlspecialchars(
                                                          $requestPayoutTitle !== ''
                                                              ? $requestPayoutTitle
                                                              : $requestPayoutUid,
                                                          ENT_QUOTES,
                                                          'UTF-8'
                                                      ) ?>">
                                                    <input type="hidden"
                                                           name="csrfToken"
                                                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden"
                                                           name="admin_action"
                                                           value="access_request_revoke">
                                                    <input type="hidden"
                                                           name="request_id"
                                                           value="<?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?>">

                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm">
                                                        <i class="fa-solid fa-ban me-1"></i>
                                                        Revoke
                                                    </button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

    <?php } elseif ($section === 'data_quality') { ?>

        <?php
            $qualityGeneratedAt =
                is_array($dataQualityReport)
                    ? (int) ($dataQualityReport['generated_at'] ?? 0)
                    : 0;

            $qualityFiles =
                is_array($dataQualityReport)
                    ? (array) ($dataQualityReport['files'] ?? [])
                    : [];

            $qualityConfigIssues =
                is_array($dataQualityReport)
                    ? (array) ($dataQualityReport['configuration_issues'] ?? [])
                    : [];

            $qualityCrossDuplicates =
                is_array($dataQualityReport)
                    ? (array) ($dataQualityReport['cross_file_duplicates'] ?? [])
                    : [];

            $qualityCrossNameDuplicates =
                is_array($dataQualityReport)
                    ? (array) ($dataQualityReport['cross_file_name_duplicates'] ?? [])
                    : [];
        ?>

        <section class="dashboard-hero surface-card admin-feature-header mb-3">
            <div>
                <div class="dashboard-kicker">Read-only validation</div>
                <h1 class="page-title mb-1">Data Quality</h1>
                <div class="page-subtitle">
                    Scan payout JSON files for structural and beneficiary-data issues without changing any record.
                </div>
            </div>

            <div class="d-flex flex-column align-items-lg-end gap-2">
                <a class="btn btn-success btn-sm"
                   href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?section=data_quality&scan=1">
                    <i class="fa-solid fa-rotate me-1"></i>
                    Refresh Scan
                </a>

                <div class="small text-secondary">
                    <?= $qualityGeneratedAt > 0
                        ? 'Scanned ' . htmlspecialchars(
                            date('M. j, Y g:i A', $qualityGeneratedAt),
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : 'Not scanned yet' ?>
                </div>
            </div>
        </section>

        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="surface-card quality-kpi">
                    <div class="quality-kpi-value">
                        <?= number_format((int) ($dataQualitySummary['records'] ?? 0)) ?>
                    </div>
                    <div class="quality-kpi-label">Records scanned</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card quality-kpi">
                    <div class="quality-kpi-value">
                        <?= number_format((int) ($dataQualitySummary['files_with_issues'] ?? 0)) ?>
                    </div>
                    <div class="quality-kpi-label">Payout files with issues</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card quality-kpi">
                    <div class="quality-kpi-value">
                        <?= number_format((int) ($dataQualitySummary['issues'] ?? 0)) ?>
                    </div>
                    <div class="quality-kpi-label">Issue occurrences</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="surface-card quality-kpi">
                    <div class="quality-kpi-value">
                        <?= number_format((int) ($dataQualitySummary['cross_file_duplicate_sysgen'] ?? 0)) ?>
                    </div>
                    <div class="quality-kpi-label">SysGen values in multiple payouts</div>
                    <div class="small text-secondary mt-1">
                        <?= number_format((int) ($dataQualitySummary['cross_file_duplicate_name'] ?? 0)) ?>
                        full-name match<?= (int) ($dataQualitySummary['cross_file_duplicate_name'] ?? 0) === 1 ? '' : 'es' ?> across payouts
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($qualityConfigIssues)) { ?>
            <section class="surface-card admin-content-card quality-section-card mb-3">
                <div class="page-title mb-2">Payout Configuration Issues</div>
                <div class="quality-issue-list">
                    <?php foreach ($qualityConfigIssues as $issue) { ?>
                        <span class="quality-issue-chip <?= ($issue['severity'] ?? '') === 'danger' ? 'is-danger' : 'is-warning' ?>">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?= htmlspecialchars((string) ($issue['label'] ?? 'Issue'), ENT_QUOTES, 'UTF-8') ?>
                            · <?= number_format((int) ($issue['count'] ?? 0)) ?>
                        </span>
                    <?php } ?>
                </div>

                <?php foreach ($qualityConfigIssues as $issue) { ?>
                    <?php if (!empty($issue['samples'])) { ?>
                        <div class="quality-samples">
                            <strong><?= htmlspecialchars((string) ($issue['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>:</strong>
                            <?= htmlspecialchars(
                                implode(' · ', (array) $issue['samples']),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>
                    <?php } ?>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="surface-card admin-content-card quality-section-card mb-3">
            <div class="quality-section-header">
                <div class="quality-section-heading">
                    <div class="quality-section-icon">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="page-title">Payout File Results</div>
                        <div class="page-subtitle">
                            Checks include JSON validity, count mismatch, missing SysGen/payroll/names,
                            duplicate SysGen, duplicate full name, invalid verification status,
                            malformed verification timestamp, and missing VerifyEncoder.
                        </div>
                    </div>
                </div>

                <div class="quality-section-summary">
                    <i class="fa-solid fa-folder-open"></i>
                    <span>
                        <?= number_format(count($qualityFiles)) ?>
                        payout file<?= count($qualityFiles) === 1 ? '' : 's' ?>
                    </span>
                </div>
            </div>

            <div class="table-responsive quality-section-table">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Payout</th>
                            <th>Records</th>
                            <th>Expected</th>
                            <th>Issues</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($qualityFiles as $qualityFile) { ?>
                        <?php $fileIssues = (array) ($qualityFile['issues'] ?? []); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars(
                                        (string) (
                                            $qualityFile['title']
                                            ?? $qualityFile['file']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                                <div class="small text-secondary">
                                    <?= htmlspecialchars(
                                        (string) ($qualityFile['file'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <?= number_format((int) ($qualityFile['actual_count'] ?? 0)) ?>
                            </td>

                            <td>
                                <?= number_format((int) ($qualityFile['expected_count'] ?? 0)) ?>
                            </td>

                            <td>
                                <?php if ((int) ($qualityFile['issue_count'] ?? 0) > 0) { ?>
                                    <span class="badge text-bg-warning">
                                        <?= number_format((int) $qualityFile['issue_count']) ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="quality-ok">
                                        <i class="fa-solid fa-circle-check me-1"></i>
                                        Passed
                                    </span>
                                <?php } ?>
                            </td>

                            <td>
                                <?php if (empty($fileIssues)) { ?>
                                    <span class="text-secondary small">No detected issues.</span>
                                <?php } else { ?>
                                    <div class="quality-issue-list">
                                        <?php foreach ($fileIssues as $issue) { ?>
                                            <span class="quality-issue-chip <?= ($issue['severity'] ?? '') === 'danger' ? 'is-danger' : 'is-warning' ?>"
                                                  title="<?= htmlspecialchars(
                                                      implode(' · ', (array) ($issue['samples'] ?? [])),
                                                      ENT_QUOTES,
                                                      'UTF-8'
                                                  ) ?>">
                                                <?= htmlspecialchars(
                                                    (string) ($issue['label'] ?? 'Issue'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                                · <?= number_format((int) ($issue['count'] ?? 0)) ?>
                                            </span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>

                    <?php if (empty($qualityFiles)) { ?>
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">
                                No payout files were available to scan.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card admin-content-card quality-section-card">
            <div class="quality-section-header">
                <div class="quality-section-heading">
                    <div class="quality-section-icon is-info">
                        <i class="fa-solid fa-link"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="page-title">Cross-Payout SysGen Matches</div>
                        <div class="page-subtitle">
                            This is informational. A farmer may legitimately appear in more than one payout,
                            so these entries are not automatically treated as errors.
                        </div>
                    </div>
                </div>

                <div class="quality-section-summary is-info">
                    <i class="fa-solid fa-code-branch"></i>
                    <span>
                        <?= number_format(count($qualityCrossDuplicates)) ?>
                        match<?= count($qualityCrossDuplicates) === 1 ? '' : 'es' ?>
                    </span>
                </div>
            </div>

            <?php if (empty($qualityCrossDuplicates)) { ?>
                <div class="quality-section-empty">
                    <div class="quality-section-empty-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">No cross-payout matches detected</div>
                        <div class="small text-secondary mt-1">
                            No SysGen value was found in multiple payout files.
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="table-responsive quality-section-table">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>SysGen</th>
                                <th>Name</th>
                                <th>Occurrences</th>
                                <th>Payouts</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($qualityCrossDuplicates, 0, 100) as $duplicate) { ?>
                            <?php
                                $duplicateOccurrences =
                                    (array) ($duplicate['occurrences'] ?? []);
                                $duplicatePayouts = [];
                                $duplicateNames = [];

                                foreach ($duplicateOccurrences as $occurrence) {
                                    $occurrenceTitle =
                                        (string) (
                                            $occurrence['title']
                                            ?? $occurrence['file']
                                            ?? ''
                                        );

                                    if ($occurrenceTitle !== '') {
                                        $duplicatePayouts[] = $occurrenceTitle;
                                    }

                                    $occurrenceName = trim(
                                        (string) ($occurrence['name'] ?? '')
                                    );

                                    if ($occurrenceName !== '') {
                                        $duplicateNames[] = $occurrenceName;
                                    }
                                }

                                $duplicatePayouts = array_values(
                                    array_unique($duplicatePayouts)
                                );

                                $duplicateNames = array_values(
                                    array_unique($duplicateNames)
                                );

                                $comparisonPayload = [
                                    'match_type' => 'sysgen',
                                    'match_label' => 'SysGen',
                                    'match_value' =>
                                        (string) ($duplicate['sysgen'] ?? ''),
                                    'sysgen' =>
                                        (string) ($duplicate['sysgen'] ?? ''),
                                    'occurrences' => $duplicateOccurrences
                                ];
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars(
                                        (string) ($duplicate['sysgen'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?php if (empty($duplicateNames)) { ?>
                                        <span class="text-secondary small">—</span>
                                    <?php } else { ?>
                                        <div class="cross-payout-name-list">
                                            <?php foreach ($duplicateNames as $duplicateName) { ?>
                                                <span class="cross-payout-name">
                                                    <i class="fa-regular fa-user"></i>
                                                    <?= htmlspecialchars(
                                                        $duplicateName,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </td>

                                <td><?= number_format(count($duplicateOccurrences)) ?></td>

                                <td class="small text-secondary">
                                    <?= htmlspecialchars(
                                        implode(' · ', $duplicatePayouts),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm cross-payout-compare-btn"
                                            data-cross-payout-compare="<?= htmlspecialchars(
                                                json_encode(
                                                    $comparisonPayload,
                                                    JSON_UNESCAPED_SLASHES
                                                    | JSON_UNESCAPED_UNICODE
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            title="Compare matching records"
                                            aria-label="Compare matching records">
                                        <i class="fa-solid fa-code-compare"></i>
                                        <span>Compare</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <section class="surface-card admin-content-card quality-section-card mt-3">
            <div class="quality-section-header">
                <div class="quality-section-heading">
                    <div class="quality-section-icon is-info">
                        <i class="fa-solid fa-user-group"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="page-title">Cross-Payout Name Matches</div>
                        <div class="page-subtitle">
                            Matches use Last Name + First Name + Middle Name + Extension, case-insensitive with extra spaces ignored. This is informational because different farmers may legitimately share the same name.
                        </div>
                    </div>
                </div>

                <div class="quality-section-summary is-info">
                    <i class="fa-solid fa-address-card"></i>
                    <span>
                        <?= number_format(count($qualityCrossNameDuplicates)) ?>
                        match<?= count($qualityCrossNameDuplicates) === 1 ? '' : 'es' ?>
                    </span>
                </div>
            </div>

            <?php if (empty($qualityCrossNameDuplicates)) { ?>
                <div class="quality-section-empty">
                    <div class="quality-section-empty-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">No cross-payout name matches detected</div>
                        <div class="small text-secondary mt-1">
                            No normalized full name was found in multiple payout files.
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="table-responsive quality-section-table">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Occurrences</th>
                                <th>SysGen Values</th>
                                <th>Payouts</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($qualityCrossNameDuplicates, 0, 100) as $duplicate) { ?>
                            <?php
                                $duplicateOccurrences =
                                    (array) ($duplicate['occurrences'] ?? []);
                                $duplicatePayouts = [];
                                $duplicateSysGens = [];

                                foreach ($duplicateOccurrences as $occurrence) {
                                    $occurrenceTitle =
                                        (string) (
                                            $occurrence['title']
                                            ?? $occurrence['file']
                                            ?? ''
                                        );

                                    if ($occurrenceTitle !== '') {
                                        $duplicatePayouts[] = $occurrenceTitle;
                                    }

                                    $occurrenceSysGen = trim(
                                        (string) ($occurrence['sysgen'] ?? '')
                                    );

                                    if ($occurrenceSysGen !== '') {
                                        $duplicateSysGens[] = $occurrenceSysGen;
                                    }
                                }

                                $duplicatePayouts = array_values(
                                    array_unique($duplicatePayouts)
                                );

                                $duplicateSysGens = array_values(
                                    array_unique($duplicateSysGens)
                                );

                                $duplicateName =
                                    (string) ($duplicate['name'] ?? '');

                                $comparisonPayload = [
                                    'match_type' => 'name',
                                    'match_label' => 'Full Name',
                                    'match_value' => $duplicateName,
                                    'occurrences' => $duplicateOccurrences
                                ];
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $duplicateName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td><?= number_format(count($duplicateOccurrences)) ?></td>

                                <td class="small text-secondary">
                                    <?= !empty($duplicateSysGens)
                                        ? htmlspecialchars(
                                            implode(' · ', $duplicateSysGens),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : '—' ?>
                                </td>

                                <td class="small text-secondary">
                                    <?= htmlspecialchars(
                                        implode(' · ', $duplicatePayouts),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm cross-payout-compare-btn"
                                            data-cross-payout-compare="<?= htmlspecialchars(
                                                json_encode(
                                                    $comparisonPayload,
                                                    JSON_UNESCAPED_SLASHES
                                                    | JSON_UNESCAPED_UNICODE
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            title="Compare matching records"
                                            aria-label="Compare matching records">
                                        <i class="fa-solid fa-code-compare"></i>
                                        <span>Compare</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

    <?php } elseif ($section === 'chat') { ?>

        <section class="surface-card admin-feature-header mb-3">
            <div>
                <div class="admin-feature-kicker">Verifier communications</div>
                <h1 class="page-title mb-1">Verifier Chat</h1>
                <div class="page-subtitle">
                    Review and moderate the global PAFFP verifier chat shared across all payout pages.
                </div>
            </div>

            <div class="admin-feature-summary">
                <i class="fa-solid fa-comments"></i>
                <span>
                    <?= number_format($chatMessageCount) ?>
                    message<?= $chatMessageCount === 1 ? '' : 's' ?>
                </span>
            </div>
        </section>

        <section class="surface-card admin-content-card">
            <div class="chat-admin-toolbar">
                <div class="chat-admin-storage">
                    <i class="fa-solid fa-globe"></i>
                    <span>Global room · data/_chat/global.json</span>
                </div>

                <?php if ($chatMessageCount > 0) { ?>
                    <form method="post"
                          id="chatClearAllForm"
                          class="m-0">
                        <input type="hidden"
                               name="csrfToken"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden"
                               name="admin_action"
                               value="chat_clear_all">

                        <button type="submit"
                                class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-trash-can me-1"></i>
                            Clear All Messages
                        </button>
                    </form>
                <?php } ?>
            </div>

            <?php if (empty($chatMessages)) { ?>
                <div class="chat-admin-empty">
                    <div>
                        <i class="fa-regular fa-comments fa-2x mb-2"></i>
                        <div class="fw-semibold">No verifier chat messages</div>
                        <div class="small mt-1">
                            Messages sent from Verify pages will appear here.
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 chat-admin-table">
                        <thead>
                            <tr>
                                <th>Verifier</th>
                                <th>Payout Source</th>
                                <th>Message</th>
                                <th>Sent</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chatMessages as $chatMessage) { ?>
                            <?php
                                $chatId = (string) ($chatMessage['id'] ?? '');
                                $chatUsername =
                                    trim((string) ($chatMessage['username'] ?? ''));
                                $chatPayoutTitle =
                                    trim((string) ($chatMessage['payout_title'] ?? ''));
                                $chatPayoutUid =
                                    trim((string) ($chatMessage['payout_uid'] ?? ''));
                                $chatText =
                                    (string) ($chatMessage['message'] ?? '');
                                $chatCreatedAt =
                                    (int) ($chatMessage['created_at'] ?? 0);

                                $chatSentLabel =
                                    $chatCreatedAt > 0
                                        ? date('M j, Y · g:i A', $chatCreatedAt)
                                        : '—';
                            ?>
                            <tr>
                                <td>
                                    <div class="chat-admin-user">
                                        <div class="chat-admin-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>

                                        <div class="chat-admin-user-name"
                                             title="<?= htmlspecialchars(
                                                 $chatUsername !== ''
                                                    ? $chatUsername
                                                    : 'Unknown',
                                                 ENT_QUOTES,
                                                 'UTF-8'
                                             ) ?>">
                                            <?= htmlspecialchars(
                                                $chatUsername !== ''
                                                    ? $chatUsername
                                                    : 'Unknown',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="chat-admin-payout">
                                        <div class="fw-semibold text-body">
                                            <?= htmlspecialchars(
                                                $chatPayoutTitle !== ''
                                                    ? $chatPayoutTitle
                                                    : 'Legacy / Unknown payout',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <?php if ($chatPayoutUid !== '') { ?>
                                            <div class="mt-1">
                                                <?= htmlspecialchars(
                                                    $chatPayoutUid,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="chat-admin-message">
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $chatText,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                        ) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="chat-admin-time">
                                        <?= htmlspecialchars(
                                            $chatSentLabel,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <form method="post"
                                          class="m-0 d-inline chat-message-delete-form"
                                          data-chat-user="<?= htmlspecialchars(
                                              $chatUsername !== ''
                                                  ? $chatUsername
                                                  : 'Unknown',
                                              ENT_QUOTES,
                                              'UTF-8'
                                          ) ?>"
                                          data-chat-preview="<?= htmlspecialchars(
                                              function_exists('mb_substr')
                                                  ? mb_substr(
                                                      preg_replace(
                                                          '/\s+/',
                                                          ' ',
                                                          trim($chatText)
                                                      ),
                                                      0,
                                                      90,
                                                      'UTF-8'
                                                  )
                                                  : substr(
                                                      preg_replace(
                                                          '/\s+/',
                                                          ' ',
                                                          trim($chatText)
                                                      ),
                                                      0,
                                                      90
                                                  ),
                                              ENT_QUOTES,
                                              'UTF-8'
                                          ) ?>">
                                        <input type="hidden"
                                               name="csrfToken"
                                               value="<?= htmlspecialchars(
                                                   $csrfToken,
                                                   ENT_QUOTES,
                                                   'UTF-8'
                                               ) ?>">
                                        <input type="hidden"
                                               name="admin_action"
                                               value="chat_message_delete">
                                        <input type="hidden"
                                               name="message_id"
                                               value="<?= htmlspecialchars(
                                                   $chatId,
                                                   ENT_QUOTES,
                                                   'UTF-8'
                                               ) ?>">

                                        <button type="submit"
                                                class="btn btn-outline-danger btn-sm chat-admin-delete-btn"
                                                title="Delete chat message"
                                                aria-label="Delete chat message">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>


    <?php } elseif ($section === 'announcements') { ?>

        <section class="surface-card admin-feature-header mb-3">
            <div>
                <div class="admin-feature-kicker">Verifier communications</div>
                <h1 class="page-title mb-1">Announcements</h1>
                <div class="page-subtitle">
                    Publish and manage notices shown to PAFFP verifiers by payout and audience.
                </div>
            </div>

            <div class="admin-feature-summary">
                <i class="fa-solid fa-bullhorn"></i>
                <span><?= number_format($activeAnnouncementCount) ?> active</span>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-12 col-xl-5">
                <section class="surface-card composer-card admin-content-card">
                    <div class="mb-3 admin-card-heading">
                        <h1 class="page-title" id="composerTitle">Push Announcement</h1>
                        <div class="page-subtitle">
                            Publish notices by payout, priority, and verifier audience. Only the Active switch determines whether an announcement is shown.
                        </div>
                    </div>

                    <form method="post" id="announcementForm">
                        <input type="hidden"
                               name="csrfToken"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden"
                               name="admin_action"
                               value="announcement_save">
                        <input type="hidden"
                               name="id"
                               id="announcementId"
                               value="">

                        <div class="mb-3">
                            <label class="form-label" for="announcementTitle">Title</label>
                            <input type="text"
                                   class="form-control"
                                   name="title"
                                   id="announcementTitle"
                                   maxlength="120"
                                   placeholder="e.g., Verification Advisory"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="announcementMessage">Message</label>
                            <textarea class="form-control"
                                      name="message"
                                      id="announcementMessage"
                                      rows="5"
                                      maxlength="2000"
                                      placeholder="Type the announcement shown to verifiers..."
                                      required></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="announcementType">Type</label>
                                <select class="form-select"
                                        name="type"
                                        id="announcementType">
                                    <option value="info">Information</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="danger">Urgent / Danger</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="announcementScope">Show to</label>
                                <select class="form-select"
                                        name="scope"
                                        id="announcementScope">
                                    <option value="all">All payouts</option>

                                    <?php foreach ($payoutMap as $uid => $payoutTitle) { ?>
                                        <option value="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($payoutTitle, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="announcementPriority">Priority</label>
                                <select class="form-select"
                                        name="priority"
                                        id="announcementPriority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="announcementAudience">Audience</label>
                                <select class="form-select"
                                        name="audience"
                                        id="announcementAudience">
                                    <option value="all">All verifiers</option>
                                    <option value="verifier">Specific verifier</option>
                                </select>
                            </div>

                            <div class="col-12 d-none"
                                 id="announcementAudienceValueWrap">
                                <label class="form-label" for="announcementAudienceValue">
                                    Verifier Username
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="audience_value"
                                       id="announcementAudienceValue"
                                       list="verifierAudienceOptions"
                                       placeholder="e.g., Inspiring Dove"
                                       maxlength="120">
                                <datalist id="verifierAudienceOptions">
                                    <?php foreach ($verifierNames as $verifierName) { ?>
                                        <option value="<?= htmlspecialchars($verifierName, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php } ?>
                                </datalist>
                                <div class="form-text">
                                    The username must match the verifier name shown in Verify.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="enabled"
                                       id="announcementEnabled"
                                       checked>
                                <label class="form-check-label small"
                                       for="announcementEnabled">
                                    Enabled
                                </label>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="dismissible"
                                       id="announcementDismissible"
                                       checked>
                                <label class="form-check-label small"
                                       for="announcementDismissible">
                                    User can dismiss
                                </label>
                                <div class="form-text">
                                    If disabled, the announcement cannot be closed and a Home button will be shown.
                                </div>
                            </div>
                        </div>

                        <div class="announcement-preview-wrap">
                            <div class="announcement-preview-label">
                                <i class="fa-regular fa-eye me-1"></i>
                                Preview
                            </div>

                            <div class="announcement-preview info"
                                 id="announcementPreview">
                                <div class="preview-icon" id="previewIcon">
                                    <i class="fa-solid fa-circle-info"></i>
                                </div>

                                <div class="preview-title"
                                     id="previewTitle">
                                    Announcement preview
                                </div>

                                <div class="preview-message"
                                     id="previewMessage">
                                    Your announcement will appear here.
                                </div>

                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm preview-home-action d-none"
                                        id="previewHomeAction"
                                        tabindex="-1">
                                    <i class="fa-solid fa-house me-1"></i>
                                    Home
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <button type="button"
                                    class="btn btn-outline-secondary d-none"
                                    id="cancelEdit">
                                Cancel Edit
                            </button>

                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-bullhorn me-1"></i>
                                <span id="saveLabel">Publish Announcement</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-7">
                <section class="surface-card announcement-list-card admin-content-card">
                    <div class="list-header d-flex align-items-center justify-content-between gap-2">
                        <div>
                            <div class="page-title">Announcements</div>
                            <div class="page-subtitle">
                                <?= count($announcements) ?> saved announcement<?= count($announcements) === 1 ? '' : 's' ?>
                                · <?= $activeAnnouncementCount ?> active
                            </div>
                        </div>

                        <button class="btn btn-outline-secondary btn-sm"
                                type="button"
                                onclick="location.reload()"
                                title="Refresh">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>

                    <?php if (empty($announcements)) { ?>
                        <div class="p-4 text-center text-secondary small">
                            No announcements have been created.
                        </div>
                    <?php } ?>

                    <?php foreach ($announcements as $item) { ?>
                        <?php
                            $state = announcementState($item, $now);
                            $scopeValue = (string) ($item['scope'] ?? 'all');

                            $scopeLabel = $scopeValue === 'all'
                                ? 'All payouts'
                                : ($payoutMap[$scopeValue] ?? $scopeValue);

                            $stateClasses = [
                                'active' => 'text-bg-success',
                                'inactive' => 'text-bg-secondary'
                            ];

                            $stateClass = isset($stateClasses[$state])
                                ? $stateClasses[$state]
                                : 'text-bg-secondary';
                        ?>

                        <article class="announcement-item">
                            <?php
                                $announcementType = (string) ($item['type'] ?? 'info');

                                $previewIconMap = [
                                    'info' => 'fa-solid fa-circle-info',
                                    'success' => 'fa-solid fa-circle-check',
                                    'warning' => 'fa-solid fa-triangle-exclamation',
                                    'danger' => 'fa-solid fa-circle-exclamation'
                                ];

                                $previewIcon = isset($previewIconMap[$announcementType])
                                    ? $previewIconMap[$announcementType]
                                    : $previewIconMap['info'];
                            ?>

                            <div class="saved-announcement-preview <?= htmlspecialchars($announcementType, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="saved-announcement-state badge <?= $stateClass ?>">
                                    <?= htmlspecialchars(ucfirst($state), ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <div class="saved-preview-icon">
                                    <i class="<?= htmlspecialchars($previewIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                </div>

                                <div class="saved-preview-title">
                                    <?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="saved-preview-message"><?= htmlspecialchars((string) ($item['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div class="meta-row">
                                <span class="meta-chip">
                                    <?= htmlspecialchars(ucfirst((string) ($item['type'] ?? 'info')), ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <span class="meta-chip">
                                    <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <span class="meta-chip">
                                    <?= htmlspecialchars(ucfirst((string) ($item['priority'] ?? 'normal')) . ' priority', ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <span class="meta-chip">
                                    <?php
                                        $savedAudience = (string) ($item['audience'] ?? 'all');
                                        $savedAudienceValue = (string) ($item['audience_value'] ?? '');
                                        echo htmlspecialchars(
                                            $savedAudience === 'verifier' && $savedAudienceValue !== ''
                                                ? 'Verifier: ' . $savedAudienceValue
                                                : 'All verifiers',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>
                                </span>

                                <span class="meta-chip">
                                    <?= !empty($item['dismissible']) ? 'Dismissible' : 'Persistent' ?>
                                </span>
                            </div>

                            <div class="announcement-actions mt-2">
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm edit-announcement"
                                        data-announcement="<?= htmlspecialchars(json_encode([
                                            'id' => (string) ($item['id'] ?? ''),
                                            'title' => (string) ($item['title'] ?? ''),
                                            'message' => (string) ($item['message'] ?? ''),
                                            'type' => (string) ($item['type'] ?? 'info'),
                                            'scope' => (string) ($item['scope'] ?? 'all'),
                                            'priority' => (string) ($item['priority'] ?? 'normal'),
                                            'audience' => (string) ($item['audience'] ?? 'all'),
                                            'audience_value' => (string) ($item['audience_value'] ?? ''),
                                            'enabled' => !empty($item['enabled']),
                                            'dismissible' => !empty($item['dismissible'])
                                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-pen me-1"></i>
                                    Edit
                                </button>

                                <form method="post" class="d-inline">
                                    <input type="hidden"
                                           name="csrfToken"
                                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden"
                                           name="admin_action"
                                           value="announcement_toggle">
                                    <input type="hidden"
                                           name="id"
                                           value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                                    <button type="submit"
                                            class="btn btn-outline-warning btn-sm">
                                        <i class="fa-solid fa-power-off me-1"></i>
                                        <?= !empty($item['enabled']) ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>

                                <form method="post"
                                      class="d-inline delete-announcement-form">
                                    <input type="hidden"
                                           name="csrfToken"
                                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden"
                                           name="admin_action"
                                           value="announcement_delete">
                                    <input type="hidden"
                                           name="id"
                                           value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                                    <button type="submit"
                                            class="btn btn-outline-danger btn-sm">
                                        <i class="fa-solid fa-trash me-1"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php } ?>
                </section>
            </div>
        </div>

    <?php } elseif ($section === 'backups') { ?>

        <section class="surface-card admin-feature-header mb-3">
            <div>
                <div class="admin-feature-kicker">Data protection</div>
                <h1 class="page-title mb-1">Backup & Restore</h1>
                <div class="page-subtitle">
                    Create verified snapshots and recover PAFFP data from previous backups.
                </div>
            </div>

            <div class="admin-feature-summary">
                <i class="fa-solid fa-box-archive"></i>
                <span><?= number_format(count($fullBackups)) ?> backup<?= count($fullBackups) === 1 ? '' : 's' ?></span>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <section class="surface-card backup-create-card admin-content-card">
                    <div class="backup-create-icon">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>

                    <h1 class="page-title mt-3">Backup All Data</h1>
                    <div class="page-subtitle">
                        Create a compressed snapshot of the PAFFP data folder, including payout JSON files, settings, announcements, and sync journals.
                    </div>

                    <form method="post" class="mt-3">
                        <input type="hidden"
                               name="csrfToken"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden"
                               name="admin_action"
                               value="backup_all">

                        <button type="submit"
                                class="btn btn-success w-100">
                            <i class="fa-solid fa-database me-1"></i>
                            Backup All Data
                        </button>
                    </form>

                </section>
            </div>

            <div class="col-12 col-xl-8">
                <section class="surface-card restore-center-card admin-content-card">
                    <div class="dashboard-panel-heading">
                        <div>
                            <div class="page-title">Restore Center</div>
                            <div class="page-subtitle">
                                <?= number_format(count($fullBackups)) ?> full backup<?= count($fullBackups) === 1 ? '' : 's' ?> available.
                            </div>
                        </div>
                    </div>

                    <?php if (empty($fullBackups)) { ?>
                        <div class="p-4 text-center text-secondary">
                            No full-data backups have been created yet.
                        </div>
                    <?php } ?>

                    <div class="backup-list">
                        <?php foreach ($fullBackups as $backupItem) { ?>
                            <article class="backup-item">
                                <div class="backup-item-icon">
                                    <i class="fa-solid fa-file-zipper"></i>
                                </div>

                                <div class="backup-item-copy min-w-0">
                                    <div class="backup-item-name text-truncate"
                                         title="<?= htmlspecialchars($backupItem['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($backupItem['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="backup-item-meta">
                                        <?= date('M. j, Y g:i A', $backupItem['modified']) ?>
                                        · <?= htmlspecialchars(formatBytes($backupItem['size']), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>

                                <div class="backup-item-actions">
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?download_backup=<?= urlencode($backupItem['name']) ?>">
                                        <i class="fa-solid fa-download me-1"></i>
                                        Download
                                    </a>

                                    <form method="post"
                                          class="restore-backup-form d-inline">
                                        <input type="hidden"
                                               name="csrfToken"
                                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden"
                                               name="admin_action"
                                               value="backup_restore">
                                        <input type="hidden"
                                               name="backup_name"
                                               value="<?= htmlspecialchars($backupItem['name'], ENT_QUOTES, 'UTF-8') ?>">

                                        <button type="submit"
                                                class="btn btn-outline-danger btn-sm">
                                            <i class="fa-solid fa-clock-rotate-left me-1"></i>
                                            Restore
                                        </button>
                                    </form>

                                    <form method="post"
                                          class="delete-backup-form d-inline">
                                        <input type="hidden"
                                               name="csrfToken"
                                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden"
                                               name="admin_action"
                                               value="backup_delete">
                                        <input type="hidden"
                                               name="backup_name"
                                               value="<?= htmlspecialchars($backupItem['name'], ENT_QUOTES, 'UTF-8') ?>">

                                        <button type="submit"
                                                class="btn btn-outline-danger btn-sm"
                                                title="Permanently delete this backup">
                                            <i class="fa-solid fa-trash-can me-1"></i>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                </section>
            </div>
        </div>

    <?php } elseif ($section === 'ads') { ?>

        <?php
            $indexAdCounts = adPageSlotCounts($adSettings, 'index');
            $verifyAdCounts = adPageSlotCounts($adSettings, 'verify');
            $monitorAdCounts = adPageSlotCounts($adSettings, 'monitor');
            $enabledAdSlots =
                $indexAdCounts['enabled']
                + $verifyAdCounts['enabled']
                + $monitorAdCounts['enabled'];
            $totalAdSlots =
                $indexAdCounts['total']
                + $verifyAdCounts['total']
                + $monitorAdCounts['total'];
        ?>

        <section class="surface-card ad-control-page admin-content-card">
            <div class="ad-control-heading admin-feature-heading">
                <div>
                    <div class="admin-feature-kicker">Advertising configuration</div>
                    <h1 class="page-title">Advertisement Controls</h1>
                    <div class="page-subtitle">
                        Enable or disable each advertisement independently, with a master switch for every page.
                    </div>
                </div>

                <div class="ad-control-summary">
                    <i class="fa-solid fa-rectangle-ad"></i>
                    <span>
                        <?= number_format($enabledAdSlots) ?> of
                        <?= number_format($totalAdSlots) ?> ad slots active
                    </span>
                </div>
            </div>

            <form method="post" id="adSettingsForm">
                <input type="hidden" name="csrfToken"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="admin_action" value="ad_settings_save">

                <div class="ad-page-groups">

                    <section class="ad-page-group" data-ad-page-group="index">
                        <label class="ad-page-master" for="adsIndex">
                            <div class="ad-control-icon"><i class="fa-solid fa-house"></i></div>
                            <div>
                                <div class="ad-page-master-title">Index Page</div>
                                <div class="ad-page-master-meta">
                                    Master switch · <?= $indexAdCounts['enabled'] ?>/<?= $indexAdCounts['total'] ?> effective slots
                                </div>
                            </div>
                            <div class="form-check form-switch ad-control-switch">
                                <input class="form-check-input ad-page-master-switch"
                                       type="checkbox" role="switch"
                                       id="adsIndex" name="ads_index"
                                       data-ad-page="index"
                                       <?= !empty($adSettings['index']) ? 'checked' : '' ?>>
                            </div>
                        </label>

                        <div class="ad-slot-list">
                            <label class="ad-slot-card" for="adsIndexBannerAfterOngoing">
                                <div>
                                    <div class="ad-slot-title">Banner after Ongoing</div>
                                    <div class="ad-slot-description">728×90 banner between Ongoing and Upcoming payouts.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsIndexBannerAfterOngoing"
                                           name="ads_index_banner_after_ongoing"
                                           <?= !empty($adSettings['slots']['index']['banner_after_ongoing']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsIndexBannerAfterUpcoming">
                                <div>
                                    <div class="ad-slot-title">Banner after Upcoming</div>
                                    <div class="ad-slot-description">728×90 banner between Upcoming and Accomplished payouts.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsIndexBannerAfterUpcoming"
                                           name="ads_index_banner_after_upcoming"
                                           <?= !empty($adSettings['slots']['index']['banner_after_upcoming']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsIndexNativeAccomplished">
                                <div>
                                    <div class="ad-slot-title">Accomplished Native Ads</div>
                                    <div class="ad-slot-description">Native ad after every 8 Accomplished payout cards.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsIndexNativeAccomplished"
                                           name="ads_index_native_accomplished"
                                           <?= !empty($adSettings['slots']['index']['native_accomplished']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsIndexPopunder">
                                <div>
                                    <div class="ad-slot-title">Popunder</div>
                                    <div class="ad-slot-description">Index-only popunder advertisement script.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsIndexPopunder"
                                           name="ads_index_popunder"
                                           <?= !empty($adSettings['slots']['index']['popunder']) ? 'checked' : '' ?>>
                                </div>
                            </label>
                        </div>
                    </section>

                    <section class="ad-page-group" data-ad-page-group="verify">
                        <label class="ad-page-master" for="adsVerify">
                            <div class="ad-control-icon"><i class="fa-solid fa-user-check"></i></div>
                            <div>
                                <div class="ad-page-master-title">Verify Page</div>
                                <div class="ad-page-master-meta">
                                    Master switch · <?= $verifyAdCounts['enabled'] ?>/<?= $verifyAdCounts['total'] ?> effective slots
                                </div>
                            </div>
                            <div class="form-check form-switch ad-control-switch">
                                <input class="form-check-input ad-page-master-switch"
                                       type="checkbox" role="switch"
                                       id="adsVerify" name="ads_verify"
                                       data-ad-page="verify"
                                       <?= !empty($adSettings['verify']) ? 'checked' : '' ?>>
                            </div>
                        </label>

                        <div class="ad-slot-list">
                            <label class="ad-slot-card" for="adsVerifyAnalyticsNative">
                                <div>
                                    <div class="ad-slot-title">Analytics Native Ad</div>
                                    <div class="ad-slot-description">Native advertisement below Verify Analytics.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsVerifyAnalyticsNative"
                                           name="ads_verify_analytics_native"
                                           <?= !empty($adSettings['slots']['verify']['analytics_native']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsVerifyContainerLocalBanner">
                                <div>
                                    <div class="ad-slot-title">Beneficiary Table Banner</div>
                                    <div class="ad-slot-description">Centered 728×90 banner immediately after the Verify beneficiary table.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsVerifyContainerLocalBanner"
                                           name="ads_verify_container_local_banner"
                                           <?= !empty($adSettings['slots']['verify']['container_local_banner']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsVerifyFooterBanner">
                                <div>
                                    <div class="ad-slot-title">Footer Banner</div>
                                    <div class="ad-slot-description">Centered 728×90 banner above the Verify footer.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsVerifyFooterBanner"
                                           name="ads_verify_footer_banner"
                                           <?= !empty($adSettings['slots']['verify']['footer_banner']) ? 'checked' : '' ?>>
                                </div>
                            </label>
                        </div>
                    </section>

                    <section class="ad-page-group" data-ad-page-group="monitor">
                        <label class="ad-page-master" for="adsMonitor">
                            <div class="ad-control-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div>
                                <div class="ad-page-master-title">Monitor Page</div>
                                <div class="ad-page-master-meta">
                                    Master switch · <?= $monitorAdCounts['enabled'] ?>/<?= $monitorAdCounts['total'] ?> effective slots
                                </div>
                            </div>
                            <div class="form-check form-switch ad-control-switch">
                                <input class="form-check-input ad-page-master-switch"
                                       type="checkbox" role="switch"
                                       id="adsMonitor" name="ads_monitor"
                                       data-ad-page="monitor"
                                       <?= !empty($adSettings['monitor']) ? 'checked' : '' ?>>
                            </div>
                        </label>

                        <div class="ad-slot-list">
                            <label class="ad-slot-card" for="adsMonitorSummaryBanner">
                                <div>
                                    <div class="ad-slot-title">Summary Banner</div>
                                    <div class="ad-slot-description">Banner between Overall Payout Summary and Live Analytics.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsMonitorSummaryBanner"
                                           name="ads_monitor_summary_banner"
                                           <?= !empty($adSettings['slots']['monitor']['summary_banner']) ? 'checked' : '' ?>>
                                </div>
                            </label>

                            <label class="ad-slot-card" for="adsMonitorStatusBanner">
                                <div>
                                    <div class="ad-slot-title">Status Banner</div>
                                    <div class="ad-slot-description">Banner between Live Analytics and Live Payout Status.</div>
                                </div>
                                <div class="form-check form-switch ad-control-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="adsMonitorStatusBanner"
                                           name="ads_monitor_status_banner"
                                           <?= !empty($adSettings['slots']['monitor']['status_banner']) ? 'checked' : '' ?>>
                                </div>
                            </label>
                        </div>
                    </section>

                </div>

                <div class="ad-control-footer">
                    <div class="ad-control-note">
                        <i class="fa-solid fa-circle-info"></i>
                        Turning a page master OFF hides all ads on that page but preserves the individual slot preferences.
                        <?php if (!empty($adSettings['updated_ts'])) { ?>
                            Last updated <?= date('M. j, Y g:i A', (int) $adSettings['updated_ts']) ?>.
                        <?php } ?>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Save Ad Settings
                    </button>
                </div>
            </form>
        </section>

    <?php } else { ?>

        <section class="surface-card json-header-card admin-feature-header">
            <div class="json-header-layout d-flex align-items-center justify-content-between gap-3">
                <div class="min-w-0">
                    <div class="admin-feature-kicker">Advanced data maintenance</div>
                    <h1 class="page-title">
                        PAFFP JSON Manager

                        <?php if ($isSystemFile) { ?>
                            <span class="system-badge">
                                <i class="fa-solid fa-shield-halved"></i>
                                System File
                            </span>
                        <?php } ?>
                    </h1>

                    <div class="page-subtitle">
                        Edit PAFFP JSON files with validation, backups, search and recovery.
                    </div>
                </div>

                <?php if (!empty($jsonFiles)) { ?>
                    <div class="file-picker" id="filePicker">
                        <i class="fa-solid fa-magnifying-glass file-search-icon"></i>

                        <input type="text"
                               class="form-control file-search-input"
                               id="fileSearch"
                               value="<?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Search JSON files..."
                               autocomplete="off">

                        <div class="file-picker-menu d-none"
                             id="filePickerMenu">

                            <?php foreach ($jsonFiles as $name) { ?>
                                <button type="button"
                                        class="file-picker-item <?= $name === $selectedFile ? 'is-current' : '' ?>"
                                        data-file="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="file-picker-item-name">
                                        <i class="fa-regular fa-file-code me-2"></i>
                                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                    <?php if (substr($name, 0, 1) === '_') { ?>
                                        <span class="badge text-bg-danger">System</span>
                                    <?php } ?>
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if ($selectedFile !== '') { ?>
                <div class="file-meta">
                    <span class="file-meta-chip <?= $isSystemFile ? 'system' : '' ?>">
                        <i class="<?= $isSystemFile ? 'fa-solid fa-shield-halved' : 'fa-regular fa-file-code' ?>"></i>
                        <?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <span class="file-meta-chip">
                        <i class="fa-solid fa-list-ol"></i>
                        <span id="serverRecordCount">
                            <?= $recordCount !== null ? number_format($recordCount) . ' records' : 'JSON object' ?>
                        </span>
                    </span>

                    <span class="file-meta-chip">
                        <i class="fa-solid fa-hard-drive"></i>
                        <span id="liveFileSize">
                            <?= htmlspecialchars(formatBytes($fileSize), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </span>

                    <?php if ($fileModified !== null) { ?>
                        <span class="file-meta-chip">
                            <i class="fa-regular fa-clock"></i>
                            Modified <?= date('M. j, Y g:i A', $fileModified) ?>
                        </span>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <?php if ($selectedFile !== '') { ?>

            <section class="surface-card backup-toolbar">
                <div>
                    <div class="backup-title">
                        <i class="fa-solid fa-clock-rotate-left me-1 text-success"></i>
                        Version Recovery
                    </div>

                    <div class="backup-subtitle">
                        A safety backup is created automatically before every save or restore.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <select class="form-select backup-select"
                            id="backupFile"
                            <?= empty($backups) ? 'disabled' : '' ?>>

                        <?php if (empty($backups)) { ?>
                            <option value="">No backups available</option>
                        <?php } else { ?>
                            <?php foreach (array_slice($backups, 0, 30) as $backupName) { ?>
                                <?php
                                    $backupPath = $backupFolder . "/" . $backupName;
                                    $backupTime = is_file($backupPath)
                                        ? filemtime($backupPath)
                                        : null;
                                ?>

                                <option value="<?= htmlspecialchars($backupName, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(
                                        ($backupTime
                                            ? date('M. j, Y g:i:s A', $backupTime) . ' · '
                                            : '') . $backupName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php } ?>
                        <?php } ?>
                    </select>

                    <button type="button"
                            class="btn btn-outline-danger"
                            id="restoreButton"
                            <?= empty($backups) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-clock-rotate-left me-md-1"></i>
                        <span class="d-none d-md-inline">Restore</span>
                    </button>
                </div>
            </section>

            <form id="editorForm" method="post" novalidate>
                <input type="hidden"
                       name="csrfToken"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden"
                       name="admin_action"
                       value="json_save">
                <input type="hidden"
                       name="selectedFile"
                       value="<?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>">

                <section class="surface-card editor-card">
                    <div class="editor-toolbar">
                        <div class="editor-toolbar-title">
                            <i class="fa-solid fa-code"></i>
                            <span class="selected-file-name">
                                <?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="editor-toolbar-actions">
                            <span class="editor-status"
                                  id="jsonStatus">
                                <i class="fa-solid fa-circle-info"></i>
                                <span>Checking…</span>
                            </span>

                            <button type="button"
                                    class="btn btn-outline-danger json-action-btn d-none"
                                    id="goToJsonError"
                                    title="Go to JSON error">
                                <i class="fa-solid fa-location-crosshairs me-sm-1"></i>
                                <span>Error</span>
                            </button>

                            <button type="button"
                                    class="btn btn-outline-secondary json-action-btn"
                                    id="findJson"
                                    title="Find (Ctrl+F)">
                                <i class="fa-solid fa-magnifying-glass me-sm-1"></i>
                                <span>Find</span>
                            </button>

                            <button type="button"
                                    class="btn btn-outline-secondary json-action-btn"
                                    id="replaceJson"
                                    title="Replace (Ctrl+H)">
                                <i class="fa-solid fa-right-left me-sm-1"></i>
                                <span>Replace</span>
                            </button>

                            <button type="button"
                                    class="btn btn-outline-secondary json-action-btn"
                                    id="formatJson"
                                    title="Format JSON">
                                <i class="fa-solid fa-align-left me-sm-1"></i>
                                <span>Format</span>
                            </button>
                        </div>
                    </div>

                    <textarea name="jsonContent"
                              id="jsonContent"
                              spellcheck="false"><?= htmlspecialchars($jsonContent, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <div class="editor-footer">
                        <div class="editor-status" id="changeStatus">
                            <i class="fa-regular fa-circle-check"></i>
                            <span>No unsaved changes</span>
                        </div>

                        <button type="submit"
                                class="btn btn-success"
                                id="saveButton">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Save Changes
                        </button>
                    </div>
                </section>
            </form>

        <?php } else { ?>

            <section class="surface-card p-5 text-center">
                <div class="text-secondary">
                    <i class="fa-regular fa-folder-open fa-2x mb-3"></i>
                    <div>No JSON files found in the data folder.</div>
                </div>
            </section>

        <?php } ?>

    <?php } ?>

</main>

<?php if ($section === 'payouts') { ?>
<div class="modal fade"
     id="temporaryAccessModal"
     tabindex="-1"
     aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content paffp-modal paffp-tone-warning">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-6">Temporary Open Access</h2>
                    <div class="small text-secondary mt-1">
                        Open every payout for viewing or editing for a limited time.
                    </div>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <form method="post" id="temporaryAccessForm">
                <div class="modal-body">
                    <input type="hidden"
                           name="csrfToken"
                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden"
                           name="admin_action"
                           value="temporary_access_start">

                    <?php if ($temporaryAccessActive) { ?>
                        <div class="alert alert-warning py-2 small">
                            <i class="fa-solid fa-clock me-1"></i>
                            Currently active for
                            <strong><?= $temporaryAccessMode === 'edit'
                                ? 'Viewing and Editing'
                                : 'Viewing Only' ?></strong>
                            for
                            <strong><?= $temporaryAccessAudience === 'user'
                                ? htmlspecialchars($temporaryAccessAudienceValue, ENT_QUOTES, 'UTF-8')
                                : 'All Users' ?></strong>
                            until
                            <strong><?= htmlspecialchars(date('M. j, Y g:i A', $temporaryAccessExpiresAt), ENT_QUOTES, 'UTF-8') ?></strong>.
                            Starting again will replace the current mode, audience, and expiry time.
                        </div>
                    <?php } ?>

                    <label class="form-label">
                        Access Type
                    </label>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <input class="btn-check"
                                   type="radio"
                                   name="temporary_access_mode"
                                   id="temporaryAccessView"
                                   value="view"
                                   <?= (!$temporaryAccessActive || $temporaryAccessMode === 'view') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary w-100 text-start h-100 p-3"
                                   for="temporaryAccessView">
                                <span class="d-flex align-items-start gap-2">
                                    <i class="fa-regular fa-eye mt-1"></i>
                                    <span>
                                        <strong class="d-block">Open for Viewing</strong>
                                        <span class="small opacity-75">
                                            Everyone can open and view payout records, but cannot verify,
                                            unverify, flag, capture photos, or save changes.
                                        </span>
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="col-12 col-sm-6">
                            <input class="btn-check"
                                   type="radio"
                                   name="temporary_access_mode"
                                   id="temporaryAccessEdit"
                                   value="edit"
                                   <?= ($temporaryAccessActive && $temporaryAccessMode === 'edit') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning w-100 text-start h-100 p-3"
                                   for="temporaryAccessEdit">
                                <span class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-pen-to-square mt-1"></i>
                                    <span>
                                        <strong class="d-block">Open for Editing</strong>
                                        <span class="small opacity-75">
                                            Everyone can view payout records and perform verification updates
                                            during the temporary access window.
                                        </span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <label class="form-label">Audience</label>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <input class="btn-check"
                                   type="radio"
                                   name="temporary_access_audience"
                                   id="temporaryAccessAudienceAll"
                                   value="all"
                                   <?= (!$temporaryAccessActive || $temporaryAccessAudience === 'all') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary w-100 text-start h-100 p-3"
                                   for="temporaryAccessAudienceAll">
                                <span class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-users mt-1"></i>
                                    <span>
                                        <strong class="d-block">All Users</strong>
                                        <span class="small opacity-75">Temporary access applies to every verifier username.</span>
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="col-12 col-sm-6">
                            <input class="btn-check"
                                   type="radio"
                                   name="temporary_access_audience"
                                   id="temporaryAccessAudienceUser"
                                   value="user"
                                   <?= ($temporaryAccessActive && $temporaryAccessAudience === 'user') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary w-100 text-start h-100 p-3"
                                   for="temporaryAccessAudienceUser">
                                <span class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-user-lock mt-1"></i>
                                    <span>
                                        <strong class="d-block">Specific User</strong>
                                        <span class="small opacity-75">Temporary access applies only to one verifier username.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3 <?= ($temporaryAccessActive && $temporaryAccessAudience === 'user') ? '' : 'd-none' ?>"
                         id="temporaryAccessUserWrap">
                        <label class="form-label" for="temporaryAccessUser">Verifier Username</label>
                        <input type="text"
                               class="form-control"
                               id="temporaryAccessUser"
                               name="temporary_access_user"
                               maxlength="120"
                               autocomplete="off"
                               placeholder="e.g. Noble Ibis"
                               value="<?= htmlspecialchars($temporaryAccessAudienceValue, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-text">
                            Must match the verifier username shown in Verify.
                        </div>
                    </div>

                    <label class="form-label" for="temporaryAccessMinutes">
                        Access Duration
                    </label>
                    <select class="form-select"
                            id="temporaryAccessMinutes"
                            name="temporary_access_minutes"
                            required>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="60" selected>1 hour</option>
                        <option value="120">2 hours</option>
                        <option value="240">4 hours</option>
                        <option value="480">8 hours</option>
                        <option value="720">12 hours</option>
                        <option value="1440">24 hours</option>
                    </select>

                    <div class="temporary-access-note mt-3">
                        <i class="fa-solid fa-shield-halved mt-1"></i>
                        <span>
                            This temporarily overrides individual payout restrictions, including
                            manually closed and accomplished payouts. Viewing mode is read-only;
                            Editing mode permits verification changes. Existing payout settings are not changed.
                            When the timer expires, each payout automatically returns to its normal access rules.
                        </span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <?php if ($temporaryAccessActive) { ?>
                        <button type="button"
                                class="btn btn-outline-danger"
                                id="modalStopTemporaryAccessButton">
                            <i class="fa-solid fa-stop me-1"></i>
                            Stop Access
                        </button>
                    <?php } ?>

                    <button type="submit"
                            class="btn btn-warning">
                        <i class="fa-solid fa-unlock-keyhole me-1"></i>
                        <?= $temporaryAccessActive ? 'Restart Timer' : 'Open All Payouts' ?>
                    </button>
                </div>
            </form>

            <form method="post" id="temporaryAccessStopForm" class="d-none">
                <input type="hidden"
                       name="csrfToken"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden"
                       name="admin_action"
                       value="temporary_access_stop">
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($section === 'payouts') { ?>
<div class="modal fade"
     id="editPayoutModal"
     tabindex="-1"
     aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content paffp-modal paffp-tone-info payout-edit-modal">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-6">Edit Payout Event</h2>
                    <div class="small text-secondary mt-1">
                        Update the payout title and date.
                    </div>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <form method="post" id="editPayoutForm">
                <div class="modal-body">
                    <input type="hidden"
                           name="csrfToken"
                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden"
                           name="admin_action"
                           value="payout_edit">
                    <input type="hidden"
                           name="payout_uid"
                           id="editPayoutUid">

                    <div class="mb-3">
                        <label class="form-label" for="editPayoutTitle">
                            Payout Title
                        </label>
                        <input type="text"
                               class="form-control"
                               id="editPayoutTitle"
                               name="payout_title"
                               maxlength="160"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="editPayoutDate">
                            Payout Date
                        </label>
                        <input type="date"
                               class="form-control"
                               id="editPayoutDate"
                               name="payout_date"
                               required>
                    </div>

                    <div class="payout-edit-info">
                        <div class="payout-edit-info-row">
                            <span>Beneficiary file</span>
                            <strong id="editPayoutFile">—</strong>
                        </div>
                        <div class="payout-edit-info-row">
                            <span>Beneficiaries</span>
                            <strong id="editPayoutCount">—</strong>
                        </div>
                    </div>

                    <div class="payout-edit-note mt-3">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            The UID and beneficiary JSON filename stay unchanged.
                            Editing the title only changes the displayed payout title.
                        </span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="savePayoutEditButton">
                        <span class="edit-payout-save-label">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Save Changes
                        </span>
                        <span class="edit-payout-saving d-none">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Saving…
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($section === 'payouts') { ?>
<div class="modal fade"
     id="accomplishedAccessModal"
     tabindex="-1"
     aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content paffp-modal paffp-tone-info payout-access-modal">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-6">Accomplished Payout Access</h2>
                    <div class="small text-secondary mt-1"
                         id="accomplishedAccessSubtitle">
                        Choose who may continue verification.
                    </div>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <form method="post"
                  id="accomplishedAccessForm">
                <div class="modal-body">
                    <input type="hidden"
                           name="csrfToken"
                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden"
                           name="admin_action"
                           value="payout_accomplished_access">
                    <input type="hidden"
                           name="payout_uid"
                           id="accomplishedAccessUid">

                    <div class="payout-access-options">
                        <label class="payout-access-option"
                               for="accessModeClosed">
                            <input class="form-check-input"
                                   type="radio"
                                   name="access_mode"
                                   id="accessModeClosed"
                                   value="closed">
                            <div class="payout-access-option-icon is-closed">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div>
                                <div class="payout-access-option-title">Auto Closed</div>
                                <div class="payout-access-option-text">
                                    No verifier can submit changes after the payout date.
                                </div>
                            </div>
                        </label>

                        <label class="payout-access-option"
                               for="accessModeAll">
                            <input class="form-check-input"
                                   type="radio"
                                   name="access_mode"
                                   id="accessModeAll"
                                   value="all">
                            <div class="payout-access-option-icon is-all">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div>
                                <div class="payout-access-option-title">Open to All</div>
                                <div class="payout-access-option-text">
                                    Every verifier may open this accomplished payout.
                                    Choose View Only or Can Edit below.
                                </div>
                            </div>
                        </label>

                        <label class="payout-access-option"
                               for="accessModeUsers">
                            <input class="form-check-input"
                                   type="radio"
                                   name="access_mode"
                                   id="accessModeUsers"
                                   value="users">
                            <div class="payout-access-option-icon is-users">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div>
                                <div class="payout-access-option-title">Specific Verifiers</div>
                                <div class="payout-access-option-text">
                                    Only listed PAFFP verifier usernames may continue.
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="payout-all-permission d-none"
                         id="payoutAllPermission">
                        <label class="form-label mb-2">
                            Open to All Permission
                        </label>

                        <div class="row g-2">
                            <div class="col-12 col-sm-6">
                                <label class="border rounded-3 p-3 w-100 h-100"
                                       for="allPermissionView">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1"
                                               type="radio"
                                               name="all_permission"
                                               id="allPermissionView"
                                               value="view">
                                        <div>
                                            <div class="fw-semibold">View Only</div>
                                            <div class="small text-secondary mt-1">
                                                Everyone can open farmer records,
                                                but verification and photo/update
                                                actions are disabled.
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="border rounded-3 p-3 w-100 h-100"
                                       for="allPermissionEdit">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1"
                                               type="radio"
                                               name="all_permission"
                                               id="allPermissionEdit"
                                               value="edit"
                                               checked>
                                        <div>
                                            <div class="fw-semibold">Can Edit</div>
                                            <div class="small text-secondary mt-1">
                                                Everyone can continue normal
                                                verification, photo and update
                                                actions.
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="payout-specific-users d-none"
                         id="payoutSpecificUsers">
                        <input type="hidden"
                               name="access_users"
                               id="accomplishedAccessUsers">
                        <input type="hidden"
                               name="access_permissions_json"
                               id="accomplishedAccessPermissionsJson">

                        <label class="form-label"
                               for="accomplishedAccessNewUser">
                            Individual Verifier Access
                        </label>

                        <div class="input-group mb-2">
                            <input type="text"
                                   class="form-control"
                                   id="accomplishedAccessNewUser"
                                   maxlength="120"
                                   autocomplete="off"
                                   placeholder="e.g. Noble Ibis">
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="addAccomplishedAccessUser">
                                <i class="fa-solid fa-plus me-1"></i>
                                Add
                            </button>
                        </div>

                        <div class="form-text mb-3">
                            Add each verifier, then choose whether the user has
                            <strong>View Only</strong> or <strong>Can Edit</strong> access.
                            Username matching is case-insensitive.
                        </div>

                        <div class="individual-access-list"
                             id="accomplishedAccessUserList"></div>

                        <div class="payout-access-error d-none"
                             id="accomplishedAccessError">
                            Add at least one verifier username.
                        </div>

                        <div class="mt-3">
                            <div class="small fw-semibold mb-2">
                                Known verifier usernames
                            </div>

                            <div class="payout-user-chip-list">
                                <?php foreach ($verifierActivity as $knownVerifier) { ?>
                                    <?php $knownVerifierName = trim((string) ($knownVerifier['name'] ?? '')); ?>
                                    <?php if ($knownVerifierName !== '') { ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary accomplished-user-chip"
                                                data-verifier="<?= htmlspecialchars($knownVerifierName, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-plus me-1"></i>
                                            <?= htmlspecialchars($knownVerifierName, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="payout-access-note mt-3">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>
                            Individual permissions are enforced by Verify sync and
                            <strong>update-json.php</strong>. View-only users can open records
                            but cannot verify, unverify, flag, or save changes.
                        </span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-warning">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Save Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>


<?php if ($section === 'data_quality') { ?>
<div class="modal fade"
     id="crossPayoutCompareModal"
     tabindex="-1"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content paffp-modal paffp-tone-info">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <div class="paffp-modal-icon">
                        <i class="fa-solid fa-code-compare"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="small text-secondary">
                            Data Quality Comparison
                        </div>
                        <h2 class="modal-title fs-5 mb-0">
                            Cross-Payout Record Comparison
                        </h2>
                        <div class="small text-secondary mt-1">
                            <span id="crossCompareMatchLabel">Match</span>:
                            <strong id="crossCompareMatchValue">—</strong>
                        </div>
                    </div>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="cross-compare-modal-summary mb-3"
                     id="crossCompareSummary"></div>

                <div class="cross-compare-grid"
                     id="crossCompareGrid"></div>
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
<?php } ?>


<div class="modal fade"
     id="adminActionModal"
     tabindex="-1"
     aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content paffp-modal paffp-tone-info admin-action-modal">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <div class="admin-action-icon"
                         id="adminActionIcon">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>

                    <div class="min-w-0">
                        <h2 class="modal-title fs-6 mb-0"
                            id="adminActionTitle">
                            Confirm Action
                        </h2>

                        <div class="small text-secondary mt-1"
                             id="adminActionSubtitle">
                            Please review this action before continuing.
                        </div>
                    </div>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="admin-action-message"
                     id="adminActionMessage">
                    Are you sure you want to continue?
                </div>

                <div class="admin-action-details d-none"
                     id="adminActionDetails"></div>
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                        id="adminActionCancel">
                    Cancel
                </button>

                <button type="button"
                        class="btn btn-primary"
                        id="adminActionProceed">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<div class="access-request-toast-container toast-container"
     id="AccessRequestToastContainer"
     aria-live="polite"
     aria-atomic="true"></div>

<footer class="footer">
    Managed by PMED Planning Section
</footer>

<?php if ($section === 'json' && $selectedFile !== '') { ?>

    <div class="modal fade"
         id="saveConfirmModal"
         tabindex="-1"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paffp-modal paffp-tone-success">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-6">Confirm JSON Save</h2>
                        <div class="small text-secondary mt-1">
                            A backup will be created before overwriting the file.
                        </div>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="d-flex flex-column gap-2 small">
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-secondary">File</span>
                            <strong class="text-end">
                                <?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-secondary">Current records</span>
                            <strong id="confirmInitialRecords">—</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-secondary">New records</span>
                            <strong id="confirmNewRecords">—</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-secondary">Record difference</span>
                            <strong id="confirmRecordDifference">—</strong>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="button"
                            class="btn btn-success"
                            id="confirmSaveButton">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Save & Back Up
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade"
         id="restoreConfirmModal"
         tabindex="-1"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paffp-modal paffp-tone-danger">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-6">Restore Previous Version</h2>
                        <div class="small text-secondary mt-1">
                            The current file will be backed up before restore.
                        </div>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body small">
                    Restore:
                    <strong id="restoreBackupName"></strong>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="button"
                            class="btn btn-danger"
                            id="confirmRestoreButton">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>
                        Restore Version
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form method="post"
          id="restoreForm"
          class="d-none">

        <input type="hidden"
               name="csrfToken"
               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden"
               name="admin_action"
               value="json_restore">
        <input type="hidden"
               name="selectedFile"
               value="<?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden"
               name="backupFile"
               id="restoreBackupField"
               value="">
    </form>

<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($section === 'json') { ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/jump-to-line.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
<?php } ?>

<script>
(function () {
    const section = <?= json_encode($section) ?>;
    const selfFile = <?= json_encode($self) ?>;
    const selectedFile = <?= json_encode($selectedFile) ?>;
    const serverFlash = <?= json_encode($flash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const initialPendingAccessRequestIds = <?= json_encode(
        array_values($pendingAccessRequestIds),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;
    const ACCESS_REQUEST_POLL_MS = 8000;

    const crossPayoutCompareModalElement =
        document.getElementById("crossPayoutCompareModal");

    const crossPayoutCompareModal =
        crossPayoutCompareModalElement
            ? bootstrap.Modal.getOrCreateInstance(
                crossPayoutCompareModalElement
            )
            : null;

    const crossCompareMatchLabel =
        document.getElementById("crossCompareMatchLabel");

    const crossCompareMatchValue =
        document.getElementById("crossCompareMatchValue");

    const crossCompareSummary =
        document.getElementById("crossCompareSummary");

    const crossCompareGrid =
        document.getElementById("crossCompareGrid");

    function escapeCrossCompareHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function crossCompareDisplayValue(value) {
        const text = String(value ?? "").trim();

        return text !== ""
            ? escapeCrossCompareHtml(text)
            : '<span class="text-secondary">—</span>';
    }

    function crossCompareStatusMeta(rawStatus) {
        const status = String(rawStatus || "").trim();

        if (status === "Verified") {
            return {
                label: "Verified",
                className: "is-verified",
                icon: "fa-solid fa-circle-check"
            };
        }

        if (status === "Verified w/ Corrections") {
            return {
                label: "Verified w/ Corrections",
                className: "is-corrections",
                icon: "fa-solid fa-pen-to-square"
            };
        }

        if (status === "Caution") {
            return {
                label: "Caution",
                className: "is-caution",
                icon: "fa-solid fa-flag"
            };
        }

        if (status !== "") {
            return {
                label: status,
                className: "is-pending",
                icon: "fa-solid fa-circle-question"
            };
        }

        return {
            label: "Not Verified",
            className: "is-pending",
            icon: "fa-regular fa-circle"
        };
    }

    function crossCompareDetail(label, value) {
        return `
            <div class="cross-compare-detail">
                <div class="cross-compare-detail-label">
                    ${escapeCrossCompareHtml(label)}
                </div>
                <div class="cross-compare-detail-value">
                    ${crossCompareDisplayValue(value)}
                </div>
            </div>
        `;
    }

    function renderCrossCompareCard(record, index) {
        const status =
            crossCompareStatusMeta(
                record.verify_status
            );

        const verificationActorLabel =
            String(record.verify_status || "") === "Caution"
                ? "Flagged by"
                : "Verified by";

        const payoutTitle =
            String(
                record.title
                || record.file
                || "Payout"
            );

        const name =
            String(record.name || "").trim();

        return `
            <article class="cross-compare-card">
                <div class="cross-compare-card-header">
                    <div class="min-w-0">
                        <div class="cross-compare-record-number">
                            Record ${index + 1}
                        </div>

                        <div class="cross-compare-payout-title">
                            ${escapeCrossCompareHtml(payoutTitle)}
                        </div>

                        <div class="cross-compare-file">
                            ${escapeCrossCompareHtml(
                                String(record.file || "")
                            )}
                        </div>
                    </div>

                    <span class="cross-compare-status ${status.className}">
                        <i class="${status.icon}"></i>
                        ${escapeCrossCompareHtml(status.label)}
                    </span>
                </div>

                <div class="cross-compare-card-body">
                    <div class="cross-compare-name">
                        <div class="cross-compare-detail-label">
                            Beneficiary
                        </div>
                        <div class="cross-compare-name-value">
                            ${
                                name !== ""
                                    ? escapeCrossCompareHtml(name)
                                    : '<span class="text-secondary">—</span>'
                            }
                        </div>
                    </div>

                    <div class="cross-compare-details">
                        ${crossCompareDetail("SysGen", record.sysgen)}
                        ${crossCompareDetail("Payroll", record.payroll)}
                        ${crossCompareDetail("Commodity", record.commodity)}
                        ${crossCompareDetail("Verifier No.", record.verifier_no)}
                        ${crossCompareDetail("Teller No.", record.teller_no)}
                        ${crossCompareDetail("Payout UID", record.uid)}
                        ${crossCompareDetail(
                            verificationActorLabel,
                            record.verify_encoder
                        )}
                        ${crossCompareDetail(
                            "Verification Time",
                            record.verify_timestamp
                        )}
                        ${crossCompareDetail(
                            "JSON Row",
                            record.row
                        )}
                    </div>
                </div>
            </article>
        `;
    }

    function openCrossPayoutComparison(payload) {
        if (
            !crossPayoutCompareModal
            || !crossCompareGrid
            || !crossCompareSummary
            || !crossCompareMatchLabel
            || !crossCompareMatchValue
        ) {
            return;
        }

        const data =
            payload && typeof payload === "object"
                ? payload
                : {};

        const records =
            Array.isArray(data.occurrences)
                ? data.occurrences
                : [];

        const matchLabel =
            String(
                data.match_label
                || (data.sysgen ? "SysGen" : "Match")
            ).trim();

        const matchValue =
            String(
                data.match_value
                || data.sysgen
                || data.name
                || ""
            ).trim();

        crossCompareMatchLabel.textContent =
            matchLabel !== ""
                ? matchLabel
                : "Match";

        crossCompareMatchValue.textContent =
            matchValue !== ""
                ? matchValue
                : "—";

        crossCompareSummary.innerHTML = `
            <span class="badge text-bg-primary px-2 py-1">
                <i class="fa-solid fa-layer-group me-1"></i>
                ${records.length}
                record${records.length === 1 ? "" : "s"}
            </span>

            <span class="badge text-bg-secondary px-2 py-1">
                <i class="fa-solid fa-scale-balanced me-1"></i>
                Side-by-side comparison
            </span>
        `;

        if (!records.length) {
            crossCompareGrid.innerHTML = `
                <div class="quality-section-empty">
                    <div class="quality-section-empty-icon">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">
                            No comparison records available
                        </div>
                    </div>
                </div>
            `;
        } else {
            crossCompareGrid.innerHTML =
                records
                    .map(renderCrossCompareCard)
                    .join("");
        }

        crossPayoutCompareModal.show();
    }

    document
        .querySelectorAll(".cross-payout-compare-btn")
        .forEach(function (button) {
            button.addEventListener(
                "click",
                function () {
                    try {
                        const payload =
                            JSON.parse(
                                button.getAttribute(
                                    "data-cross-payout-compare"
                                ) || "{}"
                            );

                        openCrossPayoutComparison(payload);
                    } catch (error) {
                        if (
                            typeof window.showPaffpNotice
                            === "function"
                        ) {
                            window.showPaffpNotice({
                                tone: "danger",
                                title: "Comparison Unavailable",
                                message:
                                    "Unable to load the selected comparison records."
                            });
                        }
                    }
                }
            );
        });

    const adminActionModalElement =
        document.getElementById("adminActionModal");

    const adminActionModal = adminActionModalElement
        ? bootstrap.Modal.getOrCreateInstance(adminActionModalElement)
        : null;

    const adminActionContent =
        adminActionModalElement
            ? adminActionModalElement.querySelector(".paffp-modal")
            : null;

    const adminActionIcon =
        document.getElementById("adminActionIcon");

    const adminActionTitle =
        document.getElementById("adminActionTitle");

    const adminActionSubtitle =
        document.getElementById("adminActionSubtitle");

    const adminActionMessage =
        document.getElementById("adminActionMessage");

    const adminActionDetails =
        document.getElementById("adminActionDetails");

    const adminActionCancel =
        document.getElementById("adminActionCancel");

    const adminActionProceed =
        document.getElementById("adminActionProceed");

    let adminActionConfirmed = false;
    let adminActionOnConfirm = null;
    let adminActionOnCancel = null;

    function configureAdminActionTone(tone) {
        if (adminActionContent) {
            adminActionContent.classList.remove(
                "paffp-tone-success",
                "paffp-tone-warning",
                "paffp-tone-danger",
                "paffp-tone-info"
            );

            adminActionContent.classList.add(
                "paffp-tone-" +
                (
                    ["danger", "warning", "success", "info"].includes(tone)
                        ? tone
                        : "info"
                )
            );
        }

        adminActionIcon.className = "admin-action-icon";
        adminActionProceed.className = "btn btn-primary";
        adminActionIcon.innerHTML =
            '<i class="fa-solid fa-circle-question"></i>';

        if (tone === "danger") {
            adminActionIcon.classList.add("is-danger");
            adminActionIcon.innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i>';
            adminActionProceed.className = "btn btn-danger";
        } else if (tone === "warning") {
            adminActionIcon.classList.add("is-warning");
            adminActionIcon.innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i>';
            adminActionProceed.className = "btn btn-warning";
        } else if (tone === "success") {
            adminActionIcon.classList.add("is-success");
            adminActionIcon.innerHTML =
                '<i class="fa-solid fa-circle-check"></i>';
            adminActionProceed.className = "btn btn-success";
        } else if (tone === "info") {
            adminActionIcon.classList.add("is-info");
            adminActionIcon.innerHTML =
                '<i class="fa-solid fa-circle-info"></i>';
            adminActionProceed.className = "btn btn-primary";
        }
    }

    function showAdminConfirm(options = {}) {
        if (!adminActionModal) {
            if (typeof options.onConfirm === "function") {
                options.onConfirm();
            }
            return;
        }

        adminActionModalElement
            .querySelector(".modal-content")
            ?.classList.remove("is-notice");

        adminActionCancel.classList.remove("d-none");

        configureAdminActionTone(
            options.tone || "warning"
        );

        adminActionTitle.textContent =
            options.title || "Confirm Action";

        adminActionSubtitle.textContent =
            options.subtitle ||
            "Please review this action before continuing.";

        adminActionMessage.textContent =
            options.message ||
            "Are you sure you want to continue?";

        if (options.details) {
            adminActionDetails.textContent =
                options.details;
            adminActionDetails.classList.remove("d-none");
        } else {
            adminActionDetails.textContent = "";
            adminActionDetails.classList.add("d-none");
        }

        adminActionProceed.textContent =
            options.confirmText || "Continue";

        adminActionConfirmed = false;

        adminActionOnConfirm =
            typeof options.onConfirm === "function"
                ? options.onConfirm
                : null;

        adminActionOnCancel =
            typeof options.onCancel === "function"
                ? options.onCancel
                : null;

        adminActionModal.show();
    }

    function showAdminNotice(options = {}) {
        if (!adminActionModal) {
            return;
        }

        adminActionModalElement
            .querySelector(".modal-content")
            ?.classList.add("is-notice");

        adminActionCancel.classList.add("d-none");

        configureAdminActionTone(
            options.tone || "info"
        );

        adminActionTitle.textContent =
            options.title || "Notice";

        adminActionSubtitle.textContent =
            options.subtitle || "";

        adminActionMessage.textContent =
            options.message || "";

        if (options.details) {
            adminActionDetails.textContent =
                options.details;
            adminActionDetails.classList.remove("d-none");
        } else {
            adminActionDetails.textContent = "";
            adminActionDetails.classList.add("d-none");
        }

        adminActionProceed.textContent =
            options.buttonText || "OK";

        adminActionConfirmed = true;
        adminActionOnConfirm = null;
        adminActionOnCancel = null;

        adminActionModal.show();
    }

    adminActionProceed?.addEventListener(
        "click",
        function () {
            const action = adminActionOnConfirm;

            adminActionConfirmed = true;
            adminActionOnConfirm = null;
            adminActionOnCancel = null;

            adminActionModal.hide();

            if (typeof action === "function") {
                window.setTimeout(action, 180);
            }
        }
    );

    adminActionModalElement?.addEventListener(
        "hidden.bs.modal",
        function () {
            const cancelAction =
                !adminActionConfirmed
                    ? adminActionOnCancel
                    : null;

            adminActionConfirmed = false;
            adminActionOnConfirm = null;
            adminActionOnCancel = null;

            adminActionCancel?.classList.remove("d-none");

            adminActionModalElement
                .querySelector(".modal-content")
                ?.classList.remove("is-notice");

            if (typeof cancelAction === "function") {
                cancelAction();
            }
        }
    );


    /*
     * Global Verifier Chat moderation.
     *
     * Destructive actions use the shared Admin confirmation modal rather than
     * browser-native confirmation dialogs.
     */
    document
        .querySelectorAll(".chat-message-delete-form")
        .forEach(function (form) {
            form.addEventListener(
                "submit",
                function (event) {
                    event.preventDefault();

                    const username =
                        form.getAttribute("data-chat-user")
                        || "Unknown verifier";

                    const preview =
                        form.getAttribute("data-chat-preview")
                        || "";

                    showAdminConfirm({
                        tone: "danger",
                        title: "Delete Chat Message",
                        subtitle: "Verifier Chat moderation",
                        message:
                            "Delete this message from the global verifier chat?",
                        details:
                            username
                            + (preview ? " — " + preview : ""),
                        confirmText: "Delete Message",
                        onConfirm: function () {
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    });
                }
            );
        });

    const chatClearAllForm =
        document.getElementById("chatClearAllForm");

    chatClearAllForm?.addEventListener(
        "submit",
        function (event) {
            event.preventDefault();

            showAdminConfirm({
                tone: "danger",
                title: "Clear Verifier Chat",
                subtitle: "This action affects all payout pages",
                message:
                    "Delete every message in the global verifier chat?",
                details:
                    "This cannot be undone from the Admin interface.",
                confirmText: "Clear All Messages",
                onConfirm: function () {
                    HTMLFormElement.prototype.submit.call(
                        chatClearAllForm
                    );
                }
            });
        }
    );


    /*
     * Live Access Request notifications.
     *
     * Existing pending requests are treated as the baseline when Admin opens.
     * Only newly-created request IDs trigger a toast/sound during this session.
     */
    const accessRequestSeenIds =
        new Set(
            Array.isArray(initialPendingAccessRequestIds)
                ? initialPendingAccessRequestIds.map(String)
                : []
        );

    const accessRequestToastContainer =
        document.getElementById("AccessRequestToastContainer");

    const accessRequestNavCount =
        document.getElementById("AccessRequestNavCount");

    const accessRequestPendingBadge =
        document.getElementById("AccessRequestPendingBadge");

    const accessRequestActiveBadge =
        document.getElementById("AccessRequestActiveBadge");

    const accessRequestSoundToggle =
        document.getElementById("toggleAccessRequestSound");

    function accessRequestSoundEnabled() {
        return localStorage.getItem(
            "paffpAdminAccessRequestSound"
        ) === "true";
    }

    function updateAccessRequestSoundButton() {
        if (!accessRequestSoundToggle) {
            return;
        }

        const enabled = accessRequestSoundEnabled();

        accessRequestSoundToggle
            .querySelector("i")
            .className =
                enabled
                    ? "fa-solid fa-bell me-1"
                    : "fa-solid fa-bell-slash me-1";

        accessRequestSoundToggle
            .querySelector("span")
            .textContent =
                enabled
                    ? "Sound On"
                    : "Sound Off";

        accessRequestSoundToggle.classList.toggle(
            "btn-outline-success",
            enabled
        );

        accessRequestSoundToggle.classList.toggle(
            "btn-outline-secondary",
            !enabled
        );
    }

    function playAccessRequestTone() {
        if (!accessRequestSoundEnabled()) {
            return;
        }

        try {
            const AudioContextClass =
                window.AudioContext
                || window.webkitAudioContext;

            if (!AudioContextClass) {
                return;
            }

            const context = new AudioContextClass();
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.type = "sine";
            oscillator.frequency.value = 760;

            gain.gain.setValueAtTime(
                0.0001,
                context.currentTime
            );
            gain.gain.exponentialRampToValueAtTime(
                0.12,
                context.currentTime + 0.02
            );
            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                context.currentTime + 0.18
            );

            oscillator.connect(gain);
            gain.connect(context.destination);

            oscillator.start();
            oscillator.stop(
                context.currentTime + 0.2
            );

            oscillator.addEventListener(
                "ended",
                function () {
                    context.close().catch(function () {});
                }
            );
        } catch (error) {
            // Notification sound is optional.
        }
    }

    function showAccessRequestToast(request, additionalCount = 0) {
        if (!accessRequestToastContainer) {
            return;
        }

        const username =
            String(request.username || "Verifier");

        const payout =
            String(
                request.payout_title
                || request.payout_uid
                || "Restricted payout"
            );

        const reason =
            String(request.reason || "").trim();

        const toast = document.createElement("div");
        toast.className =
            "toast access-request-toast";
        toast.setAttribute("role", "alert");
        toast.setAttribute("aria-live", "assertive");
        toast.setAttribute("aria-atomic", "true");

        const extraText =
            additionalCount > 0
                ? " +" + additionalCount + " more"
                : "";

        const safeText = function (value) {
            const div = document.createElement("div");
            div.textContent = String(value || "");
            return div.innerHTML;
        };

        toast.innerHTML = `
            <div class="toast-header">
                <i class="fa-solid fa-key me-2 text-success"></i>
                <strong class="me-auto">New Access Request${safeText(extraText)}</strong>
                <small>Now</small>
                <button type="button"
                        class="btn-close ms-2 mb-1"
                        data-bs-dismiss="toast"
                        aria-label="Close"></button>
            </div>

            <div class="toast-body">
                <div class="fw-semibold">${safeText(username)}</div>
                <div class="small text-secondary mt-1">
                    ${safeText(payout)}
                </div>
                ${
                    reason
                        ? `
                            <div class="small mt-2">
                                <strong>Reason:</strong>
                                ${safeText(reason)}
                            </div>`
                        : ""
                }

                <div class="mt-3">
                    <a class="btn btn-success btn-sm"
                       href="${safeText(selfFile)}?section=access_requests">
                        Review Request
                    </a>
                </div>
            </div>
        `;

        accessRequestToastContainer.appendChild(toast);

        const instance =
            bootstrap.Toast.getOrCreateInstance(
                toast,
                {
                    autohide: true,
                    delay: 9000
                }
            );

        toast.addEventListener(
            "hidden.bs.toast",
            function () {
                toast.remove();
            }
        );

        instance.show();
        playAccessRequestTone();
    }

    async function pollAccessRequests() {
        try {
            const response = await fetch(
                selfFile +
                "?api=access_request_status&_=" +
                Date.now(),
                {
                    method: "GET",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data || data.success !== true) {
                return;
            }

            const pending =
                Array.isArray(data.pending)
                    ? data.pending
                    : [];

            if (accessRequestNavCount) {
                accessRequestNavCount.textContent =
                    String(data.pending_count || 0);
            }

            if (accessRequestPendingBadge) {
                accessRequestPendingBadge.textContent =
                    String(data.pending_count || 0) +
                    " Pending";
            }

            if (accessRequestActiveBadge) {
                accessRequestActiveBadge.textContent =
                    String(data.active_count || 0) +
                    " Active";
            }

            const unseen = pending.filter(function (item) {
                const requestId =
                    String(item.id || "");

                return requestId !== ""
                    && !accessRequestSeenIds.has(requestId);
            });

            if (unseen.length > 0) {
                unseen.forEach(function (item) {
                    accessRequestSeenIds.add(
                        String(item.id || "")
                    );
                });

                showAccessRequestToast(
                    unseen[0],
                    Math.max(0, unseen.length - 1)
                );
            }
        } catch (error) {
            // Polling is intentionally non-blocking.
        }
    }

    if (accessRequestSoundToggle) {
        updateAccessRequestSoundButton();

        accessRequestSoundToggle.addEventListener(
            "click",
            function () {
                localStorage.setItem(
                    "paffpAdminAccessRequestSound",
                    accessRequestSoundEnabled()
                        ? "false"
                        : "true"
                );

                updateAccessRequestSoundButton();

                if (accessRequestSoundEnabled()) {
                    playAccessRequestTone();
                }
            }
        );
    }

    window.setInterval(
        pollAccessRequests,
        ACCESS_REQUEST_POLL_MS
    );

    window.setTimeout(
        pollAccessRequests,
        1500
    );

    document
        .querySelectorAll(".access-expiry-choice")
        .forEach(function (select) {
            const form =
                select.closest(
                    ".access-request-approve-form"
                );

            const custom =
                form
                    ? form.querySelector(
                        '[name="custom_expires_at"]'
                    )
                    : null;

            const updateCustomVisibility =
                function () {
                    if (!custom) {
                        return;
                    }

                    const isCustom =
                        select.value === "custom";

                    custom.classList.toggle(
                        "d-none",
                        !isCustom
                    );

                    custom.required = isCustom;
                };

            updateCustomVisibility();

            select.addEventListener(
                "change",
                updateCustomVisibility
            );
        });

    /*
     * Access Request actions never use browser-native confirmation dialogs.
     * All actions flow through the shared Admin modal and only submit after
     * explicit confirmation.
     */
    document
        .querySelectorAll(".access-request-action-form")
        .forEach(function (form) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();

                const action =
                    String(form.dataset.accessAction || "approve");

                const username =
                    String(form.dataset.accessUser || "Verifier");

                const payout =
                    String(form.dataset.accessPayout || "Payout");

                let tone = "success";
                let title = "Approve Access";
                let message =
                    "Grant access to " + username + "?";
                let details = payout;
                let confirmText = "Approve";

                if (action === "update") {
                    title = "Update Access";
                    message =
                        "Apply the new access settings for " +
                        username +
                        "?";
                    confirmText = "Update Access";
                } else if (action === "reject") {
                    tone = "danger";
                    title = "Reject Access Request";
                    message =
                        "Reject the access request from " +
                        username +
                        "?";
                    confirmText = "Reject Request";
                } else if (action === "revoke") {
                    tone = "danger";
                    title = "Revoke Access";
                    message =
                        "Revoke the currently approved access for " +
                        username +
                        "?";
                    confirmText = "Revoke Access";
                }

                if (action === "approve" || action === "update") {
                    const permission =
                        form.querySelector('[name="permission"]');

                    const expiryChoice =
                        form.querySelector('[name="expiry_choice"]');

                    const customExpiry =
                        form.querySelector('[name="custom_expires_at"]');

                    const permissionLabel =
                        permission?.selectedOptions?.[0]?.textContent?.trim()
                        || "View Only";

                    let expiryLabel =
                        expiryChoice?.selectedOptions?.[0]?.textContent?.trim()
                        || "1 hour";

                    if (
                        expiryChoice
                        && expiryChoice.value === "custom"
                        && customExpiry
                        && customExpiry.value
                    ) {
                        const parsed =
                            new Date(customExpiry.value);

                        if (!Number.isNaN(parsed.getTime())) {
                            expiryLabel =
                                "Until " +
                                parsed.toLocaleString();
                        }
                    }

                    details =
                        payout +
                        " • " +
                        permissionLabel +
                        " • " +
                        expiryLabel;
                }

                showAdminConfirm({
                    tone: tone,
                    title: title,
                    subtitle: "Verify Access Request",
                    message: message,
                    details: details,
                    confirmText: confirmText,
                    onConfirm: function () {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
        });

    const initialServerRecordCount = <?= $recordCount === null ? 'null' : (int) $recordCount ?>;

    const toggleTheme = document.getElementById("toggleTheme");

    function isDarkMode() {
        return localStorage.getItem("darkMode") === "true";
    }

    function applyTheme() {
        const dark = isDarkMode();

        document.documentElement.setAttribute(
            "data-bs-theme",
            dark ? "dark" : "light"
        );

        if (toggleTheme) {
            toggleTheme.querySelector("i").className =
                dark ? "fas fa-sun" : "fas fa-moon";

            toggleTheme.title =
                dark ? "Switch to light mode" : "Switch to dark mode";
        }

        if (window.adminCodeMirror) {
            window.adminCodeMirror.setOption(
                "theme",
                dark ? "material-darker" : "default"
            );
        }
    }

    if (toggleTheme) {
        toggleTheme.addEventListener("click", function () {
            localStorage.setItem(
                "darkMode",
                isDarkMode() ? "false" : "true"
            );

            applyTheme();
        });
    }

    applyTheme();

    if (
        serverFlash &&
        typeof serverFlash.message === "string" &&
        serverFlash.message.trim() !== ""
    ) {
        const flashTone =
            serverFlash.type === "danger"
                ? "danger"
                : serverFlash.type === "warning"
                    ? "warning"
                    : serverFlash.type === "success"
                        ? "success"
                        : "info";

        window.setTimeout(function () {
            showAdminNotice({
                tone: flashTone,
                title:
                    flashTone === "success"
                        ? "Completed"
                        : flashTone === "danger"
                            ? "Unable to Complete"
                            : flashTone === "warning"
                                ? "Attention"
                                : "Notice",
                subtitle: "PAFFP Admin",
                message: serverFlash.message,
                buttonText: "Close"
            });
        }, 180);
    }


    if (section === "ads") {
        document
            .querySelectorAll(".ad-page-master-switch")
            .forEach(function (master) {
                const update = function () {
                    const group = document.querySelector(
                        '[data-ad-page-group="' +
                        String(master.dataset.adPage || "") +
                        '"]'
                    );

                    if (group) {
                        group.classList.toggle(
                            "is-page-disabled",
                            !master.checked
                        );
                    }
                };

                update();
                master.addEventListener("change", update);
            });
    }

    if (section === "payouts") {
        const form = document.getElementById("payoutCreateForm");
        const sourceMode = document.getElementById("payoutSourceMode");
        const sourceFileButton = document.getElementById("payoutSourceFileButton");
        const sourceTextButton = document.getElementById("payoutSourceTextButton");
        const sourceGoogleButton = document.getElementById("payoutSourceGoogleButton");
        const filePanel = document.getElementById("payoutFilePanel");
        const textPanel = document.getElementById("payoutTextPanel");
        const googlePanel = document.getElementById("payoutGooglePanel");
        const googleUrl = document.getElementById("payoutGoogleUrl");
        const googleGid = document.getElementById("payoutGoogleGid");
        const googlePreviewButton = document.getElementById("payoutGooglePreviewButton");
        const googlePreview = document.getElementById("payoutGooglePreview");
        const googlePreviewCount = document.getElementById("payoutGooglePreviewCount");
        const googlePreviewTab = document.getElementById("payoutGooglePreviewTab");
        const googlePreviewColumns = document.getElementById("payoutGooglePreviewColumns");
        const googleSampleHead = document.getElementById("payoutGoogleSampleHead");
        const googleSampleBody = document.getElementById("payoutGoogleSampleBody");
        const csrf = form.querySelector('[name="csrfToken"]').value;
        let googlePreviewSignature = "";
        let googlePreviewLoading = false;
        const fileInput = document.getElementById("payoutJsonFile");
        const fileName = document.getElementById("payoutFileName");
        const jsonText = document.getElementById("payoutJsonText");
        const status = document.getElementById("payoutDataStatus");
        const statusTitle = document.getElementById("payoutDataStatusTitle");
        const statusText = document.getElementById("payoutDataStatusText");
        const createButton = document.getElementById("payoutCreateButton");
        const search = document.getElementById("payoutAdminSearch");
        const noResults = document.getElementById("payoutAdminNoResults");
        const payoutItems = Array.from(
            document.querySelectorAll(".payout-event-item")
        );

        function setPayoutDataStatus(state, title, message) {
            status.classList.remove("is-valid", "is-invalid");

            const icon = status.querySelector(".payout-data-status-icon i");

            if (state === "valid") {
                status.classList.add("is-valid");
                icon.className = "fa-solid fa-circle-check";
            } else if (state === "invalid") {
                status.classList.add("is-invalid");
                icon.className = "fa-solid fa-circle-exclamation";
            } else {
                icon.className = "fa-solid fa-circle-info";
            }

            statusTitle.textContent = title;
            statusText.textContent = message;
        }

        function isJsonArray(raw) {
            const parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                throw new Error("JSON_ARRAY_REQUIRED");
            }

            return parsed;
        }

        function currentGoogleSignature() {
            return googleUrl.value.trim() + "|" + googleGid.value.trim();
        }

        function setSourceMode(mode) {
            const isFile = mode === "file";
            const isText = mode === "text";
            const isGoogle = mode === "google";

            sourceMode.value = isGoogle ? "google" : isText ? "text" : "file";
            filePanel.classList.toggle("d-none", !isFile);
            textPanel.classList.toggle("d-none", !isText);
            googlePanel.classList.toggle("d-none", !isGoogle);

            [[sourceFileButton, isFile], [sourceTextButton, isText],
             [sourceGoogleButton, isGoogle]].forEach(([button, active]) => {
                button.classList.toggle("active", active);
                button.setAttribute("aria-pressed", String(active));
            });

            if (isFile) validateFile();
            if (isText) validateText();
            if (isGoogle) {
                if (googlePreviewSignature && googlePreviewSignature === currentGoogleSignature()) {
                    setPayoutDataStatus("valid", "Google Sheet ready", "Worksheet preview validated. Create the payout event when ready.");
                } else {
                    setPayoutDataStatus("idle", "Google Sheet not checked", "Paste a Google Sheets link, then click Fetch & Preview Sheet.");
                }
            }
        }

        function invalidateGooglePreview() {
            googlePreviewSignature = "";
            googlePreview.classList.add("d-none");
            if (sourceMode.value === "google") {
                setPayoutDataStatus(
                    "idle", "Preview required", "Click Fetch & Preview Sheet to validate this worksheet."
                );
            }
        }

        async function validateGoogle(forceFetch) {
            if (sourceMode.value !== "google") return false;
            if (!googleUrl.value.trim()) {
                setPayoutDataStatus("invalid", "Missing Sheet link", "Paste your Google Sheets link to continue.");
                return false;
            }
            if (!forceFetch) {
                const good = googlePreviewSignature !== "" && googlePreviewSignature === currentGoogleSignature();
                if (!good) {
                    setPayoutDataStatus("invalid", "Preview required", "Fetch & Preview the Sheet before creating this payout.");
                }
                return good;
            }
            if (googlePreviewLoading) return false;
            googlePreviewLoading = true;
            const requestSignature = currentGoogleSignature();
            googlePreviewButton.disabled = true;
            const oldButtonContent = googlePreviewButton.innerHTML;
            googlePreviewButton.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-1"></i> Retrieving Sheet…';
            setPayoutDataStatus("idle", "Fetching worksheet", "Connecting to Google Sheets and validating records…");
            googlePreview.classList.add("d-none");
            googlePreviewSignature = "";
            try {
                const payload = new FormData();
                payload.append("admin_action", "payout_google_preview");
                payload.append("csrfToken", csrf);
                payload.append("payout_google_url", googleUrl.value.trim());
                payload.append("payout_google_gid", googleGid.value.trim());
                const response = await fetch(window.location.pathname + '?section=payouts', {
                    method: "POST",
                    body: payload,
                    cache: "no-store",
                    credentials: "same-origin"
                });
                const answer = await response.json();
                if (!response.ok || !answer.success) {
                    throw new Error(answer.message || 'Could not retrieve Google Sheet.');
                }
                // Do not accept a stale preview if the link/GID changed mid-request.
                if (currentGoogleSignature() !== requestSignature) {
                    throw new Error('Sheet link changed during preview. Fetch the worksheet again.');
                }
                googlePreviewCount.textContent = Number(answer.count || 0).toLocaleString();
                googlePreviewTab.textContent = answer.worksheet
                    ? answer.worksheet + " · GID " + answer.gid
                    : "GID " + answer.gid;
                googlePreviewColumns.replaceChildren();
                (answer.headers || []).slice(0, 16).forEach(header => {
                    const chip = document.createElement("span");
                    chip.textContent = header;
                    googlePreviewColumns.appendChild(chip);
                });
                if ((answer.headers || []).length > 16) {
                    const chip = document.createElement("span");
                    chip.textContent = "+" + (answer.headers.length - 16) + " columns";
                    googlePreviewColumns.appendChild(chip);
                }
                googleSampleHead.replaceChildren();
                googleSampleBody.replaceChildren();
                const cols = answer.sample_columns || [];
                const tr = document.createElement("tr");
                cols.forEach(column => {
                    const th = document.createElement("th");
                    th.textContent = column;
                    tr.appendChild(th);
                });
                googleSampleHead.appendChild(tr);
                (answer.sample_rows || []).forEach(row => {
                    const tr = document.createElement("tr");
                    cols.forEach(column => {
                        const td = document.createElement("td");
                        td.textContent = row[column] || '';
                        td.title = row[column] || '';
                        tr.appendChild(td);
                    });
                    googleSampleBody.appendChild(tr);
                });
                googlePreview.classList.remove("d-none");
                googlePreviewSignature = currentGoogleSignature();
                setPayoutDataStatus("valid", "Google Sheet validated",
                    Number(answer.count || 0).toLocaleString() + " beneficiaries · "
                    + Number((answer.headers || []).length) + " columns · " + answer.source + ".");
                return true;
            } catch (error) {
                console.error("Google Sheets preview:", error);
                setPayoutDataStatus("invalid", "Sheet import failed", error.message || "Unable to retrieve Sheet.");
                googlePreviewSignature = "";
                return false;
            } finally {
                googlePreviewLoading = false;
                googlePreviewButton.disabled = false;
                googlePreviewButton.innerHTML = oldButtonContent;
            }
        }

        async function validateFile() {
            if (sourceMode.value !== "file") {
                return false;
            }

            const file =
                fileInput.files && fileInput.files.length
                    ? fileInput.files[0]
                    : null;

            fileName.textContent =
                file ? file.name : "No file selected";

            if (!file) {
                setPayoutDataStatus(
                    "idle",
                    "Waiting for beneficiary data",
                    "Choose a beneficiary JSON file."
                );
                return false;
            }

            if (!file.name.toLowerCase().endsWith(".json")) {
                setPayoutDataStatus(
                    "invalid",
                    "Unsupported file",
                    "Only .json files are accepted."
                );
                return false;
            }

            try {
                const parsed = isJsonArray(await file.text());

                setPayoutDataStatus(
                    "valid",
                    "Beneficiary data ready",
                    parsed.length.toLocaleString()
                        + " beneficiar"
                        + (parsed.length === 1 ? "y" : "ies")
                        + " detected in "
                        + file.name
                        + "."
                );

                return true;
            } catch (error) {
                setPayoutDataStatus(
                    "invalid",
                    error.message === "JSON_ARRAY_REQUIRED"
                        ? "JSON array required"
                        : "Invalid JSON",
                    error.message === "JSON_ARRAY_REQUIRED"
                        ? "The top-level JSON value must be an array."
                        : "The selected file contains invalid JSON."
                );

                return false;
            }
        }

        function validateText() {
            if (sourceMode.value !== "text") {
                return false;
            }

            const raw = jsonText.value.trim();

            if (!raw) {
                setPayoutDataStatus(
                    "idle",
                    "Waiting for beneficiary data",
                    "Paste the complete beneficiary JSON array."
                );
                return false;
            }

            try {
                const parsed = isJsonArray(raw);

                setPayoutDataStatus(
                    "valid",
                    "Beneficiary data ready",
                    parsed.length.toLocaleString()
                        + " beneficiar"
                        + (parsed.length === 1 ? "y" : "ies")
                        + " detected in pasted JSON."
                );

                return true;
            } catch (error) {
                setPayoutDataStatus(
                    "invalid",
                    error.message === "JSON_ARRAY_REQUIRED"
                        ? "JSON array required"
                        : "Invalid JSON",
                    error.message === "JSON_ARRAY_REQUIRED"
                        ? "The top-level JSON value must be an array."
                        : "The pasted data contains a JSON syntax error."
                );

                return false;
            }
        }

        function debounce(fn, delay) {
            let timer;

            return function (...args) {
                window.clearTimeout(timer);
                timer = window.setTimeout(
                    () => fn.apply(this, args),
                    delay
                );
            };
        }

        sourceFileButton.addEventListener("click", () => setSourceMode("file"));
        sourceTextButton.addEventListener("click", () => setSourceMode("text"));
        sourceGoogleButton.addEventListener("click", () => setSourceMode("google"));
        fileInput.addEventListener("change", validateFile);
        jsonText.addEventListener("input", debounce(validateText, 220));
        googleUrl.addEventListener("input", invalidateGooglePreview);
        googleGid.addEventListener("input", invalidateGooglePreview);
        googlePreviewButton.addEventListener("click", () => validateGoogle(true));

        let payoutSubmitting = false;
        form.addEventListener("submit", async function (event) {
            // Important: prevent default BEFORE awaiting file reads. This also
            // avoids double submissions on slow desktop/mobile browsers.
            event.preventDefault();
            if (payoutSubmitting || googlePreviewLoading) return;

            const title = document.getElementById("payoutTitle");
            const date = document.getElementById("payoutDate");
            if (!title.value.trim()) { title.focus(); return; }
            if (!date.value) { date.focus(); return; }

            let valid = false;
            if (sourceMode.value === "file") {
                valid = await validateFile();
            } else if (sourceMode.value === "text") {
                valid = validateText();
            } else if (sourceMode.value === "google") {
                valid = await validateGoogle(false);
            }
            if (!valid) {
                if (sourceMode.value === "text") jsonText.focus();
                else if (sourceMode.value === "google") googlePreviewButton.focus();
                else fileInput.focus();
                return;
            }

            payoutSubmitting = true;
            createButton.disabled = true;
            createButton.querySelector(".payout-create-label").classList.add("d-none");
            createButton.querySelector(".payout-create-loading").classList.remove("d-none");
            HTMLFormElement.prototype.submit.call(form);
        });

        if (search) {
            search.addEventListener("input", function () {
                const query =
                    search.value.trim().toLowerCase();

                let visible = 0;

                payoutItems.forEach(item => {
                    const haystack =
                        String(
                            item.dataset.payoutSearch || ""
                        ).toLowerCase();

                    const show =
                        !query || haystack.includes(query);

                    item.classList.toggle("d-none", !show);

                    if (show) {
                        visible++;
                    }
                });

                if (noResults) {
                    noResults.classList.toggle(
                        "d-none",
                        visible !== 0 || payoutItems.length === 0
                    );
                }
            });
        }

        const editPayoutModalElement =
            document.getElementById("editPayoutModal");

        const editPayoutModal = editPayoutModalElement
            ? bootstrap.Modal.getOrCreateInstance(editPayoutModalElement)
            : null;

        const editPayoutForm =
            document.getElementById("editPayoutForm");

        const editPayoutUid =
            document.getElementById("editPayoutUid");

        const editPayoutTitle =
            document.getElementById("editPayoutTitle");

        const editPayoutDate =
            document.getElementById("editPayoutDate");

        const editPayoutFile =
            document.getElementById("editPayoutFile");

        const editPayoutCount =
            document.getElementById("editPayoutCount");

        const savePayoutEditButton =
            document.getElementById("savePayoutEditButton");

        document
            .querySelectorAll(".edit-payout-event")
            .forEach(button => {
                button.addEventListener("click", function () {
                    const data = JSON.parse(
                        button.dataset.payout || "{}"
                    );

                    editPayoutUid.value = data.uid || "";
                    editPayoutTitle.value = data.title || "";
                    editPayoutDate.value = data.date || "";
                    editPayoutFile.textContent = data.file || "—";
                    editPayoutFile.title = data.file || "";
                    editPayoutCount.textContent =
                        Number(data.count || 0).toLocaleString();

                    editPayoutModal.show();

                    window.setTimeout(() => {
                        editPayoutTitle.focus();
                        editPayoutTitle.select();
                    }, 250);
                });
            });

        editPayoutForm?.addEventListener("submit", function (event) {
            if (!editPayoutTitle.value.trim()) {
                event.preventDefault();
                editPayoutTitle.focus();
                return;
            }

            if (!editPayoutDate.value) {
                event.preventDefault();
                editPayoutDate.focus();
                return;
            }

            savePayoutEditButton.disabled = true;

            savePayoutEditButton
                .querySelector(".edit-payout-save-label")
                .classList.add("d-none");

            savePayoutEditButton
                .querySelector(".edit-payout-saving")
                .classList.remove("d-none");
        });

        editPayoutModalElement?.addEventListener(
            "hidden.bs.modal",
            function () {
                if (savePayoutEditButton) {
                    savePayoutEditButton.disabled = false;

                    savePayoutEditButton
                        .querySelector(".edit-payout-save-label")
                        .classList.remove("d-none");

                    savePayoutEditButton
                        .querySelector(".edit-payout-saving")
                        .classList.add("d-none");
                }

                editPayoutForm?.reset();
            }
        );

        const accomplishedAccessModalElement =
            document.getElementById("accomplishedAccessModal");

        const accomplishedAccessModal = accomplishedAccessModalElement
            ? bootstrap.Modal.getOrCreateInstance(accomplishedAccessModalElement)
            : null;

        const accomplishedAccessForm =
            document.getElementById("accomplishedAccessForm");

        const accomplishedAccessUid =
            document.getElementById("accomplishedAccessUid");

        const accomplishedAccessSubtitle =
            document.getElementById("accomplishedAccessSubtitle");

        const accomplishedAccessUsers =
            document.getElementById("accomplishedAccessUsers");

        const accomplishedAccessPermissionsJson =
            document.getElementById("accomplishedAccessPermissionsJson");

        const accomplishedAccessUserList =
            document.getElementById("accomplishedAccessUserList");

        const accomplishedAccessNewUser =
            document.getElementById("accomplishedAccessNewUser");

        const addAccomplishedAccessUser =
            document.getElementById("addAccomplishedAccessUser");

        const payoutAllPermission =
            document.getElementById("payoutAllPermission");

        const payoutSpecificUsers =
            document.getElementById("payoutSpecificUsers");

        const accomplishedAccessError =
            document.getElementById("accomplishedAccessError");

        let accomplishedAccessEntries = [];

        function selectedAccomplishedAccessMode() {
            return (
                accomplishedAccessForm
                    ?.querySelector('input[name="access_mode"]:checked')
                    ?.value || "closed"
            );
        }

        function updateAccomplishedAccessFields() {
            const mode = selectedAccomplishedAccessMode();
            const openAll = mode === "all";
            const specific = mode === "users";

            payoutAllPermission?.classList.toggle(
                "d-none",
                !openAll
            );

            payoutSpecificUsers?.classList.toggle(
                "d-none",
                !specific
            );

            if (!specific) {
                accomplishedAccessError?.classList.add("d-none");
            }
        }

        function normalizeIndividualPermission(value) {
            return value === "view" ? "view" : "edit";
        }

        function syncAccomplishedAccessInputs() {
            accomplishedAccessUsers.value =
                accomplishedAccessEntries
                    .map(entry => entry.name)
                    .join("\n");

            accomplishedAccessPermissionsJson.value =
                JSON.stringify(
                    accomplishedAccessEntries.map(entry => ({
                        name: entry.name,
                        permission: normalizeIndividualPermission(
                            entry.permission
                        )
                    }))
                );
        }

        function renderAccomplishedAccessEntries() {
            if (!accomplishedAccessUserList) {
                return;
            }

            accomplishedAccessUserList.innerHTML = "";

            accomplishedAccessEntries.forEach(function (entry, index) {
                const row = document.createElement("div");
                row.className = "individual-access-row";

                const name = document.createElement("div");
                name.className = "individual-access-name";
                name.textContent = entry.name;
                name.title = entry.name;

                const select = document.createElement("select");
                select.className = "form-select form-select-sm";
                select.setAttribute(
                    "aria-label",
                    "Permission for " + entry.name
                );

                [
                    ["view", "View Only"],
                    ["edit", "Can Edit"]
                ].forEach(function (optionData) {
                    const option = document.createElement("option");
                    option.value = optionData[0];
                    option.textContent = optionData[1];

                    if (
                        normalizeIndividualPermission(entry.permission) ===
                        optionData[0]
                    ) {
                        option.selected = true;
                    }

                    select.appendChild(option);
                });

                select.addEventListener("change", function () {
                    accomplishedAccessEntries[index].permission =
                        normalizeIndividualPermission(select.value);
                    syncAccomplishedAccessInputs();
                });

                const remove = document.createElement("button");
                remove.type = "button";
                remove.className = "btn btn-sm btn-outline-danger";
                remove.title = "Remove " + entry.name;
                remove.setAttribute(
                    "aria-label",
                    "Remove " + entry.name
                );
                remove.innerHTML =
                    '<i class="fa-solid fa-xmark"></i>';

                remove.addEventListener("click", function () {
                    accomplishedAccessEntries.splice(index, 1);
                    renderAccomplishedAccessEntries();
                });

                row.appendChild(name);
                row.appendChild(select);
                row.appendChild(remove);
                accomplishedAccessUserList.appendChild(row);
            });

            syncAccomplishedAccessInputs();

            if (accomplishedAccessEntries.length > 0) {
                accomplishedAccessError?.classList.add("d-none");
            }
        }

        function addIndividualAccessUser(
            rawName,
            permission = "view"
        ) {
            const name = String(rawName || "").trim();

            if (!name) {
                return false;
            }

            const existing = accomplishedAccessEntries.find(
                entry =>
                    entry.name.toLowerCase() ===
                    name.toLowerCase()
            );

            if (existing) {
                return false;
            }

            accomplishedAccessEntries.push({
                name: name,
                permission: normalizeIndividualPermission(permission)
            });

            renderAccomplishedAccessEntries();
            return true;
        }

        function loadIndividualAccessEntries(data) {
            accomplishedAccessEntries = [];

            const users =
                Array.isArray(data.users)
                    ? data.users
                    : [];

            const permissions =
                data.permissions &&
                typeof data.permissions === "object"
                    ? data.permissions
                    : {};

            users.forEach(function (name) {
                const matchingKey = Object.keys(permissions).find(
                    key =>
                        key.toLowerCase() ===
                        String(name).toLowerCase()
                );

                /*
                 * Backward compatibility: existing username-only access
                 * entries were editable.
                 */
                addIndividualAccessUser(
                    name,
                    matchingKey
                        ? permissions[matchingKey]
                        : "edit"
                );
            });

            renderAccomplishedAccessEntries();
        }

        document
            .querySelectorAll(".manage-accomplished-access")
            .forEach(button => {
                button.addEventListener("click", function () {
                    const data = JSON.parse(
                        button.dataset.payoutAccess || "{}"
                    );

                    accomplishedAccessUid.value =
                        data.uid || "";

                    accomplishedAccessSubtitle.textContent =
                        data.title || "Accomplished payout";

                    const mode =
                        ["closed", "all", "users"].includes(data.mode)
                            ? data.mode
                            : "closed";

                    const radio =
                        accomplishedAccessForm.querySelector(
                            'input[name="access_mode"][value="' +
                            mode +
                            '"]'
                        );

                    if (radio) {
                        radio.checked = true;
                    }

                    const allPermission =
                        data.all_permission === "view"
                            ? "view"
                            : "edit";

                    const allPermissionRadio =
                        accomplishedAccessForm.querySelector(
                            'input[name="all_permission"][value="' +
                            allPermission +
                            '"]'
                        );

                    if (allPermissionRadio) {
                        allPermissionRadio.checked = true;
                    }

                    loadIndividualAccessEntries(data);

                    accomplishedAccessError.classList.add("d-none");

                    updateAccomplishedAccessFields();
                    accomplishedAccessModal.show();
                });
            });

        accomplishedAccessForm
            ?.querySelectorAll('input[name="access_mode"]')
            .forEach(input => {
                input.addEventListener(
                    "change",
                    updateAccomplishedAccessFields
                );
            });

        document
            .querySelectorAll(".accomplished-user-chip")
            .forEach(button => {
                button.addEventListener("click", function () {
                    const name =
                        String(button.dataset.verifier || "").trim();

                    if (!name) return;

                    addIndividualAccessUser(name, "view");
                    accomplishedAccessError.classList.add("d-none");
                });
            });

        function addManualIndividualAccessUser() {
            const added = addIndividualAccessUser(
                accomplishedAccessNewUser?.value || "",
                "view"
            );

            if (added && accomplishedAccessNewUser) {
                accomplishedAccessNewUser.value = "";
                accomplishedAccessNewUser.focus();
            }
        }

        addAccomplishedAccessUser?.addEventListener(
            "click",
            addManualIndividualAccessUser
        );

        accomplishedAccessNewUser?.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    addManualIndividualAccessUser();
                }
            }
        );

        accomplishedAccessForm?.addEventListener(
            "submit",
            function (event) {
                if (
                    selectedAccomplishedAccessMode() === "users" &&
                    accomplishedAccessEntries.length === 0
                ) {
                    event.preventDefault();
                    payoutSpecificUsers.classList.remove("d-none");
                    accomplishedAccessError.classList.remove("d-none");
                    accomplishedAccessNewUser?.focus();
                    return;
                }

                syncAccomplishedAccessInputs();
            }
        );

        accomplishedAccessModalElement?.addEventListener(
            "hidden.bs.modal",
            function () {
                accomplishedAccessForm?.reset();
                accomplishedAccessEntries = [];
                renderAccomplishedAccessEntries();
                accomplishedAccessError?.classList.add("d-none");
                payoutAllPermission?.classList.add("d-none");
                payoutSpecificUsers?.classList.add("d-none");
            }
        );

        setSourceMode("file");
    }

    if (section === "activity") {
        const activitySearch = document.getElementById("activitySearch");
        const activityRows = Array.from(
            document.querySelectorAll(".activity-row")
        );
        const activityNoResults = document.getElementById("activityNoResults");

        activitySearch?.addEventListener("input", function () {
            const query = activitySearch.value.trim().toLowerCase();
            let visible = 0;

            activityRows.forEach(function (row) {
                const haystack = String(row.dataset.search || "").toLowerCase();
                const show = query === "" || haystack.includes(query);
                row.classList.toggle("d-none", !show);
                if (show) visible++;
            });

            activityNoResults?.classList.toggle(
                "d-none",
                visible !== 0 || activityRows.length === 0
            );
        });
    }

    if (section === "backups") {
        document
            .querySelectorAll(".restore-backup-form")
            .forEach(function (form) {
                form.addEventListener("submit", function (event) {
                    if (form.dataset.confirmApproved === "true") {
                        form.dataset.confirmApproved = "false";
                        return;
                    }

                    event.preventDefault();

                    const backupName =
                        form.querySelector('[name="backup_name"]')
                            ?.value || "";

                    showAdminConfirm({
                        title: "Restore Full Backup",
                        subtitle: "This replaces the current PAFFP data.",
                        message:
                            "Restore this full PAFFP data backup?",
                        details:
                            (backupName ? "Backup: " + backupName + "\n" : "") +
                            "A pre-restore safety backup will be created automatically before any data is replaced.",
                        confirmText: "Restore Backup",
                        tone: "danger",
                        onConfirm: function () {
                            form.dataset.confirmApproved = "true";
                            form.requestSubmit();
                        }
                    });
                });
            });

        document
            .querySelectorAll(".delete-backup-form")
            .forEach(function (form) {
                form.addEventListener("submit", function (event) {
                    if (form.dataset.confirmApproved === "true") {
                        form.dataset.confirmApproved = "false";
                        return;
                    }

                    event.preventDefault();

                    const backupName =
                        form.querySelector('[name="backup_name"]')
                            ?.value || "";

                    showAdminConfirm({
                        title: "Delete Backup",
                        subtitle: backupName || "Selected backup",
                        message:
                            "Permanently delete this full PAFFP backup?",
                        details:
                            "This removes the backup archive from the server and cannot be undone. It does not change the current PAFFP data.",
                        confirmText: "Delete Backup",
                        tone: "danger",
                        onConfirm: function () {
                            form.dataset.confirmApproved = "true";
                            form.requestSubmit();
                        }
                    });
                });
            });
    }

    if (section === "payouts") {
        const temporaryAccessForm =
            document.getElementById("temporaryAccessForm");
        const temporaryAccessStopForm =
            document.getElementById("temporaryAccessStopForm");
        const stopTemporaryAccessButton =
            document.getElementById("stopTemporaryAccessButton");
        const modalStopTemporaryAccessButton =
            document.getElementById("modalStopTemporaryAccessButton");
        const temporaryAccessUserWrap =
            document.getElementById("temporaryAccessUserWrap");
        const temporaryAccessUser =
            document.getElementById("temporaryAccessUser");

        const updateTemporaryAccessAudienceUi = function () {
            const audience =
                temporaryAccessForm?.querySelector(
                    '[name="temporary_access_audience"]:checked'
                )?.value || "all";

            temporaryAccessUserWrap?.classList.toggle(
                "d-none",
                audience !== "user"
            );

            if (temporaryAccessUser) {
                temporaryAccessUser.required = audience === "user";
            }
        };

        temporaryAccessForm
            ?.querySelectorAll('[name="temporary_access_audience"]')
            .forEach(function (input) {
                input.addEventListener(
                    "change",
                    updateTemporaryAccessAudienceUi
                );
            });

        updateTemporaryAccessAudienceUi();

        if (temporaryAccessForm) {
            temporaryAccessForm.addEventListener("submit", function (event) {
                if (temporaryAccessForm.dataset.confirmApproved === "true") {
                    temporaryAccessForm.dataset.confirmApproved = "false";
                    return;
                }

                event.preventDefault();

                const durationSelect =
                    document.getElementById("temporaryAccessMinutes");
                const durationText =
                    durationSelect?.options[durationSelect.selectedIndex]?.text ||
                    "the selected duration";

                const accessMode =
                    temporaryAccessForm.querySelector(
                        '[name="temporary_access_mode"]:checked'
                    )?.value || "view";

                const accessModeLabel =
                    accessMode === "edit"
                        ? "Open for Editing"
                        : "Open for Viewing";

                const audience =
                    temporaryAccessForm.querySelector(
                        '[name="temporary_access_audience"]:checked'
                    )?.value || "all";

                const audienceUser =
                    String(temporaryAccessUser?.value || "").trim();

                if (audience === "user" && audienceUser === "") {
                    temporaryAccessUser?.focus();
                    return;
                }

                const audienceLabel =
                    audience === "user"
                        ? audienceUser
                        : "All Users";

                showAdminConfirm({
                    title: "Open All Payouts Temporarily",
                    subtitle: accessModeLabel + " · " + audienceLabel + " · " + durationText,
                    message:
                        accessMode === "edit"
                            ? "Allow " + audienceLabel + " to view and edit every payout during this temporary window?"
                            : "Allow " + audienceLabel + " to view every payout in read-only mode during this temporary window?",
                    details:
                        accessMode === "edit"
                            ? "Users can verify, unverify, flag, capture photos, and save changes until the timer expires. Existing payout access settings are preserved."
                            : "Users can read payout records, but all verification updates and photo capture remain disabled. Existing payout access settings are preserved.",
                    confirmText:
                        accessMode === "edit"
                            ? "Open for Editing"
                            : "Open for Viewing",
                    tone: "warning",
                    onConfirm: function () {
                        temporaryAccessForm.dataset.confirmApproved = "true";
                        temporaryAccessForm.requestSubmit();
                    }
                });
            });
        }

        const requestStopTemporaryAccess = function () {
            if (!temporaryAccessStopForm) return;

            showAdminConfirm({
                title: "Stop Temporary Access",
                subtitle: "Return to normal payout restrictions now",
                message:
                    "Stop temporary all-payout access before its scheduled expiry?",
                details:
                    "Each payout will immediately return to its existing Open, Closed, Auto Closed, or Specific Verifier access setting.",
                confirmText: "Stop Access",
                tone: "danger",
                onConfirm: function () {
                    temporaryAccessStopForm.submit();
                }
            });
        };

        stopTemporaryAccessButton?.addEventListener(
            "click",
            requestStopTemporaryAccess
        );

        modalStopTemporaryAccessButton?.addEventListener(
            "click",
            requestStopTemporaryAccess
        );

        document
            .querySelectorAll(".payout-delete-form")
            .forEach(function (form) {
                form.addEventListener("submit", function (event) {
                    if (form.dataset.confirmApproved === "true") {
                        form.dataset.confirmApproved = "false";
                        return;
                    }

                    event.preventDefault();

                    const payoutTitle =
                        String(form.dataset.payoutTitle || "").trim() ||
                        "Selected payout";

                    const payoutFile =
                        String(form.dataset.payoutFile || "").trim();

                    showAdminConfirm({
                        title: "Delete Payout Event",
                        subtitle: payoutTitle,
                        message:
                            "Permanently remove this payout event from the active PAFFP data?",
                        details:
                            (payoutFile
                                ? "Beneficiary file: " + payoutFile + "\n"
                                : "") +
                            "The payout entry and its beneficiary JSON file will be removed. Safety backups of both files are created first and retained in the Admin backup folder.",
                        confirmText: "Delete Payout",
                        tone: "danger",
                        onConfirm: function () {
                            form.dataset.confirmApproved = "true";
                            form.requestSubmit();
                        }
                    });
                });
            });

        document
            .querySelectorAll(".payout-control-form")
            .forEach(function (form) {
                form.addEventListener("submit", function (event) {
                    if (form.dataset.confirmApproved === "true") {
                        form.dataset.confirmApproved = "false";
                        return;
                    }

                    event.preventDefault();

                    const button =
                        form.querySelector("button[type='submit']");

                    const isClose =
                        button &&
                        button.textContent.includes("Close");

                    const eventItem =
                        form.closest(".payout-event-item");

                    const payoutTitle =
                        eventItem
                            ?.querySelector(".payout-event-title")
                            ?.textContent
                            ?.trim() || "Selected payout";

                    showAdminConfirm({
                        title:
                            isClose
                                ? "Close Payout"
                                : "Reopen Payout",
                        subtitle: payoutTitle,
                        message:
                            isClose
                                ? "Close this payout for verification?"
                                : "Reopen this payout for verification?",
                        details:
                            isClose
                                ? "Verify will become read-only and server-side verification updates will be blocked until this payout is reopened."
                                : "Verifiers will be able to submit verification updates again.",
                        confirmText:
                            isClose
                                ? "Close Payout"
                                : "Reopen Payout",
                        tone:
                            isClose
                                ? "danger"
                                : "success",
                        onConfirm: function () {
                            form.dataset.confirmApproved = "true";
                            form.requestSubmit();
                        }
                    });
                });
            });
    }

    if (section === "announcements") {
        const form = document.getElementById("announcementForm");
        const title = document.getElementById("announcementTitle");
        const message = document.getElementById("announcementMessage");
        const type = document.getElementById("announcementType");
        const scope = document.getElementById("announcementScope");
        const priority = document.getElementById("announcementPriority");
        const audience = document.getElementById("announcementAudience");
        const audienceValue = document.getElementById("announcementAudienceValue");
        const audienceValueWrap = document.getElementById("announcementAudienceValueWrap");
        const enabled = document.getElementById("announcementEnabled");
        const dismissible = document.getElementById("announcementDismissible");
        const id = document.getElementById("announcementId");

        const preview = document.getElementById("announcementPreview");
        const previewIcon = document.getElementById("previewIcon");
        const previewTitle = document.getElementById("previewTitle");
        const previewMessage = document.getElementById("previewMessage");
        const previewHomeAction = document.getElementById("previewHomeAction");

        const cancelEdit = document.getElementById("cancelEdit");
        const composerTitle = document.getElementById("composerTitle");
        const saveLabel = document.getElementById("saveLabel");

        const iconMap = {
            info: "fa-solid fa-circle-info",
            success: "fa-solid fa-circle-check",
            warning: "fa-solid fa-triangle-exclamation",
            danger: "fa-solid fa-circle-exclamation"
        };

        function updateAudienceFields() {
            const specific = audience.value === "verifier";
            audienceValueWrap.classList.toggle("d-none", !specific);
            audienceValue.required = specific;
        }

        function updatePreview() {
            const currentType = type.value || "info";

            preview.className =
                "announcement-preview " + currentType;

            previewIcon.querySelector("i").className =
                iconMap[currentType] || iconMap.info;

            previewTitle.textContent =
                title.value.trim() || "Announcement preview";

            previewMessage.textContent =
                message.value.trim() ||
                "Your announcement will appear here.";

            previewHomeAction.classList.toggle(
                "d-none",
                dismissible.checked
            );
        }

        dismissible.addEventListener("change", updatePreview);

        function resetComposer() {
            form.reset();

            id.value = "";
            enabled.checked = true;
            dismissible.checked = true;
            type.value = "info";
            scope.value = "all";
            priority.value = "normal";
            audience.value = "all";
            audienceValue.value = "";
            updateAudienceFields();

            composerTitle.textContent = "Push Announcement";
            saveLabel.textContent = "Publish Announcement";
            cancelEdit.classList.add("d-none");

            updatePreview();
        }

        document.querySelectorAll(".edit-announcement").forEach(function (button) {
            button.addEventListener("click", function () {
                const data = JSON.parse(button.dataset.announcement);

                id.value = data.id || "";
                title.value = data.title || "";
                message.value = data.message || "";
                type.value = data.type || "info";
                scope.value = data.scope || "all";
                priority.value = data.priority || "normal";
                audience.value = data.audience || "all";
                audienceValue.value = data.audience_value || "";
                updateAudienceFields();
                enabled.checked = Boolean(data.enabled);
                dismissible.checked = Boolean(data.dismissible);

                composerTitle.textContent = "Edit Announcement";
                saveLabel.textContent = "Update Announcement";
                cancelEdit.classList.remove("d-none");

                updatePreview();

                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });

                window.setTimeout(function () {
                    title.focus();
                }, 250);
            });
        });

        document
            .querySelectorAll(".delete-announcement-form")
            .forEach(function (deleteForm) {
                deleteForm.addEventListener("submit", function (event) {
                    if (deleteForm.dataset.confirmApproved === "true") {
                        deleteForm.dataset.confirmApproved = "false";
                        return;
                    }

                    event.preventDefault();

                    const announcementCard =
                        deleteForm.closest(".saved-announcement-preview");

                    const announcementTitle =
                        announcementCard
                            ?.querySelector(".saved-preview-title")
                            ?.textContent
                            ?.trim() || "Selected announcement";

                    showAdminConfirm({
                        title: "Delete Announcement",
                        subtitle: announcementTitle,
                        message:
                            "Delete this announcement permanently?",
                        details:
                            "This action removes the announcement from the saved announcement list.",
                        confirmText: "Delete Announcement",
                        tone: "danger",
                        onConfirm: function () {
                            deleteForm.dataset.confirmApproved = "true";
                            deleteForm.requestSubmit();
                        }
                    });
                });
            });

        [title, message, type, priority].forEach(function (element) {
            element.addEventListener("input", updatePreview);
            element.addEventListener("change", updatePreview);
        });

        audience.addEventListener("change", updateAudienceFields);

        form.addEventListener("submit", function (event) {
            if (audience.value === "verifier" && !audienceValue.value.trim()) {
                event.preventDefault();
                audienceValue.focus();
            }
        });

        cancelEdit.addEventListener("click", resetComposer);

        updateAudienceFields();
        updatePreview();
    }

    if (section === "json") {
        const textarea = document.getElementById("jsonContent");

        if (!textarea || !window.CodeMirror) {
            return;
        }

        const editorForm = document.getElementById("editorForm");
        const jsonStatus = document.getElementById("jsonStatus");
        const goToJsonError = document.getElementById("goToJsonError");
        const formatJson = document.getElementById("formatJson");
        const findJson = document.getElementById("findJson");
        const replaceJson = document.getElementById("replaceJson");
        const changeStatus = document.getElementById("changeStatus");
        const saveButton = document.getElementById("saveButton");

        const fileSearch = document.getElementById("fileSearch");
        const filePickerMenu = document.getElementById("filePickerMenu");
        const filePickerItems =
            Array.from(document.querySelectorAll(".file-picker-item"));

        const backupFile = document.getElementById("backupFile");
        const restoreButton = document.getElementById("restoreButton");
        const restoreForm = document.getElementById("restoreForm");
        const restoreBackupField =
            document.getElementById("restoreBackupField");

        const saveModalElement =
            document.getElementById("saveConfirmModal");

        const restoreModalElement =
            document.getElementById("restoreConfirmModal");

        const saveModal = saveModalElement
            ? new bootstrap.Modal(saveModalElement)
            : null;

        const restoreModal = restoreModalElement
            ? new bootstrap.Modal(restoreModalElement)
            : null;

        let currentJsonError = null;
        let errorLineHandle = null;
        let initialContent = "";
        let saveConfirmed = false;
        let allowNavigation = false;

        const cm = CodeMirror.fromTextArea(textarea, {
            mode: {
                name: "javascript",
                json: true
            },
            theme: isDarkMode()
                ? "material-darker"
                : "default",
            lineNumbers: true,
            lineWrapping: false,
            matchBrackets: true,
            autoCloseBrackets: true,
            foldGutter: true,
            gutters: [
                "CodeMirror-linenumbers",
                "CodeMirror-foldgutter"
            ],
            indentUnit: 4,
            tabSize: 4,
            indentWithTabs: false,
            extraKeys: {
                "Ctrl-S": function () {
                    openSaveConfirmation();
                },
                "Cmd-S": function () {
                    openSaveConfirmation();
                },
                "Ctrl-F": "findPersistent",
                "Cmd-F": "findPersistent",
                "Ctrl-H": "replace",
                "Cmd-Alt-F": "replace"
            }
        });

        window.adminCodeMirror = cm;
        initialContent = cm.getValue();

        function formatBytesClient(bytes) {
            bytes = Number(bytes) || 0;

            if (bytes < 1024) return bytes + " B";
            if (bytes < 1048576) {
                return (bytes / 1024).toFixed(1) + " KB";
            }

            return (bytes / 1048576).toFixed(1) + " MB";
        }

        function getJsonErrorLocation(raw, error) {
            const messageText =
                error && error.message
                    ? String(error.message)
                    : "";

            let match =
                messageText.match(/line\s+(\d+)\s+column\s+(\d+)/i);

            if (match) {
                return {
                    line: Math.max(1, Number(match[1]) || 1),
                    column: Math.max(1, Number(match[2]) || 1),
                    message: messageText
                };
            }

            match = messageText.match(/position\s+(\d+)/i);

            if (match) {
                const position = Math.min(
                    Math.max(0, Number(match[1]) || 0),
                    raw.length
                );

                const before = raw.slice(0, position);
                const lines = before.split("\n");

                return {
                    line: lines.length,
                    column:
                        (lines[lines.length - 1] || "").length + 1,
                    message: messageText
                };
            }

            return {
                line: 1,
                column: 1,
                message: messageText || "Invalid JSON"
            };
        }

        function clearErrorLine() {
            if (errorLineHandle !== null) {
                cm.removeLineClass(
                    errorLineHandle,
                    "background",
                    "cm-error-line"
                );

                errorLineHandle = null;
            }
        }

        function updateLiveStats(raw, parsed) {
            const countNode =
                document.getElementById("serverRecordCount");

            const sizeNode =
                document.getElementById("liveFileSize");

            if (sizeNode) {
                sizeNode.textContent =
                    formatBytesClient(new Blob([raw]).size);
            }

            if (!countNode) return;

            if (Array.isArray(parsed)) {
                countNode.textContent =
                    parsed.length.toLocaleString() + " records";
            } else if (parsed && typeof parsed === "object") {
                countNode.textContent =
                    Object.keys(parsed).length.toLocaleString() +
                    " keys";
            } else {
                countNode.textContent = "Invalid JSON";
            }
        }

        function validateJson() {
            const raw = cm.getValue();

            clearErrorLine();

            if (!raw.trim()) {
                currentJsonError = {
                    line: 1,
                    column: 1,
                    message: "JSON content is empty."
                };

                jsonStatus.className =
                    "editor-status is-invalid";

                jsonStatus.innerHTML =
                    '<i class="fa-solid fa-circle-exclamation"></i>' +
                    '<span>Empty JSON · Line 1, Column 1</span>';

                goToJsonError.classList.remove("d-none");
                updateLiveStats(raw, null);

                return {
                    valid: false,
                    parsed: null,
                    recordCount: null
                };
            }

            try {
                const parsed = JSON.parse(raw);
                const count = Array.isArray(parsed)
                    ? parsed.length
                    : null;

                currentJsonError = null;

                jsonStatus.className =
                    "editor-status is-valid";

                jsonStatus.innerHTML =
                    '<i class="fa-solid fa-circle-check"></i>' +
                    '<span>Valid JSON</span>';

                goToJsonError.classList.add("d-none");
                updateLiveStats(raw, parsed);

                return {
                    valid: true,
                    parsed: parsed,
                    recordCount: count
                };
            } catch (error) {
                currentJsonError =
                    getJsonErrorLocation(raw, error);

                const lineIndex =
                    Math.max(0, currentJsonError.line - 1);

                if (lineIndex < cm.lineCount()) {
                    errorLineHandle =
                        cm.addLineClass(
                            lineIndex,
                            "background",
                            "cm-error-line"
                        );
                }

                jsonStatus.className =
                    "editor-status is-invalid";

                jsonStatus.innerHTML =
                    '<i class="fa-solid fa-circle-exclamation"></i>' +
                    '<span>Invalid JSON · Line ' +
                    currentJsonError.line.toLocaleString() +
                    ", Column " +
                    currentJsonError.column.toLocaleString() +
                    "</span>";

                jsonStatus.title =
                    currentJsonError.message;

                goToJsonError.classList.remove("d-none");
                updateLiveStats(raw, null);

                return {
                    valid: false,
                    parsed: null,
                    recordCount: null
                };
            }
        }

        function scrollToJsonError() {
            if (!currentJsonError) return;

            const line =
                Math.max(0, currentJsonError.line - 1);

            const ch =
                Math.max(0, currentJsonError.column - 1);

            cm.focus();
            cm.setCursor({
                line: line,
                ch: ch
            });

            cm.scrollIntoView(
                {
                    line: line,
                    ch: ch
                },
                Math.max(
                    80,
                    cm.getScrollInfo().clientHeight / 3
                )
            );
        }

        function isDirty() {
            return cm.getValue() !== initialContent;
        }

        function updateChangeState() {
            if (!changeStatus) return;

            if (isDirty()) {
                changeStatus.innerHTML =
                    '<i class="fa-solid fa-pen"></i>' +
                    '<span>Unsaved changes</span>';
            } else {
                changeStatus.innerHTML =
                    '<i class="fa-regular fa-circle-check"></i>' +
                    '<span>No unsaved changes</span>';
            }
        }

        function openSaveConfirmation() {
            const result = validateJson();

            if (!result.valid) {
                scrollToJsonError();
                return;
            }

            if (!isDirty()) {
                showAdminNotice({
                    title: "No Changes",
                    message:
                        "There are no changes to save in the current JSON file.",
                    tone: "info"
                });
                return;
            }

            const initialNode =
                document.getElementById("confirmInitialRecords");

            const nextNode =
                document.getElementById("confirmNewRecords");

            const diffNode =
                document.getElementById("confirmRecordDifference");

            initialNode.textContent =
                initialServerRecordCount === null
                    ? "JSON object"
                    : Number(
                        initialServerRecordCount
                    ).toLocaleString();

            nextNode.textContent =
                result.recordCount === null
                    ? "JSON object"
                    : Number(
                        result.recordCount
                    ).toLocaleString();

            if (initialServerRecordCount !== null
                && result.recordCount !== null) {

                const difference =
                    Number(result.recordCount) -
                    Number(initialServerRecordCount);

                diffNode.textContent =
                    (difference > 0 ? "+" : "") +
                    difference.toLocaleString();

                diffNode.className =
                    difference === 0
                        ? ""
                        : difference > 0
                            ? "text-success"
                            : "text-danger";
            } else {
                diffNode.textContent = "—";
                diffNode.className = "";
            }

            saveModal.show();
        }

        function requestFileNavigation(fileName) {
            if (!fileName || fileName === selectedFile) {
                closeFilePicker();
                return;
            }

            const navigateToFile = function () {
                allowNavigation = true;

                window.location.href =
                    selfFile +
                    "?section=json&file=" +
                    encodeURIComponent(fileName);
            };

            if (isDirty()) {
                closeFilePicker();

                showAdminConfirm({
                    title: "Discard Unsaved Changes?",
                    subtitle: selectedFile,
                    message:
                        "You have unsaved JSON changes. Leave this file without saving?",
                    details:
                        "Your unsaved editor changes will be discarded if you continue.",
                    confirmText: "Leave Without Saving",
                    tone: "warning",
                    onConfirm: navigateToFile,
                    onCancel: function () {
                        fileSearch.value = selectedFile;
                    }
                });

                return;
            }

            navigateToFile();
        }

        function filterFilePicker() {
            const query =
                fileSearch.value.trim().toLowerCase();

            filePickerItems.forEach(function (item) {
                const fileName =
                    String(item.dataset.file || "")
                        .toLowerCase();

                item.classList.toggle(
                    "d-none",
                    !fileName.includes(query)
                );
            });

            filePickerMenu.classList.remove("d-none");
        }

        function closeFilePicker() {
            filePickerMenu.classList.add("d-none");
        }

        cm.on("change", function () {
            validateJson();
            updateChangeState();
        });

        validateJson();
        updateChangeState();

        goToJsonError.addEventListener(
            "click",
            scrollToJsonError
        );

        findJson.addEventListener("click", function () {
            cm.focus();
            cm.execCommand("findPersistent");
        });

        replaceJson.addEventListener("click", function () {
            cm.focus();
            cm.execCommand("replace");
        });

        formatJson.addEventListener("click", function () {
            const result = validateJson();

            if (!result.valid) {
                scrollToJsonError();
                return;
            }

            cm.setValue(
                JSON.stringify(result.parsed, null, 4)
            );
        });

        editorForm.addEventListener("submit", function (event) {
            if (!saveConfirmed) {
                event.preventDefault();
                openSaveConfirmation();
            }
        });

        document
            .getElementById("confirmSaveButton")
            .addEventListener("click", function () {
                saveConfirmed = true;
                allowNavigation = true;

                cm.save();

                saveButton.disabled = true;
                saveButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>' +
                    "Saving…";

                editorForm.submit();
            });

        fileSearch.addEventListener(
            "focus",
            filterFilePicker
        );

        fileSearch.addEventListener(
            "input",
            filterFilePicker
        );

        fileSearch.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    fileSearch.value = selectedFile;
                    closeFilePicker();
                }

                if (event.key === "Enter") {
                    event.preventDefault();

                    const firstVisible =
                        filePickerItems.find(function (item) {
                            return !item.classList.contains(
                                "d-none"
                            );
                        });

                    if (firstVisible) {
                        requestFileNavigation(
                            firstVisible.dataset.file
                        );
                    }
                }
            }
        );

        filePickerItems.forEach(function (item) {
            item.addEventListener("click", function () {
                requestFileNavigation(item.dataset.file);
            });
        });

        document.addEventListener("click", function (event) {
            const picker =
                document.getElementById("filePicker");

            if (picker && !picker.contains(event.target)) {
                closeFilePicker();
                fileSearch.value = selectedFile;
            }
        });

        if (restoreButton) {
            restoreButton.addEventListener("click", function () {
                if (!backupFile.value) return;

                const openRestoreModal = function () {
                    document.getElementById(
                        "restoreBackupName"
                    ).textContent = backupFile.value;

                    restoreBackupField.value =
                        backupFile.value;

                    restoreModal.show();
                };

                if (isDirty()) {
                    showAdminConfirm({
                        title: "Discard Unsaved Editor Changes?",
                        subtitle: selectedFile,
                        message:
                            "Restoring this version will discard your current unsaved editor changes.",
                        details:
                            "The saved server version is not changed until you confirm the restore in the next dialog.",
                        confirmText: "Continue to Restore",
                        tone: "warning",
                        onConfirm: openRestoreModal
                    });

                    return;
                }

                openRestoreModal();
            });
        }

        document
            .getElementById("confirmRestoreButton")
            .addEventListener("click", function () {
                allowNavigation = true;
                restoreForm.submit();
            });

        window.addEventListener(
            "beforeunload",
            function (event) {
                if (!allowNavigation && isDirty()) {
                    event.preventDefault();
                    event.returnValue = "";
                }
            }
        );
    }
})();
</script>

</body>
</html>
