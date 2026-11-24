<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dataDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
$listPath = $dataDir . DIRECTORY_SEPARATOR . '_list.json';
$errors = [];
$warnings = [];
$checkedFiles = [];

function audit_line($type, $message)
{
    fwrite(
        $type === 'ERROR' ? STDERR : STDOUT,
        '[' . $type . '] ' . $message . PHP_EOL
    );
}

if (!is_file($listPath) || !is_readable($listPath)) {
    audit_line('ERROR', 'data/_list.json is missing or unreadable.');
    exit(1);
}

$rawList = @file_get_contents($listPath);
$payouts = json_decode((string) $rawList, true);

if ($rawList === false || !is_array($payouts)) {
    audit_line('ERROR', 'data/_list.json is not a valid JSON array.');
    exit(1);
}

audit_line('INFO', 'Payout entries: ' . count($payouts));

$seenUid = [];
$seenFile = [];
$allowedStatuses = [
    '',
    'Verified',
    'Verified w/ Corrections',
    'Caution'
];

foreach ($payouts as $index => $payout) {
    $entry = $index + 1;

    if (!is_array($payout)) {
        $errors[] = '_list entry ' . $entry . ' is not an object.';
        continue;
    }

    $uid = trim((string) ($payout['uid'] ?? ''));
    $title = trim((string) ($payout['title'] ?? ''));
    $file = basename((string) ($payout['file'] ?? ''));
    $date = trim((string) ($payout['date'] ?? ''));
    $listedCount = max(0, (int) ($payout['count'] ?? 0));

    if ($uid === '') {
        $errors[] = '_list entry ' . $entry . ' has no UID.';
    } elseif (isset($seenUid[$uid])) {
        $errors[] = 'Duplicate payout UID: ' . $uid . '.';
    } else {
        $seenUid[$uid] = true;
    }

    if ($title === '') {
        $errors[] = '_list entry ' . $entry . ' has no title.';
    }

    if ($file === ''
        || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json'
        || strpos($file, '_') === 0) {
        $errors[] = '_list entry ' . $entry
            . ' has an invalid beneficiary filename.';
        continue;
    }

    if (isset($seenFile[strtolower($file)])) {
        $errors[] = 'Beneficiary JSON is listed more than once: '
            . $file . '.';
    } else {
        $seenFile[strtolower($file)] = true;
    }

    if ($date === ''
        || DateTimeImmutable::createFromFormat('!Y-m-d', $date) === false) {
        $warnings[] = ($title !== '' ? $title : $uid)
            . ': payout date is missing or invalid.';
    }

    $path = $dataDir . DIRECTORY_SEPARATOR . $file;
    $checkedFiles[strtolower($file)] = true;

    if (!is_file($path) || !is_readable($path)) {
        $errors[] = ($title !== '' ? $title : $uid)
            . ': beneficiary JSON not found: data/' . $file . '.';
        continue;
    }

    $raw = @file_get_contents($path);
    $records = json_decode((string) $raw, true);

    if ($raw === false || !is_array($records)) {
        $errors[] = ($title !== '' ? $title : $uid)
            . ': beneficiary JSON is invalid.';
        continue;
    }

    if (array_keys($records) !== (
        $records === []
            ? []
            : range(0, count($records) - 1)
    )) {
        $errors[] = ($title !== '' ? $title : $uid)
            . ': beneficiary JSON must be a sequential array.';
        continue;
    }

    if ($listedCount !== count($records)) {
        $warnings[] = ($title !== '' ? $title : $uid)
            . ': _list count is '
            . $listedCount
            . ' but file contains '
            . count($records)
            . ' records.';
    }

    $seenSysGen = [];
    $verified = 0;
    $corrected = 0;
    $caution = 0;
    $pending = 0;

    foreach ($records as $recordIndex => $record) {
        $row = $recordIndex + 1;

        if (!is_array($record)) {
            $errors[] = $file . ': record ' . $row . ' is not an object.';
            continue;
        }

        foreach (['SysGen', 'LastName', 'FirstName'] as $required) {
            if (trim((string) ($record[$required] ?? '')) === '') {
                $errors[] = $file . ': record ' . $row
                    . ' is missing ' . $required . '.';
            }
        }

        $sysGen = trim((string) ($record['SysGen'] ?? ''));

        if ($sysGen !== '') {
            if (isset($seenSysGen[$sysGen])) {
                $errors[] = $file . ': duplicate SysGen '
                    . $sysGen . ' at record ' . $row . '.';
            } else {
                $seenSysGen[$sysGen] = true;
            }
        }

        $status = trim((string) ($record['VerifyStatus'] ?? ''));

        if (!in_array($status, $allowedStatuses, true)) {
            $warnings[] = $file . ': record ' . $row
                . ' has unknown VerifyStatus "' . $status . '".';
        }

        if ($status === 'Verified') {
            $verified++;
        } elseif ($status === 'Verified w/ Corrections') {
            $corrected++;
        } elseif ($status === 'Caution') {
            $caution++;
        } else {
            $pending++;
        }

        $timestamp = trim((string) ($record['VerifyTimeStamp'] ?? ''));

        if ($timestamp !== '' && strtotime($timestamp) === false) {
            $warnings[] = $file . ': record ' . $row
                . ' has an invalid VerifyTimeStamp.';
        }
    }

    audit_line(
        'OK',
        $file
        . ': '
        . count($records)
        . ' records; '
        . $verified
        . ' verified; '
        . $corrected
        . ' corrections; '
        . $caution
        . ' caution; '
        . $pending
        . ' pending.'
    );
}

$allJsonFiles = glob($dataDir . DIRECTORY_SEPARATOR . '*.json');

if (is_array($allJsonFiles)) {
    foreach ($allJsonFiles as $path) {
        $name = basename($path);

        if (strpos($name, '_') === 0) {
            continue;
        }

        if (!isset($checkedFiles[strtolower($name)])) {
            $warnings[] = 'Unlisted beneficiary JSON in data/: ' . $name . '.';
        }
    }
}

foreach ($warnings as $warning) {
    audit_line('WARN', $warning);
}

foreach ($errors as $error) {
    audit_line('ERROR', $error);
}

audit_line(
    'INFO',
    'Completed with '
    . count($errors)
    . ' error(s) and '
    . count($warnings)
    . ' warning(s).'
);

exit($errors ? 1 : 0);
