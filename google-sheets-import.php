<?php
/**
 * PAFFP Google Sheets snapshot importer (PHP 7.4+).
 * Private mode: PAFFP_GOOGLE_SERVICE_ACCOUNT_FILE points to a JSON service
 * account credential outside the document root.
 * Public mode (fallback): anonymous Google CSV export. Do not publish farmer
 * personal data simply to use this fallback.
 */

function paffp_sheet_reference($link, $gidInput = '')
{
    $url = trim((string) $link);
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== 'docs.google.com'
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
        || !preg_match('~^/spreadsheets/d/([A-Za-z0-9_-]{20,160})(?:/|$)~', (string) ($parts['path'] ?? ''), $match)) {
        throw new RuntimeException('Paste a valid https://docs.google.com/spreadsheets/d/... link.');
    }

    $gid = trim((string) $gidInput);
    if ($gid === '') {
        $params = [];
        parse_str((string) ($parts['query'] ?? ''), $params);
        if (isset($params['gid'])) {
            $gid = (string) $params['gid'];
        } else {
            parse_str((string) ($parts['fragment'] ?? ''), $params);
            if (isset($params['gid'])) {
                $gid = (string) $params['gid'];
            }
        }
    }

    if ($gid !== '' && !preg_match('/^[0-9]{1,15}$/', $gid)) {
        throw new RuntimeException('The worksheet GID must be a non-negative numeric ID.');
    }

    return [
        'id' => $match[1],
        'gid' => $gid
    ];
}

function paffp_google_http($url, $bearerToken = '', $postData = null, $maxBytes = 20 * 1024 * 1024)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Google Sheets import. Enable php-curl on the server.');
    }

    $parsed = parse_url($url);
    $host = strtolower((string) ($parsed['host'] ?? ''));
    if (($parsed['scheme'] ?? '') !== 'https'
        || isset($parsed['port'])
        || !in_array($host, [
            'docs.google.com', 'drive.usercontent.google.com', 'sheets.googleapis.com', 'oauth2.googleapis.com'
        ], true)
        && !preg_match('/(^|\.)googleusercontent\.com$/', $host)) {
        throw new RuntimeException('The Google export redirected to an untrusted host.');
    }

    $response = '';
    $headers = [];
    $ch = curl_init($url);
    $requestHeaders = ['Accept: text/csv, application/json; q=0.9'];
    if ($bearerToken !== '') {
        $requestHeaders[] = 'Authorization: Bearer ' . $bearerToken;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'PAFFP-Sheets-Importer/1.0',
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$response, $maxBytes) {
            if (strlen($response) + strlen($chunk) > $maxBytes) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$headers) {
            $length = strlen($line);
            if (stripos($line, 'Location:') === 0) {
                $headers['location'] = trim(substr($line, 9));
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $headers['content-type'] = trim(substr($line, 13));
            }
            return $length;
        }
    ]);

    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }

    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($ok === false) {
        throw new RuntimeException(
            strlen($response) >= $maxBytes
                ? 'Google Sheet is too large (20 MB maximum response).'
                : 'Could not connect to Google Sheets: ' . $error
        );
    }

    return ['status' => $status, 'body' => $response, 'headers' => $headers];
}

function paffp_google_csv_download($id, $gid)
{
    $gid = $gid !== '' ? $gid : '0';
    $url = 'https://docs.google.com/spreadsheets/d/' . rawurlencode($id)
        . '/export?format=csv&gid=' . rawurlencode($gid);

    for ($redirect = 0; $redirect < 4; $redirect++) {
        $reply = paffp_google_http($url);
        if (in_array($reply['status'], [301, 302, 303, 307, 308], true)) {
            $next = (string) ($reply['headers']['location'] ?? '');
            if ($next === '') {
                throw new RuntimeException('Google returned an invalid redirect.');
            }
            if (substr($next, 0, 1) === '/') {
                $currentHost = parse_url($url, PHP_URL_HOST);
                $next = 'https://' . $currentHost . $next;
            }
            $url = $next;
            continue;
        }

        if ($reply['status'] !== 200) {
            throw new RuntimeException(
                'Google Sheet could not be downloaded (HTTP ' . $reply['status']
                . '). For a private Sheet, configure a service account and share the Sheet with its email.'
            );
        }

        $body = $reply['body'];
        if (preg_match('/^\s*<(?:!doctype\s+html|html)/i', $body)
            || stripos((string) ($reply['headers']['content-type'] ?? ''), 'text/html') !== false) {
            throw new RuntimeException(
                'Google returned a sign-in/access page rather than CSV. Use a service account for a private Sheet.'
            );
        }
        return $body;
    }
    throw new RuntimeException('Google Sheets redirected too many times.');
}

function paffp_google_csv_rows($csv)
{
    $stream = fopen('php://temp/maxmemory:2097152', 'w+');
    if (!$stream) {
        throw new RuntimeException('Unable to prepare Sheet rows.');
    }
    fwrite($stream, $csv);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
        if (count($rows) > 25002) {
            fclose($stream);
            throw new RuntimeException('The Sheet has too many rows (25,000 maximum).');
        }
    }
    fclose($stream);
    return $rows;
}

function paffp_google_rows_to_records($rows)
{
    if (!$rows || !is_array($rows)) {
        throw new RuntimeException('The selected worksheet is empty. Add headers in row 1.');
    }

    $headers = (array) array_shift($rows);
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    $headers = array_map(function ($value) {
        return trim((string) $value);
    }, $headers);

    while ($headers && end($headers) === '') {
        array_pop($headers);
    }
    if (!$headers || count($headers) > 100) {
        throw new RuntimeException('Row 1 must contain between 1 and 100 column headers.');
    }
    if (in_array('', $headers, true)) {
        throw new RuntimeException('Row 1 has an empty column header. Give every used column a unique name.');
    }
    if (count(array_unique($headers)) !== count($headers)) {
        throw new RuntimeException('Row 1 has duplicate column headers. Header names must be unique.');
    }

    // Verify relies on these exact, case-sensitive JSON property names.
    foreach (['SysGen', 'LastName', 'FirstName'] as $required) {
        if (!in_array($required, $headers, true)) {
            throw new RuntimeException('Missing required column: ' . $required . '. Check the row 1 header spelling.');
        }
    }

    $seenIds = [];
    $result = [];
    foreach ($rows as $index => $row) {
        $cells = array_map(function ($cell) { return (string) $cell; }, (array) $row);
        $nonempty = array_filter($cells, function ($cell) { return trim($cell) !== ''; });
        if (!$nonempty) {
            continue;
        }
        if (count($cells) > count($headers)) {
            $extra = array_slice($cells, count($headers));
            if (array_filter($extra, function ($cell) { return trim($cell) !== ''; })) {
                throw new RuntimeException(
                    'Row ' . ($index + 2) . ' contains data beyond the header columns.'
                );
            }
        }
        $cells = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));
        $record = array_combine($headers, $cells);
        $sysGen = trim($record['SysGen']);
        if ($sysGen === '') {
            throw new RuntimeException('Row ' . ($index + 2) . ' has an empty SysGen.');
        }
        if (isset($seenIds[$sysGen])) {
            throw new RuntimeException('Duplicate SysGen ' . $sysGen . ' at row ' . ($index + 2) . '.');
        }
        $seenIds[$sysGen] = true;
        $result[] = $record;
        if (count($result) > 25000) {
            throw new RuntimeException('Only the first 25,000 beneficiary rows are supported.');
        }
    }

    if (!$result) {
        throw new RuntimeException('No beneficiary rows were found after the header.');
    }

    return ['headers' => $headers, 'records' => $result];
}

function paffp_google_base64url($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function paffp_google_service_token($credentialFile)
{
    if (!is_file($credentialFile) || !is_readable($credentialFile)) {
        throw new RuntimeException('Google service-account credential file is missing or not readable.');
    }
    $account = json_decode((string) file_get_contents($credentialFile), true);
    if (!is_array($account)
        || empty($account['client_email'])
        || empty($account['private_key'])) {
        throw new RuntimeException('Invalid Google service-account credential file.');
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3000
    ];
    $input = paffp_google_base64url(json_encode($header)) . '.'
        . paffp_google_base64url(json_encode($claims));
    if (!openssl_sign($input, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign Google service-account credentials.');
    }
    $jwt = $input . '.' . paffp_google_base64url($signature);
    $reply = paffp_google_http(
        'https://oauth2.googleapis.com/token',
        '',
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]),
        1024 * 1024
    );
    $decoded = json_decode($reply['body'], true);
    if ($reply['status'] !== 200 || empty($decoded['access_token'])) {
        throw new RuntimeException('Google service-account authentication failed. Check credentials and server clock.');
    }
    return (string) $decoded['access_token'];
}

function paffp_google_api_json($url, $token)
{
    $reply = paffp_google_http($url, $token);
    if ($reply['status'] !== 200) {
        throw new RuntimeException(
            'Google Sheets API denied access (HTTP ' . $reply['status']
            . '). Share the Sheet with the service-account email as Viewer and enable Google Sheets API.'
        );
    }
    $decoded = json_decode($reply['body'], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Google Sheets API did not return valid JSON.');
    }
    return $decoded;
}

function paffp_google_import($link, $gidInput = '')
{
    $ref = paffp_sheet_reference($link, $gidInput);
    $credentials = trim((string) getenv('PAFFP_GOOGLE_SERVICE_ACCOUNT_FILE'));
    $worksheetTitle = '';
    $selectedGid = $ref['gid'];

    if ($credentials !== '') {
        $token = paffp_google_service_token($credentials);
        $metadata = paffp_google_api_json(
            'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($ref['id'])
            . '?fields=sheets(properties(sheetId,title))',
            $token
        );
        $tabs = (array) ($metadata['sheets'] ?? []);
        if (!$tabs) {
            throw new RuntimeException('The Google spreadsheet has no worksheets.');
        }
        $chosen = null;
        foreach ($tabs as $tab) {
            $props = (array) ($tab['properties'] ?? []);
            if ($selectedGid === '' || (string) ($props['sheetId'] ?? '') === $selectedGid) {
                $chosen = $props;
                break;
            }
        }
        if (!$chosen) {
            throw new RuntimeException('The requested worksheet GID was not found.');
        }
        $worksheetTitle = (string) ($chosen['title'] ?? '');
        $selectedGid = (string) ($chosen['sheetId'] ?? '');
        $range = "'" . str_replace("'", "''", $worksheetTitle) . "'";
        $response = paffp_google_api_json(
            'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($ref['id'])
            . '/values/' . rawurlencode($range)
            . '?majorDimension=ROWS&valueRenderOption=FORMATTED_VALUE',
            $token
        );
        $rows = (array) ($response['values'] ?? []);
        $source = 'Private Sheet / service account';
    } else {
        $rows = paffp_google_csv_rows(
            paffp_google_csv_download($ref['id'], $selectedGid)
        );
        $source = 'Google Sheets CSV export';
        $selectedGid = $selectedGid !== '' ? $selectedGid : '0';
    }

    $converted = paffp_google_rows_to_records($rows);
    return [
        'records' => $converted['records'],
        'headers' => $converted['headers'],
        'count' => count($converted['records']),
        'gid' => $selectedGid,
        'worksheet' => $worksheetTitle,
        'source' => $source
    ];
}
