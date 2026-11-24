<?php
require_once __DIR__ . '/sync-lib.php';
require_once __DIR__ . '/auth-lib.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);

function paffp_chat_response($statusCode, $payload)
{
    http_response_code((int) $statusCode);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function paffp_chat_clean_uid($value)
{
    return preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string) $value
    );
}

function paffp_chat_clean_message($value)
{
    $value = str_replace(
        ["\r\n", "\r"],
        "\n",
        (string) $value
    );

    /* Keep normal text/new lines while removing control characters. */
    $value = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $value
    );

    $value = trim((string) $value);

    /* Avoid large blank runs without destroying intentional line breaks. */
    $value = preg_replace('/\n{4,}/', "\n\n\n", $value);

    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, 500, 'UTF-8');
    } elseif (strlen($value) > 500) {
        $value = substr($value, 0, 500);
    }

    return trim((string) $value);
}

function paffp_chat_load_messages($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode((string) $raw, true);

    if ($raw === false || !is_array($decoded)) {
        return [];
    }

    $messages = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $messages[] = [
            'id' => (string) ($item['id'] ?? ''),
            'username' => (string) ($item['username'] ?? ''),
            'message' => (string) ($item['message'] ?? ''),
            'payout_uid' => (string) ($item['payout_uid'] ?? ''),
            'payout_title' => (string) ($item['payout_title'] ?? ''),
            'created_at' => (int) ($item['created_at'] ?? 0)
        ];
    }

    return $messages;
}

function paffp_chat_message_id()
{
    try {
        return bin2hex(random_bytes(10));
    } catch (Exception $error) {
        return uniqid('chat_', true);
    }
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$source = $method === 'POST' ? $_POST : $_GET;

$uid = paffp_chat_clean_uid($source['uid'] ?? '');
$viewer = paffp_sync_access_request_clean_viewer(
    paffp_auth_effective_viewer(
        $source['viewer'] ?? ''
    )
);

if (paffp_auth_enabled() && $viewer === '') {
    paffp_chat_response(401, [
        'success' => false,
        'auth_required' => true,
        'message' => 'Login is required to use Verifier Chat.'
    ]);
}

if ($uid === '' || $viewer === '') {
    paffp_chat_response(400, [
        'success' => false,
        'message' => 'Payout UID and verifier username are required.'
    ]);
}

$payout = paffp_sync_find_payout_by_uid($uid);

if (!is_array($payout)) {
    paffp_chat_response(404, [
        'success' => false,
        'message' => 'Payout was not found.'
    ]);
}

$payoutFile = (string) ($payout['file'] ?? '');
$state = paffp_sync_payout_state($payoutFile, $viewer);

if (empty($state['open'])) {
    paffp_chat_response(423, [
        'success' => false,
        'restricted' => true,
        'message' => 'Global verifier chat is available only while you have access to the current payout.'
    ]);
}

$chatDir = __DIR__ . '/data/_chat';

if (!is_dir($chatDir)) {
    if (!@mkdir($chatDir, 0775, true) && !is_dir($chatDir)) {
        paffp_chat_response(500, [
            'success' => false,
            'message' => 'Unable to initialize the verifier chat folder.'
        ]);
    }

    @chmod($chatDir, 02775);
}

/*
 * One shared verifier room across every payout.
 *
 * The current payout UID is still validated above so only a verifier who has
 * access to the payout page they are using can read or post to the room.
 * Each posted message records the payout it came from for context.
 */
$chatFile = $chatDir . '/global.json';
$lockFile = $chatDir . '/global.lock';

if ($method === 'GET') {
    $limit = isset($_GET['limit'])
        ? (int) $_GET['limit']
        : 50;

    $limit = max(10, min(100, $limit));
    $messages = paffp_chat_load_messages($chatFile);

    if (count($messages) > $limit) {
        $messages = array_slice($messages, -$limit);
    }

    paffp_chat_response(200, [
        'success' => true,
        'room' => 'global',
        'current_payout_uid' => $uid,
        'current_payout_title' => (string) ($payout['title'] ?? ''),
        'viewer' => $viewer,
        'messages' => array_values($messages),
        'server_time' => time()
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');

    paffp_chat_response(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

$message = paffp_chat_clean_message($_POST['message'] ?? '');

if ($message === '') {
    paffp_chat_response(422, [
        'success' => false,
        'message' => 'Please enter a message.'
    ]);
}

$lockHandle = @fopen($lockFile, 'c+');

if (is_file($lockFile)) {
    @chmod($lockFile, 0664);
}

if (!$lockHandle || !@flock($lockHandle, LOCK_EX)) {
    if (is_resource($lockHandle)) {
        @fclose($lockHandle);
    }

    paffp_chat_response(503, [
        'success' => false,
        'message' => 'Chat is temporarily busy. Please try again.'
    ]);
}

$messages = paffp_chat_load_messages($chatFile);
$now = time();
$last = !empty($messages)
    ? $messages[count($messages) - 1]
    : null;

/* Basic anti-double-submit protection. */
if (
    is_array($last)
    && strcasecmp((string) ($last['username'] ?? ''), $viewer) === 0
    && (int) ($last['created_at'] ?? 0) >= ($now - 2)
) {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);

    paffp_chat_response(429, [
        'success' => false,
        'message' => 'Please wait a moment before sending another message.'
    ]);
}

$newMessage = [
    'id' => paffp_chat_message_id(),
    'username' => $viewer,
    'message' => $message,
    'payout_uid' => $uid,
    'payout_title' => (string) ($payout['title'] ?? ''),
    'created_at' => $now
];

$messages[] = $newMessage;

/* Keep the room small and fast. */
if (count($messages) > 150) {
    $messages = array_slice($messages, -150);
}

$writeOk = paffp_sync_write_json_file(
    $chatFile,
    array_values($messages),
    false
);

@flock($lockHandle, LOCK_UN);
@fclose($lockHandle);

if ($writeOk && is_file($chatFile)) {
    @chmod($chatFile, 0664);
}

if (!$writeOk) {
    paffp_chat_response(500, [
        'success' => false,
        'message' => 'Unable to save the chat message.'
    ]);
}

paffp_chat_response(200, [
    'success' => true,
    'message_item' => $newMessage,
    'server_time' => $now
]);
