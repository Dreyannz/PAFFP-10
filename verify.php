<?php
require_once __DIR__ . '/auth-lib.php';

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);

$paffpAuthEnabled = paffp_auth_enabled();
$paffpAuthUsername = '';
$paffpAuthNext = paffp_auth_verify_next_url();
$paffpLoginUrl = paffp_auth_login_url($paffpAuthNext);

if ($paffpAuthEnabled) {
    $paffpAuthUsername = paffp_auth_current_username();

    if ($paffpAuthUsername === '') {
        header('Location: ' . $paffpLoginUrl, true, 302);
        exit;
    }
}

$location = trim((string) ($_GET['location'] ?? ''));
$safeLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
$adSettingsPath = __DIR__ . "/data/_adSettings.json";
$adsEnabled = true;
$adSlots = [
    'analytics_native' => true,
    'container_local_banner' => true,
    'footer_banner' => true
];

if (is_file($adSettingsPath) && is_readable($adSettingsPath)) {
    $adSettingsRaw = @file_get_contents($adSettingsPath);
    $adSettingsData = json_decode((string) $adSettingsRaw, true);

    if (is_array($adSettingsData)) {
        if (array_key_exists('verify', $adSettingsData)) {
            $adsEnabled = (bool) $adSettingsData['verify'];
        }

        if (
            isset($adSettingsData['slots']['verify'])
            && is_array($adSettingsData['slots']['verify'])
        ) {
            foreach ($adSlots as $slot => $defaultValue) {
                if (array_key_exists($slot, $adSettingsData['slots']['verify'])) {
                    $adSlots[$slot] =
                        (bool) $adSettingsData['slots']['verify'][$slot];
                }
            }
        }
    }
}

$adVerifyAnalyticsNative = $adsEnabled && $adSlots['analytics_native'];
$adVerifyContainerLocalBanner = $adsEnabled && $adSlots['container_local_banner'];
$adVerifyFooterBanner = $adsEnabled && $adSlots['footer_banner'];


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>DA RFO X | PAFFP<?= $safeLocation !== '' ? ' | ' . $safeLocation : '' ?></title>
    <meta name="title" content="DA RFO X | PAFFP<?= $safeLocation !== '' ? ' | ' . $safeLocation : '' ?>">
    <meta name="description" content="DA RFO X | PAFFP">
    <meta name="keywords" content="Masaganang Agrikultura, Maunlad na Ekonomiya">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, max-image-preview:none, max-snippet:0, max-video-preview:0">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="language" content="English">
    <meta name="author" content="DA RFO X | PMED PS AJOctat">
    <meta name="google" content="notranslate">
    <link rel="icon" href="img/DA_transparent.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://pmed10.online/" />
    <meta property="og:title" content="DA RFO X | PAFFP<?= $safeLocation !== '' ? ' | ' . $safeLocation : '' ?>" />
    <meta property="og:description" content="Department of Agriculture Regional Field Office X
    Presidential Assistance to Farmers and Fisherfolks Program" />
    <meta property="og:image" content="img/Meta.jpg" />

    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="https://pmed10.online/" />
    <meta property="twitter:title" content="DA RFO X | PAFFP" />
    <meta property="twitter:description" content="Department of Agriculture Regional Field Office X
    Presidential Assistance to Farmers and Fisherfolks Program" />
    <meta property="twitter:image" content="img/Meta.jpg" />

    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-1.11.4/datatables.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.11.4/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style type="text/css">
        @import url("https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap");
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Montserrat", sans-serif;
        }
        body {
            overflow: scroll;
            overflow-x: hidden;
        }
        #wrapper {
            display: none;
        }
        ::-webkit-scrollbar {
            height: 6px;
            width: 6px;
            background: transparent;
        }
        ::-webkit-scrollbar-track {
            background: #ffffff;
        }
        ::-webkit-scrollbar-thumb {
            background: #26a042;
            border-radius: 3px;
        }
        .header-container {
            display: flex;
            align-items: center;
        }
        .header-text {
            text-align: left;
            line-height: 1.5;
        }
        .header-text h1 {
            margin: 0;
        }
        .header-text p {
            margin: 0;
        }
        thead input {
            width: 100%;
        }
        .noWrap {
            white-space: nowrap !important;
        }
        #Home {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            display: block;
            z-index: 99;
            border: none;
            outline: none;
            width: 2.75rem;
            height: 2.75rem;
            text-align: center;
            opacity: 1;
            color: #ffffff;
            border-radius: 5px;
        }
        #Home:hover {
            opacity: 0.7;
        }

        .mypopup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 99;
            border: none;
            outline: none;
            padding-left: 55px;
            padding-right: 55px;
            padding-top: 55px;
            padding-bottom: 55px;
            justify-content: center;
            align-items: center;
            text-align: center;
            opacity: 1;
            color: #fff;
            background: #26a042;
            border-radius: 120px;
        }

        .loader {
            position: relative;
            width: 120px;
            height: 120px;
            font-size: 24px;
            color: #fff;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader::after {
            content: "";
            position: absolute;
            top: -40px;
            left: -40px;
            right: -40px;
            bottom: -40px;
            border-radius: 50%;
            border-top: 12px solid rgba(255, 255, 255, 0.2);
            border-right: 12px solid rgba(255, 255, 255, 0.2);
            border-bottom: 12px solid rgba(255, 255, 255, 0.2);
            border-left: 12px solid #fff;
            transform: translateZ(0);
            animation: load 1.2s infinite linear;
        }

        @keyframes load {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        #loading-container {
            width: fit-content;
            height: fit-content;
            background: #26a042;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #table_local tbody tr.selected {
            background-color: #d1f7d6 !important;
        }

        #table_local tbody tr.selected td {
            background-color: #d1f7d6 !important;
        }
        #table_local tbody td,
        #table_local thead th {
            vertical-align: middle !important;
        }

        table.dataTable th.col-equal,
        table.dataTable td.col-equal {
            width: 25%;
        }
        @media (max-width: 767.98px) {
            #UsernameDisplay {
                display: none !important;
            }
        }

        body.dark-mode {
            background: #121212 !important;
            color: #e9ecef;
        }

        body.dark-mode .card,
        body.dark-mode .card-header,
        body.dark-mode .card-body,
        body.dark-mode footer {
            background: #1e1e1e !important;
            color: #e9ecef !important;
        }

        body.dark-mode .bg-white {
            background: #1e1e1e !important;
        }

        body.dark-mode .text-success {
            color: #6ee7a3 !important;
        }

        body.dark-mode .text-muted,
        body.dark-mode .font-italic {
            color: #bdbdbd !important;
        }

        body.dark-mode table {
            color: #f1f1f1 !important;
        }

        body.dark-mode table.dataTable tbody tr {
            background: #1e1e1e;
        }

        body.dark-mode table.dataTable tbody td,
        body.dark-mode table.dataTable thead th {
            background: #1e1e1e !important;
            color: #f1f1f1 !important;
            border-color: #444 !important;
        }

        body.dark-mode table.dataTable tbody tr.selected td {
            background: #285943 !important;
        }

        body.dark-mode .dataTables_wrapper .dataTables_filter input,
        body.dark-mode .dataTables_wrapper .dataTables_length select {
            background: #2b2b2b;
            color: #fff;
            border: 1px solid #555;
        }

        body.dark-mode .paginate_button {
            color: #fff !important;
        }

        body.dark-mode .page-link {
            background: #2b2b2b;
            color: #fff;
            border-color: #555;
        }

        body.dark-mode .page-item.active .page-link {
            background: #198754;
            border-color: #198754;
        }

        body.dark-mode .btn-outline-success,
        body.dark-mode .btn-outline-warning,
        body.dark-mode .btn-outline-info,
        body.dark-mode .btn-outline-danger,
        body.dark-mode .btn-outline-secondary {
            color: #f8f9fa;
        }

        body.dark-mode #farmerDetails {
            color: #fff;
        }

        body.dark-mode .copyright {
            color: #bbb;
        }
        #previewContainer {
            width: 100%;
            max-width: 550px;
            aspect-ratio: 1/1;
            margin: auto;
            position: relative;
            overflow: hidden;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background: #000;
        }

        #cameraContainer,
        #imageContainer {
            width: 100%;
            height: 100%;
        }

        #cameraVideo,
        #capturedImage {
            width: 100%;
            height: 100%;
            display: block;
            image-rendering: auto;
            object-fit: cover;
        }
        #captureGuide {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 75%;
            aspect-ratio: 1.6;
            transform: translate(-50%, -50%);
            border: 3px dashed #fff;
            border-radius: 8px;
            display: none;
        }
        .btn-close {
            box-shadow: none !important;
            background-color: transparent;
            border: none;
        }

        .btn-close:focus,
        .btn-close:active,
        .btn-close:hover {
            outline: none !important;
            box-shadow: none !important;
            background-color: transparent;
            border: none;
        }
        .captureModal.disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
    </style>

    <style id="paffp-modern-ui">
        :root {
            --da-green: #198754;
            --da-green-dark: #12663f;
            --da-green-soft: #eaf6ef;
            --surface: #ffffff;
            --surface-muted: #f6f8f7;
            --border: #e4e9e6;
            --text-main: #1f2923;
            --text-soft: #69756e;
            --shadow: 0 10px 30px rgba(27, 67, 45, .08);
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            font-family: "Montserrat", sans-serif;
            background:
                radial-gradient(circle at top right, rgba(25, 135, 84, .08), transparent 28rem),
                var(--surface-muted) !important;
            color: var(--text-main);
        }

        body.dark-mode {
            --surface: #171b18;
            --surface-muted: #0f1210;
            --border: #2a312c;
            --text-main: #eef2ef;
            --text-soft: #a7b0aa;
            --shadow: 0 10px 30px rgba(0, 0, 0, .22);
            background:
                radial-gradient(circle at top right, rgba(25, 135, 84, .14), transparent 28rem),
                var(--surface-muted) !important;
            color: var(--text-main);
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; background: transparent; }
        ::-webkit-scrollbar-track { background: var(--surface); }
        ::-webkit-scrollbar-thumb { background: var(--da-green); border-radius: 3px; }

        #wrapper { display: none; min-height: 100vh; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            border-bottom: 1px solid rgba(25, 135, 84, .12);
            background: color-mix(in srgb, var(--surface) 92%, transparent);
            backdrop-filter: blur(14px);
        }

        .brand-link { color: inherit; text-decoration: none; }
        .brand-link:hover { color: inherit; }
        .brand-logo { width: 50px; height: 50px; object-fit: contain; }
        .brand-kicker { color: var(--da-green); font-weight: 800; letter-spacing: .02em; }
        .brand-subtitle { color: var(--text-soft); font-size: .82rem; line-height: 1.35; }

        .page-shell { padding-top: 1.5rem; padding-bottom: 2.5rem; }

        /* Use the available browser width on desktop/full-screen displays. */
        @media (min-width: 1400px) {
            .page-shell,
            .topbar > .container-fluid,
            footer > .container-fluid {
                padding-left: 2rem !important;
                padding-right: 2rem !important;
            }

            #paffpData {
                --bs-gutter-x: 2rem;
            }

            .surface-card .card-body {
                padding: 1.25rem 1.35rem;
            }

            #table_local tbody td {
                padding-top: .7rem;
                padding-bottom: .7rem;
            }
        }

        @media (min-width: 1800px) {
            .page-shell,
            .topbar > .container-fluid,
            footer > .container-fluid {
                padding-left: 2.5rem !important;
                padding-right: 2.5rem !important;
            }

            #paffpData {
                --bs-gutter-x: 2.5rem;
            }
        }

        .surface-card,
        .details-card,
        .summary-card,
        .analytics-card,
        .empty-profile {
            border: 1px solid var(--border) !important;
            border-radius: 18px !important;
            background: var(--surface) !important;
            box-shadow: var(--shadow) !important;
        }

        .surface-card .card-header,
        .analytics-card .card-header {
            border-bottom: 1px solid var(--border);
            border-radius: 18px 18px 0 0 !important;
            background: transparent !important;
            padding: 1rem 1.15rem;
        }

        .surface-card .card-body,
        .analytics-card .card-body { padding: 1.15rem; }

        .page-title {
            margin: 0;
            color: var(--text-main);
            font-size: clamp(1.05rem, 2vw, 1.35rem);
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .page-subtitle { color: var(--text-soft); font-size: .78rem; }

        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .4rem .7rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        #PendingBadge {
            min-width: 1.35rem;
            padding: .3rem .45rem;
            border-radius: 999px;
            font-size: .7rem;
        }

        .topbar .btn,
        .card-header .btn {
            border-radius: 10px;
            font-weight: 400;
        }

        .btn {
            font-weight: 400 !important;
        }

        .btn > i,
        .btn > span > i,
        .btn .fw-bold > i {
            line-height: 1;
            vertical-align: middle;
            flex-shrink: 0;
        }

        .btn .fw-bold {
            font-weight: 400 !important;
        }

        .table-responsive-shell { width: 100%; overflow-x: auto; }
        #table_local { width: 100% !important; margin-bottom: 0 !important; }
        #table_local thead th {
            border-color: var(--border) !important;
            background: var(--da-green-soft) !important;
            color: var(--da-green-dark) !important;
            font-size: .73rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .025em;
            vertical-align: middle !important;
        }
        #table_local tbody td {
            border-color: var(--border) !important;
            background: var(--surface) !important;
            color: var(--text-main) !important;
            vertical-align: middle !important;
            font-size: .85rem;
        }
        #table_local tbody tr:hover td { background: rgba(25, 135, 84, .035) !important; }
        #table_local tbody tr.selected td { background: #dff5e8 !important; }
        body.dark-mode #table_local thead th { background: rgba(25, 135, 84, .17) !important; color: #9be5bb !important; }
        body.dark-mode #table_local tbody tr:hover td { background: rgba(25, 135, 84, .08) !important; }
        body.dark-mode #table_local tbody tr.selected td { background: #285943 !important; }

        #table_local .viewFarmer {
            min-width: 82px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            font-weight: 400;
        }

        #table_local .viewFarmer i {
            margin: 0;
            line-height: 1;
        }


        /* ----------------------------------------------------------
           verifyTest.php - mobile-only beneficiary card experiment
           The real DataTable remains active underneath for search,
           paging, filtering, ordering and row state. On mobile, the card list is placed before the DataTables info/pagination controls.
        ----------------------------------------------------------- */
        .mobile-beneficiary-cards {
            display: none;
        }

        @media (max-width: 767.98px) {
            .table-responsive-shell {
                overflow-x: visible !important;
            }

            #table_local {
                display: none !important;
            }

            .mobile-beneficiary-cards {
                display: grid;
                gap: .55rem;
                width: 100%;
                margin-top: .65rem;
            }

            .mobile-beneficiary-card {
                position: relative;
                min-width: 0;
                padding: .72rem .75rem;
                border: 1px solid var(--border);
                border-left: 4px solid #adb5bd;
                border-radius: 13px;
                background: var(--surface);
                box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
                transition:
                    border-color .15s ease,
                    background-color .15s ease,
                    box-shadow .15s ease;
            }

            .mobile-beneficiary-card.status-verified {
                border-left-color: #198754;
            }

            .mobile-beneficiary-card.status-corrections {
                border-left-color: #f0ad00;
            }

            .mobile-beneficiary-card.status-caution {
                border-left-color: #dc3545;
            }

            .mobile-beneficiary-card.is-selected {
                border-color: rgba(25, 135, 84, .58);
                border-left-color: #198754;
                background: #eef9f3;
                box-shadow: 0 7px 18px rgba(25, 135, 84, .10);
            }

            body.dark-mode .mobile-beneficiary-card.is-selected {
                background: #244c3a;
            }

            .mobile-beneficiary-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .6rem;
                min-width: 0;
            }

            .mobile-beneficiary-payroll {
                display: flex;
                align-items: center;
                gap: .38rem;
                min-width: 0;
                color: var(--text-soft);
                font-size: .67rem;
                line-height: 1.2;
            }

            .mobile-beneficiary-payroll strong {
                color: var(--text-main);
                font-size: .76rem;
                font-weight: 800;
            }

            .mobile-beneficiary-commodity {
                overflow: hidden;
                max-width: 120px;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mobile-beneficiary-status {
                display: inline-flex;
                align-items: center;
                gap: .28rem;
                max-width: 48%;
                padding: .24rem .42rem;
                border-radius: 999px;
                background: var(--surface-muted);
                color: var(--text-soft);
                font-size: .59rem;
                font-weight: 750;
                line-height: 1.1;
                white-space: nowrap;
            }

            .mobile-beneficiary-status.status-verified {
                background: rgba(25, 135, 84, .11);
                color: #198754;
            }

            .mobile-beneficiary-status.status-corrections {
                background: rgba(255, 193, 7, .16);
                color: #9a7000;
            }

            .mobile-beneficiary-status.status-caution {
                background: rgba(220, 53, 69, .11);
                color: #dc3545;
            }

            body.dark-mode .mobile-beneficiary-status.status-corrections {
                color: #ffd76a;
            }

            .mobile-beneficiary-name {
                margin-top: .52rem;
                color: var(--text-main);
                font-size: .94rem;
                font-weight: 800;
                line-height: 1.28;
                overflow-wrap: anywhere;
            }

            .mobile-beneficiary-meta {
                display: grid;
                gap: .22rem;
                margin-top: .38rem;
                color: var(--text-soft);
                font-size: .65rem;
                line-height: 1.35;
            }

            .mobile-beneficiary-meta-line {
                display: flex;
                align-items: flex-start;
                gap: .36rem;
                min-width: 0;
            }

            .mobile-beneficiary-meta-line > i {
                width: 13px;
                flex: 0 0 13px;
                margin-top: .13rem;
                color: var(--da-green);
                text-align: center;
            }

            .mobile-beneficiary-meta-value {
                min-width: 0;
                overflow-wrap: anywhere;
            }

            .mobile-beneficiary-footer {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: .6rem;
                margin-top: .55rem;
                padding-top: .5rem;
                border-top: 1px solid var(--border);
            }


            .mobile-beneficiary-view {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                gap: .35rem;
                min-width: 76px;
                height: 34px;
                padding: 0 .65rem !important;
                border-radius: 9px;
                font-size: .72rem;
                line-height: 1;
            }

            .mobile-beneficiary-view i {
                margin: 0 !important;
                line-height: 1;
            }

            .mobile-beneficiary-empty {
                padding: 1rem;
                border: 1px dashed var(--border);
                border-radius: 12px;
                background: var(--surface-muted);
                color: var(--text-soft);
                font-size: .72rem;
                text-align: center;
            }

            /* DataTables controls remain functional but are stacked cleanly. */
            #table_local_wrapper .dataTables_length,
            #table_local_wrapper .dataTables_filter {
                float: none !important;
                width: 100%;
                margin: 0 0 .5rem !important;
                text-align: left !important;
            }

            #table_local_wrapper .dataTables_filter label {
                display: flex;
                align-items: center;
                gap: .45rem;
                width: 100%;
            }

            #table_local_wrapper .dataTables_filter input {
                min-width: 0;
                flex: 1 1 auto;
                width: 100% !important;
                margin-left: 0 !important;
            }

            #table_local_wrapper .dataTables_info {
                float: none !important;
                padding-top: .7rem !important;
                text-align: center;
            }

            #table_local_wrapper .dataTables_paginate {
                float: none !important;
                width: 100%;
                margin-top: .55rem !important;
                padding-top: .15rem;
                text-align: center !important;
            }
        }

        thead input,
        .dataTables_wrapper input,
        .dataTables_wrapper select {
            border: 1px solid var(--border) !important;
            border-radius: 9px !important;
            background: var(--surface) !important;
            color: var(--text-main) !important;
            min-height: 36px;
            padding: .35rem .55rem;
            outline: none;
        }
        thead input { width: 100%; min-width: 90px; }
        .dataTables_wrapper .dataTables_filter input { margin-left: .5rem; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: var(--text-soft) !important; font-size: .78rem; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px !important;
            color: var(--text-main) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            border-color: var(--da-green) !important;
            background: var(--da-green) !important;
            color: #fff !important;
        }

        #farmerDetailsContainer { align-self: flex-start; }
        @media (min-width: 992px) {
            #farmerDetailsContainer { position: sticky; top: 86px; }
        }

        .details-card { padding: 1.15rem; }
        .farmer-name {
            color: var(--text-main);
            font-size: clamp(1.45rem, 2.2vw, 2rem) !important;
            font-weight: 800 !important;
            line-height: 1.08;
            word-break: break-word;
        }
        .farmer-meta {
            color: var(--text-soft);
            font-size: .78rem !important;
            font-weight: 600 !important;
        }

        #farmerBDay {
            font-size: 1.05rem !important;
            line-height: 1.35;
            color: var(--text-main);
        }

        .number-tile {
            height: 100%;
            padding: .8rem .45rem;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: var(--surface-muted);
            text-align: center;
        }
        .number-tile .value {
            margin: 0;
            color: var(--text-main);
            font-size: clamp(1.35rem, 3vw, 2rem) !important;
            font-weight: 800 !important;
            line-height: 1.1;
        }
        .number-tile .label {
            margin: .3rem 0 0;
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 700;
        }

        .action-grid .btn {
            min-height: 76px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .7rem .55rem;
            font-size: .9rem;
            font-weight: 400;
            line-height: 1.15;
            text-align: center;
            overflow: hidden;
        }

        .action-grid .btn i {
            display: block !important;
            margin: 0 !important;
            font-size: 1.2rem;
            line-height: 1;
            flex-shrink: 0;
        }

        .action-grid .btn span {
            display: block !important;
            width: 100%;
            max-width: 100%;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.15;
        }

        .summary-card {
            padding: .75rem .8rem;
            border-radius: 14px !important;
        }

        .summary-compact-head {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-width: 0;
        }

        .summary-icon {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 10px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .85rem;
        }

        body.dark-mode .summary-icon {
            background: rgba(25, 135, 84, .17);
        }

        .summary-main {
            min-width: 0;
            flex: 1 1 auto;
        }

        .summary-kicker {
            overflow: hidden;
            color: var(--text-soft);
            font-size: .64rem;
            font-weight: 600;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .summary-total-row {
            display: flex;
            align-items: baseline;
            gap: .4rem;
            margin-top: .12rem;
        }

        .summary-total {
            color: var(--text-main);
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
        }

        .summary-total-label {
            color: var(--text-soft);
            font-size: .62rem;
            white-space: nowrap;
        }

        .summary-progress {
            height: 6px !important;
            margin-top: .65rem;
            overflow: hidden;
            border-radius: 999px;
            background: var(--surface-muted);
        }

        .summary-progress .progress-bar {
            font-size: 0;
        }

        .summary-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .45rem;
            margin-top: .55rem;
        }

        .summary-metric {
            display: flex;
            align-items: center;
            min-width: 0;
            gap: .4rem;
            padding: .35rem .45rem;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-muted);
        }

        .summary-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
        }

        .summary-dot.verified { background: #198754; }
        .summary-dot.corrected { background: #ffc107; }

        /* ---------------- Payout verifier chat ---------------- */
        .verify-chat-card {
            margin-top: 1rem;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .verify-chat-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem;
            padding: .7rem .75rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .verify-chat-title-wrap {
            display: flex;
            align-items: center;
            gap: .55rem;
            min-width: 0;
        }

        .verify-chat-icon {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border-radius: 9px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .8rem;
        }

        .verify-chat-title {
            color: var(--text-main);
            font-size: .92rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .verify-chat-subtitle {
            margin-top: .12rem;
            overflow: hidden;
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .verify-chat-status {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            flex: 0 0 auto;
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 700;
        }

        .verify-chat-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #6c757d;
        }

        .verify-chat-status.is-online .verify-chat-status-dot {
            background: #198754;
        }

        .verify-chat-status.is-loading .verify-chat-status-dot {
            background: #ffc107;
        }

        .verify-chat-messages {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            min-height: 145px;
            max-height: 300px;
            padding: .7rem;
            overflow-y: auto;
            overscroll-behavior: contain;
            background: var(--surface);
        }

        .verify-chat-empty {
            display: grid;
            place-items: center;
            min-height: 125px;
            padding: 1rem;
            color: var(--text-soft);
            font-size: .8rem;
            line-height: 1.5;
            text-align: center;
        }

        .verify-chat-message {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            max-width: 88%;
        }

        .verify-chat-message.is-own {
            align-self: flex-end;
            align-items: flex-end;
        }

        .verify-chat-meta {
            display: flex;
            align-items: center;
            gap: .35rem;
            max-width: 100%;
            margin-bottom: .18rem;
            color: var(--text-soft);
            font-size: .7rem;
            line-height: 1.25;
        }

        .verify-chat-user {
            overflow: hidden;
            max-width: 135px;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .verify-chat-payout {
            display: inline-flex;
            align-items: center;
            max-width: 150px;
            padding: .12rem .32rem;
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .64rem;
            font-weight: 750;
            line-height: 1.2;
        }

        .verify-chat-payout-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .verify-chat-bubble {
            max-width: 100%;
            padding: .5rem .6rem;
            border: 1px solid var(--border);
            border-radius: 11px 11px 11px 4px;
            background: var(--surface-muted);
            color: var(--text-main);
            font-size: .86rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .verify-chat-message.is-own .verify-chat-bubble {
            border-color: rgba(25,135,84,.24);
            border-radius: 11px 11px 4px 11px;
            background: var(--da-green-soft);
        }

        .verify-chat-compose {
            padding: .65rem;
            border-top: 1px solid var(--border);
            background: var(--surface-muted);
        }

        .verify-chat-input-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 38px;
            gap: .45rem;
            align-items: end;
        }

        .verify-chat-input {
            min-height: 38px !important;
            max-height: 100px;
            resize: vertical;
            border-color: var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--text-main);
            font-size: .84rem;
            line-height: 1.4;
        }

        .verify-chat-send {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0 !important;
            border-radius: 10px;
            line-height: 1;
        }

        .verify-chat-send i {
            margin: 0 !important;
            line-height: 1;
        }

        .verify-chat-compose-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            margin-top: .35rem;
            color: var(--text-soft);
            font-size: .68rem;
        }

        .verify-chat-compose-meta strong {
            overflow: hidden;
            max-width: 150px;
            color: var(--text-main);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Verifier Chat dark-mode contrast */
        body.dark-mode .verify-chat-card {
            border-color: #354039;
            background: #171b18;
        }

        body.dark-mode .verify-chat-head,
        body.dark-mode .verify-chat-compose {
            border-color: #354039;
            background: #111512;
        }

        body.dark-mode .verify-chat-messages {
            background: #171b18;
        }

        body.dark-mode .verify-chat-title {
            color: #f3f6f4;
        }

        body.dark-mode .verify-chat-subtitle,
        body.dark-mode .verify-chat-status,
        body.dark-mode .verify-chat-meta,
        body.dark-mode .verify-chat-empty,
        body.dark-mode .verify-chat-compose-meta {
            color: #b9c3bd;
        }

        body.dark-mode .verify-chat-user {
            color: #eef4f0;
        }

        body.dark-mode .verify-chat-payout {
            background: rgba(25, 135, 84, .24);
            color: #9be5bb;
        }

        body.dark-mode .verify-chat-bubble {
            border-color: #3a463f;
            background: #232a25;
            color: #f2f6f3;
        }

        body.dark-mode .verify-chat-message.is-own .verify-chat-bubble {
            border-color: rgba(76, 201, 134, .42);
            background: rgba(25, 135, 84, .30);
            color: #f4fff8;
        }

        body.dark-mode .verify-chat-input {
            border-color: #3a463f;
            background: #0f1310;
            color: #f3f6f4;
        }

        body.dark-mode .verify-chat-input::placeholder {
            color: #8f9b94;
            opacity: 1;
        }

        body.dark-mode .verify-chat-compose-meta strong {
            color: #ffffff;
        }

        .summary-metric-label {
            min-width: 0;
            flex: 1 1 auto;
            overflow: hidden;
            color: var(--text-soft);
            font-size: .62rem;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .summary-metric-value {
            flex: 0 0 auto;
            color: var(--text-main);
            font-size: .76rem;
            font-weight: 700;
            line-height: 1;
        }

        .progress {
            overflow: hidden;
            border-radius: 999px;
            background: var(--surface-muted);
        }

        .empty-profile {
            padding: 1.5rem;
            color: var(--text-soft);
            text-align: center;
        }
        .empty-profile .empty-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            margin: 0 auto .8rem;
            border-radius: 15px;
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .analytics-card { margin-bottom: 1.5rem; }
        .chart-box { min-height: 420px; height: 52vh; max-height: 520px; }

        footer { border-top: 1px solid var(--border); background: transparent !important; }
        .copyright { color: var(--text-soft) !important; font-size: .78rem; }

        #Home {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 1010;
            display: none;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            padding: 0;
            border: none;
            outline: none;
            border-radius: 5px;
            background: var(--da-green) !important;
            color: #fff !important;
            box-shadow: none !important;
            line-height: 1;
        }
        #Home:hover { opacity: .7; }
        #Home i { margin: 0; line-height: 1; }

        .mypopup {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2200 !important;
            transform: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            background: rgba(255, 255, 255, .96) !important;
            align-items: center;
            justify-content: center;
        }
        body.dark-mode .mypopup { background: rgba(15, 18, 16, .96) !important; }
        #loading-container {
            width: auto !important;
            height: auto !important;
            padding: 55px;
            border-radius: 120px;
            background: var(--da-green) !important;
            box-shadow: 0 16px 40px rgba(25, 135, 84, .22);
        }

        .modal-content {
            border: 1px solid var(--border) !important;
            border-radius: 18px !important;
            background: var(--surface) !important;
            color: var(--text-main) !important;
            overflow: hidden;
        }
        .modal-header { border-bottom-color: var(--border) !important; }
        .modal-footer { border-top-color: var(--border) !important; }
        body.dark-mode .modal-content,
        body.dark-mode .modal-header,
        body.dark-mode .modal-body,
        body.dark-mode .modal-footer { background: var(--surface) !important; color: var(--text-main) !important; }

        .text-muted, .text-secondary { color: var(--text-soft) !important; }
        body.dark-mode .bg-white { background: var(--surface) !important; }
        body.dark-mode .card { color: var(--text-main) !important; }

        @media (max-width: 767.98px) {
            .brand-subtitle { display: none; }
            .topbar .btn-label { display: none; }
            .topbar .btn.square-mobile {
                width: 42px;
                height: 42px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
            }
            .topbar .btn.square-mobile i {
                margin: 0 !important;
                line-height: 1;
            }
            .page-shell { padding-top: 1rem; }
            #UsernameDisplay { display: none !important; }
            .surface-card .card-body { padding: .8rem; }
            .chart-box { min-height: 320px; height: 360px; }
        }

        @media (max-width: 575.98px) {
            #loading-container { padding: 42px; border-radius: 100px; }
            .loader { width: 92px; height: 92px; }
            .loader::after { top: -30px; right: -30px; bottom: -30px; left: -30px; border-width: 9px; }
            .loader img { width: 138px !important; height: 138px !important; }
            .farmer-name { font-size: 1.35rem !important; }
        }

        /* Camera modal */
        #cameraModal .modal-dialog {
            max-width: 1120px;
        }

        #cameraModal .modal-content {
            border: 1px solid var(--border) !important;
            border-radius: 22px !important;
            background: var(--surface) !important;
            box-shadow: 0 24px 70px rgba(20, 46, 31, .18) !important;
            overflow: hidden;
        }

        #cameraModal .modal-header {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border) !important;
            background:
                linear-gradient(135deg, rgba(25, 135, 84, .08), transparent 65%),
                var(--surface) !important;
        }

        .camera-header-icon {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 13px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: 1.05rem;
        }

        body.dark-mode .camera-header-icon {
            background: rgba(25, 135, 84, .17);
        }

        .camera-header-kicker {
            margin-bottom: .15rem;
            color: var(--text-soft);
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        #cameraModalTitle {
            max-width: 720px;
            margin: 0;
            color: var(--text-main);
            font-size: clamp(1rem, 2vw, 1.25rem);
            font-weight: 700;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .camera-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface);
            color: var(--text-main);
            box-shadow: none !important;
        }

        .camera-modal-close:hover,
        .camera-modal-close:focus {
            border-color: rgba(220, 53, 69, .4);
            background: rgba(220, 53, 69, .08);
            color: #dc3545;
        }

        #cameraModal .modal-body {
            padding: 1.15rem;
            background: var(--surface-muted);
        }

        .capture-stage-heading {
            margin-bottom: 1rem;
            text-align: center;
        }

        .capture-stage-kicker {
            color: var(--da-green);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        #captureTitle {
            margin: .25rem 0 0;
            color: var(--text-main);
            font-size: clamp(1.1rem, 2.3vw, 1.45rem);
            font-weight: 700;
            line-height: 1.25;
        }

        .capture-stage-help {
            margin: .35rem 0 0;
            color: var(--text-soft);
            font-size: .76rem;
        }

        #previewContainer {
            width: min(100%, 650px) !important;
            max-width: 650px !important;
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(25, 135, 84, .22) !important;
            border-radius: 20px !important;
            background: #080a09;
            box-shadow: 0 14px 36px rgba(0, 0, 0, .14);
        }

        #cameraContainer,
        #imageContainer {
            width: 100%;
            height: 100%;
        }

        #cameraVideo,
        #capturedImage {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        #captureGuide {
            border: 3px dashed rgba(255, 255, 255, .95) !important;
            border-radius: 12px !important;
            box-shadow: 0 0 0 999px rgba(0, 0, 0, .08);
        }

        .camera-control-panel {
            width: min(100%, 650px);
            margin: 1rem auto 0;
            padding: .9rem;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .camera-select-label {
            margin-bottom: .4rem;
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        #cameraDropdownButton {
            min-height: 44px;
            border-radius: 11px;
            background: var(--surface);
            color: var(--text-main);
            border-color: var(--border);
            font-weight: 400;
        }

        #cameraList {
            max-height: 260px;
            overflow-y: auto;
            border-color: var(--border);
            border-radius: 12px;
            background: var(--surface);
        }

        #cameraList .dropdown-item {
            color: var(--text-main);
            font-size: .82rem;
        }

        body.dark-mode #cameraList .dropdown-item:hover,
        body.dark-mode #cameraList .dropdown-item:focus {
            background: rgba(25, 135, 84, .12);
        }

        .camera-action-row {
            margin-top: .75rem;
        }

        .camera-action-row .btn {
            min-height: 54px;
            border-radius: 12px;
            font-size: .88rem;
            font-weight: 400 !important;
        }

        .camera-action-row .btn > div {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .camera-action-row .btn i {
            margin: 0;
            font-size: 1rem;
        }

        #captureBtn .btn {
            background: var(--da-green);
            border-color: var(--da-green);
        }

        #captureBtn .btn:hover,
        #captureBtn .btn:focus {
            background: var(--da-green-dark);
            border-color: var(--da-green-dark);
        }

        #nextBtn .btn {
            border-color: rgba(25, 135, 84, .45);
            color: var(--da-green);
        }

        #nextBtn .btn:hover,
        #nextBtn .btn:focus {
            background: var(--da-green);
            border-color: var(--da-green);
            color: #fff;
        }

        #reviewContainer {
            width: min(100%, 920px);
            margin: 0 auto !important;
        }

        .camera-review-intro {
            margin-bottom: 1rem;
            text-align: center;
        }

        .camera-review-grid .review-photo-card {
            height: 100%;
            padding: .65rem;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .review-photo-label {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .55rem;
            color: var(--text-main);
            font-size: .76rem;
            font-weight: 600;
        }

        .review-photo-label i {
            color: var(--da-green);
        }

        .camera-review-grid img {
            width: 100%;
            border: 0 !important;
            border-radius: 12px !important;
            background: #090b0a;
        }

        .camera-review-actions {
            width: min(100%, 650px);
            margin: 1rem auto 0;
            padding: .85rem;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
        }

        #reviewBtnContainer {
            margin-top: 0 !important;
        }

        #reviewBtnContainer .btn {
            min-height: 50px;
            border-radius: 11px;
            font-weight: 400 !important;
        }

        #uploadProgressContainer {
            margin-top: .25rem !important;
            padding: .85rem;
            border-radius: 13px;
            background: var(--surface-muted);
        }

        #uploadProgressContainer .progress {
            height: 18px !important;
            border-radius: 999px;
        }

        #uploadProgressText {
            color: var(--text-soft);
            font-size: .76rem;
            font-weight: 600 !important;
        }

        .camera-progress-title {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            #cameraModal .modal-dialog {
                margin: .5rem;
                min-height: calc(100% - 1rem);
            }

            #cameraModal .modal-content {
                border-radius: 18px !important;
            }

            #cameraModal .modal-header {
                padding: .8rem;
            }

            .camera-header-icon {
                width: 40px;
                height: 40px;
                flex-basis: 40px;
            }

            #cameraModal .modal-body {
                padding: .8rem;
            }

            #previewContainer {
                border-radius: 16px !important;
            }

            .camera-control-panel {
                padding: .75rem;
                border-radius: 14px;
            }

            .camera-action-row .btn {
                min-height: 52px;
            }

            .camera-review-grid {
                --bs-gutter-x: .75rem;
                --bs-gutter-y: .75rem;
            }

            .camera-review-grid .review-photo-card {
                padding: .5rem;
                border-radius: 13px;
            }

            .camera-review-actions {
                padding: .7rem;
            }
        }

        @media (max-width: 575.98px) {
            #cameraModalTitle {
                max-width: calc(100vw - 150px);
            }

            .capture-stage-help {
                font-size: .72rem;
            }

            .camera-action-row {
                --bs-gutter-x: .5rem;
            }

            .camera-action-row .btn {
                padding-left: .45rem;
                padding-right: .45rem;
                font-size: .8rem;
            }

            .review-photo-label {
                font-size: .7rem;
            }
        }

        /* Keep the capture screen inside the visible viewport whenever possible. */
        #cameraModal .modal-dialog {
            width: min(1120px, calc(100vw - 1rem));
            max-width: 1120px;
            margin-top: .5rem;
            margin-bottom: .5rem;
        }

        #cameraModal .modal-content {
            max-height: calc(100dvh - 1rem);
        }

        #cameraModal .modal-body {
            overflow: hidden;
        }

        #cameraModal.review-mode .modal-body {
            overflow-y: auto;
        }

        #previewContainer {
            width: var(--camera-preview-size, min(650px, calc(100vw - 3rem))) !important;
            height: var(--camera-preview-size, min(650px, calc(100vw - 3rem))) !important;
            max-width: 100% !important;
            aspect-ratio: 1 / 1;
            flex: 0 0 auto;
        }

        #cameraContainer,
        #imageContainer {
            width: 100%;
            height: 100%;
        }

        @media (max-width: 767.98px) {
            #cameraModal .modal-dialog {
                width: calc(100vw - 1rem);
            }

            #cameraModal .modal-body {
                padding-top: .7rem;
                padding-bottom: .7rem;
            }

            .capture-stage-heading {
                margin-bottom: .65rem;
            }

            .camera-control-panel {
                margin-top: .65rem;
            }
        }

        @media (max-height: 700px) {
            #cameraModal .modal-header {
                padding-top: .65rem;
                padding-bottom: .65rem;
            }

            .camera-header-icon {
                width: 38px;
                height: 38px;
                flex-basis: 38px;
            }

            .capture-stage-heading {
                margin-bottom: .5rem;
            }

            #captureTitle {
                font-size: 1rem;
            }

            .camera-control-panel {
                margin-top: .5rem;
                padding: .65rem;
            }

            #cameraDropdownButton {
                min-height: 38px;
            }

            .camera-action-row {
                margin-top: .5rem;
            }

            .camera-action-row .btn {
                min-height: 44px;
            }
        }


        /* Exact viewport-fit camera workspace */
        #cameraModal.capture-mode .modal-body {
            display: flex;
            flex-direction: column;
            overflow: hidden !important;
            min-height: 0;
        }

        #cameraModal.capture-mode .capture-stage-heading {
            flex: 0 0 auto;
        }

        #cameraModal.capture-mode #previewContainer {
            flex: 0 0 auto;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        #cameraModal.capture-mode .camera-control-panel {
            flex: 0 0 auto;
        }

        /* While reviewing one captured image, only keep Retake / Next controls. */
        #cameraModal.captured-preview-mode .camera-select-label,
        #cameraModal.captured-preview-mode .camera-control-panel > .dropdown {
            display: none !important;
        }

        #cameraModal.captured-preview-mode .camera-control-panel {
            padding-top: .65rem;
            padding-bottom: .65rem;
        }

        #cameraModal.captured-preview-mode .camera-action-row {
            margin-top: 0;
        }

        /* Final review actions belong only to final review mode. */
        #cameraModal:not(.review-mode) .camera-review-actions {
            display: none !important;
        }

        #cameraModal.review-mode .camera-review-actions {
            display: block;
        }

        @media (max-height: 620px) {
            #cameraModal .modal-header {
                padding-top: .5rem;
                padding-bottom: .5rem;
            }

            #cameraModal.capture-mode .capture-stage-heading {
                margin-bottom: .35rem;
            }

            #cameraModal.capture-mode .camera-control-panel {
                margin-top: .35rem;
            }

            #cameraModal.capture-mode .camera-action-row .btn {
                min-height: 40px;
            }
        }


        /* Keep live video and captured image on exactly the same resized bounds. */
        #previewContainer {
            position: relative;
        }

        #cameraContainer,
        #imageContainer {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            min-width: 100% !important;
            min-height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #cameraVideo,
        #capturedImage {
            position: absolute !important;
            inset: 0 !important;
            display: block;
            width: 100% !important;
            height: 100% !important;
            min-width: 100% !important;
            min-height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            object-fit: cover !important;
        }

        /* Final Review Captured Photos state. */
        #cameraModal.review-mode .camera-control-panel {
            display: none !important;
        }

        #cameraModal.review-mode .camera-review-actions {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        #cameraModal.review-mode #reviewBtnContainer {
            visibility: visible;
            opacity: 1;
        }

        /* Single captured-photo preview still uses the normal preview dimensions. */
        #cameraModal.captured-preview-mode #imageContainer,
        #cameraModal.captured-preview-mode #capturedImage {
            width: 100% !important;
            height: 100% !important;
        }


        /* Live camera and captured image always share exactly the same preview box. */
        #previewContainer,
        #cameraContainer,
        #imageContainer,
        #cameraVideo,
        #capturedImage {
            box-sizing: border-box !important;
        }

        #cameraContainer,
        #imageContainer,
        #cameraVideo,
        #capturedImage {
            width: 100% !important;
            height: 100% !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
        }

        #cameraVideo,
        #capturedImage {
            object-fit: cover !important;
            object-position: center center !important;
        }


        /* Smaller-screen modal fit */
        @media (max-width: 767.98px) {
            #cameraModal {
                padding: 0 !important;
            }

            #cameraModal .modal-dialog {
                width: calc(100vw - .5rem) !important;
                max-width: calc(100vw - .5rem) !important;
                height: calc(100dvh - .5rem);
                max-height: calc(100dvh - .5rem);
                margin: .25rem auto !important;
                display: flex;
                align-items: center;
            }

            #cameraModal .modal-content {
                width: 100%;
                max-height: calc(100dvh - .5rem) !important;
                border-radius: 14px !important;
            }

            #cameraModal .modal-header {
                flex: 0 0 auto;
                padding: .55rem .65rem !important;
                gap: .55rem !important;
            }

            #cameraModal .camera-header-icon {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
                border-radius: 9px;
                font-size: .9rem;
            }

            #cameraModal .camera-header-kicker {
                font-size: .58rem;
                margin-bottom: .05rem;
            }

            #cameraModalTitle {
                font-size: .9rem !important;
                line-height: 1.15;
            }

            #cameraModal .camera-modal-close {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
                border-radius: 9px;
            }

            #cameraModal .modal-body {
                min-height: 0;
                padding: .55rem !important;
            }

            #cameraModal.capture-mode .modal-body {
                display: flex;
                flex-direction: column;
            }

            #cameraModal .capture-stage-heading {
                margin-bottom: .35rem !important;
            }

            #cameraModal #captureTitle {
                margin-top: 0 !important;
                font-size: .95rem !important;
                line-height: 1.15;
            }

            #cameraModal .camera-control-panel {
                margin-top: .4rem !important;
                padding: .5rem !important;
                border-radius: 11px;
            }

            #cameraModal .camera-select-label {
                margin-bottom: .25rem;
                font-size: .6rem;
            }

            #cameraModal #cameraDropdownButton {
                min-height: 34px !important;
                padding-top: .3rem;
                padding-bottom: .3rem;
                font-size: .75rem;
                border-radius: 9px;
            }

            #cameraModal .camera-action-row {
                margin-top: .35rem !important;
                --bs-gutter-x: .4rem;
            }

            #cameraModal .camera-action-row .btn {
                min-height: 38px !important;
                padding: .35rem .45rem;
                font-size: .75rem !important;
                border-radius: 9px;
            }

            #cameraModal .camera-action-row .btn i {
                font-size: .85rem;
            }

            #cameraModal.review-mode .modal-body {
                overflow-y: auto !important;
            }

            #cameraModal.review-mode #reviewContainer {
                width: 100%;
            }

            #cameraModal .camera-review-grid {
                --bs-gutter-x: .45rem;
                --bs-gutter-y: .45rem;
            }

            #cameraModal .camera-review-grid .review-photo-card {
                padding: .35rem;
                border-radius: 10px;
            }

            #cameraModal .review-photo-label {
                margin-bottom: .3rem;
                font-size: .62rem;
            }

            #cameraModal .camera-review-grid img {
                border-radius: 8px !important;
            }

            #cameraModal .camera-review-actions {
                margin-top: .55rem !important;
                padding: .5rem !important;
                border-radius: 11px;
            }

            #cameraModal #reviewBtnContainer .btn {
                min-height: 38px !important;
                padding: .35rem .45rem;
                font-size: .75rem;
                border-radius: 9px;
            }

            #cameraModal #uploadProgressContainer {
                padding: .55rem;
            }
        }

        @media (max-width: 575.98px) {
            #cameraModal .modal-dialog {
                width: calc(100vw - .25rem) !important;
                max-width: calc(100vw - .25rem) !important;
                height: calc(100dvh - .25rem);
                max-height: calc(100dvh - .25rem);
                margin: .125rem auto !important;
            }

            #cameraModal .modal-content {
                max-height: calc(100dvh - .25rem) !important;
                border-radius: 11px !important;
            }

            #cameraModal .modal-header {
                padding: .45rem .5rem !important;
            }

            #cameraModal .modal-body {
                padding: .45rem !important;
            }

            #cameraModal .camera-control-panel {
                padding: .4rem !important;
            }
        }

        @media (max-height: 650px) {
            #cameraModal .modal-header {
                padding-top: .4rem !important;
                padding-bottom: .4rem !important;
            }

            #cameraModal .camera-header-kicker {
                display: none;
            }

            #cameraModal .capture-stage-heading {
                margin-bottom: .25rem !important;
            }

            #cameraModal #captureTitle {
                font-size: .85rem !important;
            }

            #cameraModal .camera-control-panel {
                margin-top: .3rem !important;
                padding: .4rem !important;
            }

            #cameraModal .camera-select-label {
                display: none;
            }

            #cameraModal #cameraDropdownButton {
                min-height: 30px !important;
                font-size: .7rem;
            }

            #cameraModal .camera-action-row {
                margin-top: .25rem !important;
            }

            #cameraModal .camera-action-row .btn {
                min-height: 34px !important;
            }
        }


        /* Camera modal body remains content-driven; JS only resizes the preview. */
        #cameraModal .modal-body {
            height: auto !important;
            max-height: none !important;
        }


        /* Larger, more readable main-page typography.
           Camera modal sizing rules remain unchanged. */
        body {
            font-size: 1.05rem;
        }

        .brand-kicker {
            font-size: 1.05rem;
        }

        .brand-subtitle {
            font-size: .9rem;
        }

        .page-title {
            font-size: clamp(1.15rem, 2vw, 1.48rem);
        }

        .page-subtitle {
            font-size: .86rem;
        }

        .user-chip {
            font-size: .82rem;
        }

        #PendingBadge {
            font-size: .76rem;
        }

        #table_local thead th {
            font-size: .8rem;
        }

        #table_local tbody td {
            font-size: .93rem;
        }

        #table_local .viewFarmer {
            font-size: .9rem;
        }

        thead input,
        .dataTables_wrapper input,
        .dataTables_wrapper select {
            font-size: .9rem;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-size: .85rem;
        }

        .farmer-meta {
            font-size: .86rem !important;
        }

        #farmerBDay {
            font-size: 1.12rem !important;
        }

        .summary-kicker {
            font-size: .7rem;
        }

        .summary-total {
            font-size: 1.48rem;
        }

        .summary-total-label {
            font-size: .7rem;
        }

        .summary-metric-label {
            font-size: .7rem;
        }

        .summary-metric-value {
            font-size: .84rem;
        }

        .copyright {
            font-size: .84rem !important;
        }

        .action-grid .btn {
            font-size: .92rem;
        }

        @media (max-width: 575.98px) {
            .brand-kicker {
                font-size: 1rem;
            }

            .brand-subtitle {
                font-size: .84rem;
            }

            #table_local thead th {
                font-size: .76rem;
            }

            #table_local tbody td {
                font-size: .88rem;
            }

            .summary-metric-label {
                font-size: .68rem;
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


        /* Keep the payout/table title compact. */
        #TableTitle {
            display: block;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Analytics button: full label on larger screens, icon only on phones. */
        @media (max-width: 575.98px) {
            #btnAnalytics {
                width: 38px;
                min-width: 38px;
                height: 38px;
                padding: 0 !important;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 10px;
            }

            #btnAnalytics .btn-label,
            #btnAnalytics .analytics-label {
                display: none !important;
            }

            #btnAnalytics i {
                margin: 0 !important;
            }
        }


        @media (max-width: 575.98px) {
            .surface-card > .card-header {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center !important;
                gap: .5rem !important;
            }

            .verify-title-wrap {
                min-width: 0;
            }

            #TableTitle {
                width: 100%;
                margin: 0;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .verify-header-actions {
                flex-wrap: nowrap !important;
                flex: 0 0 auto;
                gap: .4rem !important;
            }

            .verify-header-actions .user-chip {
                display: none !important;
            }

            #btnAnalytics {
                flex: 0 0 38px;
            }
        }


        /* Redesigned PAFFP analytics dashboard */
        .analytics-dashboard {
            overflow: hidden;
        }

        .analytics-dashboard .analytics-header {
            padding: .9rem 1rem;
        }

        .analytics-eyebrow {
            margin-bottom: .15rem;
            color: var(--da-green);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .analytics-close {
            flex: 0 0 auto;
            min-height: 36px;
            border-radius: 10px;
        }

        .analytics-body {
            padding: .9rem !important;
        }

        .analytics-kpi {
            display: flex;
            align-items: center;
            gap: .7rem;
            height: 100%;
            min-height: 74px;
            padding: .65rem .7rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        .analytics-kpi-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: 10px;
            font-size: .82rem;
        }

        .analytics-kpi-icon.total,
        .analytics-kpi-icon.verifiers {
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .analytics-kpi-icon.completed {
            background: rgba(25, 135, 84, .12);
            color: #198754;
        }

        .analytics-kpi-icon.pending {
            background: rgba(108, 117, 125, .12);
            color: #6c757d;
        }

        body.dark-mode .analytics-kpi-icon.completed {
            background: rgba(25, 135, 84, .2);
            color: #72d29d;
        }

        body.dark-mode .analytics-kpi-icon.pending {
            background: rgba(173, 181, 189, .12);
            color: #adb5bd;
        }

        .analytics-kpi-value {
            overflow: hidden;
            color: var(--text-main);
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .analytics-kpi-label {
            margin-top: .25rem;
            overflow: hidden;
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .analytics-kpi-note {
            margin-left: .25rem;
            color: var(--da-green);
            font-weight: 700;
        }

        .analytics-panel {
            padding: .8rem;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
        }

        .analytics-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .55rem;
        }

        .analytics-panel-title {
            margin: 0;
            color: var(--text-main);
            font-size: .95rem;
            font-weight: 750;
            line-height: 1.2;
        }

        .analytics-panel-subtitle {
            margin-top: .12rem;
            color: var(--text-soft);
            font-size: .72rem;
            line-height: 1.25;
        }

        .analytics-donut-wrap {
            position: relative;
            width: min(230px, 100%);
            height: 230px;
            margin: 0 auto;
        }

        .analytics-donut-wrap canvas,
        .analytics-bar-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .analytics-donut-center {
            position: absolute;
            inset: 50% auto auto 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            text-align: center;
        }

        .analytics-donut-percent {
            color: var(--text-main);
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1;
        }

        .analytics-donut-label {
            margin-top: .2rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .analytics-status-legend {
            display: grid;
            gap: .35rem;
            margin-top: .4rem;
        }

        .analytics-status-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: .45rem;
            min-width: 0;
            padding: .35rem .45rem;
            border-radius: 8px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .76rem;
        }

        .analytics-status-item strong {
            color: var(--text-main);
            font-size: .82rem;
        }

        .analytics-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .analytics-status-dot.verified { background: #198754; }
        .analytics-status-dot.corrected { background: #ffc107; }
        .analytics-status-dot.pending { background: #adb5bd; }

        .analytics-bar-wrap {
            position: relative;
            min-height: 300px;
            height: 300px;
        }

        .analytics-verifier-list {
            display: grid;
            gap: .4rem;
        }

        .analytics-verifier-row {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) auto auto auto;
            align-items: center;
            gap: .55rem;
            min-width: 0;
            padding: .48rem .55rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-muted);
        }

        .analytics-rank {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--surface);
            color: var(--text-soft);
            font-size: .74rem;
            font-weight: 800;
        }

        .analytics-verifier-name {
            min-width: 0;
            overflow: hidden;
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .analytics-verifier-stat {
            min-width: 58px;
            color: var(--text-soft);
            font-size: .72rem;
            text-align: right;
            white-space: nowrap;
        }

        .analytics-verifier-stat strong {
            color: var(--text-main);
            font-size: .8rem;
        }

        .analytics-verifier-total {
            min-width: 48px;
            color: var(--da-green);
            font-size: .9rem;
            font-weight: 800;
            text-align: right;
        }

        .analytics-empty {
            padding: 1rem;
            color: var(--text-soft);
            font-size: .82rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .analytics-dashboard .analytics-header {
                padding: .75rem .8rem;
            }

            .analytics-body {
                padding: .7rem !important;
            }

            .analytics-kpi {
                min-height: 66px;
                padding: .55rem;
            }

            .analytics-kpi-icon {
                width: 32px;
                height: 32px;
                flex-basis: 32px;
            }

            .analytics-kpi-value {
                font-size: 1.28rem;
            }

            .analytics-donut-wrap {
                width: min(205px, 100%);
                height: 205px;
            }

            .analytics-bar-wrap {
                min-height: 260px;
                height: 260px;
            }

            .analytics-verifier-row {
                grid-template-columns: 30px minmax(0, 1fr) auto;
            }

            .analytics-verifier-stat {
                display: none;
            }
        }

        @media (max-width: 575.98px) {
            .analytics-dashboard .page-subtitle {
                display: none;
            }

            .analytics-close {
                width: 36px;
                min-width: 36px;
                height: 36px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .analytics-close i {
                margin: 0 !important;
            }

            .analytics-panel {
                padding: .65rem;
            }

            .analytics-bar-wrap {
                min-height: 240px;
                height: 240px;
            }
        }


        .analytics-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            min-width: 0;
            width: 100%;
        }

        .analytics-title-row #TableTitle2 {
            flex: 1 1 auto;
            min-width: 0;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .analytics-title-row .analytics-close {
            flex: 0 0 auto;
        }

        @media (max-width: 575.98px) {
            .analytics-title-row {
                gap: .5rem;
            }
        }


        #farmerDetails {
            position: relative;
        }

        .caution-farmer-btn {
            position: absolute;
            top: .7rem;
            right: .7rem;
            z-index: 5;
            width: 2.75rem;
            height: 2.75rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 1rem;
            background: var(--surface);
            box-shadow: 0 .2rem .65rem rgba(0, 0, 0, .08);
        }

        .caution-farmer-btn:hover,
        .caution-farmer-btn:focus {
            color: #fff;
        }

        body.dark-mode .caution-farmer-btn {
            background: var(--surface);
        }


        /* Keep the flag / unflag control visible without requiring hover. */
        #cautionFarmer.btn-outline-danger {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
            background: var(--surface) !important;
        }

        #cautionFarmer.btn-outline-danger i {
            color: #dc3545 !important;
        }

        #cautionFarmer.btn-danger {
            color: #ffffff !important;
            border-color: #dc3545 !important;
            background: #dc3545 !important;
        }

        #cautionFarmer.btn-danger i {
            color: #ffffff !important;
        }

        #cautionFarmer.btn-outline-danger:hover,
        #cautionFarmer.btn-outline-danger:focus {
            color: #ffffff !important;
            border-color: #dc3545 !important;
            background: #dc3545 !important;
        }

        #cautionFarmer.btn-outline-danger:hover i,
        #cautionFarmer.btn-outline-danger:focus i {
            color: #ffffff !important;
        }

        #cautionFarmer.btn-danger:hover,
        #cautionFarmer.btn-danger:focus {
            color: #ffffff !important;
            border-color: #bb2d3b !important;
            background: #bb2d3b !important;
        }

        #cautionFarmer.btn-danger:hover i,
        #cautionFarmer.btn-danger:focus i {
            color: #ffffff !important;
        }

        body.dark-mode #cautionFarmer.btn-outline-danger {
            background: var(--surface) !important;
        }

        body.dark-mode #cautionFarmer.btn-danger {
            background: #dc3545 !important;
        }


        /* Mark as Caution: solid red even before hover. */
        #cautionFarmer.btn-outline-danger {
            color: #ffffff !important;
            border-color: #dc3545 !important;
            background: #dc3545 !important;
        }

        #cautionFarmer.btn-outline-danger i {
            color: #ffffff !important;
        }

        #cautionFarmer.btn-outline-danger:hover,
        #cautionFarmer.btn-outline-danger:focus {
            color: #ffffff !important;
            border-color: #bb2d3b !important;
            background: #bb2d3b !important;
        }

        /* Flagged / Unflag state: darker red so it remains distinguishable. */
        #cautionFarmer.btn-danger {
            color: #ffffff !important;
            border-color: #a52834 !important;
            background: #b02a37 !important;
        }

        #cautionFarmer.btn-danger i {
            color: #ffffff !important;
        }

        #cautionFarmer.btn-danger:hover,
        #cautionFarmer.btn-danger:focus {
            color: #ffffff !important;
            border-color: #842029 !important;
            background: #842029 !important;
        }


        .caution-warning-popup {
            position: fixed;
            top: 82px;
            left: 50%;
            z-index: 99999;
            width: min(92vw, 520px);
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .8rem .95rem;
            border: 1px solid #dc3545;
            border-radius: 12px;
            background: #dc3545;
            color: #fff;
            box-shadow: 0 .75rem 2rem rgba(0, 0, 0, .2);
            opacity: 0;
            visibility: hidden;
            transform: translate(-50%, -12px);
            transition:
                opacity .2s ease,
                transform .2s ease,
                visibility .2s ease;
            pointer-events: none;
        }

        .caution-warning-popup.show {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, 0);
        }

        .caution-warning-icon {
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: rgba(255, 255, 255, .16);
            font-size: .95rem;
        }

        .caution-warning-text {
            min-width: 0;
            font-size: .86rem;
            font-weight: 600;
            line-height: 1.35;
        }

        @media (max-width: 575.98px) {
            .caution-warning-popup {
                top: 70px;
                width: calc(100vw - 24px);
                padding: .7rem .8rem;
                border-radius: 10px;
            }

            .caution-warning-icon {
                flex-basis: 30px;
                width: 30px;
                height: 30px;
                font-size: .85rem;
            }

            .caution-warning-text {
                font-size: .78rem;
            }
        }


        /* Centered caution warning with pulsing flag */
        .caution-warning-popup {
            flex-direction: column !important;
            justify-content: center;
            text-align: center;
            gap: .6rem !important;
        }

        .caution-warning-icon {
            width: 44px !important;
            height: 44px !important;
            flex: 0 0 44px !important;
            border-radius: 12px !important;
            font-size: 1.15rem !important;
            animation: cautionFlagPulse 1.15s ease-in-out infinite;
        }

        .caution-warning-text {
            width: 100%;
            text-align: center;
        }

        .caution-warning-title {
            font-size: .92rem;
            font-weight: 800;
            letter-spacing: .045em;
            line-height: 1.2;
        }

        .caution-warning-message {
            margin-top: .18rem;
            font-size: .8rem;
            font-weight: 500;
            line-height: 1.35;
        }

        @keyframes cautionFlagPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, .24);
            }
            50% {
                transform: scale(1.12);
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
            }
        }

        @media (max-width: 575.98px) {
            .caution-warning-popup {
                gap: .5rem !important;
            }

            .caution-warning-icon {
                width: 40px !important;
                height: 40px !important;
                flex-basis: 40px !important;
                font-size: 1rem !important;
            }

            .caution-warning-title {
                font-size: .84rem;
            }

            .caution-warning-message {
                font-size: .74rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .caution-warning-icon {
                animation: none !important;
            }
        }


        /* Center the caution popup in the viewport. */
        .caution-warning-popup {
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) scale(.96) !important;
        }

        .caution-warning-popup.show {
            transform: translate(-50%, -50%) scale(1) !important;
        }

        @media (max-width: 575.98px) {
            .caution-warning-popup {
                top: 50% !important;
                left: 50% !important;
            }
        }


        /* Larger caution flag icon */
        .caution-warning-icon {
            width: 64px !important;
            height: 64px !important;
            flex: 0 0 64px !important;
            border-radius: 16px !important;
            font-size: 1.65rem !important;
        }

        @media (max-width: 575.98px) {
            .caution-warning-icon {
                width: 56px !important;
                height: 56px !important;
                flex-basis: 56px !important;
                border-radius: 14px !important;
                font-size: 1.45rem !important;
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

        /* Verify banner immediately below the beneficiary table. */
        .verify-container-local-ad {
            margin-top: 1rem;
            margin-bottom: 0;
        }


        /* Native ad shown only at the bottom of the Analytics dashboard. */
        .analytics-native-ad {
            margin-top: 1rem;
            margin-bottom: 0;
        }

        .adsterra-native-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow: hidden;
        }

        .adsterra-native-frame {
            display: block;
            width: 100%;
            max-width: 100%;
            height: 250px;
            border: 0;
            background: transparent;
            overflow: hidden;
        }


        /* PAFFP live announcement region */
        .paffp-announcement-region {
            padding-top: .75rem;
        }

        .paffp-announcement-stack {
            display: grid;
            gap: .55rem;
        }

        .paffp-announcement {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: .7rem .85rem;
            border: 1px solid var(--border);
            border-left-width: 4px;
            border-radius: 12px;
            background: var(--surface);
            box-shadow: var(--shadow);
            animation: paffpAnnouncementIn .22s ease;
        }

        .paffp-announcement.info { border-left-color: #0dcaf0; }
        .paffp-announcement.success { border-left-color: #198754; }
        .paffp-announcement.warning { border-left-color: #ffc107; }
        .paffp-announcement.danger { border-left-color: #dc3545; }

        .paffp-announcement-icon {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 9px;
            background: var(--surface-muted);
            color: var(--da-green);
        }

        .paffp-announcement.warning .paffp-announcement-icon { color: #b58100; }
        .paffp-announcement.danger .paffp-announcement-icon { color: #dc3545; }
        .paffp-announcement.info .paffp-announcement-icon { color: #0aa2c0; }

        .paffp-announcement-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 36px;
            margin-top: .8rem;
            padding: .45rem .8rem;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-muted);
            color: var(--text-main);
            font-size: .74rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .paffp-announcement-home:hover,
        .paffp-announcement-home:focus {
            border-color: rgba(25, 135, 84, .35);
            background: var(--surface);
            color: var(--text-main);
        }

        .paffp-announcement-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .8rem;
        }

        .system-lock-announcement .paffp-announcement-actions {
            justify-content: center;
            width: 100%;
        }

        .paffp-announcement-actions .paffp-announcement-home {
            margin-top: 0;
        }

        .paffp-access-request-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 36px;
            padding: .45rem .8rem;
            border: 1px solid rgba(25, 135, 84, .45);
            border-radius: 9px;
            background: #198754;
            color: #fff;
            font-size: .74rem;
            font-weight: 700;
            cursor: pointer;
        }

        .paffp-access-request-btn:hover,
        .paffp-access-request-btn:focus {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }

        .paffp-access-request-btn:disabled {
            cursor: default;
            opacity: .7;
        }

        .access-expiry-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 31px;
            padding: .34rem .58rem;
            border: 1px solid rgba(25,135,84,.25);
            border-radius: 999px;
            background: rgba(25,135,84,.08);
            color: var(--da-green);
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .access-expiry-chip.is-view {
            border-color: rgba(13,110,253,.22);
            background: rgba(13,110,253,.07);
            color: #0d6efd;
        }

        #accessRequestReasonModal {
            z-index: 100200;
        }

        #accessRequestReasonModal textarea {
            min-height: 110px;
            resize: vertical;
        }

        .access-request-reason-counter {
            color: var(--text-soft);
            font-size: .7rem;
        }



        .paffp-announcement-body {
            min-width: 0;
            flex: 1 1 auto;
        }

        .paffp-announcement-title {
            color: var(--text-main);
            font-size: .82rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .paffp-announcement-message {
            margin-top: .18rem;
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.45;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .paffp-announcement-contact {
            color: inherit;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .paffp-announcement-contact:hover,
        .paffp-announcement-contact:focus {
            opacity: .78;
        }

        .paffp-announcement-username {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            margin-top: .65rem;
            color: var(--text-soft);
            font-size: .74rem;
            line-height: 1.4;
            text-align: center;
        }

        .paffp-announcement-username strong {
            color: var(--text-main);
            font-weight: 700;
        }


        .paffp-announcement-close {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--text-soft);
        }

        .paffp-announcement-close:hover {
            background: var(--surface-muted);
            color: var(--text-main);
        }

        @keyframes paffpAnnouncementIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 575.98px) {
            .paffp-announcement-region {
                padding-top: .55rem;
            }

            .paffp-announcement {
                gap: .55rem;
                padding: .62rem .68rem;
            }

            .paffp-announcement-icon {
                width: 30px;
                height: 30px;
                flex-basis: 30px;
                font-size: .82rem;
            }

            .paffp-announcement-title {
                font-size: .78rem;
            }

            .paffp-announcement-message {
                font-size: .7rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .paffp-announcement {
                animation: none;
            }
        }


        /* Announcement modal overlay */
        .paffp-announcement-overlay {
            position: fixed;
            inset: 0;
            z-index: 99990;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(7, 12, 9, .42);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
        }

        .paffp-announcement-overlay.d-none {
            display: none !important;
        }

        .paffp-announcement-modal {
            width: min(92vw, 560px);
            max-height: min(82vh, 720px);
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .28);
            animation: paffpAnnouncementModalIn .22s ease;
        }

        .paffp-announcement-stack {
            display: grid;
            gap: 0;
        }

        .paffp-announcement {
            margin: 0;
            padding: 1rem 1.05rem;
            border: 0;
            border-left: 5px solid transparent;
            border-radius: 0;
            box-shadow: none;
            background: var(--surface);
        }

        .paffp-announcement + .paffp-announcement {
            border-top: 1px solid var(--border);
        }

        .paffp-announcement:first-child {
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
        }

        .paffp-announcement:last-child {
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
        }

        .paffp-announcement.info { border-left-color: #0dcaf0; }
        .paffp-announcement.success { border-left-color: #198754; }
        .paffp-announcement.warning { border-left-color: #ffc107; }
        .paffp-announcement.danger { border-left-color: #dc3545; }

        .paffp-announcement-icon {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 11px;
            font-size: 1rem;
        }

        .paffp-announcement-title {
            font-size: .92rem;
        }

        .paffp-announcement-message {
            font-size: .8rem;
        }

        @keyframes paffpAnnouncementModalIn {
            from {
                opacity: 0;
                transform: scale(.97) translateY(8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @media (max-width: 575.98px) {
            .paffp-announcement-overlay {
                padding: .75rem;
            }

            .paffp-announcement-modal {
                width: min(94vw, 560px);
                max-height: 84vh;
                border-radius: 15px;
            }

            .paffp-announcement {
                padding: .85rem .9rem;
            }

            .paffp-announcement:first-child {
                border-top-left-radius: 15px;
                border-top-right-radius: 15px;
            }

            .paffp-announcement:last-child {
                border-bottom-left-radius: 15px;
                border-bottom-right-radius: 15px;
            }

            .paffp-announcement-icon {
                width: 36px;
                height: 36px;
                flex-basis: 36px;
                border-radius: 10px;
                font-size: .9rem;
            }

            .paffp-announcement-title {
                font-size: .84rem;
            }

            .paffp-announcement-message {
                font-size: .75rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .paffp-announcement-modal {
                animation: none !important;
            }
        }


        /* Redesigned vertical announcement modal */
        .paffp-announcement-modal {
            width: min(92vw, 520px);
            border-radius: 22px;
            overflow: hidden;
        }

        .paffp-announcement-stack {
            display: grid;
            gap: 0;
        }

        .paffp-announcement {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            padding: 1.6rem 1.35rem 1.45rem;
            border: 0 !important;
            background: var(--surface);
            text-align: center;
        }

        .paffp-announcement + .paffp-announcement {
            border-top: 1px solid var(--border) !important;
        }

        .paffp-announcement-icon {
            width: 78px;
            height: 78px;
            flex: 0 0 78px;
            display: grid;
            place-items: center;
            margin: 0 auto .95rem;
            border-radius: 50%;
            font-size: 2rem;
        }

        .paffp-announcement.info .paffp-announcement-icon {
            background: rgba(13, 202, 240, .12);
            color: #0aa2c0;
        }

        .paffp-announcement.success .paffp-announcement-icon {
            background: rgba(25, 135, 84, .12);
            color: #198754;
        }

        .paffp-announcement.warning .paffp-announcement-icon {
            background: rgba(255, 193, 7, .16);
            color: #b58100;
        }

        .paffp-announcement.danger .paffp-announcement-icon {
            background: rgba(220, 53, 69, .12);
            color: #dc3545;
        }

        .paffp-announcement-body {
            width: 100%;
            min-width: 0;
        }

        .paffp-announcement-title {
            margin: 0;
            color: var(--text-main);
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .paffp-announcement-message {
            max-width: 430px;
            margin: .55rem auto 0;
            color: var(--text-soft);
            font-size: .88rem;
            line-height: 1.55;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .paffp-announcement-close {
            position: absolute;
            top: .7rem;
            right: .7rem;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            font-size: .9rem;
        }

        @media (max-width: 575.98px) {
            .paffp-announcement-modal {
                width: min(94vw, 520px);
                border-radius: 18px;
            }

            .paffp-announcement {
                padding: 1.35rem 1rem 1.2rem;
            }

            .paffp-announcement-icon {
                width: 68px;
                height: 68px;
                flex-basis: 68px;
                margin-bottom: .8rem;
                font-size: 1.7rem;
            }

            .paffp-announcement-title {
                font-size: 1.05rem;
            }

            .paffp-announcement-message {
                margin-top: .45rem;
                font-size: .8rem;
                line-height: 1.5;
            }

            .paffp-announcement-close {
                top: .55rem;
                right: .55rem;
                width: 32px;
                height: 32px;
            }
        }


        /* Pulse animation for announcement icon */
        .paffp-announcement-icon {
            animation: paffpAnnouncementIconPulse 1.8s ease-in-out infinite;
            transform-origin: center;
        }

        @keyframes paffpAnnouncementIconPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(25, 135, 84, 0);
            }
            50% {
                transform: scale(1.08);
                box-shadow: 0 0 0 10px rgba(25, 135, 84, .08);
            }
        }

        .paffp-announcement.info .paffp-announcement-icon {
            --announcement-pulse: 13, 202, 240;
        }

        .paffp-announcement.success .paffp-announcement-icon {
            --announcement-pulse: 25, 135, 84;
        }

        .paffp-announcement.warning .paffp-announcement-icon {
            --announcement-pulse: 255, 193, 7;
        }

        .paffp-announcement.danger .paffp-announcement-icon {
            --announcement-pulse: 220, 53, 69;
        }

        @keyframes paffpAnnouncementIconPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(var(--announcement-pulse, 25, 135, 84), 0);
            }
            50% {
                transform: scale(1.08);
                box-shadow: 0 0 0 11px rgba(var(--announcement-pulse, 25, 135, 84), .10);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .paffp-announcement-icon {
                animation: none !important;
            }
        }

    
        /* ============================================================
           CAMERA MODAL FINAL FIXES
           Desktop-safe viewport fitting + scroll fallback.
        ============================================================ */
        #cameraModal .modal-dialog {
            max-height: calc(100dvh - 1rem) !important;
        }

        #cameraModal .modal-content {
            display: flex !important;
            flex-direction: column !important;
            max-height: calc(100dvh - 1rem) !important;
            overflow: hidden !important;
        }

        #cameraModal .modal-header {
            flex: 0 0 auto;
        }

        #cameraModal .modal-body {
            flex: 1 1 auto;
            min-height: 0 !important;
            height: auto !important;
            max-height: none !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
        }

        #cameraModal.capture-mode .modal-body {
            display: flex;
            flex-direction: column;
            overflow-y: auto !important;
        }

        #cameraModal #previewContainer {
            flex-shrink: 0;
        }

        #cameraModal .camera-control-panel {
            position: relative;
            z-index: 5;
            flex: 0 0 auto;
        }

        #cameraModal .camera-action-row {
            align-items: stretch;
        }

        #cameraModal .camera-action-row > [class*="col-"] {
            min-width: 0;
        }

        #cameraModal .camera-action-row .btn,
        #cameraModal #reviewBtnContainer .btn {
            height: 100%;
        }

        #cameraModal .camera-action-row .btn:disabled,
        #cameraModal #reviewBtnContainer .btn:disabled {
            cursor: not-allowed;
            opacity: .55;
        }

        #cameraModal #captureGuide {
            pointer-events: none;
        }

        /* Show the full captured photo. This is especially important for ID
           front/back images, whose 1.6:1 crop was previously re-cropped by
           object-fit: cover inside the square preview. */
        #cameraModal #capturedImage {
            object-fit: contain !important;
            object-position: center center !important;
            background: #080a09;
        }

        .camera-status {
            display: flex;
            align-items: center;
            gap: .4rem;
            min-height: 24px;
            margin-top: .45rem;
            color: var(--text-soft);
            font-size: .72rem;
            line-height: 1.3;
        }

        .camera-status i {
            width: 16px;
            color: var(--da-green);
            text-align: center;
        }

        .camera-status.is-ready {
            color: var(--da-green);
        }

        .camera-status.is-warning {
            color: #b58100;
        }

        .camera-status.is-error {
            color: #dc3545;
        }

        #cameraModal.review-mode .modal-body {
            overflow-y: auto !important;
        }

        #cameraModal.review-mode #reviewContainer {
            padding-bottom: .15rem;
        }

        @media (min-width: 768px) {
            #cameraModal .modal-dialog {
                width: min(1120px, calc(100vw - 2rem)) !important;
                margin: .5rem auto !important;
            }

            #cameraModal .modal-content {
                max-height: calc(100dvh - 1rem) !important;
            }
        }

        @media (max-width: 767.98px) {
            #cameraModal .modal-body {
                scrollbar-gutter: auto;
            }

            .camera-status {
                margin-top: .35rem;
                font-size: .68rem;
            }
        }

    
        /* Better offline / sync indicator */
        .sync-indicator {
            display: inline-flex;
            align-items: center;
            gap: .36rem;
            min-height: 34px;
            max-width: 260px;
            padding: .38rem .55rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-soft);
            font-size: .72rem;
            line-height: 1;
            white-space: nowrap;
        }

        .sync-indicator-icon {
            display: inline-grid;
            place-items: center;
            width: 16px;
            color: inherit;
        }

        .sync-indicator.is-synced {
            border-color: rgba(25,135,84,.2);
            background: rgba(25,135,84,.07);
            color: #198754;
        }

        .sync-indicator.is-syncing,
        .sync-indicator.is-starting {
            border-color: rgba(13,110,253,.18);
            background: rgba(13,110,253,.06);
            color: #0d6efd;
        }

        .sync-indicator.is-queued {
            border-color: rgba(255,193,7,.3);
            background: rgba(255,193,7,.09);
            color: #a87500;
        }

        .sync-indicator.is-offline,
        .sync-indicator.is-error {
            border-color: rgba(220,53,69,.24);
            background: rgba(220,53,69,.07);
            color: #dc3545;
        }

        .sync-pending {
            padding: .17rem .3rem;
            border-radius: 999px;
            background: rgba(0,0,0,.10);
            color: inherit;
            font-size: .62rem;
            font-weight: 750;
        }

        .sync-last {
            opacity: .72;
            font-size: .64rem;
        }

        .payout-closed-notice {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            margin-bottom: 1rem;
            padding: .75rem .85rem;
            border: 1px solid rgba(220,53,69,.24);
            border-radius: 13px;
            background: rgba(220,53,69,.07);
            color: var(--text-main);
        }

        .payout-closed-notice > i {
            margin-top: .15rem;
            color: #dc3545;
        }

        .payout-closed-notice strong {
            display: block;
            margin-bottom: .12rem;
            color: #dc3545;
            font-size: .82rem;
        }

        .payout-closed-notice span {
            display: block;
            color: var(--text-soft);
            font-size: .76rem;
            line-height: 1.4;
        }

        /* Retake a single photo from the final camera review */
        #cameraModal .review-photo-card {
            position: relative;
        }

        #cameraModal .review-retake-one {
            position: absolute;
            right: .42rem;
            bottom: .42rem;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            min-height: 30px;
            padding: .3rem .48rem;
            border: 1px solid rgba(255,255,255,.72);
            border-radius: 999px;
            background: rgba(0,0,0,.72);
            color: #fff;
            font-size: .66rem;
            line-height: 1;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        #cameraModal .review-retake-one:hover {
            background: rgba(0,0,0,.88);
        }

        #cameraModal .review-retake-one:disabled {
            opacity: .5;
            pointer-events: none;
        }

        @media (max-width: 575.98px) {
            .sync-last {
                display: none !important;
            }

            .sync-indicator {
                max-width: 150px;
            }

            #cameraModal .review-retake-one span {
                display: none;
            }

            #cameraModal .review-retake-one {
                width: 30px;
                justify-content: center;
                padding: .25rem;
            }
        }

    
        /* Larger payout-closed notice text */
        #payoutClosedNotice .payout-closed-title,
        #payoutClosedNotice strong {
            font-size: 1.05rem !important;
            line-height: 1.35 !important;
        }

        #payoutClosedNotice .payout-closed-message,
        #payoutClosedNotice .small,
        #payoutClosedNotice p {
            font-size: .92rem !important;
            line-height: 1.55 !important;
        }

        @media (max-width: 575.98px) {
            #payoutClosedNotice .payout-closed-title,
            #payoutClosedNotice strong {
                font-size: .98rem !important;
            }

            #payoutClosedNotice .payout-closed-message,
            #payoutClosedNotice .small,
            #payoutClosedNotice p {
                font-size: .86rem !important;
            }
        }

    
        /* Non-dismissible system payout-lock announcement */
        .paffp-announcement.system-lock-announcement {
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 1.5rem 1.25rem !important;
            border-left: 0 !important;
            text-align: center;
        }

        .paffp-announcement.system-lock-announcement .paffp-announcement-icon {
            width: 86px;
            height: 86px;
            flex: 0 0 86px;
            margin-bottom: .95rem;
            border-radius: 50%;
            background: rgba(220, 53, 69, .12);
            color: #dc3545;
            font-size: 2.1rem;
            animation: payoutLockDangerPulse 1.8s ease-in-out infinite;
        }

        .paffp-announcement.system-lock-announcement .paffp-announcement-body {
            width: 100%;
            max-width: 470px;
        }

        

        

        @keyframes payoutLockDangerPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }

            50% {
                transform: scale(1.07);
                box-shadow: 0 0 0 13px rgba(220, 53, 69, .09);
            }
        }

        @media (max-width: 575.98px) {
            .paffp-announcement.system-lock-announcement {
                min-height: 0;
                padding: 1.25rem 1rem !important;
            }

            .paffp-announcement.system-lock-announcement .paffp-announcement-icon {
                width: 72px;
                height: 72px;
                flex-basis: 72px;
                font-size: 1.75rem;
            }

            

            
        }

        @media (prefers-reduced-motion: reduce) {
            .paffp-announcement.system-lock-announcement .paffp-announcement-icon {
                animation: none !important;
            }
        }

    
        .paffp-announcement-username {
            margin-top: .65rem;
            font-style: italic;
            font-weight: 400;
        }

</style>

    <link rel="stylesheet" href="css/paffp-modal-system.css?v=20261011b">
</head>

<div class="mypopup" id="loadingPops" style="display: none;">
    <div id="loading-container">
        <div class="loader"><img class="rounded-circle" src="img/Logo_DA.png" width="180" height="180"></div>
    </div>
</div>
<body id="page-top" class="bg-white">

    <div id="cautionWarningPopup"
         class="caution-warning-popup"
         role="alert"
         aria-live="assertive"
         aria-atomic="true">
        <div class="caution-warning-icon">
            <i class="fas fa-flag"></i>
        </div>
        <div class="caution-warning-text">
            <div class="caution-warning-title">PROCEED WITH CAUTION</div>
            <div class="caution-warning-message">Profile has been reported by other Verifier</div>
        </div>
    </div>
    <div class="modal fade" id="cameraModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content paffp-modal paffp-tone-success">
                <div class="modal-header d-flex align-items-center gap-3">
                    <div class="camera-header-icon">
                        <i class="fa-solid fa-camera"></i>
                    </div>

                    <div class="flex-grow-1 min-w-0">
                        <div class="camera-header-kicker">PAFFP Photo Capture</div>
                        <h2 class="modal-title" id="cameraModalTitle"></h2>
                    </div>

                    <button type="button"
                            class="camera-modal-close"
                            id="closeCaptureModal"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="capture-stage-heading">
                        <h3 id="captureTitle"></h3>
                    </div>

                    <div id="previewContainer" class="mx-auto">
                        <div id="cameraContainer">
                            <video id="cameraVideo" autoplay playsinline></video>
                            <div id="captureGuide"></div>
                        </div>

                        <div id="imageContainer" style="display:none;">
                            <img id="capturedImage" alt="Captured Photo">
                        </div>
                    </div>

                    <canvas id="cameraCanvas" style="display:none;"></canvas>

                    <div id="reviewContainer" style="display:none;">
<div class="row g-3 camera-review-grid">
                            <div class="col-6">
                                <div class="review-photo-card">
                                    <div class="review-photo-label">
                                        <i class="fa-solid fa-user"></i>
                                        <span>Farmer Photo</span>
                                    </div>
                                    <img id="reviewFarmer" class="img-fluid" style="aspect-ratio:1/1;object-fit:cover;" alt="Farmer photo">
                                    <button type="button"
                                            class="review-retake-one"
                                            data-retake-step="0"
                                            title="Retake Farmer Photo">
                                        <i class="fa-solid fa-camera-rotate"></i>
                                        <span>Retake</span>
                                    </button>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="review-photo-card">
                                    <div class="review-photo-label">
                                        <i class="fa-solid fa-id-card"></i>
                                        <span>Farmer with ID</span>
                                    </div>
                                    <img id="reviewFarmerID" class="img-fluid" style="aspect-ratio:1/1;object-fit:cover;" alt="Farmer with ID">
                                    <button type="button"
                                            class="review-retake-one"
                                            data-retake-step="1"
                                            title="Retake Farmer with ID">
                                        <i class="fa-solid fa-camera-rotate"></i>
                                        <span>Retake</span>
                                    </button>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="review-photo-card">
                                    <div class="review-photo-label">
                                        <i class="fa-solid fa-address-card"></i>
                                        <span>ID Front</span>
                                    </div>
                                    <img id="reviewFront" class="img-fluid" style="aspect-ratio:1.6/1;object-fit:cover;" alt="ID front">
                                    <button type="button"
                                            class="review-retake-one"
                                            data-retake-step="2"
                                            title="Retake ID Front">
                                        <i class="fa-solid fa-camera-rotate"></i>
                                        <span>Retake</span>
                                    </button>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="review-photo-card">
                                    <div class="review-photo-label">
                                        <i class="fa-regular fa-address-card"></i>
                                        <span>ID Back</span>
                                    </div>
                                    <img id="reviewBack" class="img-fluid" style="aspect-ratio:1.6/1;object-fit:cover;" alt="ID back">
                                    <button type="button"
                                            class="review-retake-one"
                                            data-retake-step="3"
                                            title="Retake ID Back">
                                        <i class="fa-solid fa-camera-rotate"></i>
                                        <span>Retake</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="camera-review-actions">
                            <div id="reviewBtnContainer">
                                <div class="row g-2">
                                    <div class="col-5" id="reviewRetakeAll">
                                        <button type="button" class="btn btn-outline-warning w-100">
                                            <div class="d-flex align-items-center justify-content-center gap-2 text-truncate">
                                                <i class="fas fa-redo-alt"></i>
                                                <span>Retake</span>
                                            </div>
                                        </button>
                                    </div>

                                    <div class="col-7" id="reviewSave">
                                        <button type="button" class="btn btn-success w-100">
                                            <div class="d-flex align-items-center justify-content-center gap-2 text-truncate">
                                                <i class="fas fa-save"></i>
                                                <span>Save Photos</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="uploadProgressContainer" style="display:none;">
                                <div class="camera-progress-title text-center mb-2">
                                    Uploading photos…
                                </div>
                                <div class="progress">
                                    <div id="uploadProgressBar"
                                         class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                         role="progressbar"
                                         style="width:0%">0%</div>
                                </div>
                                <div class="text-center mt-2" id="uploadProgressText">
                                    Uploading 0 of 4...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="camera-control-panel">
                        <div class="camera-select-label">
                            <i class="fa-solid fa-video me-1"></i> Camera
                        </div>

                        <div class="dropdown">
                            <button id="cameraDropdownButton"
                                    class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center text-truncate"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-boundary="viewport"
                                    aria-expanded="false">
                                Select Camera
                            </button>
                            <ul id="cameraList" class="dropdown-menu w-100"></ul>
                        </div>

                        <div id="cameraStatus"
                             class="camera-status"
                             role="status"
                             aria-live="polite">
                            <i class="fa-solid fa-circle-notch fa-spin"></i>
                            <span>Preparing camera…</span>
                        </div>

                        <div class="row g-2 camera-action-row">
                            <div class="col-8" id="captureBtn">
                                <button type="button" class="btn btn-primary w-100" disabled>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <i class="fa-solid fa-camera"></i>
                                        <span>Capture Photo</span>
                                    </div>
                                </button>
                            </div>

                            <div class="col-8" id="retakeBtn" style="display:none;">
                                <button type="button" class="btn btn-outline-warning w-100">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <i class="fas fa-redo-alt"></i>
                                        <span>Retake</span>
                                    </div>
                                </button>
                            </div>

                            <div class="col-4" id="nextBtn">
                                <button type="button" class="btn btn-outline-info w-100" disabled>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span>Next</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paffp-modal paffp-tone-success">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div class="paffp-modal-icon">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="small text-secondary">PAFFP Photo Capture</div>
                            <h5 class="modal-title">Upload Successful</h5>
                        </div>
                    </div>
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-cloud-upload-alt fa-4x text-success mb-3"></i>
                    <h5>Your files were uploaded successfully.</h5>
                    <p class="text-muted mb-0">You may now continue to AgriVerify or simply close this message.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-primary" id="openAgriVerifyBtn">
                        <i class="fas fa-external-link-alt"></i> Open AgriVerify
                    </button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade"
         id="paffpNoticeModal"
         tabindex="-1"
         aria-hidden="true"
         data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paffp-modal paffp-tone-info">
                <div class="modal-header"
                     id="paffpNoticeHeader">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div id="paffpNoticeIconWrap">
                            <i class="fa-solid fa-circle-info"
                               id="paffpNoticeIcon"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="small text-secondary">PAFFP Notice</div>
                            <h5 class="modal-title"
                                id="paffpNoticeTitle">Notice</h5>
                        </div>
                    </div>
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0"
                       id="paffpNoticeMessage"></p>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-primary"
                            data-bs-dismiss="modal"
                            id="paffpNoticeClose">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade"
         id="accessRequestReasonModal"
         tabindex="-1"
         aria-hidden="true"
         data-bs-backdrop="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paffp-modal paffp-tone-info">
                <form id="accessRequestReasonForm">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="paffp-modal-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="small text-secondary">
                                    Restricted Content
                                </div>
                                <h5 class="modal-title">
                                    Request Access
                                </h5>
                            </div>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <label class="form-label fw-semibold"
                               for="accessRequestReason">
                            Reason for access
                            <span class="text-secondary fw-normal">
                                (optional)
                            </span>
                        </label>

                        <textarea class="form-control"
                                  id="accessRequestReason"
                                  maxlength="500"
                                  placeholder="Briefly explain why you need access to this payout."></textarea>

                        <div class="d-flex justify-content-between gap-3 mt-2">
                            <div class="form-text mt-0">
                                This reason will be shown to the Admin reviewing your request.
                            </div>

                            <div class="access-request-reason-counter">
                                <span id="accessRequestReasonCount">0</span>/500
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-success"
                                id="submitAccessRequestButton">
                            <i class="fa-solid fa-paper-plane me-1"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <button class="btn" onclick="home()" id="Home" title="Back to top" aria-label="Back to top" style="display:none"><i class="fas fa-chevron-up"></i></button>

    <div id="wrapper">
        <header class="topbar">
            <div class="container-fluid px-3 px-lg-4 py-2">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <a class="brand-link d-flex align-items-center gap-3 min-w-0"
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
                        <button class="btn btn-outline-secondary square-mobile" id="toggleTheme" type="button" title="Switch theme" aria-label="Switch theme">
                            <i class="fas fa-moon"></i>
                        </button>
                        <a class="btn btn-outline-secondary square-mobile" href="./" title="Home" aria-label="Home">
                            <i class="fa-solid fa-house me-md-2"></i><span class="btn-label">Home</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="paffp-announcement-overlay d-none"
             id="paffpAnnouncementRegion"
             role="dialog"
             aria-modal="true"
             aria-live="polite"
             aria-label="PAFFP announcements">
            <div class="paffp-announcement-modal">
                <div class="paffp-announcement-stack" id="paffpAnnouncementStack"></div>
            </div>
        </div>

        <main class="container-fluid px-3 px-lg-4 page-shell">
            <div class="row g-4" id="paffpData">
                <div class="col-12 col-lg-8 col-xl-9">
                    <section class="card surface-card">
                        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div class="verify-title-wrap min-w-0">
                                <h1 class="page-title text-truncate" id="TableTitle"></h1>
                                <div class="page-subtitle mt-1"></div>
                            </div>

                            <div class="verify-header-actions d-flex flex-wrap align-items-center gap-2">
                                <span id="SyncIndicator"
                                      class="sync-indicator is-starting"
                                      title="PAFFP synchronization status"
                                      aria-live="polite">
                                    <span class="sync-indicator-icon">
                                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                                    </span>
                                    <span id="SyncLabel">Starting…</span>
                                    <span id="PendingBadge" class="sync-pending d-none">0</span>
                                    <span id="SyncLast" class="sync-last d-none"></span>
                                </span>
                                <span class="user-chip">
                                    <i class="fa-solid fa-user-shield"></i>
                                    <span id="UsernameDisplay"></span>
                                </span>
                                <?php if ($paffpAuthEnabled) { ?>
                                <a class="btn btn-outline-secondary btn-sm square-mobile"
                                   href="logout.php"
                                   title="Logout"
                                   aria-label="Logout">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span class="btn-label ms-1">Logout</span>
                                </a>
                                <?php } ?>
                                <span id="AccessExpiryBadge"
                                      class="access-expiry-chip d-none"
                                      title="Temporary access expiry">
                                    <i class="fa-regular fa-clock"></i>
                                    <span id="AccessExpiryLabel"></span>
                                </span>
                                <button type="button" id="btnAnalytics" class="btn btn-outline-success btn-sm" title="View payout analytics">
                                    <i class="fas fa-chart-line me-1"></i><span class="btn-label">Analytics</span>
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <div id="containerLocal" class="content-div table-responsive-shell">
                                <table id="table_local" class="table align-middle" style="width:100%;">
                                    <thead>
                                        <tr id="headerLocal">
                                            <th>No.</th>
                                            <th class="noWrap col-equal">Last Name</th>
                                            <th class="noWrap col-equal">First & Ext Name</th>
                                            <th class="noWrap col-equal">Middle Name</th>
                                            <th></th>
                                            <th></th>
                                            <th class="noWrap action-col"></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>

                                <!-- Testing-only mobile beneficiary layout.
                                     Desktop/tablet continue to use the original DataTable. -->
                                <div id="mobileBeneficiaryCards"
                                     class="mobile-beneficiary-cards"
                                     aria-live="polite"></div>
                            </div>

                            <?php if ($adVerifyContainerLocalBanner) { ?>
                            <div class="adsterra-banner-wrap verify-container-local-ad"
                                 aria-label="Advertisement">
                                <iframe class="adsterra-banner-frame"
                                        src="ad-banner.php"
                                        width="728"
                                        height="90"
                                        scrolling="no"
                                        loading="lazy"
                                        title="Advertisement"></iframe>
                            </div>
                            <?php } ?>
                        </div>
                    </section>
                </div>

                <aside class="col-12 col-lg-4 col-xl-3" id="farmerDetailsContainer">
                    <div class="empty-profile mb-4" id="farmerDetailsEmpty">
                        <div class="empty-icon"><i class="fa-solid fa-user-check fa-lg"></i></div>
                        <div class="fw-bold mb-1">Select a beneficiary</div>
                        <div class="small">Choose <strong>View</strong> from the table to open the payout verification details.</div>
                    </div>

                    <section class="details-card mb-4" id="farmerDetails" style="display:none;">
                        <button type="button"
                                id="cautionFarmer"
                                class="btn btn-outline-danger caution-farmer-btn"
                                title="Mark as Caution"
                                aria-label="Mark farmer as Caution">
                            <i class="fas fa-flag"></i>
                        </button>

                        <div class="text-center">
                            <div class="mb-3">
                                <p id="farmerLastName" class="farmer-name mb-1"></p>
                                <p id="farmerFirstName" class="farmer-name mb-1"></p>
                                <p id="farmerMiddleName" class="farmer-name mb-1"></p>
                                <p id="farmerExtName" class="farmer-name mb-1"></p>
                                <p id="farmerBDay" class="farmer-meta mt-3 mb-1"></p>
                                <p id="farmerSysgen" class="farmer-meta mb-0"></p>
                            </div>

                            <div class="row g-2 mt-3">
                                <div class="col-4">
                                    <div class="number-tile">
                                        <p id="farmerVerifier" class="value"></p>
                                        <p class="label">Verifier No.</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="number-tile">
                                        <p id="farmerTeller" class="value"></p>
                                        <p class="label">Teller No.</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="number-tile">
                                        <p id="farmerID" class="value"></p>
                                        <p class="label">Payroll No.</p>
                                    </div>
                                </div>
                            </div>

                            <p id="farmerVerifierID" class="farmer-meta mt-2 mb-0"></p>

                            <div class="row g-2 mt-4 action-grid">
                                <div class="col-6">
                                    <button id="verifyFarmer0" class="btn btn-success w-100 verify-btn">
                                        <i class="fas fa-check-circle"></i>
                                        <span title="Verify">Verify</span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button id="verifyFarmer1" class="btn btn-outline-success w-100 verify-btn">
                                        <i class="fas fa-edit"></i>
                                        <span title="Verify w/ Corrections">Verify w/ Corrections</span>
                                    </button>
                                </div>
                                <div class="col-12 d-none" id="unverifyContainer">
                                    <button id="unverifyFarmer" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-times-circle"></i>
                                        <span title="Unverify">Unverify</span>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-2 mt-2 action-grid">
                                <div class="col-4" id="captureButtonContainer">
                                    <button class="btn btn-outline-primary w-100 captureModal">
                                        <i class="fas fa-camera"></i>
                                        <span title="Capture">Capture</span>
                                    </button>
                                </div>
                                <div class="col-4" id="viewButtonContainer">
                                    <a class="btn btn-outline-info w-100 farmerProfile" href="#" target="_blank" rel="noopener">
                                        <i class="fas fa-user"></i>
                                        <span title="View">View</span>
                                    </a>
                                </div>
                                <div class="col-4" id="hideButtonContainer">
                                    <button class="btn btn-outline-warning w-100 clearProfile">
                                        <i class="fas fa-eye-slash"></i>
                                        <span title="Hide">Hide</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="summary-card">
                        <div class="summary-compact-head">
                            <div class="summary-icon">
                                <i class="fas fa-user-check"></i>
                            </div>

                            <div class="summary-main">
                                <div class="summary-kicker">Your Verified Profiles</div>
                                <div class="summary-total-row">
                                    <div class="summary-total" id="totalVerifiedProfiles">0</div>
                                    <div class="summary-total-label">Total</div>
                                </div>
                            </div>
                        </div>

                        <div class="progress summary-progress">
                            <div id="verifiedBar"
                                 class="progress-bar bg-success"
                                 role="progressbar"
                                 style="width:0%"></div>
                            <div id="correctedBar"
                                 class="progress-bar bg-warning"
                                 role="progressbar"
                                 style="width:0%"></div>
                        </div>

                        <div class="summary-metrics">
                            <div class="summary-metric" title="Verified">
                                <span class="summary-dot verified"></span>
                                <span class="summary-metric-label">Verified</span>
                                <span class="summary-metric-value" id="verifiedCount">0</span>
                            </div>

                            <div class="summary-metric" title="Verified w/ Corrections">
                                <span class="summary-dot corrected"></span>
                                <span class="summary-metric-label">Corrections</span>
                                <span class="summary-metric-value" id="correctedCount">0</span>
                            </div>
                        </div>
                    </section>

                    <section class="verify-chat-card"
                             id="paffpVerifierChat"
                             aria-label="Global verifier chat">
                        <div class="verify-chat-head">
                            <div class="verify-chat-title-wrap">
                                <div class="verify-chat-icon">
                                    <i class="fa-solid fa-comments"></i>
                                </div>

                                <div class="min-w-0">
                                    <div class="verify-chat-title">Verifier Chat</div>
                                    <div class="verify-chat-subtitle">All payouts · shared verifier room</div>
                                </div>
                            </div>

                            <div class="verify-chat-status"
                                 id="verifyChatStatus">
                                <span class="verify-chat-status-dot"></span>
                                <span id="verifyChatStatusText">Waiting</span>
                            </div>
                        </div>

                        <div class="verify-chat-messages"
                             id="verifyChatMessages"
                             aria-live="polite">
                            <div class="verify-chat-empty">
                                Loading verifier chat…
                            </div>
                        </div>

                        <form class="verify-chat-compose"
                              id="verifyChatForm">
                            <div class="verify-chat-input-row">
                                <textarea class="form-control verify-chat-input"
                                          id="verifyChatInput"
                                          rows="2"
                                          maxlength="500"
                                          placeholder="Message other verifiers…"
                                          aria-label="Chat message"></textarea>

                                <button type="submit"
                                        class="btn btn-success verify-chat-send"
                                        id="verifyChatSend"
                                        title="Send message"
                                        aria-label="Send message">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>

                            <div class="verify-chat-compose-meta">
                                <span>Posting as <strong id="verifyChatViewer">—</strong></span>
                                <span><span id="verifyChatCount">0</span>/500</span>
                            </div>
                        </form>
                    </section>
                </aside>
            </div>

            <section class="card analytics-card analytics-dashboard" id="paffpStatus" style="display:none;">
                <div class="card-header analytics-header">
                    <div class="analytics-title-row">
                        <h2 class="page-title" id="TableTitle2">Payout Analytics</h2>

                        <button type="button"
                                id="btnData"
                                class="btn btn-outline-secondary btn-sm analytics-close"
                                title="Close analytics"
                                aria-label="Close analytics">
                            <i class="fas fa-times me-md-1"></i>
                            <span class="d-none d-md-inline">Close</span>
                        </button>
                    </div>
                </div>

                <div class="card-body analytics-body">
                    <div class="row g-2 g-lg-3 analytics-kpi-row">
                        <div class="col-6 col-xl-3">
                            <div class="analytics-kpi">
                                <div class="analytics-kpi-icon total">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="analytics-kpi-value" id="analyticsTotal">0</div>
                                    <div class="analytics-kpi-label">Beneficiaries</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="analytics-kpi">
                                <div class="analytics-kpi-icon completed">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="analytics-kpi-value" id="analyticsCompleted">0</div>
                                    <div class="analytics-kpi-label">
                                        Completed
                                        <span id="analyticsCompletion" class="analytics-kpi-note">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="analytics-kpi">
                                <div class="analytics-kpi-icon pending">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="analytics-kpi-value" id="analyticsPending">0</div>
                                    <div class="analytics-kpi-label">Pending</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="analytics-kpi">
                                <div class="analytics-kpi-icon verifiers">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="analytics-kpi-value" id="analyticsVerifiers">0</div>
                                    <div class="analytics-kpi-label">Active Verifiers</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-xl-4">
                            <div class="analytics-panel h-100">
                                <div class="analytics-panel-head">
                                    <div>
                                        <h3 class="analytics-panel-title">Verification Status</h3>
                                        <div class="analytics-panel-subtitle">Overall payout completion</div>
                                    </div>
                                </div>

                                <div class="analytics-donut-wrap">
                                    <canvas id="verificationChart"></canvas>
                                    <div class="analytics-donut-center" aria-hidden="true">
                                        <div id="analyticsCenterPercent" class="analytics-donut-percent">0%</div>
                                        <div class="analytics-donut-label">Complete</div>
                                    </div>
                                </div>

                                <div class="analytics-status-legend">
                                    <div class="analytics-status-item">
                                        <span class="analytics-status-dot verified"></span>
                                        <span>Verified</span>
                                        <strong id="analyticsVerifiedLegend">0</strong>
                                    </div>
                                    <div class="analytics-status-item">
                                        <span class="analytics-status-dot corrected"></span>
                                        <span>Corrections</span>
                                        <strong id="analyticsCorrectedLegend">0</strong>
                                    </div>
                                    <div class="analytics-status-item">
                                        <span class="analytics-status-dot pending"></span>
                                        <span>Pending</span>
                                        <strong id="analyticsPendingLegend">0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-8">
                            <div class="analytics-panel h-100">
                                <div class="analytics-panel-head">
                                    <div>
                                        <h3 class="analytics-panel-title">Verifier Performance</h3>
                                        <div class="analytics-panel-subtitle">Verified and corrected profiles by verifier</div>
                                    </div>
                                </div>

                                <div class="analytics-bar-wrap" id="encoderChartWrap">
                                    <canvas id="encoderChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="analytics-panel mt-3">
                        <div class="analytics-panel-head">
                            <div>
                                <h3 class="analytics-panel-title">Verifier Summary</h3>
                                <div class="analytics-panel-subtitle">Ranked by total completed profiles</div>
                            </div>
                        </div>

                        <div class="analytics-verifier-list" id="analyticsVerifierList">
                            <div class="analytics-empty">No verifier activity yet.</div>
                        </div>
                    </div>

                    <?php if ($adVerifyAnalyticsNative) { ?>
                    <div class="adsterra-native-wrap analytics-native-ad" aria-label="Advertisement">
                        <iframe class="adsterra-native-frame"
                                src="ad-native.php"
                                scrolling="no"
                                loading="lazy"
                                title="Advertisement"></iframe>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </main>

        <?php if ($adVerifyFooterBanner) { ?>
        <div class="container-fluid px-3 px-lg-4">
            <div class="adsterra-banner-wrap" aria-label="Advertisement">
            <iframe class="adsterra-banner-frame"
                    src="ad-banner.php"
                    width="728"
                    height="90"
                    scrolling="no"
                    loading="lazy"
                    title="Advertisement"></iframe>
        </div>
        </div>
        <?php } ?>

        <footer>
            <div class="container-fluid px-3 px-lg-4 py-4 text-center">
                <div class="copyright">Managed by PMED Planning Section</div>
            </div>
        </footer>
    </div>

    <script>
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
        window.addEventListener("DOMContentLoaded", fitAdsterraBanners);
    </script>


    <script>
        window.addEventListener("message", function(event) {
            if (!event.data || event.data.type !== "paffp-native-ad-height") return;

            document.querySelectorAll(".adsterra-native-frame").forEach(function(frame) {
                if (frame.contentWindow === event.source) {
                    const height = Math.max(1, Number(event.data.height) || 250);
                    frame.style.height = height + "px";
                }
            });
        });
    </script>

</body>

<script type = "text/javascript" >
    var row = null;
    var selectedRow = null;
    var tableLocal = null;
    var rowLocal = null;
    var STORAGE_KEY = null;
    var pageTitle = null;
    var pageFile = null;
    var pageDate = null;

    // Fail closed until the lightweight server-side access preflight succeeds.
    var payoutOpen = false;
    var payoutWritable = false;
    var accessStateResolved = false;
    var accessRestricted = true;

    window.PAFFP_ACCESS_STATE_RESOLVED = false;
    window.PAFFP_PAYOUT_STATE = {
        open: false,
        writable: false,
        reason: "checking"
    };

    window.PAFFP_ACCESS_REQUEST_STATE = {
        id: "",
        status: "none",
        permission: "",
        expires_at: 0
    };

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    const PAFFP_AUTH_ENABLED =
        <?= $paffpAuthEnabled ? 'true' : 'false' ?>;
    const PAFFP_AUTH_USERNAME =
        <?= json_encode($paffpAuthUsername, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const PAFFP_LOGIN_URL =
        <?= json_encode($paffpLoginUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const PENDING_QUEUE_KEY = "PendingServerUpdates";
    const RETRY_INTERVAL = 1 * 60 * 1000;
    const NTFY_TOPIC = "https://ntfy.sh/darfox-paffp";
    const SYNC_ACTIVE_INTERVAL = 30 * 1000;
    const SYNC_HIDDEN_INTERVAL = 2 * 60 * 1000;
    const SYNC_RETRY_MIN = 5 * 1000;
    const SYNC_RETRY_MAX = 60 * 1000;

    // Restricted pages check only payout authorization state.
    // No beneficiary JSON/cache is loaded until access is granted.
    const ACCESS_STATE_WATCH_INTERVAL = 5 * 1000;

    let accessStateWatchTimer = null;
    let accessStateCheckInProgress = false;
    let accessRevision = "";
    let individualPermission = "";

    let SYNC_STATE_KEY = null;
    let syncState = { epoch: "", version: 0 };
    let syncTimer = null;
    let syncInProgress = false;
    let syncFailureCount = 0;
    let currentData = [];
    let lastSuccessfulSyncAt = 0;
    let syncDisplayState = "starting";
    let syncStatusRefreshTimer = null;

    let verified = 0;
    let corrected = 0;
    let pending = 0;
    let userVerified = 0;
    let userCorrected = 0;
    let encoderCounts = {};
    if (!id) {
        window.location.replace('./');
    }
    $(document).ready(function() {
        refreshPayoutAccessState({ forceInit: true });
        startPayoutAccessWatcher();
        loadTheme();
        $("#toggleTheme").click(function() {
            $("body").toggleClass("dark-mode");
            const dark = $("body").hasClass("dark-mode");
            localStorage.setItem("darkMode", dark);
            $("#toggleTheme i").toggleClass("fa-moon", !dark).toggleClass("fa-sun", dark);
            $("#toggleTheme").attr("title", dark ? "Switch to light mode" : "Switch to dark mode");
            $("#toggleTheme").attr("aria-label", $("#toggleTheme").attr("title"));
            if (tableLocal) {
                tableLocal.columns.adjust().draw(false);
            }

            if (window.verificationChart instanceof Chart || window.encoderChart instanceof Chart) {
                const sortedAnalytics = Object.entries(encoderCounts)
                    .sort((a, b) =>
                        (b[1].verified + b[1].corrected) -
                        (a[1].verified + a[1].corrected)
                    );

                renderBarChart(
                    sortedAnalytics.map(item => item[0]),
                    sortedAnalytics.map(item => item[1].verified),
                    sortedAnalytics.map(item => item[1].corrected)
                );

                dOnut(verified, corrected, pending);
            }
        });
        loadingPops();
        $('#wrapper').show();
        updatePendingBadge();

        if (syncStatusRefreshTimer) {
            clearInterval(syncStatusRefreshTimer);
        }

        syncStatusRefreshTimer = setInterval(
            refreshSyncDisplayState,
            10000
        );

        if (!navigator.onLine) {
            setSyncDisplayState("offline");
        }

        setInterval(processPendingQueue, RETRY_INTERVAL);

        window.addEventListener("online", async function() {
            setSyncDisplayState("syncing");

            const authorized = await refreshPayoutAccessState();

            if (!authorized || accessRestricted) {
                return;
            }

            await processPendingQueue();
            scheduleSync(250);
        });

        window.addEventListener("offline", function() {
            setSyncDisplayState("offline");
        });

        document.addEventListener("visibilitychange", async function() {
            if (document.hidden) {
                scheduleSync(SYNC_HIDDEN_INTERVAL);
                return;
            }

            const authorized = await refreshPayoutAccessState();

            if (!authorized || accessRestricted) {
                return;
            }

            scheduleSync(250);
        });
        let mobileBeneficiaryResizeTimer = null;

        window.addEventListener("resize", function() {
            window.clearTimeout(mobileBeneficiaryResizeTimer);

            mobileBeneficiaryResizeTimer = window.setTimeout(
                function() {
                    if (tableLocal) {
                        renderMobileBeneficiaryCards(tableLocal);
                        tableLocal.columns.adjust();
                    }
                },
                120
            );
        });

        $("#UsernameDisplay").text(getUsername());
        var Home = document.getElementById("Home");
        window.onscroll = function() {
            scrollFunction()
        };
        $(document).on("click", "#btnAnalytics", function() {
            const currentTitle = $("#TableTitle").text().trim();
            $("#TableTitle2").text(currentTitle ? currentTitle + " Analytics" : "Payout Analytics");
            $("#paffpData").fadeOut(200);
            $("#paffpStatus").fadeIn(200);
        });
        $(document).on("click", "#btnData", function() {
            $("#paffpStatus").fadeOut(200);
            $("#paffpData").fadeIn(200);
        });
        $(document).on("click", ".mobile-beneficiary-view", function() {
            if (!tableLocal) {
                return;
            }

            const rowIndex = Number(
                $(this).attr("data-row-index")
            );

            if (!Number.isFinite(rowIndex)) {
                return;
            }

            const dtRow = tableLocal.row(rowIndex);
            const node = dtRow.node();

            if (!node) {
                return;
            }

            $(node).find(".viewFarmer").trigger("click");
        });

        $('#table_local tbody').on('click', '.viewFarmer', function() {
            var currentRow = $(this).closest('tr');
            if (selectedRow) {
                selectedRow.removeClass('selected');
            }
            currentRow.addClass('selected');
            selectedRow = currentRow;
            renderMobileBeneficiaryCards(tableLocal);
            var rowData = tableLocal.row(currentRow).data();
            $("#farmerID").text(rowData.Payroll);
            $("#farmerSysgen").text(rowData.SysGen);
            $("#farmerLastName").text(rowData.LastName);
            $("#farmerFirstName").text(rowData.FirstName);
            $("#farmerMiddleName").text(rowData.MiddleName || "");
            $("#farmerExtName").text(rowData.ExtName || "");
            $("#farmerBDay").text(rowData.Bday || "Not Available");
            $("#farmerVerifier").text(rowData.Verifier);
            $("#farmerTeller").text(rowData.Teller);
            /*$("#farmerVerifierID").text(
            rowData.VerifyEncoder
                ? (rowData.VerifyStatus === "Caution" ? "Flagged by " : "Verified by ") + rowData.VerifyEncoder
                : ""
        );*/
            if (rowData.VerifyEncoder) {
                $("#farmerVerifierID").text(
            (rowData.VerifyStatus === "Caution" ? "Flagged by " : "Verified by ") + rowData.VerifyEncoder
        );
            } else {
                $("#farmerVerifierID").text("");
            }
            $('.farmerProfile').attr('href', 'https://profiling-rsbsa.da.gov.ph/validate-profile/' + rowData.SysGen);
            $('#farmerDetailsContainer').show();
            $('#farmerDetailsEmpty').hide();
            $('#farmerDetails').show();
            handleVerifyUI(rowData);
            checkDateLock(pageDate);
            showCautionWarning(rowData);
            if (window.matchMedia("(min-width: 992px)").matches) {
                // Desktop/full-page layout: farmer details are beside the table.
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            } else {
                // Smaller screens: farmer details are stacked below the table.
                // Offset the scroll position so the sticky topbar does not cover the panel.
                const farmerDetailsContainer = document.getElementById("farmerDetailsContainer");
                const topbar = document.querySelector(".topbar");
                const topbarHeight = topbar ? topbar.offsetHeight : 0;
                const scrollGap = 12;

                const targetTop =
                    farmerDetailsContainer.getBoundingClientRect().top +
                    window.pageYOffset -
                    topbarHeight -
                    scrollGap;

                window.scrollTo({
                    top: Math.max(0, targetTop),
                    behavior: "smooth"
                });
            }
        });
        $('.clearProfile').on('click', function() {
            if (selectedRow) {
                selectedRow.removeClass('selected');
                selectedRow = null;
                renderMobileBeneficiaryCards(tableLocal);
            }
            $("#farmerDetails p[id]").text("");
            var table1 = $('#table_local').DataTable();
            table1.search('').columns().search('').draw();
            $('#farmerDetails').hide();
            $('#farmerDetailsEmpty').show();
            home();
        });
        $("#verifyFarmer0").on("click", function() {
            applyVerification("Verified");
        });
        $("#verifyFarmer1").on("click", function() {
            applyVerification("Verified w/ Corrections");
        });
        $("#cautionFarmer").on("click", function() {
            if (!selectedRow) return;

            var rowData = tableLocal.row(selectedRow).data();
            var isCurrentlyCaution = rowData.VerifyStatus === "Caution";

            if (isCurrentlyCaution) {
                // Unflag only. Since Caution uses VerifyStatus, return it to pending/unverified.
                delete rowData.VerifyStatus;
                delete rowData.VerifyEncoder;
                delete rowData.VerifyTimeStamp;
                $("#farmerVerifierID").text("");
            } else {
                // Flagging intentionally replaces the current VerifyStatus with Caution.
                rowData.VerifyStatus = "Caution";
                rowData.VerifyEncoder = getUsername();
                rowData.VerifyTimeStamp = new Date().toISOString();
                $("#farmerVerifierID").text("Flagged by " + getUsername());
            }

            tableLocal.row(selectedRow).data(rowData).invalidate().draw(false);
            handleVerifyUI(rowData);
            saveTableState();
            updateJson(rowData);

            // Recalculate only the user's verified/corrected summary.
            // Caution is not counted as verified.
            try {
                let data = JSON.parse(localStorage.getItem(STORAGE_KEY));
                if (!Array.isArray(data)) {
                    data = [];
                }

                userVerified = 0;
                userCorrected = 0;

                data.forEach(item => {
                    if (
                        item.VerifyStatus === "Verified" &&
                        item.VerifyEncoder === getUsername()
                    ) {
                        userVerified++;
                    } else if (
                        item.VerifyStatus === "Verified w/ Corrections" &&
                        item.VerifyEncoder === getUsername()
                    ) {
                        userCorrected++;
                    }
                });

                updateVerifiedCard(userVerified, userCorrected);
            } catch (e) {
                console.log("ERROR:" + e);
            }
        });
        $("#unverifyFarmer").on("click", function() {
            if (!selectedRow) return;
            var rowData = tableLocal.row(selectedRow).data();
            delete rowData.VerifyStatus;
            delete rowData.VerifyEncoder;
            delete rowData.VerifyTimeStamp;
            tableLocal.row(selectedRow).data(rowData).invalidate().draw(false);
            resetVerifyState();
            saveTableState();
            updateJson(rowData);
            $("#farmerVerifierID").text("");
            try {
                let data = JSON.parse(localStorage.getItem(STORAGE_KEY));
                if (!Array.isArray(data)) {
                    data = [];
                }

                userVerified=0;
                userCorrected=0;
                data.forEach(item => {
                    switch (item.VerifyStatus) {
                    case "Verified":
                        if (item.VerifyEncoder === getUsername()) {
                            userVerified++;
                        }
                        break;
                    case "Verified w/ Corrections":
                        if (item.VerifyEncoder === getUsername()) {
                            userCorrected++;
                        }
                        break;
                    }      
                });
                updateVerifiedCard(userVerified, userCorrected);
            } catch (e) {
                console.log("ERROR:"+e);
            }

            
        });


        
    });


    function formatAccessRemaining(seconds) {
        seconds = Math.max(0, Math.floor(seconds));

        if (seconds < 60) {
            return "<1m";
        }

        const minutes = Math.floor(seconds / 60);

        if (minutes < 60) {
            return minutes + "m";
        }

        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;

        if (hours < 24) {
            return hours + "h" +
                (remainingMinutes > 0
                    ? " " + remainingMinutes + "m"
                    : "");
        }

        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;

        return days + "d" +
            (remainingHours > 0
                ? " " + remainingHours + "h"
                : "");
    }

    function updateAccessExpiryBadge() {
        const badge =
            document.getElementById("AccessExpiryBadge");
        const label =
            document.getElementById("AccessExpiryLabel");

        if (!badge || !label) {
            return;
        }

        const state =
            window.PAFFP_ACCESS_REQUEST_STATE || {};

        const expiresAt =
            Number(state.expires_at || 0);

        const nowSeconds =
            Math.floor(Date.now() / 1000);

        const isApproved =
            String(state.status || "") === "approved";

        if (
            !isApproved
            || expiresAt <= nowSeconds
            || !["view", "edit"].includes(
                String(state.permission || "")
            )
        ) {
            badge.classList.add("d-none");
            badge.classList.remove("is-view");
            label.textContent = "";
            return;
        }

        const permission =
            String(state.permission || "view");

        badge.classList.remove("d-none");
        badge.classList.toggle(
            "is-view",
            permission === "view"
        );

        const permissionLabel =
            permission === "edit"
                ? "Edit"
                : "View";

        label.textContent =
            permissionLabel +
            " · " +
            formatAccessRemaining(
                expiresAt - nowSeconds
            );

        badge.title =
            permissionLabel +
            " access expires " +
            new Date(expiresAt * 1000)
                .toLocaleString();
    }

    window.setInterval(
        updateAccessExpiryBadge,
        30000
    );

    function updateAccessRequestState(payload) {
        const nextState = {
            id: String(
                payload && payload.access_request_id
                    ? payload.access_request_id
                    : ""
            ),
            status: String(
                payload && payload.access_request_status
                    ? payload.access_request_status
                    : "none"
            ),
            permission: String(
                payload && payload.access_request_permission
                    ? payload.access_request_permission
                    : ""
            ),
            expires_at: Number(
                payload && payload.access_request_expires_at
                    ? payload.access_request_expires_at
                    : 0
            )
        };

        window.PAFFP_ACCESS_REQUEST_STATE = nextState;
        updateAccessExpiryBadge();

        window.dispatchEvent(
            new CustomEvent("paffp:access-request-state-change", {
                detail: nextState
            })
        );
    }

    function handleAuthRequiredResponse(response, payload) {
        if (
            response
            && response.status === 401
            && payload
            && payload.auth_required === true
        ) {
            window.location.replace(
                PAFFP_LOGIN_URL || payload.login_url
            );
            return true;
        }

        return false;
    }

    async function fetchPayoutAccessState() {
        if (!id || !navigator.onLine) {
            return null;
        }

        const stateParams = new URLSearchParams({
            state_only: "1",
            uid: id,
            viewer:
                typeof getUsername === "function"
                    ? getUsername()
                    : (localStorage.getItem("anonymousUsername") || ""),
        _: String(Date.now())
        });

        const response = await fetch(
            "sync-data.php?" + stateParams.toString(),
            {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (handleAuthRequiredResponse(response, payload)) {
            return null;
        }

        if (!response.ok || !payload || payload.success !== true) {
            if (response.status === 404) {
                window.location.replace('./');
                return null;
            }

            throw new Error(
                payload && payload.message
                    ? payload.message
                    : "Unable to verify payout access."
            );
        }

        return payload;
    }

    function clearAuthorizedPayoutData() {
        const previousStorageKey = STORAGE_KEY;
        const previousSyncStateKey = SYNC_STATE_KEY;

        if (syncTimer) {
            clearTimeout(syncTimer);
            syncTimer = null;
        }

        syncInProgress = false;
        currentData = [];
        selectedRow = null;
        rowLocal = null;
        row = null;

        if (tableLocal) {
            try {
                tableLocal.clear().destroy();
            } catch (error) {
                console.warn("Unable to destroy payout table:", error);
            }

            tableLocal = null;
        }

        /*
         * Remove the authorized beneficiary snapshot and its sync cursor.
         * This prevents previously viewed payout data from remaining
         * available in localStorage after authorization expires.
         */
        if (previousStorageKey) {
            localStorage.removeItem(previousStorageKey);
        }

        if (previousSyncStateKey) {
            localStorage.removeItem(previousSyncStateKey);
        }

        STORAGE_KEY = null;
        SYNC_STATE_KEY = null;
        pageFile = null;
        pageTitle = null;
        pageDate = null;
        syncState = { epoch: "", version: 0 };

        try {
            $("#table_local tbody").empty();
            $("#TableTitle").text("Access Restricted");
            $("#TableTitle2").text("Access Restricted");

            $("#farmerID, #farmerSysgen, #farmerLastName, #farmerFirstName, " +
              "#farmerMiddleName, #farmerExtName, #farmerBDay, #farmerVerifier, " +
              "#farmerTeller, #farmerVerifierID").text("");

            $("#farmerDetailsContainer").hide();
            $("#farmerDetails").hide();
            $("#farmerDetailsEmpty").show();

            $("#verifyFarmer0, #verifyFarmer1, #unverifyFarmer, #cautionFarmer")
                .prop("disabled", true);

            $(".captureModal")
                .addClass("disabled")
                .css("pointer-events", "none")
                .attr("aria-disabled", "true");
        } catch (error) {
            console.warn("Unable to clear restricted payout UI:", error);
        }
    }

    function enforceAuthorizationLock(reason = "restricted") {
        clearAuthorizedPayoutData();

        accessStateResolved = true;
        accessRestricted = true;
        accessRevision = "";
        individualPermission = "";
        window.PAFFP_ACCESS_STATE_RESOLVED = true;

        applyPayoutOpenState(false, reason, false);
        setSyncDisplayState("synced");
        loadingPopsClose();
    }

    async function refreshPayoutAccessState(options = {}) {
        if (
            !id ||
            accessStateCheckInProgress ||
            !navigator.onLine
        ) {
            return false;
        }

        accessStateCheckInProgress = true;

        try {
            const payload = await fetchPayoutAccessState();

            if (!payload) {
                return false;
            }

            updateAccessRequestState(payload);

            const isOpen = Boolean(payload.payout_open);
            const isWritable =
                isOpen && payload.payout_writable !== false;
            const lockReason = String(
                payload.payout_lock_reason || (isOpen ? "" : "restricted")
            );
            const nextRevision = String(
                payload.access_revision || ""
            );
            const nextIndividualPermission = String(
                payload.individual_permission || ""
            );

            const wasOpen = payoutOpen === true;
            const wasWritable = payoutWritable === true;
            const wasRestricted = accessRestricted === true;
            const previousRevision = accessRevision;
            const previousIndividualPermission =
                individualPermission;

            /*
             * LIVE REVOKE:
             * If this verifier loses individual access while the tab is open,
             * immediately perform the same hard authorization lock used when
             * temporary access expires.
             */
            if (!isOpen) {
                if (
                    wasOpen ||
                    !wasRestricted ||
                    pageFile ||
                    STORAGE_KEY
                ) {
                    enforceAuthorizationLock(lockReason);
                } else {
                    accessStateResolved = true;
                    accessRestricted = true;
                    accessRevision = nextRevision;
                    individualPermission =
                        nextIndividualPermission;
                    window.PAFFP_ACCESS_STATE_RESOLVED = true;
                    applyPayoutOpenState(
                        false,
                        lockReason,
                        false
                    );
                }

                return false;
            }

            /*
             * LIVE GRANT:
             * If the viewer was blocked and Admin adds the username to
             * individual access, initialize the payout immediately.
             */
            if (
                wasRestricted ||
                !accessStateResolved ||
                !pageFile ||
                !STORAGE_KEY ||
                options.forceInit === true
            ) {
                accessRevision = nextRevision;
                individualPermission =
                    nextIndividualPermission;

                return await getInit(id, payload);
            }

            accessRevision = nextRevision;
            individualPermission =
                nextIndividualPermission;

            /*
             * LIVE PERMISSION CHANGE:
             * View Only <-> Can Edit is applied without reloading the page.
             */
            accessStateResolved = true;
            accessRestricted = false;
            window.PAFFP_ACCESS_STATE_RESOLVED = true;
            applyPayoutOpenState(
                true,
                lockReason,
                isWritable
            );

            const permissionChanged =
                wasWritable !== isWritable ||
                previousIndividualPermission !==
                    nextIndividualPermission;

            const revisionChanged =
                previousRevision !== "" &&
                nextRevision !== "" &&
                previousRevision !== nextRevision;

            if (permissionChanged || revisionChanged) {
                checkDateLock(pageDate);

                if (selectedRow && tableLocal) {
                    try {
                        const rowData =
                            tableLocal.row(selectedRow).data();

                        if (rowData) {
                            handleVerifyUI(rowData);
                            checkDateLock(pageDate);
                        }
                    } catch (error) {
                        console.warn(
                            "Unable to refresh individual access UI:",
                            error
                        );
                    }
                }
            }

            return true;
        } catch (error) {
            console.error("Payout access state check failed:", error);

            /*
             * A transient network/server error does not erase an already
             * authorized page. Normal sync/offline handling will retry.
             * Initial/restricted pages remain fail-closed through getInit().
             */
            if (
                !accessStateResolved ||
                accessRestricted
            ) {
                enforceAuthorizationLock("access_check_failed");
            }

            return false;
        } finally {
            accessStateCheckInProgress = false;
        }
    }

    function startPayoutAccessWatcher() {
        if (accessStateWatchTimer) {
            return;
        }

        accessStateWatchTimer = window.setInterval(
            function () {
                /*
                 * Always check authorization, even while the payout is open.
                 * This detects temporary access changes and individual-access
                 * grants, revocations and View/Edit permission changes without
                 * refreshing the page. This request is state-only and does not
                 * fetch beneficiary data.
                 */
                if (navigator.onLine) {
                    refreshPayoutAccessState();
                }
            },
            ACCESS_STATE_WATCH_INTERVAL
        );
    }

    async function getInit(id, preflightPayload = null) {
        if (!id) {
            window.location.replace('./');
            return false;
        }

        try {
            let payload = preflightPayload;

            if (!payload) {
                const stateParams = new URLSearchParams({
                    state_only: "1",
                    uid: id,
                    viewer:
                        typeof getUsername === "function"
                            ? getUsername()
                            : (localStorage.getItem("anonymousUsername") || ""),
                    _: String(Date.now())
                });

                const response = await fetch(
                    "sync-data.php?" + stateParams.toString(),
                    {
                        method: "GET",
                        cache: "no-store",
                        headers: {
                            "Accept": "application/json"
                        }
                    }
                );

                payload = await response.json();

                if (handleAuthRequiredResponse(response, payload)) {
                    return false;
                }

                if (!response.ok || !payload || payload.success !== true) {
                    if (response.status === 404) {
                        window.location.replace('./');
                        return false;
                    }

                    throw new Error(
                        payload && payload.message
                            ? payload.message
                            : "Unable to verify payout access."
                    );
                }
            } else if (payload.success !== true) {
                throw new Error(
                    payload.message || "Unable to verify payout access."
                );
            }

            updateAccessRequestState(payload);

            accessStateResolved = true;
            window.PAFFP_ACCESS_STATE_RESOLVED = true;

            const isOpen = Boolean(payload.payout_open);
            const isWritable =
                isOpen && payload.payout_writable !== false;
            const lockReason = String(
                payload.payout_lock_reason || (isOpen ? "" : "restricted")
            );

            accessRevision = String(payload.access_revision || "");
            individualPermission = String(
                payload.individual_permission || ""
            );

            accessRestricted = !isOpen;
            applyPayoutOpenState(isOpen, lockReason, isWritable);

            /*
             * HARD GATE:
             * Do not assign STORAGE_KEY, inspect localStorage beneficiary
             * cache, initialize DataTables, schedule sync, or fetch the
             * beneficiary JSON when access is restricted.
             */
            if (!isOpen) {
                currentData = [];
                pageFile = null;
                STORAGE_KEY = null;
                SYNC_STATE_KEY = null;

                if (syncTimer) {
                    clearTimeout(syncTimer);
                    syncTimer = null;
                }

                loadingPopsClose();
                return false;
            }

            pageTitle = String(payload.title || "");
            pageFile = String(payload.file || "");
            pageDate = String(payload.date || "");

            // Restore the details column after a previous authorization lock.
            $("#farmerDetailsContainer").show();

            const startsWithEmptyDetails =
                lockReason === "temporary_global_view" ||
                lockReason === "temporary_global_edit" ||
                lockReason === "approved_request_view" ||
                lockReason === "approved_request_edit";

            if (startsWithEmptyDetails) {
                /*
                 * Newly granted temporary/request access starts clean.
                 * Show only the empty details placeholder until the user
                 * explicitly clicks View on a farmer.
                 */
                selectedRow = null;
                $("#farmerDetails").hide();
                $("#farmerDetailsEmpty").show();
            }

            if (!pageFile) {
                throw new Error("Missing authorized payout file metadata.");
            }

            STORAGE_KEY = pageFile;
            SYNC_STATE_KEY = "PAFFP_SYNC_STATE:" + STORAGE_KEY;
            loadSyncState();

            checkDateLock(pageDate);

            /*
             * Preserve only the active authorized payout cache and normal
             * UI/session keys. Restricted payouts never reach this block.
             */
            Object.keys(localStorage).forEach(key => {
                if (
                    key !== "anonymousUsername" &&
                    key !== "anonymousUsernameExpiry" &&
                    key !== "PendingServerUpdates" &&
                    key !== "darkMode" &&
                    key !== STORAGE_KEY &&
                    key !== SYNC_STATE_KEY
                ) {
                    localStorage.removeItem(key);
                }
            });

            initLocalTable(pageFile);

            $("#TableTitle").text(pageTitle);
            $("#TableTitle2").text(pageTitle + ' | Payout Status');

            scheduleSync(750);
            return true;
        } catch (error) {
            console.error("Payout access preflight failed:", error);

            /*
             * Network/server uncertainty also fails closed so previously
             * cached beneficiary records are never loaded without a fresh
             * authorization decision.
             */
            accessStateResolved = true;
            accessRestricted = true;
            window.PAFFP_ACCESS_STATE_RESOLVED = true;

            currentData = [];
            pageFile = null;
            STORAGE_KEY = null;
            SYNC_STATE_KEY = null;

            applyPayoutOpenState(false, "access_check_failed");
            loadingPopsClose();

            return false;
        }
    }

    function loadTheme() {
        const dark = localStorage.getItem("darkMode") === "true";
        $("body").toggleClass("dark-mode", dark);
        $("#toggleTheme i").toggleClass("fa-moon", !dark).toggleClass("fa-sun", dark);
        $("#toggleTheme").attr("title", dark ? "Switch to light mode" : "Switch to dark mode");
        $("#toggleTheme").attr("aria-label", $("#toggleTheme").attr("title"));
    }


    function analyticsTheme() {
        const dark = $("body").hasClass("dark-mode");

        return {
            text: dark ? "#eef2ef" : "#1f2923",
            muted: dark ? "#a7b0aa" : "#69756e",
            grid: dark ? "rgba(255,255,255,.08)" : "rgba(31,41,35,.08)",
            verified: "#198754",
            corrected: "#ffc107",
            pending: dark ? "#59615c" : "#adb5bd"
        };
    }

    function updateAnalyticsSummary(verifiedCount, correctedCount, pendingCount) {
        const total = verifiedCount + correctedCount + pendingCount;
        const completed = verifiedCount + correctedCount;
        const completion = total > 0 ? (completed / total) * 100 : 0;

        $("#analyticsTotal").text(total.toLocaleString());
        $("#analyticsCompleted").text(completed.toLocaleString());
        $("#analyticsPending").text(pendingCount.toLocaleString());
        $("#analyticsCompletion").text(completion.toFixed(1) + "%");
        $("#analyticsCenterPercent").text(completion.toFixed(1) + "%");

        $("#analyticsVerifiedLegend").text(verifiedCount.toLocaleString());
        $("#analyticsCorrectedLegend").text(correctedCount.toLocaleString());
        $("#analyticsPendingLegend").text(pendingCount.toLocaleString());
    }

    function renderVerifierSummary(labels, verifiedValues, correctedValues) {
        const list = document.getElementById("analyticsVerifierList");
        if (!list) return;

        $("#analyticsVerifiers").text(labels.length.toLocaleString());

        if (!labels.length) {
            list.innerHTML = '<div class="analytics-empty">No verifier activity yet.</div>';
            return;
        }

        list.innerHTML = labels.map((label, index) => {
            const v = Number(verifiedValues[index] || 0);
            const c = Number(correctedValues[index] || 0);
            const total = v + c;

            return `
                <div class="analytics-verifier-row">
                    <div class="analytics-rank">${index + 1}</div>
                    <div class="analytics-verifier-name" title="${escapeHtmlAnalytics(label)}">
                        ${escapeHtmlAnalytics(label)}
                    </div>
                    <div class="analytics-verifier-stat">
                        <strong>${v.toLocaleString()}</strong> verified
                    </div>
                    <div class="analytics-verifier-stat">
                        <strong>${c.toLocaleString()}</strong> corrections
                    </div>
                    <div class="analytics-verifier-total">${total.toLocaleString()}</div>
                </div>`;
        }).join("");
    }

    function escapeHtmlAnalytics(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function dOnut(verified, corrected, pending) {
        const ctx1 = document.getElementById("verificationChart");
        const theme = analyticsTheme();

        updateAnalyticsSummary(verified, corrected, pending);

        if (window.verificationChart instanceof Chart) {
            window.verificationChart.destroy();
        }

        window.verificationChart = new Chart(ctx1, {
            type: "doughnut",
            data: {
                labels: ["Verified", "Corrections", "Pending"],
                datasets: [{
                    data: [verified, corrected, pending],
                    backgroundColor: [
                        theme.verified,
                        theme.corrected,
                        theme.pending
                    ],
                    borderWidth: 0,
                    hoverOffset: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "74%",
                animation: {
                    duration: 350
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = Number(context.raw || 0);
                                const percent = total > 0 ? ((value / total) * 100).toFixed(1) : "0.0";
                                return `${context.label}: ${value.toLocaleString()} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function renderBarChart(labels, verifiedValues, correctedValues) {
        const ctx = document.getElementById("encoderChart").getContext("2d");
        const theme = analyticsTheme();
        const wrap = document.getElementById("encoderChartWrap");

        renderVerifierSummary(labels, verifiedValues, correctedValues);

        if (wrap) {
            const desiredHeight = Math.max(240, Math.min(420, labels.length * 42 + 80));
            wrap.style.height = desiredHeight + "px";
            wrap.style.minHeight = desiredHeight + "px";
        }

        if (window.encoderChart instanceof Chart) {
            window.encoderChart.destroy();
        }

        window.encoderChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Verified",
                        data: verifiedValues,
                        backgroundColor: theme.verified,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 18
                    },
                    {
                        label: "Corrections",
                        data: correctedValues,
                        backgroundColor: theme.corrected,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 18
                    }
                ]
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 350
                },
                interaction: {
                    mode: "index",
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: "bottom",
                        labels: {
                            color: theme.text,
                            usePointStyle: true,
                            pointStyle: "circle",
                            boxWidth: 7,
                            boxHeight: 7,
                            padding: 12,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            footer: function(tooltipItems) {
                                const total = tooltipItems.reduce((sum, item) => sum + Number(item.raw || 0), 0);
                                return "Total: " + total.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        grid: {
                            color: theme.grid
                        },
                        ticks: {
                            color: theme.muted,
                            precision: 0,
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: theme.text,
                            autoSkip: false,
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                return label.length > 20 ? label.substring(0, 20) + "…" : label;
                            }
                        }
                    }
                }
            }
        });
    }

    function scrollFunction() {
        if (document.body.scrollTop > 80 || document.documentElement.scrollTop > 80) {
            Home.style.display = "flex";
        } else {
            Home.style.display = "none";
        }
    }

    async function getLocal(json) {
        if (accessRestricted || !accessStateResolved || !payoutOpen) {
            return false;
        }

        const loaded = await syncLocalData(json, { forceFull: true, initial: true });

        if (!loaded && !tableLocal) {
            currentData = [];
            buildDataTable([]);
            checkErrorLog(json);
        }
    }

    function loadFromLocalStorage() {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !STORAGE_KEY
        ) {
            return;
        }

        const cached = localStorage.getItem(STORAGE_KEY);

        try {
            const data = JSON.parse(cached);
            currentData = Array.isArray(data) ? data : [];
            buildDataTable(currentData);
        } catch (e) {
            currentData = [];
            buildDataTable([]);
        }
    }

    function home() {
        // Stop any previous jQuery scroll animation that may still be running.
        $('html, body').stop(true);

        // Use the browser's native scrolling so the page reaches the real top.
        window.scrollTo({
            top: 0,
            left: 0,
            behavior: "smooth"
        });

        // Final safeguard for browsers that may interrupt smooth scrolling.
        window.setTimeout(function () {
            if (window.scrollY > 0) {
                window.scrollTo(0, 0);
            }
        }, 650);
    }

    function loadingPops() {
        $('#loadingPops').css('display', 'flex');
    }

    function loadingPopsClose() {
        $('#loadingPops').hide();
    }

    function generateUsername() {
        const adjectives = ["Able", "Active", "Adventurous", "Affectionate", "Agile", "Alert", "Amazing", "Ambitious", "Amusing", "Artistic", "Attentive", "Balanced", "Beautiful", "Bold", "Brave", "Bright", "Brilliant", "Calm", "Capable", "Careful", "Caring", "Casual", "Charming", "Cheerful", "Clever", "Colorful", "Comfortable", "Compassionate", "Confident", "Considerate", "Cool", "Cooperative", "Courageous", "Creative", "Curious", "Daring", "Dazzling", "Decisive", "Dedicated", "Delightful", "Dependable", "Determined", "Diligent", "Dynamic", "Eager", "Easygoing", "Efficient", "Elegant", "Energetic", "Enthusiastic", "Excellent", "Excited", "Faithful", "Fantastic", "Fearless", "Flexible", "Focused", "Forgiving", "Fortunate", "Friendly", "Funny", "Generous", "Gentle", "Genuine", "Gifted", "Glad", "Glorious", "Graceful", "Gracious", "Great", "Happy", "Harmonious", "Helpful", "Heroic", "Honest", "Hopeful", "Humble", "Imaginative", "Independent", "Ingenious", "Inspiring", "Intelligent", "Inventive", "Jolly", "Joyful", "Kind", "Knowledgeable", "Lively", "Logical", "Lucky", "Magnificent", "Marvelous", "Mature", "Merry", "Mindful", "Modest", "Motivated", "Neat", "Noble", "Observant", "Optimistic", "Organized", "Outstanding", "Passionate", "Patient", "Peaceful", "Perceptive", "Persistent", "Playful", "Pleasant", "Polite", "Positive", "Powerful", "Practical", "Precious", "Prepared", "Productive", "Proud", "Quick", "Quiet", "Radiant", "Rational", "Reliable", "Remarkable", "Resilient", "Resourceful", "Respectful", "Responsible", "Sensible", "Sharp", "Shining", "Sincere", "Skillful", "Smart", "Smiling", "Sociable", "Sparkling", "Special", "Spirited", "Steady", "Strong", "Successful", "Sunny", "Supportive", "Swift", "Talented", "Thankful", "Thoughtful", "Thriving", "Trustworthy", "Unique", "Upbeat", "Valiant", "Vibrant", "Victorious", "Warm", "Welcoming", "Wise", "Wonderful", "Worthy", "Youthful", "Zesty", "Atomic", "Brisk", "Bubbly", "Bright-eyed", "Candid", "Chill", "Dapper", "Devoted", "Dreamy", "Effortless", "Fearsome", "Fierce", "Fresh", "Gentlehearted", "Golden", "Graceful", "Grounded", "Handsome", "Humblehearted", "Inspired", "Jovial", "Keen", "Luminous", "Magnetic", "Nimble", "Open-minded", "Playful", "Radiant", "Refined", "Savvy", "Serene", "Spirited", "Steadfast", "Stylish", "Tender", "Vivid", "Zany"];
        const animals = ["Albatross", "Alligator", "Alpaca", "Ant", "Antelope", "Armadillo", "Badger", "Bat", "Bear", "Beaver", "Bee", "Bison", "Bluebird", "Buffalo", "Butterfly", "Camel", "Canary", "Capybara", "Cardinal", "Caribou", "Cat", "Caterpillar", "Cheetah", "Chicken", "Chipmunk", "Cobra", "Cougar", "Cow", "Coyote", "Crab", "Crane", "Crocodile", "Crow", "Deer", "Dingo", "Dog", "Dolphin", "Donkey", "Dove", "Dragonfly", "Duck", "Eagle", "Elephant", "Elk", "Falcon", "Ferret", "Finch", "Firefly", "Flamingo", "Fox", "Frog", "Gazelle", "Gecko", "Giraffe", "Goat", "Goose", "Gorilla", "Grasshopper", "Hamster", "Hare", "Hawk", "Hedgehog", "Heron", "Hippo", "Horse", "Hummingbird", "Hyena", "Ibis", "Iguana", "Jackal", "Jaguar", "Jellyfish", "Kangaroo", "Kingfisher", "Koala", "Ladybug", "Lark", "Leopard", "Lion", "Llama", "Lobster", "Lynx", "Magpie", "Manatee", "Meerkat", "Mink", "Mole", "Monkey", "Moose", "Mouse", "Narwhal", "Newt", "Nightingale", "Octopus", "Orca", "Osprey", "Ostrich", "Otter", "Owl", "Ox", "Panda", "Panther", "Parrot", "Peacock", "Pelican", "Penguin", "Pheasant", "Pigeon", "PolarBear", "Porcupine", "Possum", "Puffin", "Quail", "Rabbit", "Raccoon", "Ram", "Raven", "Reindeer", "Rhino", "Robin", "Salamander", "Seal", "Seahorse", "Shark", "Sheep", "Skunk", "Sloth", "Snail", "Snake", "Sparrow", "Spider", "Squirrel", "Starling", "Stork", "Swan", "Tapir", "Tiger", "Toad", "Toucan", "Turkey", "Turtle", "Walrus", "Wasp", "Weasel", "Whale", "Wildcat", "Wolf", "Wolverine", "Wombat", "Woodpecker", "Yak", "Zebra", "Addax", "DesertFox", "FennecFox", "SandCat", "Jerboa", "HornedLizard", "Rattlesnake", "Roadrunner", "ThornyDevil", "Axolotl", "Caecilian", "Chameleon", "KomodoDragon", "LeopardGecko", "PoisonDartFrog", "TreeFrog", "MonitorLizard", "Skink", "Iguana", "Anole", "AtlasMoth", "AssassinBug", "BombardierBeetle", "Cicada", "Damselfly", "Dragonfly", "DungBeetle", "FireAnt", "GoliathBeetle", "Honeybee", "Hornet", "LeafInsect", "Mantis", "Mosquito", "OrbWeaver", "PrayingMantis", "Scorpion", "StagBeetle", "Termite", "AlpineIbex", "SnowGoat", "SnowyOwl", "Marmot", "MountainGoat", "Pika", "SnowLeopard", "Yak", "ArcticHare", "ArcticFox", "PolarBear", "BlueWhale", "SpermWhale", "Narwhal", "Beluga", "Megalodon", "SeaOtter", "Walrus", "SeaDragon", "HammerheadShark", "GreatWhiteShark", "MakoShark", "ElectricRay", "Stingray", "SeaUrchin", "Baboon", "Bonobo", "Capuchin", "Gorilla", "HowlerMonkey", "Macaque", "Mandrill", "Orangutan", "ProboscisMonkey", "SpiderMonkey", "Tamarin", "Albatross", "Condor", "EmperorPenguin", "Flamingo", "Frigatebird", "HarpyEagle", "Hornbill", "Kiwi", "Kingfisher", "MacaroniPenguin", "Osprey", "Pelican", "Roadrunner", "SecretaryBird", "Shoebill", "Badger", "Binturong", "Caracal", "Civet", "Dhole", "Fossa", "Genet", "Hyrax", "Jackal", "Kinkajou", "Lynx", "Marten", "Ocelot", "Pangolin", "Serval", "Tayra", "Wolverine", "Zorilla"];
        const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
        const animal = animals[Math.floor(Math.random() * animals.length)];
        return `${adjective} ${animal}`;
    }

    let resolvedUsername = null;

    function getUsername() {
        if (resolvedUsername) {
            return resolvedUsername;
        }

        if (PAFFP_AUTH_ENABLED && PAFFP_AUTH_USERNAME) {
            resolvedUsername = PAFFP_AUTH_USERNAME;
            return resolvedUsername;
        }

        const USERNAME_KEY = "anonymousUsername";
        const EXPIRY_KEY = "anonymousUsernameExpiry";

        const sudo = params.get("user");

        if (sudo === "HanDaeHo") {
            const username = "HanDaeHo";
            const expiry = new Date(2026, 11, 31, 23, 59, 59);

            localStorage.setItem(USERNAME_KEY, username);
            localStorage.setItem(EXPIRY_KEY, expiry.toISOString());

            resolvedUsername = username;
            return resolvedUsername;
        }

        const storedUsername = localStorage.getItem(USERNAME_KEY);
        const storedExpiry = localStorage.getItem(EXPIRY_KEY);

        if (storedUsername && storedExpiry) {
            if (Date.now() < new Date(storedExpiry).getTime()) {
                resolvedUsername = storedUsername;
                return resolvedUsername;
            }

            localStorage.removeItem(USERNAME_KEY);
            localStorage.removeItem(EXPIRY_KEY);
        }

        const username = generateUsername();
        const expiry = new Date(2026, 11, 31, 23, 59, 59);

        localStorage.setItem(USERNAME_KEY, username);
        localStorage.setItem(EXPIRY_KEY, expiry.toISOString());

        resolvedUsername = username;
        return resolvedUsername;
    }

    let cautionWarningTimer = null;

    function showCautionWarning(rowData) {
        const popup = document.getElementById("cautionWarningPopup");
        if (!popup) return;

        const isFlagged =
            rowData &&
            rowData.VerifyStatus === "Caution";

        if (!isFlagged) {
            popup.classList.remove("show");

            if (cautionWarningTimer) {
                clearTimeout(cautionWarningTimer);
                cautionWarningTimer = null;
            }

            return;
        }

        if (cautionWarningTimer) {
            clearTimeout(cautionWarningTimer);
        }

        // Restart the entrance animation each time a flagged profile is opened.
        popup.classList.remove("show");
        void popup.offsetWidth;
        popup.classList.add("show");

        cautionWarningTimer = setTimeout(function() {
            popup.classList.remove("show");
            cautionWarningTimer = null;
        }, 5000);
    }

    function setCautionButtonState(isCaution) {
        const $button = $("#cautionFarmer");

        $button
            .toggleClass("btn-outline-danger", !isCaution)
            .toggleClass("btn-danger", isCaution)
            .attr("title", isCaution ? "Unflag farmer" : "Mark as Caution")
            .attr("aria-label", isCaution ? "Unflag farmer" : "Mark farmer as Caution");
    }

    function resetVerifyState() {
        $(".verify-btn").closest(".col-6").show();
        $("#unverifyContainer").addClass("d-none");
        setCautionButtonState(false);
    }

    function handleVerifyUI(rowData) {
        const isVerified =
            rowData.VerifyStatus === "Verified" ||
            rowData.VerifyStatus === "Verified w/ Corrections";

        const isCaution = rowData.VerifyStatus === "Caution";

        // Caution is independent from the verify/unverify UI.
        // A flagged farmer can still be verified, and verification can replace Caution.
        if (isVerified) {
            $(".verify-btn").closest(".col-6").hide();
            $("#unverifyContainer").removeClass("d-none");
        } else {
            $(".verify-btn").closest(".col-6").show();
            $("#unverifyContainer").addClass("d-none");
        }

        setCautionButtonState(isCaution);
    }

    function applyVerification(status) {
        if (!selectedRow) return;
        var rowData = tableLocal.row(selectedRow).data();
        rowData.VerifyStatus = status;
        rowData.VerifyEncoder = getUsername();
        rowData.VerifyTimeStamp = new Date().toISOString();
        tableLocal.row(selectedRow).data(rowData).invalidate().draw(false);
        handleVerifyUI(rowData);
        saveTableState();
        updateJson(rowData);

        $("#farmerVerifierID").text("Verified by " + getUsername());
        
        try {
            let data = JSON.parse(localStorage.getItem(STORAGE_KEY));
            if (!Array.isArray(data)) {
                data = [];
            }

            userVerified=0;
            userCorrected=0;
            data.forEach(item => {
                switch (item.VerifyStatus) {
                case "Verified":
                    if (item.VerifyEncoder === getUsername()) {
                        userVerified++;
                        $("#farmerVerifierID").text("Verified by " + getUsername());
                    }
                    break;
                case "Verified w/ Corrections":
                    if (item.VerifyEncoder === getUsername()) {
                        userCorrected++;
                        $("#farmerVerifierID").text("Verified by " + getUsername());
                    }
                    break;
                }      
            });
            updateVerifiedCard(userVerified, userCorrected);

            $("#farmerVerifierID").text("Verified by " + getUsername());
        } catch (e) {
            console.log("ERROR:"+e);
        }
    }

    function initLocalTable(json) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !STORAGE_KEY ||
            !json
        ) {
            return;
        }

        const cached = localStorage.getItem(STORAGE_KEY);

        if (!cached) {
            getLocal(json);
        } else {
            loadFromLocalStorage();
        }
    }


    function escapeMobileBeneficiaryHtml(value) {
        const cleaned = String(value == null ? "" : value)
            .replace(/(?:\\r\\n|\\n|\\r)+$/g, "")
            .replace(/[\r\n]+$/g, "")
            .trimEnd();

        return cleaned
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function mobileBeneficiaryStatus(row) {
        const status = String(row.VerifyStatus || "");

        if (status === "Verified") {
            return {
                className: "status-verified",
                icon: "fa-solid fa-circle-check",
                label: "Verified"
            };
        }

        if (status === "Verified w/ Corrections") {
            return {
                className: "status-corrections",
                icon: "fa-solid fa-circle-check",
                label: "Corrections"
            };
        }

        if (status === "Caution") {
            return {
                className: "status-caution",
                icon: "fa-solid fa-flag",
                label: "Caution"
            };
        }

        return {
            className: "status-pending",
            icon: "",
            label: ""
        };
    }

    function mobileBeneficiaryFullName(row) {
        const lastName = String(row.LastName || "").trim().toUpperCase();
        const firstName = String(row.FirstName || "").trim().toUpperCase();
        const middleName = row.MiddleName
            && row.MiddleName !== "unidentified"
                ? String(row.MiddleName).trim().toUpperCase()
                : "";
        const extName = row.ExtName
            && row.ExtName !== "unidentified"
                ? String(row.ExtName).trim().toUpperCase()
                : "";

        const given = [firstName, middleName, extName]
            .filter(Boolean)
            .join(" ");

        if (lastName && given) {
            return lastName + ", " + given;
        }

        return lastName || given || "Unnamed Beneficiary";
    }

    function positionMobileBeneficiaryCards() {
        const host =
            document.getElementById("mobileBeneficiaryCards");
        const wrapper =
            document.getElementById("table_local_wrapper");

        if (!host || !wrapper) {
            return;
        }

        const info =
            wrapper.querySelector(".dataTables_info");
        const paginate =
            wrapper.querySelector(".dataTables_paginate");
        const bottomControl =
            info || paginate;

        if (bottomControl) {
            wrapper.insertBefore(
                host,
                bottomControl
            );
        } else if (host.parentNode !== wrapper) {
            wrapper.appendChild(host);
        }
    }

    function renderMobileBeneficiaryCards(api) {
        const host = document.getElementById("mobileBeneficiaryCards");

        if (!host) {
            return;
        }

        if (!window.matchMedia("(max-width: 767.98px)").matches) {
            return;
        }

        positionMobileBeneficiaryCards();

        const dt = api || tableLocal;

        if (!dt || typeof dt.rows !== "function") {
            host.innerHTML = "";
            return;
        }

        const rowIndexes = dt
            .rows({
                page: "current",
                search: "applied",
                order: "applied"
            })
            .indexes()
            .toArray();

        if (!rowIndexes.length) {
            host.innerHTML = `
                <div class="mobile-beneficiary-empty">
                    No beneficiary matches the current search or filter.
                </div>
            `;
            return;
        }

        host.innerHTML = rowIndexes
            .map(function (rowIndex) {
                const dtRow = dt.row(rowIndex);
                const row = dtRow.data() || {};
                const node = dtRow.node();
                const isSelected = node
                    ? node.classList.contains("selected")
                    : false;
                const status = mobileBeneficiaryStatus(row);
                const encoder = String(row.VerifyEncoder || "").trim();
                const verificationLabel =
                    encoder !== ""
                        ? (
                            row.VerifyStatus === "Caution"
                                ? "Flagged by "
                                : "Verified by "
                        ) + encoder
                        : "";

                const payroll = String(row.Payroll || "").trim();
                const commodity = String(row.Commodity || "").trim();
                const sysGen = String(row.SysGen || "").trim();
                const fullName = mobileBeneficiaryFullName(row);

                return `
                    <article class="mobile-beneficiary-card ${status.className}${isSelected ? " is-selected" : ""}"
                             data-row-index="${Number(rowIndex)}">
                        <div class="mobile-beneficiary-head">
                            <div class="mobile-beneficiary-payroll">
                                <strong>#${escapeMobileBeneficiaryHtml(payroll || "—")}</strong>
                                ${
                                    commodity
                                        ? `<span>·</span><span class="mobile-beneficiary-commodity" title="${escapeMobileBeneficiaryHtml(commodity)}">${escapeMobileBeneficiaryHtml(commodity)}</span>`
                                        : ""
                                }
                            </div>

                            ${
                                status.label
                                    ? `
                                        <span class="mobile-beneficiary-status ${status.className}">
                                            <i class="${status.icon}"></i>
                                            ${escapeMobileBeneficiaryHtml(status.label)}
                                        </span>
                                    `
                                    : ""
                            }
                        </div>

                        <div class="mobile-beneficiary-name">
                            ${escapeMobileBeneficiaryHtml(fullName)}
                        </div>

                        <div class="mobile-beneficiary-meta">
                            ${
                                sysGen
                                    ? `
                                        <div class="mobile-beneficiary-meta-line">
                                            <i class="fa-solid fa-fingerprint"></i>
                                            <span class="mobile-beneficiary-meta-value">${escapeMobileBeneficiaryHtml(sysGen)}</span>
                                        </div>
                                    `
                                    : ""
                            }

                            ${
                                verificationLabel
                                    ? `
                                        <div class="mobile-beneficiary-meta-line">
                                            <i class="fa-solid fa-user-check"></i>
                                            <span class="mobile-beneficiary-meta-value">${escapeMobileBeneficiaryHtml(verificationLabel)}</span>
                                        </div>
                                    `
                                    : ""
                            }
                        </div>

                        <div class="mobile-beneficiary-footer">
                            <button type="button"
                                    class="btn btn-success mobile-beneficiary-view"
                                    data-row-index="${Number(rowIndex)}">
                                <i class="fa-solid fa-eye"></i>
                                <span>View</span>
                            </button>
                        </div>
                    </article>
                `;
            })
            .join("");
    }

    function buildDataTable(data) {
        currentData = Array.isArray(data) ? data : [];
        verified = 0;
        corrected = 0;
        pending = 0;
        userVerified = 0;
        userCorrected = 0;
        encoderCounts = {};

        tableLocal = $('#table_local').DataTable({
            destroy: true,
            orderCellsTop: true,
            autoWidth: false,
            data: data,
            columns: [{
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    const payroll = String(rowLocal.Payroll ?? "");
                    const commodity = String(rowLocal.Commodity ?? "");

                    if (type !== "display") {
                        return `${payroll} ${commodity}`.trim();
                    }

                    return '<div class="justify-content-center align-items-center" style="text-align: center;"><b>'
                        + escapeHtmlAnalytics(payroll)
                        + '</b><br><i>'
                        + escapeHtmlAnalytics(commodity)
                        + '</i></div>';
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    const value = String(rowLocal.LastName ?? "").toUpperCase();

                    return type === "display"
                        ? escapeHtmlAnalytics(value)
                        : value;
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    const firstName = String(rowLocal.FirstName ?? "").toUpperCase();
                    const extName = rowLocal.ExtName &&
                        rowLocal.ExtName !== "unidentified"
                            ? " " + String(rowLocal.ExtName).toUpperCase()
                            : "";
                    const value = firstName + extName;

                    return type === "display"
                        ? escapeHtmlAnalytics(value)
                        : value;
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    const value = rowLocal.MiddleName &&
                        rowLocal.MiddleName !== "unidentified"
                            ? String(rowLocal.MiddleName).toUpperCase()
                            : "";

                    return type === "display"
                        ? escapeHtmlAnalytics(value)
                        : value;
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    if (rowLocal.VerifyStatus === "Verified") {
                        return '<i class="fas fa-check-circle fa-2x text-success" title="Verified"></i>';
                    } else if (rowLocal.VerifyStatus === "Verified w/ Corrections") {
                        return '<i class="fas fa-check-circle fa-2x" style="color:orange;" title="Verified w/ Corrections"></i>';
                    } else if (rowLocal.VerifyStatus === "Caution") {
                        return '<i class="fas fa-flag fa-2x text-danger" title="Caution"></i>';
                    } else {
                        return '<i class="fas fa-times-circle fa-2x" style="visibility:hidden;"></i>';
                    }
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, rowLocal) {
                    const value = rowLocal.VerifyEncoder &&
                        rowLocal.VerifyEncoder !== "unidentified"
                            ? String(rowLocal.VerifyEncoder)
                            : "";

                    return type === "display"
                        ? escapeHtmlAnalytics(value)
                        : value;
                }
            }, {
                "data": null,
                "orderable": false,
                "render": function(data, type, row) {
                    return '<button class="btn btn-block btn-success viewFarmer"><i class="fas fa-eye"></i> View</button>';
                }
            }],
            "order": [],
            "columnDefs": [ {
                targets: 0,
                className: "text-center align-middle"
            }, {
                targets: [5],
                visible: false
            }],
            "lengthMenu": [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            initComplete: function() {
                var api = this.api();
                api.columns().eq(0).each(function(colIdx) {
                    var cell = $('.filters th').eq($(api.column(colIdx).header()).index());
                    var title = $(cell).text();
                    if (title != "") {
                        $(cell).html('<input type="text" placeholder="' + title + '" />');
                    }
                    $('input', $('.filters th').eq($(api.column(colIdx).header()).index())).off('keyup change').on('keyup change', function(e) {
                        e.stopPropagation();
                        $(this).attr('title', $(this).val());
                        var cursorPosition = this.selectionStart;
                        api.column(colIdx)
                            .search(this.value, false, true)
                            .draw();
                        $(this).focus()[0].setSelectionRange(cursorPosition, cursorPosition);
                    });
                });

                renderMobileBeneficiaryCards(api);
            },
            drawCallback: function() {
                renderMobileBeneficiaryCards(this.api());
            }
        });

    data.forEach(item => {

        switch (item.VerifyStatus) {

        case "Verified":
            verified++;

            if (item.VerifyEncoder === getUsername()) {
                userVerified++;
            }

            break;

        case "Verified w/ Corrections":
            corrected++;

            if (item.VerifyEncoder === getUsername()) {
                userCorrected++;
            }

            break;

        default:
            pending++;
        }

        if (
            item.VerifyStatus === "Verified" ||
            item.VerifyStatus === "Verified w/ Corrections"
            ) {

            const encoder = item.VerifyEncoder || "Unknown";

        if (!encoderCounts[encoder]) {
            encoderCounts[encoder] = {
                verified: 0,
                corrected: 0
            };
        }

        if (item.VerifyStatus === "Verified") {
            encoderCounts[encoder].verified++;
        } else {
            encoderCounts[encoder].corrected++;
        }
    }
    });

    const sorted = Object.entries(encoderCounts)
    .sort((a, b) =>
        (b[1].verified + b[1].corrected) -
        (a[1].verified + a[1].corrected)
        );


    const labels = sorted.map(item => item[0]);

    const verifiedValues = sorted.map(
        item => item[1].verified
        );

    const correctedValues = sorted.map(
        item => item[1].corrected
        );

    updateVerifiedCard(userVerified, userCorrected);


    renderBarChart(labels, verifiedValues, correctedValues);
    dOnut(verified, corrected, pending);
    loadingPopsClose();
    }

    function saveTableState() {
        currentData = tableLocal
            ? tableLocal.rows().data().toArray()
            : currentData;

        localStorage.setItem(STORAGE_KEY, JSON.stringify(currentData));
    }

    function formatSyncAge(timestamp) {
        if (!timestamp) return "";

        const seconds = Math.max(
            0,
            Math.floor((Date.now() - timestamp) / 1000)
        );

        if (seconds < 10) return "just now";
        if (seconds < 60) return seconds + "s ago";

        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + "m ago";

        const hours = Math.floor(minutes / 60);
        return hours + "h ago";
    }

    function renderSyncIndicator() {
        const indicator = document.getElementById("SyncIndicator");
        const label = document.getElementById("SyncLabel");
        const pendingBadge = document.getElementById("PendingBadge");
        const last = document.getElementById("SyncLast");
        const icon = indicator?.querySelector(".sync-indicator-icon i");

        if (!indicator || !label || !pendingBadge || !last || !icon) {
            return;
        }

        const pendingCount = pendingForCurrentFile().length;

        indicator.className =
            "sync-indicator is-" + syncDisplayState;

        const stateConfig = {
            starting: {
                label: "Starting…",
                icon: "fa-solid fa-circle-notch fa-spin"
            },
            syncing: {
                label: "Syncing",
                icon: "fa-solid fa-arrows-rotate fa-spin"
            },
            synced: {
                label: "Online",
                icon: "fa-solid fa-cloud-arrow-up"
            },
            queued: {
                label: "Online",
                icon: "fa-solid fa-clock-rotate-left"
            },
            offline: {
                label: "Offline",
                icon: "fa-solid fa-wifi"
            },
            error: {
                label: "Sync issue",
                icon: "fa-solid fa-triangle-exclamation"
            }
        };

        const config =
            stateConfig[syncDisplayState] || stateConfig.starting;

        label.textContent = config.label;
        icon.className = config.icon;

        pendingBadge.textContent = pendingCount + " queued";
        pendingBadge.classList.toggle("d-none", pendingCount === 0);

        if (lastSuccessfulSyncAt) {
            last.textContent = "· " + formatSyncAge(lastSuccessfulSyncAt);
            last.classList.remove("d-none");
        } else {
            last.textContent = "";
            last.classList.add("d-none");
        }

        let title = config.label;

        if (pendingCount > 0) {
            title += " · " + pendingCount + " pending server update" +
                (pendingCount === 1 ? "" : "s");
        }

        if (lastSuccessfulSyncAt) {
            title += " · Last synced " +
                new Date(lastSuccessfulSyncAt).toLocaleTimeString();
        }

        indicator.title = title;
    }

    function setSyncDisplayState(state) {
        syncDisplayState = state;
        renderSyncIndicator();
    }

    function refreshSyncDisplayState() {
        if (!navigator.onLine) {
            setSyncDisplayState("offline");
            return;
        }

        if (syncDisplayState === "offline") {
            setSyncDisplayState(
                pendingForCurrentFile().length > 0
                    ? "queued"
                    : "synced"
            );
            return;
        }

        renderSyncIndicator();
    }

    function applyPayoutAccessUi() {
        if (!payoutOpen) {
            return;
        }

        /*
         * Authorized viewing should keep the farmer details column visible.
         * Only editing controls are disabled when the payout is read-only.
         */
        $("#farmerDetailsContainer").show();

        const readOnly = !payoutWritable;

        $("#verifyFarmer0, #verifyFarmer1, #unverifyFarmer, #cautionFarmer")
            .prop("disabled", readOnly);

        $(".captureModal")
            .toggleClass("disabled", readOnly)
            .css("pointer-events", readOnly ? "none" : "")
            .attr("aria-disabled", readOnly ? "true" : "false");

        /*
         * Keep the action areas visible. handleVerifyUI() can still decide
         * which verification action is appropriate for the current record,
         * but viewing mode must not hide the details panel itself.
         */
        $(".action-grid").removeClass("d-none").show();
    }

    function applyPayoutOpenState(
        isOpen,
        reason = '',
        isWritable = true
    ) {
        payoutOpen = Boolean(isOpen);
        payoutWritable = payoutOpen && Boolean(isWritable);

        if (accessStateResolved) {
            accessRestricted = !payoutOpen;
        }

        payoutLockReason = String(
            reason || (payoutOpen ? '' : payoutLockReason || 'manual')
        );

        window.PAFFP_PAYOUT_STATE = {
            open: payoutOpen,
            writable: payoutWritable,
            reason: payoutLockReason
        };

        window.dispatchEvent(
            new CustomEvent("paffp:payout-state-change", {
                detail: {
                    open: payoutOpen,
                    writable: payoutWritable,
                    reason: payoutLockReason
                }
            })
        );

        /*
         * First apply the coarse View/Edit permission, then let checkDateLock()
         * make the final decision. This prevents the access watcher from
         * re-enabling controls that the date rule intentionally locked, while
         * explicit edit overrides (approved request, temporary edit, whitelist,
         * accomplished edit) remain immediately editable.
         */
        if (payoutOpen) {
            applyPayoutAccessUi();
        }

        checkDateLock(pageDate);

        if (!payoutOpen || !payoutWritable) {
            $('.captureModal')
                .addClass('disabled')
                .css('pointer-events', 'none')
                .attr('aria-disabled', 'true');
        }
    }

    function loadSyncState() {
        syncState = { epoch: "", version: 0 };

        if (!SYNC_STATE_KEY) return;

        try {
            const stored = JSON.parse(
                localStorage.getItem(SYNC_STATE_KEY) || "{}"
            );

            if (stored && typeof stored === "object") {
                syncState.epoch = String(stored.epoch || "");
                syncState.version = Math.max(
                    0,
                    Number(stored.version || 0)
                );
            }
        } catch (error) {
            localStorage.removeItem(SYNC_STATE_KEY);
        }
    }

    function saveSyncState(epoch, version) {
        syncState = {
            epoch: String(epoch || ""),
            version: Math.max(0, Number(version || 0))
        };

        if (SYNC_STATE_KEY) {
            localStorage.setItem(
                SYNC_STATE_KEY,
                JSON.stringify(syncState)
            );
        }
    }

    function getSyncDelay() {
        if (syncFailureCount > 0) {
            return Math.min(
                SYNC_RETRY_MAX,
                SYNC_RETRY_MIN * Math.pow(2, syncFailureCount - 1)
            );
        }

        return document.hidden
            ? SYNC_HIDDEN_INTERVAL
            : SYNC_ACTIVE_INTERVAL;
    }

    function scheduleSync(delay = null) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !pageFile
        ) {
            return;
        }

        if (syncTimer) {
            clearTimeout(syncTimer);
        }

        syncTimer = setTimeout(async function() {
            await syncLocalData(pageFile);
            scheduleSync();
        }, delay === null ? getSyncDelay() : delay);
    }

    function pendingForCurrentFile() {
        return getPendingQueue().filter(item =>
            item && item.file === STORAGE_KEY
        );
    }

    function applyVerificationFields(record, fields) {
        if (!record || !fields) return;

        ["VerifyStatus", "VerifyEncoder", "VerifyTimeStamp"]
            .forEach(field => {
                if (!Object.prototype.hasOwnProperty.call(fields, field)
                    || fields[field] === null) {
                    delete record[field];
                } else {
                    record[field] = fields[field];
                }
            });
    }

    function applyPendingOverlay(data) {
        const pendingQueue = pendingForCurrentFile();

        if (!pendingQueue.length || !Array.isArray(data)) {
            return data;
        }

        const indexBySysGen = new Map();

        data.forEach((row, index) => {
            if (row && row.SysGen) {
                indexBySysGen.set(String(row.SysGen), index);
            }
        });

        pendingQueue.forEach(item => {
            const index = indexBySysGen.get(String(item.SysGen || ""));
            if (index === undefined) return;

            const fields = {};

            ["VerifyStatus", "VerifyEncoder", "VerifyTimeStamp"]
                .forEach(field => {
                    fields[field] = Object.prototype.hasOwnProperty.call(item, field)
                        ? item[field]
                        : null;
                });

            applyVerificationFields(data[index], fields);
        });

        return data;
    }

    function selectedSysGen() {
        if (!selectedRow || !tableLocal) return null;

        try {
            const data = tableLocal.row(selectedRow).data();
            return data && data.SysGen ? String(data.SysGen) : null;
        } catch (error) {
            return null;
        }
    }

    function restoreSelectedFarmer(sysGen) {
        if (!sysGen || !tableLocal) return;

        if (
            payoutLockReason === "temporary_global_view" ||
            payoutLockReason === "temporary_global_edit"
        ) {
            return;
        }

        selectedRow = null;

        tableLocal.rows().every(function() {
            const data = this.data();

            if (!data || String(data.SysGen) !== String(sysGen)) {
                return;
            }

            const node = $(this.node());
            node.addClass("selected");
            selectedRow = node;

            $("#farmerID").text(data.Payroll);
            $("#farmerSysgen").text(data.SysGen);
            $("#farmerLastName").text(data.LastName);
            $("#farmerFirstName").text(data.FirstName);
            $("#farmerMiddleName").text(data.MiddleName || "");
            $("#farmerExtName").text(data.ExtName || "");
            $("#farmerBDay").text(data.Bday || "Not Available");
            $("#farmerVerifier").text(data.Verifier);
            $("#farmerTeller").text(data.Teller);

            if (data.VerifyEncoder) {
                $("#farmerVerifierID").text(
                    (data.VerifyStatus === "Caution"
                        ? "Flagged by "
                        : "Verified by ") + data.VerifyEncoder
                );
            } else {
                $("#farmerVerifierID").text("");
            }

            handleVerifyUI(data);
            showCautionWarning(data);
        });
    }

    function refreshAnalyticsFromData(data) {
        verified = 0;
        corrected = 0;
        pending = 0;
        userVerified = 0;
        userCorrected = 0;
        encoderCounts = {};

        (Array.isArray(data) ? data : []).forEach(item => {
            switch (item.VerifyStatus) {
            case "Verified":
                verified++;
                if (item.VerifyEncoder === getUsername()) {
                    userVerified++;
                }
                break;

            case "Verified w/ Corrections":
                corrected++;
                if (item.VerifyEncoder === getUsername()) {
                    userCorrected++;
                }
                break;

            default:
                pending++;
            }

            if (
                item.VerifyStatus === "Verified" ||
                item.VerifyStatus === "Verified w/ Corrections"
            ) {
                const encoder = item.VerifyEncoder || "Unknown";

                if (!encoderCounts[encoder]) {
                    encoderCounts[encoder] = {
                        verified: 0,
                        corrected: 0
                    };
                }

                if (item.VerifyStatus === "Verified") {
                    encoderCounts[encoder].verified++;
                } else {
                    encoderCounts[encoder].corrected++;
                }
            }
        });

        const sorted = Object.entries(encoderCounts)
            .sort((a, b) =>
                (b[1].verified + b[1].corrected) -
                (a[1].verified + a[1].corrected)
            );

        updateVerifiedCard(userVerified, userCorrected);

        renderBarChart(
            sorted.map(item => item[0]),
            sorted.map(item => item[1].verified),
            sorted.map(item => item[1].corrected)
        );

        dOnut(verified, corrected, pending);
    }

    function applyFullSnapshot(data) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !STORAGE_KEY
        ) {
            return;
        }

        const keepSelected = selectedSysGen();
        currentData = applyPendingOverlay(
            Array.isArray(data) ? data : []
        );

        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(currentData)
        );

        if (!tableLocal) {
            buildDataTable(currentData);
        } else {
            tableLocal.clear();
            tableLocal.rows.add(currentData);
            tableLocal.draw(false);
            refreshAnalyticsFromData(currentData);
            restoreSelectedFarmer(keepSelected);
        }
    }

    function applyDeltaChanges(changes) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !STORAGE_KEY
        ) {
            return false;
        }

        if (!Array.isArray(changes) || changes.length === 0) {
            return true;
        }

        if (!Array.isArray(currentData) || currentData.length === 0) {
            try {
                const cached = JSON.parse(
                    localStorage.getItem(STORAGE_KEY) || "[]"
                );
                currentData = Array.isArray(cached) ? cached : [];
            } catch (error) {
                currentData = [];
            }
        }

        const keepSelected = selectedSysGen();
        const indexBySysGen = new Map();

        currentData.forEach((row, index) => {
            if (row && row.SysGen) {
                indexBySysGen.set(String(row.SysGen), index);
            }
        });

        const changedIndexes = new Set();

        for (const change of changes) {
            const sysGen = String(change.SysGen || "");
            const index = indexBySysGen.get(sysGen);

            if (index === undefined) {
                return false;
            }

            applyVerificationFields(
                currentData[index],
                change.fields || {}
            );

            changedIndexes.add(index);
        }

        applyPendingOverlay(currentData);

        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(currentData)
        );

        if (!tableLocal) {
            buildDataTable(currentData);
            return true;
        }

        changedIndexes.forEach(index => {
            tableLocal
                .row(index)
                .data(currentData[index])
                .invalidate();
        });

        tableLocal.draw(false);
        refreshAnalyticsFromData(currentData);
        restoreSelectedFarmer(keepSelected);

        return true;
    }

    async function syncLocalData(json, options = {}) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !json
        ) {
            return false;
        }

        if (!navigator.onLine) {
            setSyncDisplayState("offline");
            return false;
        }

        if (syncInProgress || !json) {
            return false;
        }

        syncInProgress = true;
        setSyncDisplayState("syncing");

        try {
            const params = new URLSearchParams({
                file: json,
                viewer:
                    typeof getUsername === "function"
                        ? getUsername()
                        : (localStorage.getItem("anonymousUsername") || ""),
                epoch: options.forceFull ? "" : syncState.epoch,
                since: options.forceFull ? "0" : String(syncState.version)
            });

            if (options.forceFull) {
                params.set("full", "1");
            }

            const response = await fetch(
                "sync-data.php?" + params.toString(),
                {
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok) {
                if (response.status === 423) {
                    const reason = String(
                        payload?.payout_lock_reason ||
                        "restricted"
                    );

                    enforceAuthorizationLock(reason);
                    return false;
                }

                throw new Error("Sync HTTP " + response.status);
            }

            if (!payload || payload.success !== true) {
                throw new Error(
                    payload && payload.message
                        ? payload.message
                        : "Invalid sync response"
                );
            }

            if (Object.prototype.hasOwnProperty.call(payload, "payout_open")) {
                applyPayoutOpenState(
                    Boolean(payload.payout_open),
                    String(payload.payout_lock_reason || ''),
                    payload.payout_writable !== false
                );

                if (!payoutOpen) {
                    return false;
                }
            }

            if (payload.mode === "full") {
                applyFullSnapshot(payload.data || []);
            } else if (payload.mode === "delta") {
                const applied = applyDeltaChanges(payload.changes || []);

                if (!applied) {
                    syncInProgress = false;
                    return await syncLocalData(json, { forceFull: true });
                }
            }

            saveSyncState(payload.epoch, payload.version);
            syncFailureCount = 0;
            lastSuccessfulSyncAt = Date.now();
            setSyncDisplayState(
                pendingForCurrentFile().length > 0
                    ? "queued"
                    : "synced"
            );
            return true;
        } catch (error) {
            syncFailureCount = Math.min(syncFailureCount + 1, 5);
            setSyncDisplayState(
                navigator.onLine ? "error" : "offline"
            );
            console.error("PAFFP delta sync:", error);
            checkErrorLog(json);
            return false;
        } finally {
            syncInProgress = false;
        }
    }

    async function postServerUpdate(payload) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !payoutWritable ||
            !STORAGE_KEY
        ) {
            const error = new Error(
                payoutOpen
                    ? "Viewing Only"
                    : "Access Restricted"
            );
            error.status = 423;
            throw error;
        }

        const response = await fetch("update-json.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(payload),
            cache: "no-store"
        });

        let result = null;

        try {
            result = await response.json();
        } catch (error) {
            result = null;
        }

        if (!response.ok || !result || result.success !== true) {
            const error = new Error(
                result && result.message
                    ? result.message
                    : "Server update failed"
            );
            error.status = response.status;
            error.payload = result;
            throw error;
        }

        return result;
    }

    async function updateJson(rowData) {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !payoutWritable ||
            !STORAGE_KEY
        ) {
            return;
        }

        const payload = {
            file: STORAGE_KEY,
            Actor: getUsername(),
            SysGen: rowData.SysGen,
            VerifyStatus: rowData.VerifyStatus,
            VerifyEncoder: rowData.VerifyEncoder,
            VerifyTimeStamp: rowData.VerifyTimeStamp
        };

        if (!navigator.onLine) {
            addPendingUpdate(payload);
            return;
        }

        try {
            await postServerUpdate(payload);
            scheduleSync(400);
        } catch (error) {
            if (Number(error.status || 0) === 423) {
                const reason = String(
                    error.payload?.payout_lock_reason ||
                    payoutLockReason ||
                    'manual'
                );

                const stillOpen =
                    error.payload?.payout_open === true;

                if (!stillOpen) {
                    enforceAuthorizationLock(reason);
                    return;
                }

                applyPayoutOpenState(
                    true,
                    reason,
                    error.payload?.payout_writable !== false
                );
                setSyncDisplayState('synced');

                // Reload the authoritative snapshot after a read-only rejection.
                await syncLocalData(pageFile, { forceFull: true });
                return;
            }

            addPendingUpdate(payload);
        }
    }

    function getPendingQueue() {
        try {
            const parsed = JSON.parse(
                localStorage.getItem(PENDING_QUEUE_KEY) || "[]"
            );

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            localStorage.removeItem(PENDING_QUEUE_KEY);
            return [];
        }
    }

    function savePendingQueue(queue) {
        localStorage.setItem(
            PENDING_QUEUE_KEY,
            JSON.stringify(Array.isArray(queue) ? queue : [])
        );
        updatePendingBadge();
    }

    function addPendingUpdate(data) {
        const queue = getPendingQueue();
        const index = queue.findIndex(item =>
            item &&
            item.file === data.file &&
            item.SysGen === data.SysGen
        );

        if (index >= 0) {
            queue[index] = data;
        } else {
            queue.push(data);
        }

        savePendingQueue(queue);
    }

    function updatePendingBadge() {
        if (!navigator.onLine) {
            syncDisplayState = "offline";
        } else if (pendingForCurrentFile().length > 0
            && syncDisplayState !== "syncing") {
            syncDisplayState = "queued";
        } else if (syncDisplayState === "queued") {
            syncDisplayState = "synced";
        }

        renderSyncIndicator();
    }

    async function processPendingQueue() {
        if (
            accessRestricted ||
            !accessStateResolved ||
            !payoutOpen ||
            !payoutWritable ||
            !STORAGE_KEY
        ) {
            return;
        }

        if (!navigator.onLine) {
            setSyncDisplayState("offline");
            return;
        }

        let queue = getPendingQueue();

        while (navigator.onLine) {
            const index = queue.findIndex(item =>
                item && item.file === STORAGE_KEY
            );

            if (index < 0) {
                break;
            }

            const item = queue[index];

            if (!item.Actor) {
                item.Actor = getUsername();
            }

            try {
                await postServerUpdate(item);
                queue.splice(index, 1);
                savePendingQueue(queue);
            } catch (error) {
                if (Number(error.status || 0) === 423) {
                    const reason = String(
                        error.payload?.payout_lock_reason ||
                        payoutLockReason ||
                        'manual'
                    );

                    const stillOpen =
                        error.payload?.payout_open === true;

                    if (!stillOpen) {
                        enforceAuthorizationLock(reason);
                        return;
                    }

                    applyPayoutOpenState(
                        true,
                        reason,
                        error.payload?.payout_writable !== false
                    );
                    setSyncDisplayState('queued');
                }

                break;
            }
        }

        if (pendingForCurrentFile().length === 0) {
            scheduleSync(300);
        }
    }

    function sendNtfyNotification(title, message, priority = "urgent") {
        fetch(NTFY_TOPIC, {
            method: "POST",
            headers: {
                "Title": title,
                "Priority": priority,
                "Tags": "warning"
            },
            body: message
        }).then(function(response) {
            if (!response.ok) {
                throw new Error("ntfy returned HTTP " + response.status);
            }
        }).catch(function(error) {
        });
    }

    async function checkErrorLog(json) {
        try {
            const response = await fetch(
                "checkErrorLog.php?file=" + encodeURIComponent(json) + "&" + Date.now()
            );
            const data = await response.json();
            if (data.trigger === true) {
                sendNtfyNotification(
                    "PAFFP 10 Website Alert",
                    $("#TableTitle").text().trim() +
                    " JSON File Invalid. \nFor Troubleshooting ASAP.\n\n-" +
                    $("#UsernameDisplay").text().trim(),
                    "high"
                );
            }
            return data.trigger;
        } catch (error) {
            console.error("checkErrorLog:", error);
            return false;
        }
    }
    function updateVerifiedCard(userVerified, userCorrected) {

        const totalVerified = userVerified + userCorrected;

        $("#totalVerifiedProfiles").text(totalVerified.toLocaleString());
        $("#verifiedCount").text(userVerified.toLocaleString());
        $("#correctedCount").text(userCorrected.toLocaleString());

        const verifiedPercent = totalVerified > 0
        ? (userVerified / totalVerified) * 100
        : 0;

        const correctedPercent = totalVerified > 0
        ? (userCorrected / totalVerified) * 100
        : 0;

        $("#verifiedBar")
        .css("width", verifiedPercent + "%")
        .attr("aria-valuenow", verifiedPercent)
        .attr("title", `${userVerified.toLocaleString()} (${verifiedPercent.toFixed(1)}%)`)
        .text("");

        $("#correctedBar")
        .css("width", correctedPercent + "%")
        .attr("aria-valuenow", correctedPercent)
        .attr("title", `${userCorrected.toLocaleString()} (${correctedPercent.toFixed(1)}%)`)
        .text("");
    }

    function checkDateLock(itemDate) {
        const sudo = params.get("user");
        const storedUsername = localStorage.getItem("anonymousUsername");

        if (!payoutOpen || !payoutWritable) {
            $("#verifyFarmer0, #verifyFarmer1, #unverifyFarmer, #cautionFarmer")
                .prop("disabled", true);

            $(".captureModal")
                .addClass("disabled")
                .css("pointer-events", "none")
                .attr("aria-disabled", "true");

            if (payoutOpen) {
                $("#farmerDetailsContainer").show();
                $(".action-grid").removeClass("d-none").show();
            }

            return payoutOpen;
        }

        const hasExplicitEditAccessOverride =
            payoutOpen &&
            payoutWritable &&
            (
                payoutLockReason === "accomplished_override_all" ||
                payoutLockReason === "accomplished_override_user" ||
                payoutLockReason === "temporary_global_edit" ||
                payoutLockReason === "approved_request_edit" ||
                payoutLockReason === "whitelist_edit"
            );

        if (
            hasExplicitEditAccessOverride ||
            sudo === "bypass" ||
            sudo === "HanDaeHo" ||
            storedUsername === "HanDaeHo"
        ) {
            $("#verifyFarmer0, #verifyFarmer1, #unverifyFarmer, #cautionFarmer")
            .prop("disabled", false);

            $(".captureModal")
            .removeClass("disabled")
            .css("pointer-events", "")
            .attr("aria-disabled", "false");

            return true;
        }

        const today = new Intl.DateTimeFormat("en-CA", {
            timeZone: "Asia/Manila",
            year: "numeric",
            month: "2-digit",
            day: "2-digit"
        }).format(new Date());

        const isDateValid = itemDate === today;

        $("#verifyFarmer0, #verifyFarmer1, #unverifyFarmer, #cautionFarmer")
        .prop("disabled", !isDateValid);

        $(".captureModal")
        .toggleClass("disabled", !isDateValid)
        .css("pointer-events", isDateValid ? "" : "none")
        .attr("aria-disabled", !isDateValid);

        return isDateValid;
    }
</script>

<script>
(function () {
    const ANNOUNCEMENT_ENDPOINT = "admin.php?api=announcements";
    const ACCESS_REQUEST_ENDPOINT = "access-request.php";
    const ANNOUNCEMENT_REFRESH_MS = 15000;

    const region = document.getElementById("paffpAnnouncementRegion");
    const stack = document.getElementById("paffpAnnouncementStack");

    if (!region || !stack) return;

    const pageParams = new URLSearchParams(window.location.search);
    const payoutUid = pageParams.get("id") || "";

    const iconMap = {
        info: "fa-solid fa-circle-info",
        success: "fa-solid fa-circle-check",
        warning: "fa-solid fa-triangle-exclamation",
        danger: "fa-solid fa-circle-exclamation"
    };

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function dismissKey(item) {
        return "paffpAnnouncementDismissed:" +
            item.id +
            ":" +
            String(item.updated_ts || 0);
    }

    function isDismissed(item) {
        if (!item.dismissible) return false;

        try {
            return localStorage.getItem(dismissKey(item)) === "1";
        } catch {
            return false;
        }
    }

    function dismissAnnouncement(item, element) {
        try {
            localStorage.setItem(dismissKey(item), "1");
        } catch {}

        element.remove();

        if (!stack.children.length) {
            region.classList.add("d-none");
            document.body.style.overflow = "";
        }
    }

    let serverAnnouncements = [];
    let lastAnnouncementRenderFingerprint = null;

    function getPayoutLockAnnouncement() {
        if (!window.PAFFP_ACCESS_STATE_RESOLVED) {
            return null;
        }

        const state = window.PAFFP_PAYOUT_STATE || {
            open: false,
            reason: "restricted"
        };

        if (state.open) {
            return null;
        }

        return {
            id: "__paffp_payout_lock__",
            title: "Access Restricted",
            message:
                "Access is restricted to authorized users only to prevent unauthorized access, disclosure, or dissemination of personal and confidential information, in compliance with applicable data privacy and information security requirements.\r\n\r\nNeed access? Use the Request Access button below or contact AJ Octat.",
            type: "danger",
            priority: "urgent",
            dismissible: false,
            system_lock: true,
            contact_name: "AJ Octat",
            contact_url: "https://www.facebook.com/Dreyannz",
            payout_uid:
                typeof id !== "undefined" && id
                    ? id
                    : "",
            username:
                typeof getUsername === "function"
                    ? getUsername()
                    : (localStorage.getItem("anonymousUsername") || "Unknown User"),
            updated_ts: 0
        };
    }

    function currentAccessRequestState() {
        const state = window.PAFFP_ACCESS_REQUEST_STATE || {};

        return {
            id: String(state.id || ""),
            status: String(state.status || "none"),
            permission: String(state.permission || ""),
            expires_at: Number(state.expires_at || 0)
        };
    }


    const accessRequestReasonModalElement =
        document.getElementById(
            "accessRequestReasonModal"
        );

    const accessRequestReasonModal =
        accessRequestReasonModalElement
            ? bootstrap.Modal.getOrCreateInstance(
                accessRequestReasonModalElement
            )
            : null;

    const accessRequestReasonForm =
        document.getElementById(
            "accessRequestReasonForm"
        );

    const accessRequestReasonInput =
        document.getElementById(
            "accessRequestReason"
        );

    const accessRequestReasonCount =
        document.getElementById(
            "accessRequestReasonCount"
        );

    let accessRequestTriggerButton = null;

    function updateAccessRequestReasonCount() {
        if (
            accessRequestReasonInput
            && accessRequestReasonCount
        ) {
            accessRequestReasonCount.textContent =
                String(
                    accessRequestReasonInput.value.length
                );
        }
    }

    function openAccessRequestReasonModal(button) {
        accessRequestTriggerButton = button || null;

        if (accessRequestReasonInput) {
            accessRequestReasonInput.value = "";
            updateAccessRequestReasonCount();
        }

        if (accessRequestReasonModal) {
            accessRequestReasonModal.show();

            window.setTimeout(function () {
                accessRequestReasonInput?.focus();
            }, 180);

            return;
        }

        submitAccessRequest(
            accessRequestTriggerButton,
            ""
        );
    }

    accessRequestReasonInput?.addEventListener(
        "input",
        updateAccessRequestReasonCount
    );

    accessRequestReasonForm?.addEventListener(
        "submit",
        function (event) {
            event.preventDefault();

            const reason =
                accessRequestReasonInput
                    ? accessRequestReasonInput.value.trim()
                    : "";

            const button =
                accessRequestTriggerButton;

            accessRequestReasonModal?.hide();

            submitAccessRequest(
                button,
                reason
            );
        }
    );

    accessRequestReasonModalElement?.addEventListener(
        "hidden.bs.modal",
        function () {
            accessRequestTriggerButton = null;
        }
    );

    function accessRequestButtonHtml(item) {
        if (!item.system_lock) {
            return "";
        }

        const state = currentAccessRequestState();

        if (state.status === "pending") {
            return `
                <button type="button"
                        class="paffp-access-request-btn"
                        disabled>
                    <i class="fa-solid fa-clock"></i>
                    <span>Request Pending</span>
                </button>`;
        }

        if (state.status === "approved") {
            return `
                <button type="button"
                        class="paffp-access-request-btn"
                        disabled>
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Access Approved</span>
                </button>`;
        }

        const label =
            ["rejected", "revoked", "expired"].includes(state.status)
                ? "Request Again"
                : "Request Access";

        return `
            <button type="button"
                    class="paffp-access-request-btn"
                    data-request-access="1">
                <i class="fa-solid fa-key"></i>
                <span>${label}</span>
            </button>`;
    }

    async function submitAccessRequest(button, reason = '') {
        if (!button || button.disabled) {
            return;
        }

        const viewer =
            typeof getUsername === "function"
                ? getUsername()
                : (localStorage.getItem("anonymousUsername") || "");

        if (!payoutUid || !viewer) {
            return;
        }

        const label = button.querySelector("span");
        const originalText = label
            ? label.textContent
            : "Request Access";

        button.disabled = true;

        if (label) {
            label.textContent = "Submitting...";
        }

        try {
            const body = new URLSearchParams({
                uid: payoutUid,
                viewer: viewer,
                reason: String(reason || "").slice(0, 500)
            });

            const response = await fetch(
                ACCESS_REQUEST_ENDPOINT,
                {
                    method: "POST",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json",
                        "Content-Type":
                            "application/x-www-form-urlencoded;charset=UTF-8"
                    },
                    body: body.toString()
                }
            );

            const data = await response.json();

            if (!response.ok || !data || data.success !== true) {
                throw new Error(
                    data && data.message
                        ? data.message
                        : "Unable to submit access request."
                );
            }

            const request = data.request || {};

            window.PAFFP_ACCESS_REQUEST_STATE = {
                id: String(request.id || ""),
                status: String(request.status || "pending"),
                permission: String(request.permission || ""),
                expires_at: Number(request.expires_at || 0)
            };

            if (data.created === true) {
                const payoutLabel =
                    String(
                        request.payout_title
                        || payoutUid
                        || "Restricted payout"
                    );

                const reasonText =
                    String(reason || "").trim();

                sendNtfyNotification(
                    "PAFFP Access Request",
                    viewer +
                    " requested access to " +
                    payoutLabel +
                    (
                        reasonText
                            ? "\nReason: " + reasonText
                            : ""
                    ),
                    "default"
                );
            }

            renderAnnouncements(serverAnnouncements);

            if (typeof refreshPayoutAccessState === "function") {
                refreshPayoutAccessState();
            }
        } catch (error) {
            console.error("Access request failed:", error);

            button.disabled = false;

            if (label) {
                label.textContent = "Request Failed";
            }

            if (typeof window.showPaffpNotice === "function") {
                window.showPaffpNotice({
                    tone: "danger",
                    title: "Request Failed",
                    message:
                        error && error.message
                            ? error.message
                            : "Unable to submit the access request."
                });
            }

            window.setTimeout(function () {
                if (label && document.body.contains(button)) {
                    label.textContent = originalText;
                }
            }, 1800);
        }
    }

    function announcementRenderFingerprint(items) {
        const requestState = currentAccessRequestState();

        const normalized = (Array.isArray(items) ? items : []).map(function (item) {
            return {
                id: String(item.id || ""),
                title: String(item.title || ""),
                message: String(item.message || ""),
                type: String(item.type || "info"),
                dismissible: Boolean(item.dismissible),
                system_lock: Boolean(item.system_lock),
                home_action: Boolean(item.home_action),
                contact_name: String(item.contact_name || ""),
                contact_url: String(item.contact_url || ""),
                payout_uid: String(item.payout_uid || ""),
                username: String(item.username || ""),
                request_state: item.system_lock
                    ? {
                        id: requestState.id,
                        status: requestState.status,
                        permission: requestState.permission,
                        expires_at: requestState.expires_at
                    }
                    : null
            };
        });

        /*
         * updated_ts is intentionally excluded. A timestamp-only Admin save
         * must not make an unchanged popup disappear/reappear or restart its
         * animation. Any actual displayed content/state change is included.
         */
        return JSON.stringify(normalized);
    }

    function renderAnnouncements(items, force = false) {
        const combined = [];
        const lockAnnouncement = getPayoutLockAnnouncement();

        if (lockAnnouncement) {
            combined.push(lockAnnouncement);
        }

        (Array.isArray(items) ? items : []).forEach(function (item) {
            combined.push(item);
        });

        const visible = combined.filter(function (item) {
            return !isDismissed(item);
        });

        const renderFingerprint =
            announcementRenderFingerprint(visible);

        if (
            !force &&
            renderFingerprint === lastAnnouncementRenderFingerprint
        ) {
            return false;
        }

        lastAnnouncementRenderFingerprint = renderFingerprint;

        if (!visible.length) {
            stack.innerHTML = "";
            region.classList.add("d-none");
            document.body.style.overflow = "";
            return true;
        }

        stack.innerHTML = "";

        visible.forEach(function (item) {
            const type = ["info", "success", "warning", "danger"].includes(item.type)
                ? item.type
                : "info";

            const announcement = document.createElement("article");
            announcement.className =
                "paffp-announcement " +
                type +
                (item.system_lock ? " system-lock-announcement" : "");
            announcement.dataset.announcementId = item.id || "";

            const closeButton = item.dismissible
                ? `
                    <button type="button"
                            class="paffp-announcement-close"
                            title="Dismiss announcement"
                            aria-label="Dismiss announcement">
                        <i class="fa-solid fa-xmark"></i>
                    </button>`
                : "";

            const homeButton =
                (!item.dismissible || item.home_action)
                    ? `
                        <button type="button"
                                class="paffp-announcement-home"
                                title="Return to PAFFP Home"
                                aria-label="Return to PAFFP Home">
                            <i class="fa-solid fa-house"></i>
                            <span>Home</span>
                        </button>`
                    : "";

            let messageHtml = escapeHtml(item.message);

            if (
                item.system_lock &&
                item.contact_name &&
                item.contact_url
            ) {
                const escapedContactName = escapeHtml(item.contact_name);
                const escapedContactUrl = escapeHtml(item.contact_url);

                messageHtml = messageHtml.replace(
                    escapedContactName,
                    `<a href="${escapedContactUrl}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="paffp-announcement-contact">${escapedContactName}</a>`
                );
            }

            const usernameHtml =
                item.system_lock && item.username
                    ? `
                        <div class="paffp-announcement-username"><em>${
                            item.payout_uid
                                ? escapeHtml(item.payout_uid) + " | "
                                : ""
                        }${escapeHtml(item.username)}</em></div>`
                    : "";

            const requestButton = accessRequestButtonHtml(item);

            const actionsHtml =
                requestButton || homeButton
                    ? `
                        <div class="paffp-announcement-actions">
                            ${requestButton}
                            ${homeButton}
                        </div>`
                    : "";

            announcement.innerHTML = `
                ${closeButton}

                <div class="paffp-announcement-icon">
                    <i class="${iconMap[type] || iconMap.info}"></i>
                </div>

                <div class="paffp-announcement-body">
                    <div class="paffp-announcement-title">${escapeHtml(item.title)}</div>
                    <div class="paffp-announcement-message">${messageHtml}</div>
                    ${actionsHtml}
                    ${usernameHtml}
                </div>
            `;

            const close = announcement.querySelector(".paffp-announcement-close");

            if (close) {
                close.addEventListener("click", function () {
                    dismissAnnouncement(item, announcement);
                });
            }

            const requestAccess = announcement.querySelector(
                "[data-request-access='1']"
            );

            if (requestAccess) {
                requestAccess.addEventListener("click", function () {
                    openAccessRequestReasonModal(
                        requestAccess
                    );
                });
            }

            const home = announcement.querySelector(
                ".paffp-announcement-home"
            );

            if (home) {
                home.addEventListener("click", function () {
                    window.location.href = "./";
                });
            }

            stack.appendChild(announcement);
        });

        region.classList.remove("d-none");
        document.body.style.overflow = "hidden";
        return true;
    }

    async function loadAnnouncements() {
        const payoutState = window.PAFFP_PAYOUT_STATE;

        /*
         * A restricted page displays only the built-in Access Restricted
         * notice. Do not fetch normal announcement data while locked.
         */
        if (
            !window.PAFFP_ACCESS_STATE_RESOLVED ||
            !payoutState ||
            payoutState.open !== true
        ) {
            serverAnnouncements = [];
            renderAnnouncements(serverAnnouncements);
            return;
        }

        try {
            const url =
                ANNOUNCEMENT_ENDPOINT +
                "&scope=" + encodeURIComponent(payoutUid) +
                "&user=" + encodeURIComponent(
                    typeof getUsername === "function"
                        ? getUsername()
                        : (localStorage.getItem("anonymousUsername") || "")
                ) +
                "&_=" + Date.now();

            const response = await fetch(url, {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            });

            if (!response.ok) {
                throw new Error("HTTP " + response.status);
            }

            const data = await response.json();

            serverAnnouncements =
                Array.isArray(data.announcements)
                    ? data.announcements
                    : [];

            /*
             * renderAnnouncements() compares display content first. The 15s
             * poll therefore stays live, but the popup DOM is not rebuilt
             * unless something the user can actually see/use has changed.
             */
            renderAnnouncements(serverAnnouncements);
        } catch (error) {
            console.error("Announcement refresh failed:", error);
        }
    }

    window.addEventListener(
        "paffp:access-request-state-change",
        function () {
            const payoutState = window.PAFFP_PAYOUT_STATE;

            if (
                payoutState &&
                payoutState.open !== true
            ) {
                renderAnnouncements(serverAnnouncements);
            }
        }
    );

    window.addEventListener(
        "paffp:payout-state-change",
        function (event) {
            const isOpen = Boolean(
                event &&
                event.detail &&
                event.detail.open
            );

            if (isOpen) {
                loadAnnouncements();
            } else {
                serverAnnouncements = [];
                renderAnnouncements(serverAnnouncements);
            }
        }
    );

    // Render the payout lock immediately even if the Admin announcement
    // request is still in progress.
    renderAnnouncements(serverAnnouncements);

    loadAnnouncements();
    window.setInterval(loadAnnouncements, ANNOUNCEMENT_REFRESH_MS);

    window.addEventListener("online", loadAnnouncements);
})();
</script>


<script>
(function () {
    "use strict";

    const modalElement =
        document.getElementById("paffpNoticeModal");

    if (!modalElement || typeof bootstrap === "undefined") {
        return;
    }

    const modal =
        bootstrap.Modal.getOrCreateInstance(modalElement);

    const content =
        modalElement.querySelector(".paffp-modal");

    const header =
        document.getElementById("paffpNoticeHeader");

    const icon =
        document.getElementById("paffpNoticeIcon");

    const title =
        document.getElementById("paffpNoticeTitle");

    const message =
        document.getElementById("paffpNoticeMessage");

    const closeButton =
        document.getElementById("paffpNoticeClose");

    const toneMap = {
        success: {
            icon: "fa-solid fa-circle-check",
            button: "btn btn-success"
        },
        warning: {
            icon: "fa-solid fa-triangle-exclamation",
            button: "btn btn-warning"
        },
        danger: {
            icon: "fa-solid fa-circle-exclamation",
            button: "btn btn-danger"
        },
        info: {
            icon: "fa-solid fa-circle-info",
            button: "btn btn-primary"
        }
    };

    window.showPaffpNotice = function (options = {}) {
        const tone =
            toneMap[options.tone]
                ? options.tone
                : "info";

        const config = toneMap[tone];

        if (content) {
            content.classList.remove(
                "paffp-tone-success",
                "paffp-tone-warning",
                "paffp-tone-danger",
                "paffp-tone-info"
            );
            content.classList.add(
                "paffp-tone-" + tone
            );
        }

        header.className = "modal-header";

        icon.className = config.icon;

        title.textContent =
            options.title || "Notice";

        message.textContent =
            options.message || "";

        closeButton.className =
            config.button;

        closeButton.textContent =
            options.buttonText || "Close";

        modal.show();
    };
})();
</script>

<script>
(function () {
    "use strict";

    const CHAT_ENDPOINT = "verify-chat.php";
    const CHAT_REFRESH_MS = 5000;
    const CHAT_LIMIT = 50;

    const chatCard =
        document.getElementById("paffpVerifierChat");
    const chatMessages =
        document.getElementById("verifyChatMessages");
    const chatForm =
        document.getElementById("verifyChatForm");
    const chatInput =
        document.getElementById("verifyChatInput");
    const chatSend =
        document.getElementById("verifyChatSend");
    const chatViewerLabel =
        document.getElementById("verifyChatViewer");
    const chatCount =
        document.getElementById("verifyChatCount");
    const chatStatus =
        document.getElementById("verifyChatStatus");
    const chatStatusText =
        document.getElementById("verifyChatStatusText");

    if (
        !chatCard
        || !chatMessages
        || !chatForm
        || !chatInput
        || !chatSend
    ) {
        return;
    }

    const chatParams =
        new URLSearchParams(window.location.search);
    const chatPayoutUid =
        chatParams.get("id") || "";

    let chatFingerprint = null;
    let chatLoading = false;
    let chatSending = false;
    let chatInitialized = false;

    function chatViewer() {
        return typeof getUsername === "function"
            ? String(getUsername() || "")
            : String(
                localStorage.getItem("anonymousUsername")
                || ""
            );
    }

    function escapeChatHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatChatTime(timestamp) {
        const seconds = Number(timestamp || 0);

        if (!seconds) {
            return "";
        }

        const date = new Date(seconds * 1000);
        const now = new Date();
        const sameDay =
            date.getFullYear() === now.getFullYear()
            && date.getMonth() === now.getMonth()
            && date.getDate() === now.getDate();

        return date.toLocaleString(
            [],
            sameDay
                ? {
                    hour: "numeric",
                    minute: "2-digit"
                }
                : {
                    month: "short",
                    day: "numeric",
                    hour: "numeric",
                    minute: "2-digit"
                }
        );
    }

    function setChatStatus(state, text) {
        chatStatus.classList.remove(
            "is-online",
            "is-loading"
        );

        if (state === "online") {
            chatStatus.classList.add("is-online");
        } else if (state === "loading") {
            chatStatus.classList.add("is-loading");
        }

        chatStatusText.textContent = text;
    }

    function setChatComposerEnabled(enabled) {
        chatInput.disabled = !enabled;
        chatSend.disabled = !enabled || chatSending;
    }

    function chatPayoutAccessible() {
        return Boolean(
            window.PAFFP_ACCESS_STATE_RESOLVED
            && window.PAFFP_PAYOUT_STATE
            && window.PAFFP_PAYOUT_STATE.open === true
        );
    }

    function renderChatUnavailable(message) {
        chatFingerprint = null;
        chatMessages.innerHTML = `
            <div class="verify-chat-empty">
                ${escapeChatHtml(message)}
            </div>
        `;
    }

    function renderChatMessages(items, forceScroll) {
        const viewer = chatViewer();
        const messages = Array.isArray(items) ? items : [];
        const fingerprint = messages
            .map(function (item) {
                return String(item.id || "")
                    + ":"
                    + String(item.created_at || 0);
            })
            .join("|");

        if (fingerprint === chatFingerprint) {
            return;
        }

        const nearBottom =
            chatMessages.scrollHeight
            - chatMessages.scrollTop
            - chatMessages.clientHeight
            < 70;

        chatFingerprint = fingerprint;

        if (!messages.length) {
            chatMessages.innerHTML = `
                <div class="verify-chat-empty">
                    No messages yet.<br>
                    Start the conversation with PAFFP verifiers.
                </div>
            `;
            return;
        }

        chatMessages.innerHTML = messages
            .map(function (item) {
                const username =
                    String(item.username || "Unknown");
                const own =
                    viewer !== ""
                    && username.toLowerCase()
                        === viewer.toLowerCase();

                const payoutTitle =
                    String(item.payout_title || "").trim();

                return `
                    <div class="verify-chat-message${own ? " is-own" : ""}">
                        <div class="verify-chat-meta">
                            <span class="verify-chat-user"
                                  title="${escapeChatHtml(username)}">
                                ${escapeChatHtml(own ? "You" : username)}
                            </span>

                            ${
                                payoutTitle !== ""
                                    ? `
                                        <span class="verify-chat-payout"
                                              title="${escapeChatHtml(payoutTitle)}">
                                            <i class="fa-solid fa-location-dot me-1"></i>
                                            <span class="verify-chat-payout-text">
                                                ${escapeChatHtml(payoutTitle)}
                                            </span>
                                        </span>
                                    `
                                    : ""
                            }

                            <span>·</span>
                            <span>${escapeChatHtml(formatChatTime(item.created_at))}</span>
                        </div>

                        <div class="verify-chat-bubble">${escapeChatHtml(item.message || "")}</div>
                    </div>
                `;
            })
            .join("");

        if (forceScroll || nearBottom || !chatInitialized) {
            chatMessages.scrollTop =
                chatMessages.scrollHeight;
        }

        chatInitialized = true;
    }

    async function loadVerifierChat(options) {
        options = options || {};

        if (chatLoading || document.hidden) {
            return;
        }

        const viewer = chatViewer();

        if (chatViewerLabel) {
            chatViewerLabel.textContent =
                viewer || "Unknown";
        }

        if (!navigator.onLine) {
            setChatStatus("offline", "Offline");
            setChatComposerEnabled(false);
            return;
        }

        if (!chatPayoutUid) {
            setChatStatus("offline", "Unavailable");
            setChatComposerEnabled(false);
            renderChatUnavailable(
                "Chat is unavailable because the payout UID is missing."
            );
            return;
        }

        if (!chatPayoutAccessible()) {
            setChatStatus("offline", "Restricted");
            setChatComposerEnabled(false);
            renderChatUnavailable(
                "Chat becomes available when you have access to this payout."
            );
            return;
        }

        chatLoading = true;

        if (!options.silent) {
            setChatStatus("loading", "Syncing");
        }

        try {
            const query = new URLSearchParams({
                uid: chatPayoutUid,
                viewer: viewer,
                limit: String(CHAT_LIMIT),
                ts: String(Date.now())
            });

            const response = await fetch(
                CHAT_ENDPOINT + "?" + query.toString(),
                {
                    method: "GET",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

            const data = await response.json();

            if (!response.ok || !data || data.success !== true) {
                throw new Error(
                    data && data.message
                        ? data.message
                        : "Unable to load verifier chat."
                );
            }

            renderChatMessages(
                data.messages,
                Boolean(options.forceScroll)
            );

            setChatComposerEnabled(true);
            setChatStatus("online", "Live");
        } catch (error) {
            console.error("Verifier chat refresh failed:", error);
            setChatStatus("offline", "Unavailable");
            setChatComposerEnabled(false);

            if (!chatInitialized) {
                renderChatUnavailable(
                    error && error.message
                        ? error.message
                        : "Unable to load verifier chat."
                );
            }
        } finally {
            chatLoading = false;
        }
    }

    async function sendVerifierChatMessage() {
        if (chatSending) {
            return;
        }

        const viewer = chatViewer();
        const message = chatInput.value.trim();

        if (!message) {
            chatInput.focus();
            return;
        }

        if (!chatPayoutAccessible()) {
            loadVerifierChat();
            return;
        }

        chatSending = true;
        chatSend.disabled = true;
        setChatStatus("loading", "Sending");

        try {
            const body = new URLSearchParams({
                uid: chatPayoutUid,
                viewer: viewer,
                message: message.slice(0, 500)
            });

            const response = await fetch(
                CHAT_ENDPOINT,
                {
                    method: "POST",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json",
                        "Content-Type":
                            "application/x-www-form-urlencoded;charset=UTF-8"
                    },
                    body: body.toString()
                }
            );

            const data = await response.json();

            if (!response.ok || !data || data.success !== true) {
                throw new Error(
                    data && data.message
                        ? data.message
                        : "Unable to send chat message."
                );
            }

            chatInput.value = "";
            chatCount.textContent = "0";
            chatFingerprint = null;

            await loadVerifierChat({
                forceScroll: true
            });
        } catch (error) {
            console.error("Verifier chat send failed:", error);

            setChatStatus("offline", "Send failed");

            if (typeof window.showPaffpNotice === "function") {
                window.showPaffpNotice({
                    tone: "warning",
                    title: "Message Not Sent",
                    message:
                        error && error.message
                            ? error.message
                            : "Unable to send your chat message."
                });
            }
        } finally {
            chatSending = false;
            setChatComposerEnabled(
                chatPayoutAccessible()
                && navigator.onLine
            );
        }
    }

    chatInput.addEventListener(
        "input",
        function () {
            chatCount.textContent =
                String(chatInput.value.length);
        }
    );

    chatInput.addEventListener(
        "keydown",
        function (event) {
            if (
                event.key === "Enter"
                && !event.shiftKey
            ) {
                event.preventDefault();
                chatForm.requestSubmit();
            }
        }
    );

    chatForm.addEventListener(
        "submit",
        function (event) {
            event.preventDefault();
            sendVerifierChatMessage();
        }
    );

    window.addEventListener(
        "paffp:payout-state-change",
        function (event) {
            const isOpen = Boolean(
                event
                && event.detail
                && event.detail.open
            );

            if (isOpen) {
                chatFingerprint = null;
                loadVerifierChat({ forceScroll: true });
            } else {
                setChatComposerEnabled(false);
                setChatStatus("offline", "Restricted");
                renderChatUnavailable(
                    "Chat becomes available when you have access to this payout."
                );
            }
        }
    );

    window.addEventListener(
        "online",
        function () {
            loadVerifierChat({ forceScroll: true });
        }
    );

    window.addEventListener(
        "offline",
        function () {
            setChatStatus("offline", "Offline");
            setChatComposerEnabled(false);
        }
    );

    document.addEventListener(
        "visibilitychange",
        function () {
            if (!document.hidden) {
                loadVerifierChat({ silent: true });
            }
        }
    );

    chatViewerLabel.textContent =
        chatViewer() || "Unknown";
    chatCount.textContent =
        String(chatInput.value.length);
    setChatComposerEnabled(false);

    window.setTimeout(
        function () {
            loadVerifierChat({ forceScroll: true });
        },
        800
    );

    window.setInterval(
        function () {
            loadVerifierChat({ silent: true });
        },
        CHAT_REFRESH_MS
    );
})();
</script>

<script src="js/camera.js"></script>\n</html>