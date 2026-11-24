<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$adSettingsPath = __DIR__ . "/data/_adSettings.json";
$adsEnabled = true;
$adSlots = [
    'banner_after_ongoing' => true,
    'banner_after_upcoming' => true,
    'native_accomplished' => true,
    'popunder' => true
];

if (is_file($adSettingsPath) && is_readable($adSettingsPath)) {
    $raw = @file_get_contents($adSettingsPath);
    $settings = json_decode((string) $raw, true);

    if (is_array($settings)) {
        if (array_key_exists('index', $settings)) {
            $adsEnabled = (bool) $settings['index'];
        }

        if (
            isset($settings['slots']['index'])
            && is_array($settings['slots']['index'])
        ) {
            foreach ($adSlots as $slot => $defaultValue) {
                if (array_key_exists($slot, $settings['slots']['index'])) {
                    $adSlots[$slot] =
                        (bool) $settings['slots']['index'][$slot];
                }
            }
        }
    }
}

$adIndexBannerAfterOngoing =
    $adsEnabled && $adSlots['banner_after_ongoing'];
$adIndexBannerAfterUpcoming =
    $adsEnabled && $adSlots['banner_after_upcoming'];
$adIndexNativeAccomplished =
    $adsEnabled && $adSlots['native_accomplished'];
$adIndexPopunder =
    $adsEnabled && $adSlots['popunder'];
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">

    <script>
        (function () {
            try {
                const darkMode =
                    localStorage.getItem("darkMode") === "true";

                document.documentElement.setAttribute(
                    "data-bs-theme",
                    darkMode ? "dark" : "light"
                );
            } catch (error) {
                document.documentElement.setAttribute(
                    "data-bs-theme",
                    "light"
                );
            }
        })();
    </script>

    <title>DA RFO X | PAFFP</title>
    <meta name="robots"
          content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="author" content="DA RFO X | PMED PS AJOctat">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link rel="icon" href="img/DA_transparent.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --da-green: #198754;
            --da-green-dark: #12663f;
            --da-green-soft: rgba(25,135,84,.09);
            --surface: #ffffff;
            --surface-muted: #f5f8f6;
            --surface-strong: #eef5f1;
            --border: #dfe7e2;
            --text-main: #1d2821;
            --text-soft: #69756e;
            --shadow:
                0 .45rem 1.5rem rgba(28, 57, 41, .065);
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 10px;
        }

        [data-bs-theme="dark"] {
            --surface: #171d19;
            --surface-muted: #101512;
            --surface-strong: #1d2922;
            --border: #2c3931;
            --text-main: #eef3ef;
            --text-soft: #a5b0a9;
            --da-green-soft: rgba(25,135,84,.16);
            --shadow:
                0 .45rem 1.5rem rgba(0,0,0,.22);
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
            background:
                radial-gradient(
                    circle at top right,
                    rgba(25,135,84,.10),
                    transparent 32rem
                ),
                var(--surface-muted);
            color: var(--text-main);
            font-family:
                "Montserrat",
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .min-w-0 {
            min-width: 0;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            border-bottom: 1px solid var(--border);
            background:
                color-mix(
                    in srgb,
                    var(--surface) 92%,
                    transparent
                );
            backdrop-filter: blur(14px);
        }

        .topbar-inner {
            min-height: 62px;
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand-kicker {
            color: var(--da-green);
            font-size: .92rem;
            font-weight: 800;
            letter-spacing: .015em;
        }

        .brand-subtitle {
            margin-top: .08rem;
            color: var(--text-soft);
            font-size: .73rem;
            line-height: 1.3;
        }

        .topbar .btn {
            display: inline-grid;
            place-items: center;
            min-width: 40px;
            height: 40px;
            padding: 0 .7rem;
            border-radius: 11px;
        }

        .page-shell {
            max-width: 1480px;
            margin: 0 auto;
            padding: 1rem 1rem 3rem;
        }

        .surface-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: 220px;
            padding: 1.3rem;
        }

        .hero::after {
            content: "";
            position: absolute;
            top: -100px;
            right: -90px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background:
                radial-gradient(
                    circle,
                    rgba(25,135,84,.14),
                    rgba(25,135,84,0)
                );
            pointer-events: none;
        }

        .hero-layout {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns:
                minmax(0, 1.45fr)
                minmax(280px, .55fr);
            gap: 1rem;
            align-items: stretch;
        }

        .hero-main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 188px;
            padding: 1.1rem;
        }

        .hero-brand-panel {
            justify-content: center;
        }

        .hero-brand-lockup {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .hero-brand-logo {
            width: 86px;
            height: 86px;
            flex: 0 0 86px;
            object-fit: contain;
        }

        .hero-brand-title {
            color: var(--da-green);
            font-size: clamp(1.55rem, 2.8vw, 2.35rem);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -.03em;
        }

        .hero-brand-subtitle {
            margin-top: .35rem;
            color: var(--text-soft);
            font-size: clamp(.84rem, 1.4vw, 1rem);
            line-height: 1.45;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            width: fit-content;
            margin-bottom: .65rem;
            padding: .34rem .58rem;
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .hero-title {
            max-width: 760px;
            margin: 0;
            font-size:
                clamp(1.65rem, 3vw, 2.5rem);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -.035em;
        }

        .hero-copy {
            max-width: 730px;
            margin-top: .7rem;
            color: var(--text-soft);
            font-size: .88rem;
            line-height: 1.55;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .55rem;
            margin-top: 1rem;
        }

        .hero-actions .btn {
            min-height: 42px;
            border-radius: 11px;
            font-size: .8rem;
            font-weight: 600;
        }

        #heroMonitorButton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            text-align: center;
            line-height: 1;
        }

        #heroMonitorButton i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            line-height: 1;
        }

        .hero-side {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: .8rem;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface-muted);
        }

        .today-label {
            color: var(--text-soft);
            font-size: .67rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .today-date {
            margin-top: .25rem;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .hero-live-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .75rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
        }

        .hero-live-copy {
            min-width: 0;
        }

        .hero-live-label {
            color: var(--text-soft);
            font-size: .68rem;
        }

        .hero-live-value {
            margin-top: .16rem;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            flex: 0 0 10px;
            border-radius: 50%;
            background: var(--da-green);
            box-shadow:
                0 0 0 0 rgba(25,135,84,.45);
            animation:
                pulseStatus 1.9s infinite;
        }

        @keyframes pulseStatus {
            0% {
                box-shadow:
                    0 0 0 0 rgba(25,135,84,.35);
            }
            70% {
                box-shadow:
                    0 0 0 8px rgba(25,135,84,0);
            }
            100% {
                box-shadow:
                    0 0 0 0 rgba(25,135,84,0);
            }
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin-top: .85rem;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-height: 92px;
            padding: .9rem;
        }

        .summary-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px;
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .summary-value {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
        }

        .summary-label {
            margin-top: .27rem;
            color: var(--text-soft);
            font-size: .7rem;
            font-weight: 650;
        }

        .command-bar {
            position: relative;
            z-index: 1;
            margin-top: 1rem;
            padding: .75rem;
        }

        .command-layout {
            display: grid;
            grid-template-columns:
                minmax(260px, 1fr)
                auto;
            gap: .75rem;
            align-items: center;
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap i {
            position: absolute;
            top: 50%;
            left: .9rem;
            z-index: 2;
            color: var(--text-soft);
            transform: translateY(-50%);
        }

        .search-wrap input {
            min-height: 44px;
            padding-left: 2.5rem;
            border-color: var(--border);
            border-radius: 11px;
            background: var(--surface);
            color: var(--text-main);
            font-size: .82rem;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: .35rem;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .filter-group::-webkit-scrollbar {
            display: none;
        }

        .filter-chip {
            flex: 0 0 auto;
            min-height: 38px;
            padding: .42rem .72rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface);
            color: var(--text-soft);
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .filter-chip.active {
            border-color:
                rgba(25,135,84,.28);
            background: var(--da-green-soft);
            color: var(--da-green);
        }

        .result-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-top: .65rem;
            color: var(--text-soft);
            font-size: .7rem;
        }

        .section-block {
            margin-top: 1.1rem;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: .7rem;
        }

        .section-heading-copy {
            min-width: 0;
        }

        .section-kicker {
            color: var(--text-soft);
            font-size: .63rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: .15rem 0 0;
            font-size: 1rem;
            font-weight: 800;
        }

        .section-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 24px;
            padding: 0 .46rem;
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .68rem;
            font-weight: 800;
        }

        .section-heading .btn {
            min-height: 36px;
            border-radius: 10px;
            font-size: .72rem;
            font-weight: 600;
        }

        .live-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .live-card {
            position: relative;
            overflow: hidden;
            min-height: 210px;
            padding: 1rem;
            border-left:
                4px solid var(--da-green);
        }

        .live-card::after {
            content: "";
            position: absolute;
            right: -35px;
            bottom: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--da-green-soft);
            pointer-events: none;
        }

        .live-card-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .7rem;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .55rem;
            border-radius: 999px;
            background: var(--da-green-soft);
            color: var(--da-green);
            font-size: .67rem;
            font-weight: 800;
        }

        .live-card-title {
            max-width: 90%;
            margin: .7rem 0 0;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .live-card-date {
            margin-top: .32rem;
            color: var(--text-soft);
            font-size: .72rem;
        }

        .live-card-bottom {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-top: .85rem;
        }

        .live-beneficiaries-label {
            color: var(--text-soft);
            font-size: .66rem;
        }

        .live-beneficiaries {
            margin-top: .12rem;
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .live-card .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 40px;
            border-radius: 10px;
            font-size: .75rem;
            font-weight: 650;
            line-height: 1;
        }

        .live-card .btn i {
            display: block;
            margin: 0 !important;
            line-height: 1;
        }

        .timeline-card {
            padding: .85rem;
        }

        .timeline-list {
            display: grid;
            gap: .5rem;
        }

        .timeline-item {
            display: grid;
            grid-template-columns:
                92px
                minmax(0, 1fr)
                auto;
            gap: .75rem;
            align-items: center;
            padding: .7rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-muted);
        }

        .timeline-date {
            min-width: 0;
        }

        .timeline-date-day {
            color: var(--da-green);
            font-size: .88rem;
            font-weight: 800;
        }

        .timeline-date-year {
            margin-top: .1rem;
            color: var(--text-soft);
            font-size: .64rem;
        }

        .timeline-main {
            min-width: 0;
        }

        .timeline-title {
            overflow: hidden;
            color: var(--text-main);
            font-size: .8rem;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .timeline-meta {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .67rem;
        }

        .timeline-item .btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            min-height: 38px;
            padding: 0 !important;
            border-radius: 10px;
            line-height: 1;
        }

        .timeline-item .btn i {
            display: block;
            margin: 0 !important;
            line-height: 1;
        }

        .archive-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: .65rem;
        }

        .archive-card {
            display: flex;
            flex-direction: column;
            min-height: 126px;
            padding: .7rem;
            border-left:
                3px solid #6c757d;
        }

        .archive-card-title {
            overflow: hidden;
            font-size: .8rem;
            font-weight: 750;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .archive-date {
            margin-top: .28rem;
            color: var(--text-soft);
            font-size: .68rem;
        }

        .archive-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            margin-top: .55rem;
            padding-top: .45rem;
            border-top: 1px solid var(--border);
        }

        .archive-count-label {
            color: var(--text-soft);
            font-size: .62rem;
        }

        .archive-count {
            margin-top: .08rem;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .archive-card .btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            min-width: 34px;
            min-height: 34px;
            padding: 0 !important;
            border-radius: 9px;
            line-height: 1;
        }

        .archive-card .btn i {
            display: block;
            margin: 0 !important;
            line-height: 1;
        }

        .empty-state {
            padding: 1.2rem;
            border: 1px dashed var(--border);
            border-radius: 14px;
            background: var(--surface-muted);
            color: var(--text-soft);
            text-align: center;
            font-size: .76rem;
        }

        .live-grid > .empty-state {
            grid-column: 1 / -1;
            width: 100%;
        }

        .adsterra-banner-wrap {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            width: 100%;
            margin: 1rem 0;
            overflow: hidden;
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

        .adsterra-native-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
            overflow: hidden;
        }

        .adsterra-native-frame {
            display: block;
            width: 100%;
            height: 250px;
            border: 0;
            background: transparent;
        }

        /* Same loading screen used by Verify. */
        .mypopup {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2200 !important;
            display: flex;
            transform: none !important;
            padding: 0 !important;
            border: none;
            border-radius: 0 !important;
            outline: none;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            background: rgba(255, 255, 255, .96) !important;
        }

        [data-bs-theme="dark"] .mypopup {
            background: rgba(15, 18, 16, .96) !important;
        }

        #loading-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: auto !important;
            height: auto !important;
            padding: 55px;
            border-radius: 120px;
            background: var(--da-green) !important;
            box-shadow: 0 16px 40px rgba(25, 135, 84, .22);
        }

        .loader {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 120px;
            color: #fff;
            font-size: 24px;
            letter-spacing: 2px;
        }

        .loader::after {
            content: "";
            position: absolute;
            top: -40px;
            right: -40px;
            bottom: -40px;
            left: -40px;
            border-radius: 50%;
            border-top: 12px solid rgba(255, 255, 255, .2);
            border-right: 12px solid rgba(255, 255, 255, .2);
            border-bottom: 12px solid rgba(255, 255, 255, .2);
            border-left: 12px solid #fff;
            transform: translateZ(0);
            animation: load 1.2s infinite linear;
        }

        .loader img {
            width: 180px;
            height: 180px;
            object-fit: contain;
        }

        @keyframes load {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .floating-top {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 1010;
            display: none;
            place-items: center;
            width: 44px;
            height: 44px;
            padding: 0;
            border-radius: 10px;
        }

        .footer-note {
            color: var(--text-soft);
            font-size: .72rem;
        }

        @media (max-width: 1199.98px) {
            .archive-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }

            .hero-main {
                min-height: 0;
            }

            .summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .command-layout {
                grid-template-columns: 1fr;
            }

            .live-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .brand-subtitle {
                display: none;
            }

            .page-shell {
                padding-right: .75rem;
                padding-left: .75rem;
            }

            .hero {
                padding: .85rem;
            }

            .hero-main,
            .hero-side {
                padding: .85rem;
            }

            .hero-brand-logo {
                width: 68px;
                height: 68px;
                flex-basis: 68px;
            }

            .hero-brand-title {
                font-size: 1.4rem;
            }

            .hero-brand-subtitle {
                font-size: .78rem;
            }

            .summary-grid {
                gap: .55rem;
            }

            .summary-card {
                min-height: 84px;
                padding: .72rem;
            }

            .timeline-item {
                grid-template-columns:
                    74px
                    minmax(0, 1fr)
                    auto;
                gap: .55rem;
                padding: .6rem;
            }

            .archive-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            #loading-container {
                padding: 42px;
                border-radius: 100px;
            }

            .loader {
                width: 92px;
                height: 92px;
            }

            .loader::after {
                top: -30px;
                right: -30px;
                bottom: -30px;
                left: -30px;
                border-width: 9px;
            }

            .loader img {
                width: 138px !important;
                height: 138px !important;
            }

            .topbar-inner {
                min-height: 64px;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
            }

            .brand-kicker {
                font-size: .82rem;
            }

            .topbar .btn {
                min-width: 38px;
                height: 38px;
                padding: 0;
            }

            .topbar .btn-label {
                display: none;
            }

            .hero-kicker {
                font-size: .62rem;
            }

            .hero-copy {
                font-size: .8rem;
            }

            .hero-actions .btn {
                flex: 1 1 auto;
            }

            .summary-value {
                font-size: 1.12rem;
            }

            .summary-label {
                font-size: .64rem;
            }

            .summary-icon {
                width: 38px;
                height: 38px;
                flex-basis: 38px;
            }

            .filter-chip {
                font-size: .67rem;
            }

            .result-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: .18rem;
            }

            .live-card-bottom {
                align-items: stretch;
                flex-direction: column;
            }

            .live-card .btn {
                width: 100%;
            }

            .timeline-item {
                grid-template-columns:
                    66px
                    minmax(0, 1fr)
                    36px;
            }

            .timeline-meta {
                flex-wrap: wrap;
                gap: .35rem;
            }
        }
    </style>
</head>

<body>
    <div class="mypopup"
         id="loadingScreen"
         aria-live="polite"
         aria-busy="true">
        <div id="loading-container">
            <div class="loader"
                 role="status"
                 aria-label="Loading PAFFP payouts">
                <img class="rounded-circle"
                     src="img/Logo_DA.png"
                     width="180"
                     height="180"
                     alt="Department of Agriculture logo">
            </div>
        </div>
    </div>

    <header class="topbar">
        <div class="container-fluid px-3 px-xl-4">
            <div class="topbar-inner d-flex align-items-center justify-content-between gap-3">
                <div></div>

                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="refreshButton"
                            title="Refresh payout schedules"
                            aria-label="Refresh payout schedules">
                        <i class="fa-solid fa-rotate"></i>
                    </button>

                    <a href="admin.php"
                       class="btn btn-outline-secondary"
                       title="Open Admin"
                       aria-label="Open Admin">
                        <i class="fa-solid fa-shield-halved"></i>
                    </a>

                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="themeButton"
                            title="Switch theme"
                            aria-label="Switch theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <section class="surface-card hero">
            <div class="hero-layout">
                <div class="hero-main hero-brand-panel">
                    <div class="hero-brand-lockup">
                        <img class="hero-brand-logo"
                             src="img/DA_transparent.png"
                             alt="Department of Agriculture logo">

                        <div class="min-w-0">
                            <div class="hero-brand-title">
                                <span class="d-block">DEPARTMENT OF AGRICULTURE</span>
                                <span class="d-block">REGIONAL FIELD OFFICE 10</span>
                            </div>

                            <div class="hero-brand-subtitle">
                                Presidential Assistance to Farmers and Fisherfolks Program
                            </div>
                        </div>
                    </div>

                    <div class="hero-actions">
                        <a href="monitor.php"
                           class="btn btn-success d-none"
                           id="heroMonitorButton">
                            <i class="fa-solid fa-chart-line"></i>
                            Live Monitor
                        </a>

                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="focusSearchButton">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Find a Payout
                        </button>
                    </div>
                </div>

                <aside class="hero-side">
                    <div>
                        <div class="today-label">Today</div>
                        <div class="today-date"
                             id="todayLabel">—</div>
                    </div>

                    <div class="hero-live-row">
                        <div class="hero-live-copy">
                            <div class="hero-live-label">
                                Active payout schedules
                            </div>
                            <div class="hero-live-value"
                                 id="heroOngoingCount">0</div>
                        </div>

                        <div class="pulse-dot"
                             id="heroPulseDot"
                             aria-hidden="true"></div>
                    </div>

                    <div class="hero-live-row">
                        <div class="hero-live-copy">
                            <div class="hero-live-label">
                                Next scheduled payout
                            </div>
                            <div class="hero-live-value text-truncate"
                                 id="nextPayoutLabel">
                                Loading…
                            </div>
                        </div>

                        <i class="fa-regular fa-calendar text-success"></i>
                    </div>
                </aside>
            </div>
        </section>

        <section class="summary-grid"
                 aria-label="Payout summary">
            <article class="surface-card summary-card">
                <div class="summary-icon">
                    <i class="fa-solid fa-circle-play"></i>
                </div>
                <div>
                    <div class="summary-value"
                         id="ongoingStat">0</div>
                    <div class="summary-label">Ongoing Today</div>
                </div>
            </article>

            <article class="surface-card summary-card">
                <div class="summary-icon">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="summary-value"
                         id="upcomingStat">0</div>
                    <div class="summary-label">Upcoming</div>
                </div>
            </article>

            <article class="surface-card summary-card">
                <div class="summary-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="summary-value"
                         id="completedStat">0</div>
                    <div class="summary-label">Accomplished</div>
                </div>
            </article>

            <article class="surface-card summary-card">
                <div class="summary-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="summary-value"
                         id="beneficiaryStat">0</div>
                    <div class="summary-label">Beneficiaries</div>
                </div>
            </article>
        </section>

        <section class="surface-card command-bar">
            <div class="command-layout">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search"
                           class="form-control"
                           id="payoutSearch"
                           autocomplete="off"
                           placeholder="Search location or payout activity…"
                           aria-label="Search payout">
                </div>

                <div class="filter-group"
                     role="group"
                     aria-label="Payout status filter">
                    <button type="button"
                            class="filter-chip active"
                            data-filter="all">
                        All
                    </button>

                    <button type="button"
                            class="filter-chip"
                            data-filter="ongoing">
                        Today
                    </button>

                    <button type="button"
                            class="filter-chip"
                            data-filter="upcoming">
                        Upcoming
                    </button>

                    <button type="button"
                            class="filter-chip"
                            data-filter="completed">
                        Accomplished
                    </button>
                </div>
            </div>

            <div class="result-meta">
                <span id="resultSummary">
                    Loading payout schedules…
                </span>
                <span id="lastUpdated"></span>
            </div>
        </section>

        <div id="loadError"
             class="alert alert-danger d-none mt-3"
             role="alert">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <strong>Unable to load payout schedules.</strong>
                    <div class="small"
                         id="loadErrorMessage">
                        Please check the payout list and try again.
                    </div>
                </div>

                <button type="button"
                        class="btn btn-outline-danger btn-sm"
                        id="retryButton">
                    <i class="fa-solid fa-rotate me-1"></i>
                    Retry
                </button>
            </div>
        </div>

        <section class="section-block"
                 id="ongoingSection">
            <div class="section-heading">
                <div class="section-heading-copy">
                    <div class="section-kicker">
                        Live operations
                    </div>
                    <h2 class="section-title">
                        Ongoing Payouts
                        <span class="section-count"
                              id="ongoingCount">0</span>
                    </h2>
                </div>

                <a href="monitor.php"
                   class="btn btn-outline-success d-none"
                   id="liveMonitorButton">
                    <i class="fa-solid fa-chart-line"></i>
                    Live Monitor
                </a>
            </div>

            <div class="live-grid"
                 id="ongoingContainer"></div>
        </section>

        <?php if ($adIndexBannerAfterOngoing) { ?>
        <div class="adsterra-banner-wrap"
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

        <section class="section-block"
                 id="upcomingSection">
            <div class="section-heading">
                <div class="section-heading-copy">
                    <div class="section-kicker">
                        Forward schedule
                    </div>
                    <h2 class="section-title">
                        Upcoming Payouts
                        <span class="section-count"
                              id="upcomingCount">0</span>
                    </h2>
                </div>
            </div>

            <div class="surface-card timeline-card"
                 id="upcomingTimelineCard">
                <div class="timeline-list"
                     id="upcomingContainer"></div>
            </div>

            <div class="empty-state d-none"
                 id="upcomingEmptyState">
                No upcoming payout schedules.
            </div>
        </section>

        <?php if ($adIndexBannerAfterUpcoming) { ?>
        <div class="adsterra-banner-wrap"
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

        <section class="section-block"
                 id="accomplishedSection">
            <div class="section-heading">
                <div class="section-heading-copy">
                    <div class="section-kicker">
                        Activity archive
                    </div>
                    <h2 class="section-title">
                        Accomplished Payouts
                        <span class="section-count"
                              id="accomplishedCount">0</span>
                    </h2>
                </div>

                <button type="button"
                        class="btn btn-outline-secondary d-none"
                        id="loadMoreButton">
                    <i class="fa-solid fa-chevron-down"></i>
                    Show More
                </button>
            </div>

            <div class="archive-grid"
                 id="accomplishedContainer"></div>
        </section>

        <div class="d-none mt-3"
             id="noResults">
            <div class="empty-state">
                <i class="fa-solid fa-magnifying-glass fa-lg mb-2"></i>
                <div class="fw-semibold">
                    No matching payout found
                </div>
                <div class="mt-1">
                    Try another location, activity name, or status filter.
                </div>
            </div>
        </div>
    </main>

    <footer class="border-top">
        <div class="container-fluid px-3 py-4 text-center">
            <div class="footer-note">
                Managed by PMED Planning Section
            </div>
        </div>
    </footer>

    <button type="button"
            class="btn btn-success floating-top"
            id="backToTop"
            title="Back to top"
            aria-label="Back to top">
        <i class="fa-solid fa-chevron-up"></i>
    </button>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="appToast"
             class="toast"
             role="status"
             aria-live="polite"
             aria-atomic="true">
            <div class="toast-header">
                <i class="fa-solid fa-circle-info text-success me-2"></i>
                <strong class="me-auto">PAFFP Portal</strong>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="toast"
                        aria-label="Close"></button>
            </div>

            <div class="toast-body"
                 id="toastMessage"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (() => {
            "use strict";

            const INDEX_NATIVE_ADS_ENABLED =
                <?php echo $adIndexNativeAccomplished ? 'true' : 'false'; ?>;

            const ACCOMPLISHED_PAGE_SIZE = 9;

            let payouts = [];
            let activeFilter = "all";
            let accomplishedVisible =
                ACCOMPLISHED_PAGE_SIZE;
            let appToast;

            const els = {
                loadingScreen:
                    document.getElementById("loadingScreen"),
                payoutSearch:
                    document.getElementById("payoutSearch"),
                refreshButton:
                    document.getElementById("refreshButton"),
                retryButton:
                    document.getElementById("retryButton"),
                themeButton:
                    document.getElementById("themeButton"),
                backToTop:
                    document.getElementById("backToTop"),
                resultSummary:
                    document.getElementById("resultSummary"),
                lastUpdated:
                    document.getElementById("lastUpdated"),
                loadError:
                    document.getElementById("loadError"),
                loadErrorMessage:
                    document.getElementById("loadErrorMessage"),
                noResults:
                    document.getElementById("noResults"),
                loadMoreButton:
                    document.getElementById("loadMoreButton"),
                ongoingContainer:
                    document.getElementById("ongoingContainer"),
                upcomingContainer:
                    document.getElementById("upcomingContainer"),
                upcomingTimelineCard:
                    document.getElementById("upcomingTimelineCard"),
                upcomingEmptyState:
                    document.getElementById("upcomingEmptyState"),
                accomplishedContainer:
                    document.getElementById("accomplishedContainer"),
                ongoingSection:
                    document.getElementById("ongoingSection"),
                upcomingSection:
                    document.getElementById("upcomingSection"),
                accomplishedSection:
                    document.getElementById("accomplishedSection"),
                liveMonitorButton:
                    document.getElementById("liveMonitorButton"),
                heroMonitorButton:
                    document.getElementById("heroMonitorButton"),
                focusSearchButton:
                    document.getElementById("focusSearchButton"),
                heroOngoingCount:
                    document.getElementById("heroOngoingCount"),
                nextPayoutLabel:
                    document.getElementById("nextPayoutLabel"),
                todayLabel:
                    document.getElementById("todayLabel"),
                heroPulseDot:
                    document.getElementById("heroPulseDot")
            };

            document.addEventListener(
                "DOMContentLoaded",
                init
            );

            function init() {
                appToast =
                    bootstrap.Toast.getOrCreateInstance(
                        document.getElementById("appToast"),
                        { delay: 3000 }
                    );

                applySavedTheme();
                bindEvents();
                setTodayLabel();
                loadPayouts();
            }

            function bindEvents() {
                els.payoutSearch.addEventListener(
                    "input",
                    function () {
                        accomplishedVisible =
                            ACCOMPLISHED_PAGE_SIZE;
                        renderPayouts();
                    }
                );

                els.refreshButton.addEventListener(
                    "click",
                    function () {
                        loadPayouts(true);
                    }
                );

                els.retryButton.addEventListener(
                    "click",
                    function () {
                        loadPayouts(true);
                    }
                );

                els.themeButton.addEventListener(
                    "click",
                    toggleTheme
                );

                els.focusSearchButton.addEventListener(
                    "click",
                    function () {
                        els.payoutSearch.focus();

                        window.scrollTo({
                            top:
                                Math.max(
                                    0,
                                    els.payoutSearch
                                        .getBoundingClientRect()
                                        .top
                                    + window.scrollY
                                    - 120
                                ),
                            behavior: "smooth"
                        });
                    }
                );

                document
                    .querySelectorAll(".filter-chip")
                    .forEach(function (button) {
                        button.addEventListener(
                            "click",
                            function () {
                                activeFilter =
                                    button.getAttribute(
                                        "data-filter"
                                    ) || "all";

                                document
                                    .querySelectorAll(".filter-chip")
                                    .forEach(function (chip) {
                                        chip.classList.toggle(
                                            "active",
                                            chip === button
                                        );
                                    });

                                accomplishedVisible =
                                    ACCOMPLISHED_PAGE_SIZE;

                                renderPayouts();
                            }
                        );
                    });

                els.loadMoreButton.addEventListener(
                    "click",
                    function () {
                        accomplishedVisible +=
                            ACCOMPLISHED_PAGE_SIZE;

                        renderPayouts();
                    }
                );

                els.backToTop.addEventListener(
                    "click",
                    function () {
                        window.scrollTo({
                            top: 0,
                            behavior: "smooth"
                        });
                    }
                );

                window.addEventListener(
                    "scroll",
                    function () {
                        els.backToTop.style.display =
                            window.scrollY > 400
                                ? "grid"
                                : "none";
                    },
                    { passive: true }
                );

                window.addEventListener(
                    "resize",
                    fitAdsterraBanners
                );
            }

            async function loadPayouts(
                showFeedback = false
            ) {
                setRefreshing(true);
                els.loadError.classList.add("d-none");

                try {
                    const response =
                        await fetch(
                            "payout-list.php?ts="
                            + Date.now(),
                            { cache: "no-store" }
                        );

                    if (!response.ok) {
                        throw new Error(
                            "Server returned HTTP "
                            + response.status
                            + "."
                        );
                    }

                    const data =
                        await response.json();

                    if (!Array.isArray(data)) {
                        throw new Error(
                            "Payout list endpoint must return a JSON array."
                        );
                    }

                    payouts =
                        data
                            .filter(function (item) {
                                return (
                                    item
                                    && item.uid
                                    && item.date
                                    && item.title
                                );
                            })
                            .map(function (item) {
                                return {
                                    uid:
                                        String(item.uid),
                                    title:
                                        String(item.title),
                                    date:
                                        String(item.date),
                                    count:
                                        Number(item.count) || 0
                                };
                            });

                    accomplishedVisible =
                        ACCOMPLISHED_PAGE_SIZE;

                    updateSummaryStats();
                    updateHero();
                    renderPayouts();
                    updateLastUpdated();

                    if (showFeedback) {
                        showToast(
                            "Payout schedules refreshed."
                        );
                    }
                } catch (error) {
                    console.error(error);

                    showLoadError(
                        error.message
                        || "Unknown loading error."
                    );
                } finally {
                    setRefreshing(false);
                    hideInitialLoader();
                }
            }

            function renderPayouts() {
                const query =
                    els.payoutSearch
                        .value
                        .trim()
                        .toLowerCase();

                const today =
                    todayLocalISO();

                const searched =
                    payouts.filter(function (item) {
                        return item.title
                            .toLowerCase()
                            .includes(query);
                    });

                const ongoing =
                    searched
                        .filter(function (item) {
                            return item.date === today;
                        })
                        .sort(function (a, b) {
                            return a.title
                                .localeCompare(b.title);
                        });

                const upcoming =
                    searched
                        .filter(function (item) {
                            return item.date > today;
                        })
                        .sort(function (a, b) {
                            return (
                                a.date.localeCompare(b.date)
                                || a.title.localeCompare(b.title)
                            );
                        });

                const accomplished =
                    searched
                        .filter(function (item) {
                            return item.date < today;
                        })
                        .sort(function (a, b) {
                            return (
                                b.date.localeCompare(a.date)
                                || a.title.localeCompare(b.title)
                            );
                        });

                const showOngoing =
                    activeFilter === "all"
                    || activeFilter === "ongoing";

                const showUpcoming =
                    activeFilter === "all"
                    || activeFilter === "upcoming";

                const showCompleted =
                    activeFilter === "all"
                    || activeFilter === "completed";

                els.ongoingContainer.innerHTML =
                    liveCards(ongoing);

                els.upcomingContainer.innerHTML =
                    timelineRows(upcoming);

                els.upcomingTimelineCard.classList.toggle(
                    "d-none",
                    upcoming.length === 0
                );

                els.upcomingEmptyState.classList.toggle(
                    "d-none",
                    upcoming.length !== 0
                );

                els.accomplishedContainer.innerHTML =
                    archiveCards(
                        accomplished.slice(
                            0,
                            accomplishedVisible
                        )
                    );

                document.getElementById(
                    "ongoingCount"
                ).textContent =
                    ongoing.length.toLocaleString();

                document.getElementById(
                    "upcomingCount"
                ).textContent =
                    upcoming.length.toLocaleString();

                document.getElementById(
                    "accomplishedCount"
                ).textContent =
                    accomplished.length.toLocaleString();

                els.ongoingSection.classList.toggle(
                    "d-none",
                    !showOngoing
                );

                els.upcomingSection.classList.toggle(
                    "d-none",
                    !showUpcoming
                );

                els.accomplishedSection.classList.toggle(
                    "d-none",
                    !showCompleted
                );

                els.loadMoreButton.classList.toggle(
                    "d-none",
                    !showCompleted
                    || accomplished.length
                        <= accomplishedVisible
                );

                const filteredCount =
                    (
                        showOngoing
                            ? ongoing.length
                            : 0
                    )
                    + (
                        showUpcoming
                            ? upcoming.length
                            : 0
                    )
                    + (
                        showCompleted
                            ? accomplished.length
                            : 0
                    );

                els.noResults.classList.toggle(
                    "d-none",
                    filteredCount !== 0
                );

                if (query || activeFilter !== "all") {
                    els.resultSummary.textContent =
                        filteredCount.toLocaleString()
                        + " matching payout"
                        + (
                            filteredCount === 1
                                ? ""
                                : "s"
                        );
                } else {
                    els.resultSummary.textContent =
                        payouts.length.toLocaleString()
                        + " payout schedule"
                        + (
                            payouts.length === 1
                                ? ""
                                : "s"
                        );
                }

                requestAnimationFrame(
                    fitAdsterraBanners
                );
            }

            function liveCards(items) {
                if (!items.length) {
                    return `
                        <div class="empty-state">
                            No payout is scheduled for today.
                        </div>
                    `;
                }

                return items
                    .map(function (item) {
                        const params =
                            payoutParams(item);

                        return `
                            <article class="surface-card live-card">
                                <div class="live-card-top">
                                    <div class="min-w-0">
                                        <span class="live-badge">
                                            <span class="pulse-dot"></span>
                                            Live Today
                                        </span>

                                        <h3 class="live-card-title">
                                            ${escapeHtml(item.title)}
                                        </h3>

                                        <div class="live-card-date">
                                            <i class="fa-regular fa-calendar me-1"></i>
                                            ${escapeHtml(formatDate(item.date))}
                                        </div>
                                    </div>
                                </div>

                                <div class="live-card-bottom">
                                    <div>
                                        <div class="live-beneficiaries-label">
                                            Beneficiaries
                                        </div>
                                        <div class="live-beneficiaries">
                                            ${item.count.toLocaleString()}
                                        </div>
                                    </div>

                                    <a class="btn btn-success"
                                       href="verify.php?${params}">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <span>Open Payout</span>
                                    </a>
                                </div>
                            </article>
                        `;
                    })
                    .join("");
            }

            function timelineRows(items) {
                if (!items.length) {
                    return "";
                }

                return items
                    .map(function (item) {
                        const params =
                            payoutParams(item);

                        const parts =
                            formatDateParts(item.date);

                        return `
                            <article class="timeline-item">
                                <div class="timeline-date">
                                    <div class="timeline-date-day">
                                        ${escapeHtml(parts.primary)}
                                    </div>
                                    <div class="timeline-date-year">
                                        ${escapeHtml(parts.year)}
                                    </div>
                                </div>

                                <div class="timeline-main">
                                    <div class="timeline-title"
                                         title="${escapeHtml(item.title)}">
                                        ${escapeHtml(item.title)}
                                    </div>

                                    <div class="timeline-meta">
                                        <span>
                                            <i class="fa-solid fa-users me-1"></i>
                                            ${item.count.toLocaleString()}
                                            beneficiaries
                                        </span>

                                        <span>
                                            <i class="fa-regular fa-clock me-1"></i>
                                            ${daysUntil(item.date)}
                                        </span>
                                    </div>
                                </div>

                                <a class="btn btn-outline-success"
                                   href="verify.php?${params}"
                                   title="Open payout"
                                   aria-label="Open payout">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </article>
                        `;
                    })
                    .join("");
            }

            function archiveCards(items) {
                if (!items.length) {
                    return `
                        <div class="empty-state">
                            No accomplished payout schedules.
                        </div>
                    `;
                }

                let output = "";

                items.forEach(function (item, index) {
                    const params =
                        payoutParams(item);

                    output += `
                        <article class="surface-card archive-card">
                            <div class="archive-card-title"
                                 title="${escapeHtml(item.title)}">
                                ${escapeHtml(item.title)}
                            </div>

                            <div class="archive-date">
                                <i class="fa-regular fa-calendar me-1"></i>
                                ${escapeHtml(formatDate(item.date))}
                            </div>

                            <div class="archive-bottom">
                                <div>
                                    <div class="archive-count-label">
                                        Beneficiaries
                                    </div>
                                    <div class="archive-count">
                                        ${item.count.toLocaleString()}
                                    </div>
                                </div>

                                <a class="btn btn-outline-secondary"
                                   href="verify.php?${params}"
                                   title="Open payout"
                                   aria-label="Open payout">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>
                        </article>
                    `;

                    if (
                        INDEX_NATIVE_ADS_ENABLED
                        && (index + 1) % 9 === 0
                    ) {
                        output += `
                            <div class="adsterra-native-wrap"
                                 style="grid-column:1/-1;"
                                 aria-label="Advertisement">
                                <iframe class="adsterra-native-frame"
                                        src="ad-native.php"
                                        scrolling="no"
                                        loading="lazy"
                                        title="Advertisement"></iframe>
                            </div>
                        `;
                    }
                });

                return output;
            }

            function updateSummaryStats() {
                const today =
                    todayLocalISO();

                const ongoing =
                    payouts.filter(function (item) {
                        return item.date === today;
                    }).length;

                const upcoming =
                    payouts.filter(function (item) {
                        return item.date > today;
                    }).length;

                const completed =
                    payouts.filter(function (item) {
                        return item.date < today;
                    }).length;

                const beneficiaries =
                    payouts.reduce(
                        function (sum, item) {
                            return sum + item.count;
                        },
                        0
                    );

                document.getElementById(
                    "ongoingStat"
                ).textContent =
                    ongoing.toLocaleString();

                document.getElementById(
                    "upcomingStat"
                ).textContent =
                    upcoming.toLocaleString();

                document.getElementById(
                    "completedStat"
                ).textContent =
                    completed.toLocaleString();

                document.getElementById(
                    "beneficiaryStat"
                ).textContent =
                    beneficiaries.toLocaleString();
            }

            function updateHero() {
                const today =
                    todayLocalISO();

                const ongoing =
                    payouts.filter(function (item) {
                        return item.date === today;
                    });

                const upcoming =
                    payouts
                        .filter(function (item) {
                            return item.date > today;
                        })
                        .sort(function (a, b) {
                            return (
                                a.date.localeCompare(b.date)
                                || a.title.localeCompare(b.title)
                            );
                        });

                els.heroOngoingCount.textContent =
                    ongoing.length.toLocaleString();

                els.heroPulseDot.style.opacity =
                    ongoing.length > 0
                        ? "1"
                        : ".25";

                els.liveMonitorButton.classList.toggle(
                    "d-none",
                    ongoing.length === 0
                );

                els.heroMonitorButton.classList.toggle(
                    "d-none",
                    ongoing.length === 0
                );

                if (upcoming.length) {
                    els.nextPayoutLabel.textContent =
                        upcoming[0].title;

                    els.nextPayoutLabel.title =
                        upcoming[0].title;
                } else {
                    els.nextPayoutLabel.textContent =
                        "No upcoming schedule";
                }
            }

            function setTodayLabel() {
                const now =
                    new Date();

                els.todayLabel.textContent =
                    now.toLocaleDateString(
                        undefined,
                        {
                            weekday: "long",
                            month: "long",
                            day: "numeric",
                            year: "numeric"
                        }
                    );
            }

            function payoutParams(item) {
                return new URLSearchParams({
                    location: item.title,
                    id: item.uid
                }).toString();
            }

            function formatDateParts(dateString) {
                const date =
                    localDate(dateString);

                if (!date) {
                    return {
                        primary: dateString,
                        year: ""
                    };
                }

                return {
                    primary:
                        date.toLocaleDateString(
                            undefined,
                            {
                                month: "short",
                                day: "numeric"
                            }
                        ),
                    year:
                        String(date.getFullYear())
                };
            }

            function daysUntil(dateString) {
                const target =
                    localDate(dateString);

                if (!target) {
                    return "";
                }

                const now =
                    localDate(todayLocalISO());

                const diff =
                    Math.round(
                        (
                            target.getTime()
                            - now.getTime()
                        )
                        / 86400000
                    );

                if (diff === 0) {
                    return "Today";
                }

                if (diff === 1) {
                    return "Tomorrow";
                }

                return "In "
                    + diff.toLocaleString()
                    + " days";
            }

            function localDate(dateString) {
                const parts =
                    String(dateString)
                        .split("-")
                        .map(Number);

                if (
                    parts.length !== 3
                    || !parts[0]
                    || !parts[1]
                    || !parts[2]
                ) {
                    return null;
                }

                const date =
                    new Date(
                        parts[0],
                        parts[1] - 1,
                        parts[2]
                    );

                return Number.isNaN(
                    date.getTime()
                )
                    ? null
                    : date;
            }

            function formatDate(dateString) {
                const date =
                    localDate(dateString);

                if (!date) {
                    return dateString;
                }

                return date.toLocaleDateString(
                    undefined,
                    {
                        month: "short",
                        day: "numeric",
                        year: "numeric"
                    }
                );
            }

            function todayLocalISO() {
                const now =
                    new Date();

                const year =
                    now.getFullYear();

                const month =
                    String(
                        now.getMonth() + 1
                    ).padStart(2, "0");

                const day =
                    String(
                        now.getDate()
                    ).padStart(2, "0");

                return year
                    + "-"
                    + month
                    + "-"
                    + day;
            }

            function updateLastUpdated() {
                const now =
                    new Date();

                els.lastUpdated.textContent =
                    "Updated "
                    + now.toLocaleTimeString(
                        [],
                        {
                            hour: "numeric",
                            minute: "2-digit"
                        }
                    );
            }

            function setRefreshing(isRefreshing) {
                els.refreshButton.disabled =
                    isRefreshing;

                const icon =
                    els.refreshButton
                        .querySelector("i");

                if (icon) {
                    icon.classList.toggle(
                        "fa-spin",
                        isRefreshing
                    );
                }
            }

            function showLoadError(message) {
                els.loadErrorMessage.textContent =
                    message;

                els.loadError.classList.remove(
                    "d-none"
                );

                els.resultSummary.textContent =
                    "Payout schedules unavailable";
            }

            function hideInitialLoader() {
                els.loadingScreen.style.display = "none";

                els.loadingScreen.setAttribute(
                    "aria-busy",
                    "false"
                );
            }

            function showToast(message) {
                document.getElementById(
                    "toastMessage"
                ).textContent =
                    message;

                appToast.show();
            }

            function applySavedTheme() {
                const dark =
                    document.documentElement
                        .getAttribute(
                            "data-bs-theme"
                        ) === "dark";

                updateThemeIcon(dark);
            }

            function toggleTheme() {
                const current =
                    document.documentElement
                        .getAttribute(
                            "data-bs-theme"
                        );

                const dark =
                    current !== "dark";

                document.documentElement
                    .setAttribute(
                        "data-bs-theme",
                        dark
                            ? "dark"
                            : "light"
                    );

                try {
                    localStorage.setItem(
                        "darkMode",
                        dark
                            ? "true"
                            : "false"
                    );
                } catch (error) {
                    console.warn(error);
                }

                updateThemeIcon(dark);
            }

            function updateThemeIcon(dark) {
                const icon =
                    els.themeButton
                        .querySelector("i");

                icon.className =
                    dark
                        ? "fa-solid fa-sun"
                        : "fa-solid fa-moon";

                els.themeButton.title =
                    dark
                        ? "Switch to light mode"
                        : "Switch to dark mode";

                els.themeButton.setAttribute(
                    "aria-label",
                    els.themeButton.title
                );
            }

            function fitAdsterraBanners() {
                document
                    .querySelectorAll(
                        ".adsterra-banner-wrap"
                    )
                    .forEach(function (wrap) {
                        const frame =
                            wrap.querySelector(
                                ".adsterra-banner-frame"
                            );

                        if (!frame) {
                            return;
                        }

                        const available =
                            Math.max(
                                1,
                                wrap.clientWidth
                            );

                        const scale =
                            Math.min(
                                1,
                                available / 728
                            );

                        frame.style.transform =
                            "scale("
                            + scale
                            + ")";

                        wrap.style.height =
                            Math.ceil(
                                90 * scale
                            )
                            + "px";
                    });
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            window.addEventListener(
                "message",
                function (event) {
                    if (
                        !event.data
                        || event.data.type
                            !== "paffp-native-ad-height"
                    ) {
                        return;
                    }

                    document
                        .querySelectorAll(
                            ".adsterra-native-frame"
                        )
                        .forEach(function (frame) {
                            if (
                                frame.contentWindow
                                === event.source
                            ) {
                                const height =
                                    Math.max(
                                        1,
                                        Number(
                                            event.data.height
                                        ) || 250
                                    );

                                frame.style.height =
                                    height + "px";
                            }
                        });
                }
            );
        })();
    </script>

    <?php if ($adIndexPopunder) { ?>
    <!-- Existing Index popunder slot preserved -->
    <script src="https://pl31545981.profitableratecpmnetwork.com/5f/81/44/5f814490ca004ae485bb3c373e1f48ea.js"></script>
    <?php } ?>
</body>
</html>
