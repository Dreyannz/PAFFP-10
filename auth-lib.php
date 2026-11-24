<?php

require_once __DIR__ . '/sync-lib.php';

function paffp_auth_settings_path()
{
    return __DIR__ . '/data/_authSettings.json';
}

function paffp_auth_users_path()
{
    return __DIR__ . '/data/_users.json';
}

function paffp_auth_users_lock_path()
{
    return __DIR__ . '/data/_users.lock';
}

function paffp_auth_default_settings()
{
    return [
        'enabled' => false,
        'updated_ts' => 0
    ];
}

function paffp_auth_load_settings()
{
    $defaults = paffp_auth_default_settings();
    $path = paffp_auth_settings_path();

    if (!is_file($path) || !is_readable($path)) {
        return $defaults;
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return $defaults;
    }

    if (array_key_exists('enabled', $decoded)) {
        $defaults['enabled'] = !empty($decoded['enabled']);
    }

    $defaults['updated_ts'] = max(
        0,
        (int) ($decoded['updated_ts'] ?? 0)
    );

    return $defaults;
}

function paffp_auth_save_settings($settings)
{
    $payload = paffp_auth_default_settings();
    $payload['enabled'] = !empty($settings['enabled']);
    $payload['updated_ts'] = time();

    return paffp_sync_write_json_file(
        paffp_auth_settings_path(),
        $payload,
        true
    );
}

function paffp_auth_enabled()
{
    $settings = paffp_auth_load_settings();

    return !empty($settings['enabled']);
}

function paffp_auth_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure =
        !empty($_SERVER['HTTPS'])
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    session_start();
}

function paffp_auth_clean_username($value)
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim((string) $value);
}

function paffp_auth_username_key($value)
{
    $value = paffp_auth_clean_username($value);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function paffp_auth_validate_username($username)
{
    $username = paffp_auth_clean_username($username);
    $length = function_exists('mb_strlen')
        ? mb_strlen($username, 'UTF-8')
        : strlen($username);

    if ($length < 3 || $length > 40) {
        return 'Username must be 3 to 40 characters.';
    }

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.-]*$/', $username)) {
        return 'Username may contain letters, numbers, spaces, periods, underscores, and hyphens.';
    }

    return '';
}

function paffp_auth_validate_password($password)
{
    $password = (string) $password;
    $length = strlen($password);

    if ($length < 8) {
        return 'Password must be at least 8 characters.';
    }

    if ($length > 200) {
        return 'Password is too long.';
    }

    return '';
}

function paffp_auth_load_users()
{
    $path = paffp_auth_users_path();

    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return [];
    }

    $users = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $username = paffp_auth_clean_username(
            $item['username'] ?? ''
        );

        $hash = (string) ($item['password_hash'] ?? '');

        if ($username === '' || $hash === '') {
            continue;
        }

        $users[] = [
            'id' => (string) ($item['id'] ?? ''),
            'username' => $username,
            'username_key' => paffp_auth_username_key($username),
            'password_hash' => $hash,
            'status' => (string) ($item['status'] ?? 'active'),
            'created_at' => max(0, (int) ($item['created_at'] ?? 0)),
            'last_login_at' => max(0, (int) ($item['last_login_at'] ?? 0))
        ];
    }

    return $users;
}

function paffp_auth_open_users_lock()
{
    $path = paffp_auth_users_lock_path();
    $handle = @fopen($path, 'c+');

    if (!$handle) {
        return false;
    }

    @chmod($path, 0664);

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        return false;
    }

    return $handle;
}

function paffp_auth_close_users_lock($handle)
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function paffp_auth_save_users($users)
{
    return paffp_sync_write_json_file(
        paffp_auth_users_path(),
        array_values((array) $users),
        true
    );
}

function paffp_auth_register_user($username, $password)
{
    $username = paffp_auth_clean_username($username);

    $usernameError = paffp_auth_validate_username($username);
    if ($usernameError !== '') {
        return [
            'success' => false,
            'message' => $usernameError
        ];
    }

    $passwordError = paffp_auth_validate_password($password);
    if ($passwordError !== '') {
        return [
            'success' => false,
            'message' => $passwordError
        ];
    }

    /*
     * These names have special server-side PAFFP access privileges and
     * must never be claimable through public self-registration.
     */
    if (in_array(
        paffp_auth_username_key($username),
        ['handaeho', 'bypass'],
        true
    )) {
        return [
            'success' => false,
            'message' => 'That username is reserved and cannot be self-registered.'
        ];
    }

    $lock = paffp_auth_open_users_lock();

    if (!$lock) {
        return [
            'success' => false,
            'message' => 'Unable to initialize account storage.'
        ];
    }

    try {
        $users = paffp_auth_load_users();
        $key = paffp_auth_username_key($username);

        foreach ($users as $user) {
            if (
                paffp_auth_username_key($user['username'] ?? '')
                === $key
            ) {
                return [
                    'success' => false,
                    'message' => 'That username is already registered.'
                ];
            }
        }

        $id = '';

        try {
            $id = bin2hex(random_bytes(12));
        } catch (Exception $error) {
            $id = uniqid('usr_', true);
        }

        $users[] = [
            'id' => $id,
            'username' => $username,
            'username_key' => $key,
            'password_hash' => password_hash(
                (string) $password,
                PASSWORD_DEFAULT
            ),
            'status' => 'active',
            'created_at' => time(),
            'last_login_at' => 0
        ];

        if (!paffp_auth_save_users($users)) {
            return [
                'success' => false,
                'message' => 'Unable to save the new account.'
            ];
        }

        return [
            'success' => true,
            'username' => $username
        ];
    } finally {
        paffp_auth_close_users_lock($lock);
    }
}

function paffp_auth_login_user($username, $password)
{
    $username = paffp_auth_clean_username($username);
    $key = paffp_auth_username_key($username);

    if ($username === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'Enter your username and password.'
        ];
    }

    $users = paffp_auth_load_users();
    $matched = null;

    foreach ($users as $user) {
        if (
            paffp_auth_username_key($user['username'] ?? '')
            === $key
        ) {
            $matched = $user;
            break;
        }
    }

    if (
        !is_array($matched)
        || !password_verify(
            (string) $password,
            (string) ($matched['password_hash'] ?? '')
        )
    ) {
        return [
            'success' => false,
            'message' => 'Invalid username or password.'
        ];
    }

    if (
        strtolower((string) ($matched['status'] ?? 'active'))
        !== 'active'
    ) {
        return [
            'success' => false,
            'message' => 'This account is not active.'
        ];
    }

    paffp_auth_start_session();
    session_regenerate_id(true);

    $_SESSION['paffp_user_authenticated'] = true;
    $_SESSION['paffp_user_username'] =
        (string) $matched['username'];
    $_SESSION['paffp_user_id'] =
        (string) ($matched['id'] ?? '');
    $_SESSION['paffp_user_last_activity'] = time();

    $lock = paffp_auth_open_users_lock();

    if ($lock) {
        try {
            $latestUsers = paffp_auth_load_users();

            foreach ($latestUsers as &$user) {
                if (
                    paffp_auth_username_key($user['username'] ?? '')
                    === $key
                ) {
                    $user['last_login_at'] = time();
                    break;
                }
            }
            unset($user);

            paffp_auth_save_users($latestUsers);
        } finally {
            paffp_auth_close_users_lock($lock);
        }
    }

    return [
        'success' => true,
        'username' => (string) $matched['username']
    ];
}

function paffp_auth_logout_user()
{
    paffp_auth_start_session();

    unset(
        $_SESSION['paffp_user_authenticated'],
        $_SESSION['paffp_user_username'],
        $_SESSION['paffp_user_id'],
        $_SESSION['paffp_user_last_activity']
    );

    session_regenerate_id(true);
}

function paffp_auth_current_username()
{
    paffp_auth_start_session();

    if (empty($_SESSION['paffp_user_authenticated'])) {
        return '';
    }

    $lastActivity = max(
        0,
        (int) ($_SESSION['paffp_user_last_activity'] ?? 0)
    );

    if (
        $lastActivity > 0
        && (time() - $lastActivity) > (12 * 60 * 60)
    ) {
        paffp_auth_logout_user();
        return '';
    }

    $username = paffp_auth_clean_username(
        $_SESSION['paffp_user_username'] ?? ''
    );

    if ($username === '') {
        paffp_auth_logout_user();
        return '';
    }

    $_SESSION['paffp_user_last_activity'] = time();

    return $username;
}

function paffp_auth_is_authenticated()
{
    return paffp_auth_current_username() !== '';
}

function paffp_auth_effective_viewer($requestedViewer)
{
    if (!paffp_auth_enabled()) {
        return paffp_auth_clean_username($requestedViewer);
    }

    return paffp_auth_current_username();
}

function paffp_auth_safe_next($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return 'index.php';
    }

    $decoded = rawurldecode($value);

    if (
        strpos($decoded, "\r") !== false
        || strpos($decoded, "\n") !== false
        || strpos($decoded, '://') !== false
        || strpos($decoded, '//') === 0
    ) {
        return 'index.php';
    }

    if (
        $decoded === 'verify.php'
        || strpos($decoded, 'verify.php?') === 0
    ) {
        return $decoded;
    }

    return 'index.php';
}

function paffp_auth_verify_next_url()
{
    $query = $_GET;
    unset($query['logout']);

    $next = 'verify.php';

    if (!empty($query)) {
        $next .= '?' . http_build_query($query);
    }

    return $next;
}

function paffp_auth_login_url($next)
{
    return 'login.php?next=' . rawurlencode(
        paffp_auth_safe_next($next)
    );
}
