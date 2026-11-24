<?php
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);

date_default_timezone_set('Asia/Manila');
$adSettingsPath = __DIR__ . "/data/_adSettings.json";
$adsEnabled = true;
$adSlots = [
    'summary_banner' => true,
    'status_banner' => true
];

if (is_file($adSettingsPath) && is_readable($adSettingsPath)) {
    $adSettingsRaw = @file_get_contents($adSettingsPath);
    $adSettingsData = json_decode((string) $adSettingsRaw, true);

    if (is_array($adSettingsData)) {
        if (array_key_exists('monitor', $adSettingsData)) {
            $adsEnabled = (bool) $adSettingsData['monitor'];
        }

        if (
            isset($adSettingsData['slots']['monitor'])
            && is_array($adSettingsData['slots']['monitor'])
        ) {
            foreach ($adSlots as $slot => $defaultValue) {
                if (array_key_exists($slot, $adSettingsData['slots']['monitor'])) {
                    $adSlots[$slot] =
                        (bool) $adSettingsData['slots']['monitor'][$slot];
                }
            }
        }
    }
}

$adMonitorSummaryBanner = $adsEnabled && $adSlots['summary_banner'];
$adMonitorStatusBanner = $adsEnabled && $adSlots['status_banner'];



function json_response($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_array($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return array(null, 'File not found or unreadable.');
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return array(null, 'Unable to read file.');
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return array(null, 'Invalid JSON: ' . json_last_error_msg());
    }

    if (!is_array($decoded)) {
        return array(null, 'JSON root must be an array.');
    }

    return array($decoded, null);
}

function safe_data_file($dataDir, $fileName)
{
    $fileName = basename((string)$fileName);

    if ($fileName === '' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'json') {
        return null;
    }

    return $dataDir . DIRECTORY_SEPARATOR . $fileName;
}

function status_is_verified($status)
{
    return $status === 'Verified';
}

function status_is_corrected($status)
{
    return $status === 'Verified w/ Corrections';
}

function parse_timestamp($value)
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        $dt = new DateTime($value);
        return array(
            'epoch' => $dt->getTimestamp(),
            'iso' => $dt->format(DateTime::ATOM)
        );
    } catch (Exception $e) {
        return null;
    }
}

function encoder_add(&$encoders, $encoder, $status)
{
    $encoder = trim((string)$encoder);

    if ($encoder === '') {
        $encoder = 'Unknown';
    }

    if (!isset($encoders[$encoder])) {
        $encoders[$encoder] = array(
            'name' => $encoder,
            'verified' => 0,
            'corrected' => 0,
            'total' => 0
        );
    }

    if (status_is_verified($status)) {
        $encoders[$encoder]['verified']++;
    } elseif (status_is_corrected($status)) {
        $encoders[$encoder]['corrected']++;
    }

    $encoders[$encoder]['total']++;
}

function build_live_payload()
{
    $dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    $listFile = $dataDir . DIRECTORY_SEPARATOR . '_list.json';
    $today = date('Y-m-d');

    list($list, $listError) = read_json_array($listFile);

    if ($listError !== null) {
        return array(
            'ok' => false,
            'message' => 'Unable to read data/_list.json: ' . $listError,
            'server_time' => date(DateTime::ATOM),
            'today' => $today
        );
    }

    $ongoing = array();

    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string)($item['date'] ?? '') !== $today) {
            continue;
        }

        $ongoing[] = $item;
    }

    usort($ongoing, function ($a, $b) {
        return strcasecmp(
            (string)($a['title'] ?? ''),
            (string)($b['title'] ?? '')
        );
    });

    $overall = array(
        'payouts' => count($ongoing),
        'beneficiaries' => 0,
        'verified' => 0,
        'corrected' => 0,
        'pending' => 0,
        'completed' => 0,
        'active_encoders' => 0,
        'progress' => 0
    );

    $allEncoders = array();
    $allRecent = array();
    $payouts = array();

    foreach ($ongoing as $item) {
        $title = trim((string)($item['title'] ?? 'Untitled Payout'));
        $uid = trim((string)($item['uid'] ?? ''));
        $fileName = trim((string)($item['file'] ?? ''));
        $listedCount = (int)($item['count'] ?? 0);

        $filePath = safe_data_file($dataDir, $fileName);
        $records = null;
        $fileError = null;

        if ($filePath === null) {
            $fileError = 'Invalid payout JSON filename.';
        } else {
            list($records, $fileError) = read_json_array($filePath);
        }

        $stats = array(
            'beneficiaries' => $listedCount,
            'verified' => 0,
            'corrected' => 0,
            'pending' => $listedCount,
            'completed' => 0,
            'progress' => 0,
            'active_encoders' => 0
        );

        $localEncoders = array();
        $latest = null;
        $modified = null;

        if ($filePath !== null && is_file($filePath)) {
            $mtime = @filemtime($filePath);
            if ($mtime !== false) {
                $modified = date(DateTime::ATOM, $mtime);
            }
        }

        if (is_array($records)) {
            $stats['beneficiaries'] = count($records);

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $status = (string)($record['VerifyStatus'] ?? '');

                if (status_is_verified($status)) {
                    $stats['verified']++;
                } elseif (status_is_corrected($status)) {
                    $stats['corrected']++;
                } else {
                    continue;
                }

                $encoder = (string)($record['VerifyEncoder'] ?? 'Unknown');

                encoder_add($localEncoders, $encoder, $status);
                encoder_add($allEncoders, $encoder, $status);

                $parsedTime = parse_timestamp($record['VerifyTimeStamp'] ?? null);

                if ($parsedTime !== null) {
                    $activity = array(
                        'title' => $title,
                        'uid' => $uid,
                        'encoder' => trim($encoder) !== '' ? trim($encoder) : 'Unknown',
                        'status' => $status,
                        'timestamp' => $parsedTime['iso'],
                        'epoch' => $parsedTime['epoch']
                    );

                    $allRecent[] = $activity;

                    if ($latest === null || $parsedTime['epoch'] > $latest['epoch']) {
                        $latest = $activity;
                    }
                }
            }

            $stats['completed'] = $stats['verified'] + $stats['corrected'];
            $stats['pending'] = max(0, $stats['beneficiaries'] - $stats['completed']);
            $stats['progress'] = $stats['beneficiaries'] > 0
                ? round(($stats['completed'] / $stats['beneficiaries']) * 100, 1)
                : 0;
            $stats['active_encoders'] = count($localEncoders);
        }

        uasort($localEncoders, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        $payouts[] = array(
            'uid' => $uid,
            'title' => $title,
            'file' => $fileName,
            'date' => (string)($item['date'] ?? ''),
            'stats' => $stats,
            'encoders' => array_values($localEncoders),
            'latest_activity' => $latest,
            'file_modified' => $modified,
            'error' => $fileError
        );

        $overall['beneficiaries'] += $stats['beneficiaries'];
        $overall['verified'] += $stats['verified'];
        $overall['corrected'] += $stats['corrected'];
        $overall['pending'] += $stats['pending'];
        $overall['completed'] += $stats['completed'];
    }

    $overall['active_encoders'] = count($allEncoders);
    $overall['progress'] = $overall['beneficiaries'] > 0
        ? round(($overall['completed'] / $overall['beneficiaries']) * 100, 1)
        : 0;

    uasort($allEncoders, function ($a, $b) {
        if ($a['total'] === $b['total']) {
            return strcasecmp($a['name'], $b['name']);
        }
        return $b['total'] <=> $a['total'];
    });

    usort($allRecent, function ($a, $b) {
        return $b['epoch'] <=> $a['epoch'];
    });

    $allRecent = array_slice($allRecent, 0, 15);

    foreach ($allRecent as &$activity) {
        unset($activity['epoch']);
    }
    unset($activity);

    return array(
        'ok' => true,
        'today' => $today,
        'server_time' => date(DateTime::ATOM),
        'refresh_seconds' => 10,
        'overall' => $overall,
        'payouts' => $payouts,
        'encoders' => array_values($allEncoders),
        'recent_activity' => $allRecent
    );
}

if (isset($_GET['api'])) {
    json_response(build_live_payload());
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">

    <script>
        (function () {
            try {
                const darkMode = localStorage.getItem("darkMode") === "true";
                document.documentElement.setAttribute("data-bs-theme", darkMode ? "dark" : "light");
            } catch (error) {
                document.documentElement.setAttribute("data-bs-theme", "light");
            }
        })();
    </script>

    <title>DA RFO X | PAFFP Live Monitor</title>
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="author" content="DA RFO X | PMED PS AJOctat">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="img/DA_transparent.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --da-green: #198754;
            --da-green-dark: #12663f;
            --da-green-soft: #eaf6ef;
            --surface: #ffffff;
            --surface-muted: #f6f8f7;
            --surface-soft: #f9fbfa;
            --border: #e4e9e6;
            --text-main: #1f2923;
            --text-soft: #69756e;
            --warning: #d39e00;
            --danger: #dc3545;
            --shadow: 0 10px 30px rgba(27, 67, 45, .08);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: "Montserrat", sans-serif;
            font-size: 1rem;
            background:
                radial-gradient(circle at top right, rgba(25, 135, 84, .07), transparent 28rem),
                var(--surface-muted);
            color: var(--text-main);
        }

        [data-bs-theme="dark"] body {
            --surface: #171b18;
            --surface-muted: #0f1210;
            --surface-soft: #121613;
            --border: #2a312c;
            --text-main: #eef2ef;
            --text-soft: #a7b0aa;
            --da-green-soft: rgba(25, 135, 84, .17);
            --shadow: 0 10px 30px rgba(0, 0, 0, .22);
            background:
                radial-gradient(circle at top right, rgba(25, 135, 84, .13), transparent 28rem),
                var(--surface-muted);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            border-bottom: 1px solid rgba(25, 135, 84, .13);
            background: color-mix(in srgb, var(--surface) 92%, transparent);
            backdrop-filter: blur(14px);
        }

        .brand-logo {
            width: 50px;
            height: 50px;
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
            line-height: 1.35;
        }

        @media (max-width: 767.98px) {
            .brand-subtitle {
                display: none;
            }
        }

        .surface-card,
        .stat-card,
        .payout-card {
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .surface-card {
            border-radius: 16px;
        }

        .live-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 34px;
            padding: .35rem .65rem;
            border: 1px solid rgba(25, 135, 84, .22);
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green-dark);
            font-size: .74rem;
            font-weight: 700;
        }

        [data-bs-theme="dark"] .live-chip {
            color: #8ad2a9;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--da-green);
            box-shadow: 0 0 0 4px rgba(25, 135, 84, .12);
            animation: pulse 1.8s ease-out infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, .28); }
            70% { box-shadow: 0 0 0 7px rgba(25, 135, 84, 0); }
            100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .live-dot { animation: none; }
        }

        .toolbar-btn {
            min-height: 38px;
            border-radius: 10px;
            font-size: .82rem;
            font-weight: 500;
        }

        .page-title {
            margin: 0;
            color: var(--text-main);
            font-size: clamp(1.25rem, 2.2vw, 1.75rem);
            font-weight: 800;
        }

        .page-subtitle {
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .82rem;
        }

        .stat-card {
            height: 100%;
            padding: .85rem;
            border-radius: 14px;
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: 10px;
            background: var(--surface-muted);
            color: var(--da-green);
            font-size: .85rem;
        }

        .stat-value {
            color: var(--text-main);
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            margin-top: .22rem;
            color: var(--text-soft);
            font-size: .68rem;
            line-height: 1.15;
        }

        .section-title {
            margin: 0;
            color: var(--text-main);
            font-size: .95rem;
            font-weight: 750;
        }

        .section-subtitle {
            margin-top: .15rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .payout-card {
            height: 100%;
            padding: .9rem;
            border-left: 4px solid var(--da-green);
            border-radius: 14px;
        }

        .payout-title {
            min-width: 0;
            margin: 0;
            overflow: hidden;
            color: var(--text-main);
            font-size: 1rem;
            font-weight: 750;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payout-file {
            margin-top: .2rem;
            overflow: hidden;
            color: var(--text-soft);
            font-size: .66rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payout-total {
            color: var(--text-main);
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
        }

        .payout-total-label {
            margin-top: .14rem;
            color: var(--text-soft);
            font-size: .66rem;
        }

        .stack-progress {
            display: flex;
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--surface-muted);
        }

        .progress-verified { background: #198754; }
        .progress-corrected { background: #ffc107; }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .4rem;
        }

        .metric {
            min-width: 0;
            padding: .48rem .5rem;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-muted);
        }

        .metric-value {
            color: var(--text-main);
            font-size: .86rem;
            font-weight: 750;
            line-height: 1;
        }

        .metric-label {
            margin-top: .18rem;
            overflow: hidden;
            color: var(--text-soft);
            font-size: .61rem;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payout-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding-top: .7rem;
            border-top: 1px solid var(--border);
            color: var(--text-soft);
            font-size: .66rem;
        }

        .payout-footer > div {
            min-width: 0;
        }

        .latest-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .open-link {
            flex: 0 0 auto;
            color: var(--da-green);
            font-weight: 700;
            text-decoration: none;
        }

        .open-link:hover {
            color: var(--da-green-dark);
            text-decoration: underline;
        }

        .progress-number {
            color: var(--da-green);
            font-size: .78rem;
            font-weight: 800;
        }

        .leader-row,
        .activity-row {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .62rem 0;
            border-bottom: 1px solid var(--border);
        }

        .leader-row:last-child,
        .activity-row:last-child {
            border-bottom: 0;
        }

        .rank-badge {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 8px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 800;
        }

        .leader-name,
        .activity-main {
            min-width: 0;
            flex: 1 1 auto;
        }

        .leader-name strong,
        .activity-title {
            display: block;
            overflow: hidden;
            color: var(--text-main);
            font-size: .76rem;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .leader-meta,
        .activity-meta {
            margin-top: .15rem;
            color: var(--text-soft);
            font-size: .64rem;
        }

        .leader-total {
            flex: 0 0 auto;
            color: var(--text-main);
            font-size: .86rem;
            font-weight: 800;
        }

        .activity-status {
            flex: 0 0 auto;
            border-radius: 999px;
            padding: .24rem .45rem;
            font-size: .58rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .activity-status.verified {
            background: rgba(25, 135, 84, .12);
            color: #198754;
        }

        .activity-status.corrected {
            background: rgba(255, 193, 7, .17);
            color: #9a7300;
        }

        [data-bs-theme="dark"] .activity-status.verified { color: #75d49f; }
        [data-bs-theme="dark"] .activity-status.corrected { color: #f5ce62; }

        .empty-state {
            padding: 2rem 1rem;
            color: var(--text-soft);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            margin: 0 auto .75rem;
            border-radius: 14px;
            background: var(--surface-muted);
            color: var(--da-green);
            font-size: 1.15rem;
        }

        .error-banner {
            display: none;
            border: 1px solid rgba(220, 53, 69, .25);
            border-radius: 12px;
            background: rgba(220, 53, 69, .07);
            color: #b02a37;
            font-size: .78rem;
        }

        [data-bs-theme="dark"] .error-banner {
            color: #ff9aa5;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--surface-muted) 96%, transparent);
            transition: opacity .2s ease, visibility .2s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .da-loader {
            width: 46px;
            height: 46px;
            border: 5px solid rgba(25, 135, 84, .18);
            border-top-color: var(--da-green);
            border-radius: 50%;
            animation: spin .75s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (prefers-reduced-motion: reduce) {
            .da-loader { animation-duration: 1.5s; }
        }


        .analytics-card {
            height: 100%;
            padding: .9rem;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .chart-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .chart-title {
            margin: 0;
            color: var(--text-main);
            font-size: .88rem;
            font-weight: 750;
        }

        .chart-subtitle {
            margin-top: .15rem;
            color: var(--text-soft);
            font-size: .65rem;
            line-height: 1.25;
        }

        .chart-icon {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border-radius: 9px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .75rem;
        }

        .chart-wrap {
            position: relative;
            width: 100%;
            min-height: 230px;
        }

        .chart-wrap.chart-short {
            min-height: 210px;
        }

        .chart-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }

        @media (max-width: 767.98px) {
            .chart-wrap,
            .chart-wrap.chart-short {
                min-height: 220px;
            }
        }

        @media (max-width: 575.98px) {
            .brand-kicker {
                font-size: 1rem;
            }

            .brand-subtitle {
                font-size: .84rem;
            }

            .metric-grid { gap: .3rem; }
            .metric { padding: .42rem; }
            .payout-card { padding: .8rem; }
        }

        /* Compact live analytics */
        .analytics-card {
            padding: .65rem .7rem;
            border-radius: 12px;
        }

        .chart-heading {
            align-items: center;
            margin-bottom: .4rem;
        }

        .chart-title {
            font-size: .78rem;
            line-height: 1.15;
        }

        .chart-subtitle {
            display: none;
        }

        .chart-icon {
            width: 28px;
            height: 28px;
            flex-basis: 28px;
            border-radius: 8px;
            font-size: .68rem;
        }

        .chart-wrap {
            min-height: 230px;
            height: 230px;
        }

        .chart-wrap.chart-short {
            min-height: 230px;
            height: 230px;
        }

        .analytics-compact-row .analytics-card {
            min-height: 0;
        }

        @media (max-width: 991.98px) {
            .chart-wrap,
            .chart-wrap.chart-short {
                min-height: 220px;
                height: 220px;
            }
        }

        @media (max-width: 575.98px) {
            .analytics-card {
                padding: .6rem;
            }

            .chart-wrap,
            .chart-wrap.chart-short {
                min-height: 210px;
                height: 210px;
            }
        }


        .brand-link,
        .brand-link:hover,
        .brand-link:focus,
        .brand-link:active,
        .brand-link:visited {
            color: inherit !important;
            text-decoration: none !important;
        }


        .monitor-update-meta {
            flex: 0 0 auto;
        }

        @media (max-width: 767.98px) {
            .monitor-update-meta {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: .25rem .55rem;
                width: 100%;
                padding: .5rem .65rem;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: var(--surface);
                box-shadow: var(--shadow);
                text-align: left !important;
            }

            .monitor-update-label {
                display: none;
            }

            #lastUpdated {
                font-size: .74rem;
                font-weight: 600 !important;
                line-height: 1.2;
                white-space: nowrap;
            }

            #lastUpdated::before {
                content: "Updated ";
                color: var(--text-soft);
                font-weight: 500;
            }

            #nextRefresh {
                margin-top: 0 !important;
                margin-left: auto;
                font-size: .7rem;
                line-height: 1.2;
                white-space: nowrap;
            }
        }

        @media (max-width: 420px) {
            .monitor-update-meta {
                gap: .2rem;
            }

            #lastUpdated,
            #nextRefresh {
                width: 100%;
            }

            #nextRefresh {
                margin-left: 0;
            }
        }


        /* Centered Adsterra 728x90 banner */
        .adsterra-banner-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow: hidden;
            margin: 1rem 0;
        }

        .adsterra-banner-frame {
            display: block;
            width: 728px;
            height: 90px;
            min-width: 728px;
            flex: 0 0 728px;
            border: 0;
            background: transparent;
            transform-origin: top center;
        }

        .adsterra-grid-row {
            width: 100%;
        }

    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="da-loader" aria-label="Loading live payout data"></div>
</div>

<header class="topbar">
    <div class="container-fluid px-3 px-lg-4 py-2">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <a class="brand-link d-flex align-items-center gap-3 min-w-0 text-decoration-none"
               href="./"
               title="Return to Home"
               aria-label="Return to Home">
                <img class="brand-logo" src="img/DA_transparent.png" alt="Department of Agriculture logo">
                <div class="min-w-0">
                    <div class="brand-kicker">DA RFO X</div>
                    <div class="brand-subtitle text-truncate">Presidential Assistance to Farmers and Fisherfolks Program</div>
                </div>
            </a>

            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <div class="live-chip d-none d-sm-inline-flex" id="connectionChip">
                    <span class="live-dot"></span>
                    <span id="connectionText">Live</span>
                </div>

                <button class="btn btn-outline-secondary toolbar-btn" id="refreshBtn" type="button" title="Refresh now">
                    <i class="fa-solid fa-rotate"></i>
                    <span class="d-none d-md-inline ms-1">Refresh</span>
                </button>

                <button class="btn btn-outline-secondary toolbar-btn" id="themeBtn" type="button" title="Toggle dark mode">
                    <i class="fa-solid fa-moon" id="themeIcon"></i>
                </button>

                <a class="btn btn-outline-secondary toolbar-btn" href="./" title="Home">
                    <i class="fa-solid fa-house"></i>
                    <span class="d-none d-md-inline ms-1">Home</span>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-3">
        <div>
            <h1 class="page-title">Ongoing Payout Monitoring</h1>
            <div class="page-subtitle" id="pageSubtitle">Loading today’s ongoing payout data…</div>
        </div>

        <div class="monitor-update-meta text-lg-end">
            <div class="monitor-update-label small text-secondary">Last update</div>
            <div class="fw-semibold" id="lastUpdated">—</div>
            <div class="small text-secondary mt-1" id="nextRefresh">Auto-refresh every 10 seconds</div>
        </div>
    </div>

    <div class="error-banner p-3 mb-3" id="errorBanner"></div>

    <section class="row g-2 g-lg-3 mb-4" aria-label="Overall payout summary">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div>
                        <div class="stat-value" id="statPayouts">0</div>
                        <div class="stat-label">Ongoing Payouts</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="stat-value" id="statBeneficiaries">0</div>
                        <div class="stat-label">Beneficiaries</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div>
                        <div class="stat-value" id="statVerified">0</div>
                        <div class="stat-label">Verified</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <div class="stat-value" id="statCorrected">0</div>
                        <div class="stat-label">With Corrections</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
                    <div>
                        <div class="stat-value" id="statPending">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div>
                        <div class="stat-value" id="statProgress">0%</div>
                        <div class="stat-label">Completion</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($adMonitorSummaryBanner) { ?>
    <div class="adsterra-banner-wrap" aria-label="Advertisement">
            <iframe class="adsterra-banner-frame"
                    src="ad-banner.php"
                    width="728"
                    height="90"
                    scrolling="no"
                    loading="lazy"
                    title="Advertisement"></iframe>
        </div>
    <?php } ?>

    <section class="mb-4" aria-label="Live payout analytics">
        <div class="d-flex align-items-end justify-content-between gap-2 mb-2">
            <div>
                <h2 class="section-title">Live Analytics</h2>
                <div class="section-subtitle">Charts update automatically with the live payout data</div>
            </div>
        </div>

        <div class="row g-2 analytics-compact-row">
            <div class="col-12 col-lg-6">
                <div class="analytics-card">
                    <div class="chart-heading">
                        <h3 class="chart-title">Overall Verification</h3>
                        <div class="chart-icon"><i class="fa-solid fa-chart-pie"></i></div>
                    </div>

                    <div class="chart-wrap chart-short">
                        <canvas id="overallStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="analytics-card">
                    <div class="chart-heading">
                        <h3 class="chart-title">Payout Completion</h3>
                        <div class="chart-icon"><i class="fa-solid fa-chart-bar"></i></div>
                    </div>

                    <div class="chart-wrap">
                        <canvas id="payoutProgressChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($adMonitorStatusBanner) { ?>
    <div class="adsterra-banner-wrap" aria-label="Advertisement">
            <iframe class="adsterra-banner-frame"
                    src="ad-banner.php"
                    width="728"
                    height="90"
                    scrolling="no"
                    loading="lazy"
                    title="Advertisement"></iframe>
        </div>
    <?php } ?>

    <section class="mb-4">
        <div class="d-flex align-items-end justify-content-between gap-2 mb-2">
            <div>
                <h2 class="section-title">Live Payout Status</h2>
                <div class="section-subtitle">All payout entries scheduled for today</div>
            </div>
            <div class="small text-secondary">
                <i class="fa-solid fa-user-check me-1"></i>
                <span id="activeEncoders">0</span> active encoders
            </div>
        </div>

        <div class="row g-3" id="payoutGrid"></div>
    </section>

    <section class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="surface-card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="section-title">Encoder Activity</h2>
                        <div class="section-subtitle">Combined output across today’s ongoing payouts</div>
                    </div>
                    <i class="fa-solid fa-ranking-star text-success"></i>
                </div>
                <div id="encoderList"></div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="surface-card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="section-title">Recent Verification Activity</h2>
                        <div class="section-subtitle">Latest verification updates across all ongoing payouts</div>
                    </div>
                    <i class="fa-solid fa-bolt text-success"></i>
                </div>
                <div id="activityList"></div>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
    const REFRESH_SECONDS = 10;
    let refreshTimer = null;
    let countdownTimer = null;
    let secondsUntilRefresh = REFRESH_SECONDS;
    let isRefreshing = false;
    let firstLoad = true;
    let latestPayload = null;

    const els = {
        loadingOverlay: document.getElementById("loadingOverlay"),
        refreshBtn: document.getElementById("refreshBtn"),
        themeBtn: document.getElementById("themeBtn"),
        themeIcon: document.getElementById("themeIcon"),
        connectionChip: document.getElementById("connectionChip"),
        connectionText: document.getElementById("connectionText"),
        pageSubtitle: document.getElementById("pageSubtitle"),
        lastUpdated: document.getElementById("lastUpdated"),
        nextRefresh: document.getElementById("nextRefresh"),
        errorBanner: document.getElementById("errorBanner"),
        payoutGrid: document.getElementById("payoutGrid"),
        encoderList: document.getElementById("encoderList"),
        activityList: document.getElementById("activityList"),
        activeEncoders: document.getElementById("activeEncoders"),
        statPayouts: document.getElementById("statPayouts"),
        statBeneficiaries: document.getElementById("statBeneficiaries"),
        statVerified: document.getElementById("statVerified"),
        statCorrected: document.getElementById("statCorrected"),
        statPending: document.getElementById("statPending"),
        statProgress: document.getElementById("statProgress"),
        overallStatusChart: document.getElementById("overallStatusChart"),
        payoutProgressChart: document.getElementById("payoutProgressChart")
    };

    let overallStatusChartInstance = null;
    let payoutProgressChartInstance = null;

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    function formatDateLong(isoDate) {
        if (!isoDate) return "Today";

        const parts = isoDate.split("-").map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return isoDate;

        const months = [
            "Jan.", "Feb.", "Mar.", "Apr.", "May", "Jun.",
            "Jul.", "Aug.", "Sept.", "Oct.", "Nov.", "Dec."
        ];

        return `${months[parts[1] - 1]} ${parts[2]}, ${parts[0]}`;
    }

    function formatTime(value) {
        if (!value) return "No activity yet";

        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return "No activity yet";

        return d.toLocaleTimeString([], {
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function formatDateTime(value) {
        if (!value) return "—";

        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return "—";

        return d.toLocaleString([], {
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
            second: "2-digit"
        });
    }

    function buildVerifyUrl(payout) {
        const params = new URLSearchParams({
            location: payout.title || "",
            id: payout.uid || ""
        });
        return `verify.php?${params.toString()}`;
    }

    function progressWidth(part, total) {
        if (!total) return 0;
        return Math.max(0, Math.min(100, (Number(part) / Number(total)) * 100));
    }


    function chartTheme() {
        const dark = document.documentElement.getAttribute("data-bs-theme") === "dark";

        return {
            text: dark ? "#eef2ef" : "#1f2923",
            muted: dark ? "#a7b0aa" : "#69756e",
            grid: dark ? "rgba(255,255,255,.08)" : "rgba(31,41,35,.08)",
            verified: "#198754",
            corrected: "#ffc107",
            pending: dark ? "#59615c" : "#dfe5e1"
        };
    }

    function destroyChart(instance) {
        if (instance) {
            instance.destroy();
        }
        return null;
    }

    function commonChartOptions() {
        const theme = chartTheme();

        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 250
            },
            plugins: {
                legend: {
                    labels: {
                        color: theme.text,
                        usePointStyle: true,
                        boxWidth: 8,
                        boxHeight: 8,
                        font: {
                            family: "Montserrat",
                            size: 9
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || context.label || "";
                            const value = Number(context.raw || 0).toLocaleString();
                            return `${label ? label + ": " : ""}${value}`;
                        }
                    }
                }
            }
        };
    }

    function renderOverallStatusChart(overall) {
        if (!window.Chart || !els.overallStatusChart) return;

        overallStatusChartInstance = destroyChart(overallStatusChartInstance);
        const theme = chartTheme();

        overallStatusChartInstance = new Chart(els.overallStatusChart, {
            type: "doughnut",
            data: {
                labels: ["Verified", "Corrections", "Pending"],
                datasets: [{
                    data: [
                        Number(overall.verified || 0),
                        Number(overall.corrected || 0),
                        Number(overall.pending || 0)
                    ],
                    backgroundColor: [
                        theme.verified,
                        theme.corrected,
                        theme.pending
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                ...commonChartOptions(),
                cutout: "72%",
                plugins: {
                    ...commonChartOptions().plugins,
                    legend: {
                        ...commonChartOptions().plugins.legend,
                        position: "bottom",
                        labels: {
                            ...commonChartOptions().plugins.legend.labels,
                            padding: 8
                        }
                    }
                }
            }
        });
    }

    function renderPayoutProgressChart(payouts) {
        if (!window.Chart || !els.payoutProgressChart) return;

        payoutProgressChartInstance = destroyChart(payoutProgressChartInstance);
        const theme = chartTheme();

        const rows = (Array.isArray(payouts) ? payouts : [])
            .slice()
            .sort((a, b) => Number(b.stats?.beneficiaries || 0) - Number(a.stats?.beneficiaries || 0))
            .slice(0, 8);
        const labels = rows.map(payout => payout.title || "Untitled");
        const completed = rows.map(payout => Number(payout.stats?.completed || 0));
        const pending = rows.map(payout => Number(payout.stats?.pending || 0));

        payoutProgressChartInstance = new Chart(els.payoutProgressChart, {
            type: "bar",
            data: {
                labels,
                datasets: [
                    {
                        label: "Completed",
                        data: completed,
                        backgroundColor: theme.verified,
                        borderRadius: 5,
                        borderSkipped: false
                    },
                    {
                        label: "Pending",
                        data: pending,
                        backgroundColor: theme.pending,
                        borderRadius: 5,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                ...commonChartOptions(),
                indexAxis: "y",
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            color: theme.muted,
                            precision: 0,
                            font: {
                                family: "Montserrat",
                                size: 9
                            }
                        },
                        grid: {
                            color: theme.grid
                        }
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            color: theme.text,
                            autoSkip: false,
                            font: {
                                family: "Montserrat",
                                size: 9
                            },
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                return label.length > 15
                                    ? label.substring(0, 15) + "…"
                                    : label;
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    ...commonChartOptions().plugins,
                    legend: {
                        ...commonChartOptions().plugins.legend,
                        position: "bottom",
                        labels: {
                            ...commonChartOptions().plugins.legend.labels,
                            padding: 8
                        }
                    }
                }
            }
        });
    }

    function renderCharts(payload) {
        const overall = payload.overall || {};
        const payouts = payload.payouts || [];

        renderOverallStatusChart(overall);
        renderPayoutProgressChart(payouts);
    }

    function renderPayouts(payouts) {
        if (!Array.isArray(payouts) || payouts.length === 0) {
            els.payoutGrid.innerHTML = `
                <div class="col-12">
                    <div class="surface-card empty-state">
                        <div class="empty-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                        <div class="fw-semibold">No ongoing payouts today</div>
                        <div class="small mt-1">Payouts appear here when their date in <code>data/_list.json</code> matches today.</div>
                    </div>
                </div>`;
            return;
        }

        els.payoutGrid.innerHTML = payouts.map(payout => {
            const stats = payout.stats || {};
            const total = Number(stats.beneficiaries || 0);
            const verified = Number(stats.verified || 0);
            const corrected = Number(stats.corrected || 0);
            const pending = Number(stats.pending || 0);
            const progress = Number(stats.progress || 0);

            const verifiedWidth = progressWidth(verified, total);
            const correctedWidth = progressWidth(corrected, total);

            let latestText = "No verification activity yet";

            if (payout.latest_activity) {
                latestText = `${escapeHtml(payout.latest_activity.encoder)} · ${formatTime(payout.latest_activity.timestamp)}`;
            } else if (payout.file_modified) {
                latestText = `File updated ${formatTime(payout.file_modified)}`;
            }

            const errorHtml = payout.error
                ? `<div class="alert alert-danger py-2 px-2 mt-2 mb-0 small">
                       <i class="fa-solid fa-triangle-exclamation me-1"></i>
                       ${escapeHtml(payout.error)}
                   </div>`
                : "";

            return `
                <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                    <article class="payout-card d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0 flex-grow-1">
                                <h3 class="payout-title" title="${escapeHtml(payout.title)}">
                                    ${escapeHtml(payout.title)}
                                </h3>
                                <div class="payout-file">${escapeHtml(payout.file || "")}</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="payout-total">${formatNumber(total)}</div>
                                <div class="payout-total-label">Beneficiaries</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-2 mt-3 mb-1">
                            <span class="small text-secondary">Progress</span>
                            <span class="progress-number">${progress.toFixed(1)}%</span>
                        </div>

                        <div class="stack-progress" title="${progress.toFixed(1)}% complete">
                            <div class="progress-verified" style="width:${verifiedWidth}%"></div>
                            <div class="progress-corrected" style="width:${correctedWidth}%"></div>
                        </div>

                        <div class="metric-grid mt-2">
                            <div class="metric">
                                <div class="metric-value">${formatNumber(verified)}</div>
                                <div class="metric-label">Verified</div>
                            </div>
                            <div class="metric">
                                <div class="metric-value">${formatNumber(corrected)}</div>
                                <div class="metric-label">Corrections</div>
                            </div>
                            <div class="metric">
                                <div class="metric-value">${formatNumber(pending)}</div>
                                <div class="metric-label">Pending</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-2 mt-2 small">
                            <span class="text-secondary">
                                <i class="fa-solid fa-user-check me-1"></i>
                                ${formatNumber(stats.active_encoders || 0)} encoders
                            </span>
                            <span class="text-secondary">
                                ${formatNumber(stats.completed || 0)} completed
                            </span>
                        </div>

                        ${errorHtml}

                        <div class="payout-footer mt-3">
                            <div class="latest-text" title="${escapeHtml(latestText)}">
                                <i class="fa-regular fa-clock me-1"></i>${latestText}
                            </div>
                            <a class="open-link" href="${buildVerifyUrl(payout)}">
                                Open <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                            </a>
                        </div>
                    </article>
                </div>`;
        }).join("");
    }

    function renderEncoders(encoders) {
        if (!Array.isArray(encoders) || encoders.length === 0) {
            els.encoderList.innerHTML = `
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="fa-solid fa-user-clock"></i></div>
                    <div class="small">No verification activity yet.</div>
                </div>`;
            return;
        }

        els.encoderList.innerHTML = encoders.slice(0, 12).map((encoder, index) => `
            <div class="leader-row">
                <div class="rank-badge">${index + 1}</div>
                <div class="leader-name">
                    <strong title="${escapeHtml(encoder.name)}">${escapeHtml(encoder.name)}</strong>
                    <div class="leader-meta">
                        ${formatNumber(encoder.verified)} verified ·
                        ${formatNumber(encoder.corrected)} corrections
                    </div>
                </div>
                <div class="leader-total">${formatNumber(encoder.total)}</div>
            </div>
        `).join("");
    }

    function renderRecentActivity(activity) {
        if (!Array.isArray(activity) || activity.length === 0) {
            els.activityList.innerHTML = `
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="fa-solid fa-wave-square"></i></div>
                    <div class="small">No recent verification activity yet.</div>
                </div>`;
            return;
        }

        els.activityList.innerHTML = activity.map(item => {
            const corrected = item.status === "Verified w/ Corrections";
            const statusClass = corrected ? "corrected" : "verified";
            const statusLabel = corrected ? "Corrections" : "Verified";

            return `
                <div class="activity-row">
                    <div class="activity-main">
                        <div class="activity-title" title="${escapeHtml(item.title)}">
                            ${escapeHtml(item.title)}
                        </div>
                        <div class="activity-meta">
                            ${escapeHtml(item.encoder)} · ${formatDateTime(item.timestamp)}
                        </div>
                    </div>
                    <span class="activity-status ${statusClass}">${statusLabel}</span>
                </div>`;
        }).join("");
    }

    function updateSummary(payload) {
        const overall = payload.overall || {};

        els.statPayouts.textContent = formatNumber(overall.payouts);
        els.statBeneficiaries.textContent = formatNumber(overall.beneficiaries);
        els.statVerified.textContent = formatNumber(overall.verified);
        els.statCorrected.textContent = formatNumber(overall.corrected);
        els.statPending.textContent = formatNumber(overall.pending);
        els.statProgress.textContent = `${Number(overall.progress || 0).toFixed(1)}%`;
        els.activeEncoders.textContent = formatNumber(overall.active_encoders);

        els.pageSubtitle.textContent =
            `${formatDateLong(payload.today)} · ${formatNumber(overall.payouts)} ongoing payout${Number(overall.payouts) === 1 ? "" : "s"}`;

        els.lastUpdated.textContent = formatDateTime(payload.server_time);
    }

    function showError(message) {
        els.errorBanner.style.display = "block";
        els.errorBanner.innerHTML =
            `<i class="fa-solid fa-triangle-exclamation me-2"></i>${escapeHtml(message)}`;
    }

    function clearError() {
        els.errorBanner.style.display = "none";
        els.errorBanner.textContent = "";
    }

    function setConnectionState(online) {
        els.connectionText.textContent = online ? "Live" : "Offline";
        els.connectionChip.style.opacity = online ? "1" : ".65";
    }

    async function loadLiveData(manual = false) {
        if (isRefreshing) return;
        isRefreshing = true;

        if (manual) {
            els.refreshBtn.disabled = true;
            els.refreshBtn.querySelector("i").classList.add("fa-spin");
        }

        try {
            const endpoint = `${window.location.pathname}?api=1&ts=${Date.now()}`;
            const response = await fetch(endpoint, {
                method: "GET",
                cache: "no-store",
                headers: { "Accept": "application/json" }
            });

            if (!response.ok) {
                throw new Error(`Server returned HTTP ${response.status}.`);
            }

            const payload = await response.json();

            if (!payload.ok) {
                throw new Error(payload.message || "Unable to load live payout data.");
            }

            clearError();
            setConnectionState(true);
            latestPayload = payload;
            updateSummary(payload);
            renderPayouts(payload.payouts);
            renderEncoders(payload.encoders);
            renderRecentActivity(payload.recent_activity);
            renderCharts(payload);

            secondsUntilRefresh = REFRESH_SECONDS;
        } catch (error) {
            showError(error.message || "Unable to refresh live payout data.");
            setConnectionState(false);
        } finally {
            isRefreshing = false;

            if (manual) {
                els.refreshBtn.disabled = false;
                els.refreshBtn.querySelector("i").classList.remove("fa-spin");
            }

            if (firstLoad) {
                firstLoad = false;
                els.loadingOverlay.classList.add("hidden");
            }
        }
    }

    function updateCountdown() {
        if (!navigator.onLine) {
            els.nextRefresh.textContent = "Waiting for connection…";
            return;
        }

        els.nextRefresh.textContent =
            secondsUntilRefresh > 0
                ? `Refreshing in ${secondsUntilRefresh}s`
                : "Refreshing…";

        secondsUntilRefresh--;

        if (secondsUntilRefresh < 0) {
            secondsUntilRefresh = REFRESH_SECONDS;
        }
    }

    function startTimers() {
        clearInterval(refreshTimer);
        clearInterval(countdownTimer);

        refreshTimer = setInterval(() => {
            loadLiveData(false);
        }, REFRESH_SECONDS * 1000);

        countdownTimer = setInterval(updateCountdown, 1000);
        updateCountdown();
    }

    function applyThemeIcon() {
        const dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
        els.themeIcon.className = dark ? "fa-solid fa-sun" : "fa-solid fa-moon";
    }

    els.themeBtn.addEventListener("click", () => {
        const dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
        const nextDark = !dark;

        document.documentElement.setAttribute("data-bs-theme", nextDark ? "dark" : "light");

        try {
            localStorage.setItem("darkMode", nextDark ? "true" : "false");
        } catch (error) {}

        applyThemeIcon();

        if (latestPayload) {
            renderCharts(latestPayload);
        }
    });

    els.refreshBtn.addEventListener("click", () => {
        secondsUntilRefresh = REFRESH_SECONDS;
        loadLiveData(true);
    });

    window.addEventListener("online", () => {
        setConnectionState(true);
        secondsUntilRefresh = REFRESH_SECONDS;
        loadLiveData(false);
    });

    window.addEventListener("offline", () => {
        setConnectionState(false);
    });

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            loadLiveData(false);
        }
    });

    function fitAdsterraBanners() {
        document.querySelectorAll(".adsterra-banner-wrap").forEach(function(wrap) {
            const frame = wrap.querySelector(".adsterra-banner-frame");
            if (!frame) return;

            const available = Math.max(1, wrap.clientWidth);
            const scale = Math.min(1, available / 728);

            frame.style.transform = "scale(" + scale + ")";
            wrap.style.height = Math.ceil(90 * scale) + "px";
        });
    }

    window.addEventListener("resize", fitAdsterraBanners);

    applyThemeIcon();
    setConnectionState(navigator.onLine);
    startTimers();
    fitAdsterraBanners();
    loadLiveData(false);
</script>

</body>
</html>
