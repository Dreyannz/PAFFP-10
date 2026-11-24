<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function readHidden($prompt)
{
    fwrite(STDOUT, $prompt);
    $isTty =
        function_exists('stream_isatty')
            ? @stream_isatty(STDIN)
            : (
                function_exists('posix_isatty')
                    ? @posix_isatty(STDIN)
                    : false
            );

    $sttyAvailable =
        $isTty &&
        trim((string) @shell_exec('command -v stty 2>/dev/null')) !== '';

    if ($sttyAvailable) {
        @shell_exec('stty -echo');
    }

    $value = trim((string) fgets(STDIN));

    if ($sttyAvailable) {
        @shell_exec('stty echo');
    }

    fwrite(STDOUT, PHP_EOL);
    return $value;
}

$password = readHidden('New PAFFP Admin password: ');
$confirm = readHidden('Confirm password: ');

if ($password === '' || $password !== $confirm) {
    fwrite(STDERR, "Passwords are empty or do not match.\n");
    exit(1);
}

if (strlen($password) < 14) {
    fwrite(STDERR, "Use at least 14 characters (a long passphrase is recommended).\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if (!is_string($hash) || $hash === '') {
    fwrite(STDERR, "Unable to create password hash.\n");
    exit(1);
}

fwrite(STDOUT, "\nReplace the hash in admin.php with:\n\$adminPasswordHash = '" . $hash . "';\n");
