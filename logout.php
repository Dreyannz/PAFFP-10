<?php
require_once __DIR__ . '/auth-lib.php';

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$next = paffp_auth_safe_next($_GET['next'] ?? 'index.php');

paffp_auth_logout_user();

header('Location: ' . $next, true, 302);
exit;
