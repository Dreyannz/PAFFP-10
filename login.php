<?php
require_once __DIR__ . '/auth-lib.php';

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

paffp_auth_start_session();

$next = paffp_auth_safe_next($_GET['next'] ?? $_POST['next'] ?? 'index.php');

if (!paffp_auth_enabled()) {
    header('Location: ' . $next, true, 302);
    exit;
}

if (paffp_auth_is_authenticated()) {
    header('Location: ' . $next, true, 302);
    exit;
}

if (empty($_SESSION['paffp_user_csrf'])) {
    $_SESSION['paffp_user_csrf'] = bin2hex(random_bytes(24));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrfToken'] ?? '');

    if (
        $csrf === ''
        || !hash_equals(
            (string) $_SESSION['paffp_user_csrf'],
            $csrf
        )
    ) {
        $error = 'The login form expired. Please try again.';
    } else {
        $result = paffp_auth_login_user(
            $_POST['username'] ?? '',
            $_POST['password'] ?? ''
        );

        if (!empty($result['success'])) {
            header('Location: ' . $next, true, 302);
            exit;
        }

        $error = (string) (
            $result['message']
            ?? 'Unable to sign in.'
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PAFFP | Login</title>
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
    <link rel="icon" href="img/DA_transparent.png" type="image/png">
    <script>
        (function () {
            const dark = localStorage.getItem("darkMode") === "true";
            document.documentElement.setAttribute(
                "data-bs-theme",
                dark ? "dark" : "light"
            );
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --da-green: #198754;
            --page-bg: #f5f7f6;
            --surface: #ffffff;
            --text-main: #1f2923;
            --text-soft: #6d7771;
            --border: #dfe6e1;
        }
        [data-bs-theme="dark"] {
            --page-bg: #101512;
            --surface: #181f1b;
            --text-main: #eef3ef;
            --text-soft: #a8b2ab;
            --border: #2d3831;
        }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            display: grid;
            place-items: center;
            padding: 1rem;
            background:
                radial-gradient(circle at top left, rgba(25,135,84,.12), transparent 34%),
                var(--page-bg);
            color: var(--text-main);
            font-family: "Montserrat", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .auth-card {
            width: min(100%, 430px);
            padding: 1.35rem;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--surface);
            box-shadow: 0 18px 50px rgba(15,23,42,.09);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: .85rem;
            margin-bottom: 1.2rem;
        }
        .brand img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }
        .brand-title {
            color: var(--da-green);
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .brand-subtitle {
            margin-top: .18rem;
            color: var(--text-soft);
            font-size: .75rem;
            line-height: 1.35;
        }
        .auth-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
        }
        .auth-copy {
            margin-top: .25rem;
            color: var(--text-soft);
            font-size: .82rem;
        }
        .form-label {
            color: var(--text-main);
            font-size: .76rem;
            font-weight: 700;
        }
        .form-control {
            min-height: 44px;
            border-color: var(--border);
            border-radius: 11px;
            background: var(--surface);
            color: var(--text-main);
        }
        .form-control:focus {
            border-color: var(--da-green);
            box-shadow: 0 0 0 .2rem rgba(25,135,84,.12);
        }
        .btn {
            min-height: 44px;
            border-radius: 11px;
            font-weight: 500;
        }
        .auth-footer {
            margin-top: 1rem;
            color: var(--text-soft);
            font-size: .78rem;
            text-align: center;
        }
        .auth-footer a {
            color: var(--da-green);
            font-weight: 700;
            text-decoration: none;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-top: .9rem;
            color: var(--text-soft);
            font-size: .76rem;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="auth-card">
        <div class="brand">
            <img src="img/DA_transparent.png" alt="Department of Agriculture logo">
            <div>
                <div class="brand-title">DA RFO X | PAFFP</div>
                <div class="brand-subtitle">Verifier account access</div>
            </div>
        </div>

        <h1 class="auth-title">Login</h1>
        <div class="auth-copy">
            Sign in to continue to payout verification.
        </div>

        <?php if ($error !== '') { ?>
            <div class="alert alert-danger mt-3 mb-0" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php } ?>

        <form method="post" class="mt-3" autocomplete="on">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($_SESSION['paffp_user_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control"
                       type="text"
                       id="username"
                       name="username"
                       maxlength="40"
                       autocomplete="username"
                       required
                       autofocus
                       value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control"
                       type="password"
                       id="password"
                       name="password"
                       maxlength="200"
                       autocomplete="current-password"
                       required>
            </div>

            <button class="btn btn-success w-100" type="submit">
                <i class="fa-solid fa-right-to-bracket me-1"></i>
                Login
            </button>
        </form>

        <div class="auth-footer">
            No account yet?
            <a href="signup.php?next=<?= rawurlencode($next) ?>">Create account</a>
        </div>

        <a class="back-link" href="index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Back to payout list
        </a>
    </main>
</body>
</html>
