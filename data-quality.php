<?php

/*
 * PAFFP Data Quality Scanner
 * --------------------------
 * Read-only scanner. It never modifies beneficiary/payout JSON files.
 */

function paffp_quality_is_sequential_array($value)
{
    if (!is_array($value)) {
        return false;
    }

    if ($value === []) {
        return true;
    }

    return array_keys($value) === range(0, count($value) - 1);
}

function paffp_quality_valid_iso_date($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        new DateTimeZone('Asia/Manila')
    );

    $errors = DateTimeImmutable::getLastErrors();

    return $date
        && !(
            is_array($errors)
            && (
                $errors['warning_count'] > 0
                || $errors['error_count'] > 0
            )
        )
        && $date->format('Y-m-d') === $value;
}

function paffp_quality_add_issue(
    &$issues,
    $type,
    $label,
    $sample = '',
    $severity = 'warning'
) {
    if (!isset($issues[$type])) {
        $issues[$type] = [
            'type' => $type,
            'label' => $label,
            'severity' => $severity,
            'count' => 0,
            'samples' => []
        ];
    }

    $issues[$type]['count']++;

    $sample = trim((string) $sample);

    if (
        $sample !== ''
        && count($issues[$type]['samples']) < 5
        && !in_array($sample, $issues[$type]['samples'], true)
    ) {
        $issues[$type]['samples'][] = $sample;
    }
}

function paffp_quality_issue_count($issues)
{
    $total = 0;

    foreach ((array) $issues as $issue) {
        $total += (int) ($issue['count'] ?? 0);
    }

    return $total;
}

function paffp_quality_normalize_name_component($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string) $value);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function paffp_quality_name_identity(
    $lastName,
    $firstName,
    $middleName = '',
    $extName = ''
) {
    $last = paffp_quality_normalize_name_component($lastName);
    $first = paffp_quality_normalize_name_component($firstName);
    $middle = paffp_quality_normalize_name_component($middleName);
    $ext = paffp_quality_normalize_name_component($extName);

    /*
     * Require LastName + FirstName so incomplete records are not falsely
     * treated as duplicate names. Missing-name checks handle those separately.
     */
    if ($last === '' || $first === '') {
        return null;
    }

    $normalized = implode('|', [$last, $first, $middle, $ext]);

    $displayLast = preg_replace(
        '/\s+/u',
        ' ',
        trim((string) $lastName)
    );
    $displayFirst = preg_replace(
        '/\s+/u',
        ' ',
        trim((string) $firstName)
    );
    $displayMiddle = preg_replace(
        '/\s+/u',
        ' ',
        trim((string) $middleName)
    );
    $displayExt = preg_replace(
        '/\s+/u',
        ' ',
        trim((string) $extName)
    );

    $givenParts = [];

    if ($displayFirst !== '') {
        $givenParts[] = $displayFirst;
    }

    if ($displayMiddle !== '') {
        $givenParts[] = $displayMiddle;
    }

    if ($displayExt !== '') {
        $givenParts[] = $displayExt;
    }

    $display = $displayLast;

    if (!empty($givenParts)) {
        $display .= ', ' . implode(' ', $givenParts);
    }

    return [
        'normalized' => $normalized,
        /* Compact key keeps the cross-payout first pass memory-conscious. */
        'hash' => md5($normalized),
        'display' => trim($display)
    ];
}

function paffp_quality_scan($dataFolder, $payouts)
{
    $report = [
        'schema_version' => 5,
        'generated_at' => time(),
        'summary' => [
            'payout_files' => 0,
            'records' => 0,
            'files_with_issues' => 0,
            'issues' => 0,
            'cross_file_duplicate_sysgen' => 0,
            'cross_file_duplicate_name' => 0
        ],
        'configuration_issues' => [],
        'files' => [],
        'cross_file_duplicates' => [],
        'cross_file_name_duplicates' => []
    ];

    $globalUidSeen = [];
    $globalFileSeen = [];

    /*
     * Memory-conscious Cross-Payout SysGen detection.
     *
     * firstFile stores only SysGen => small integer payout-file ID.
     * duplicates stores only SysGen values actually found in another file.
     */
    $crossSysGenFirstFile = [];
    $crossDuplicateSysGen = [];

    /*
     * Full-name duplicate detection follows the same two-pass strategy.
     * The first pass keeps only a compact MD5 identity => payout-file ID.
     */
    $crossNameFirstFile = [];
    $crossDuplicateName = [];

    $crossFileIds = [];
    $nextCrossFileId = 1;

    foreach ((array) $payouts as $payoutIndex => $payout) {
        if (!is_array($payout)) {
            paffp_quality_add_issue(
                $report['configuration_issues'],
                'invalid_payout_entry',
                'Invalid payout entry',
                'List row ' . ($payoutIndex + 1),
                'danger'
            );
            continue;
        }

        $uid = trim((string) ($payout['uid'] ?? ''));
        $title = trim((string) ($payout['title'] ?? $uid));
        $file = basename(trim((string) ($payout['file'] ?? '')));
        $date = trim((string) ($payout['date'] ?? ''));
        $expectedCount = max(0, (int) ($payout['count'] ?? 0));

        if ($uid === '') {
            paffp_quality_add_issue(
                $report['configuration_issues'],
                'missing_payout_uid',
                'Missing payout UID',
                $title !== '' ? $title : 'List row ' . ($payoutIndex + 1),
                'danger'
            );
        } else {
            $uidKey = strtolower($uid);

            if (isset($globalUidSeen[$uidKey])) {
                paffp_quality_add_issue(
                    $report['configuration_issues'],
                    'duplicate_payout_uid',
                    'Duplicate payout UID',
                    $uid,
                    'danger'
                );
            }

            $globalUidSeen[$uidKey] = true;
        }

        if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json') {
            paffp_quality_add_issue(
                $report['configuration_issues'],
                'invalid_payout_file',
                'Invalid payout filename',
                $file !== '' ? $file : $title,
                'danger'
            );
            continue;
        }

        $fileKey = strtolower($file);

        if (!isset($crossFileIds[$fileKey])) {
            $crossFileIds[$fileKey] = $nextCrossFileId++;
        }

        $crossFileId = (int) $crossFileIds[$fileKey];

        if (isset($globalFileSeen[$fileKey])) {
            paffp_quality_add_issue(
                $report['configuration_issues'],
                'duplicate_payout_file',
                'Duplicate payout filename',
                $file,
                'danger'
            );
        }

        $globalFileSeen[$fileKey] = true;

        if (!paffp_quality_valid_iso_date($date)) {
            paffp_quality_add_issue(
                $report['configuration_issues'],
                'invalid_payout_date',
                'Invalid payout date',
                ($title !== '' ? $title : $file) . ': ' . ($date !== '' ? $date : '(blank)'),
                'warning'
            );
        }

        $fileIssues = [];
        $path = rtrim($dataFolder, '/\\') . DIRECTORY_SEPARATOR . $file;
        $actualCount = 0;

        if (!is_file($path)) {
            paffp_quality_add_issue(
                $fileIssues,
                'file_missing',
                'Payout JSON file is missing',
                $file,
                'danger'
            );
        } elseif (!is_readable($path)) {
            paffp_quality_add_issue(
                $fileIssues,
                'file_unreadable',
                'Payout JSON file is unreadable',
                $file,
                'danger'
            );
        } else {
            $raw = @file_get_contents($path);
            $records = json_decode((string) $raw, true);

            if (
                $raw === false
                || json_last_error() !== JSON_ERROR_NONE
            ) {
                paffp_quality_add_issue(
                    $fileIssues,
                    'invalid_json',
                    'Invalid JSON',
                    json_last_error_msg(),
                    'danger'
                );
            } elseif (!paffp_quality_is_sequential_array($records)) {
                paffp_quality_add_issue(
                    $fileIssues,
                    'invalid_top_level',
                    'Top-level JSON must be an array',
                    $file,
                    'danger'
                );
            } else {
                $actualCount = count($records);
                $report['summary']['records'] += $actualCount;

                if ($expectedCount !== $actualCount) {
                    paffp_quality_add_issue(
                        $fileIssues,
                        'count_mismatch',
                        'Beneficiary count mismatch',
                        'Expected ' . number_format($expectedCount)
                        . ', actual ' . number_format($actualCount),
                        'warning'
                    );
                }

                $sysgenSeen = [];
                $nameSeen = [];

                foreach ($records as $recordIndex => $record) {
                    $rowNumber = $recordIndex + 1;

                    if (!is_array($record)) {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'invalid_record',
                            'Record is not a JSON object',
                            'Row ' . $rowNumber,
                            'danger'
                        );
                        continue;
                    }

                    $sysgen = trim((string) ($record['SysGen'] ?? ''));
                    $payroll = trim((string) ($record['Payroll'] ?? ''));
                    $lastName = trim((string) ($record['LastName'] ?? ''));
                    $firstName = trim((string) ($record['FirstName'] ?? ''));
                    $middleName = trim((string) ($record['MiddleName'] ?? ''));
                    $extName = trim((string) ($record['ExtName'] ?? ''));
                    $verifyStatus = trim((string) ($record['VerifyStatus'] ?? ''));
                    $verifyEncoder = trim((string) ($record['VerifyEncoder'] ?? ''));
                    $verifyTimestamp = trim((string) ($record['VerifyTimeStamp'] ?? ''));

                    $recordLabel =
                        $sysgen !== ''
                            ? $sysgen
                            : 'Row ' . $rowNumber;

                    if ($sysgen === '') {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'missing_sysgen',
                            'Missing SysGen',
                            'Row ' . $rowNumber,
                            'danger'
                        );
                    } else {
                        $sysKey = strtolower($sysgen);

                        if (isset($sysgenSeen[$sysKey])) {
                            paffp_quality_add_issue(
                                $fileIssues,
                                'duplicate_sysgen',
                                'Duplicate SysGen in payout',
                                $sysgen,
                                'danger'
                            );
                        }

                        $sysgenSeen[$sysKey] = true;

                        /*
                         * First pass: one compact integer per unique SysGen.
                         * Only values seen in a different payout file are
                         * promoted to the duplicate set.
                         */
                        if (!array_key_exists(
                            $sysKey,
                            $crossSysGenFirstFile
                        )) {
                            $crossSysGenFirstFile[$sysKey] =
                                $crossFileId;
                        } elseif (
                            $crossSysGenFirstFile[$sysKey]
                            !== $crossFileId
                        ) {
                            $crossDuplicateSysGen[$sysKey] =
                                $sysgen;
                        }
                    }

                    $nameIdentity = paffp_quality_name_identity(
                        $lastName,
                        $firstName,
                        $middleName,
                        $extName
                    );

                    if (is_array($nameIdentity)) {
                        $nameHash = (string) $nameIdentity['hash'];
                        $nameDisplay = (string) $nameIdentity['display'];

                        if (isset($nameSeen[$nameHash])) {
                            paffp_quality_add_issue(
                                $fileIssues,
                                'duplicate_full_name',
                                'Duplicate full name in payout',
                                $nameDisplay
                                . ' · rows '
                                . (int) $nameSeen[$nameHash]
                                . ' and '
                                . $rowNumber,
                                'warning'
                            );
                        } else {
                            $nameSeen[$nameHash] = $rowNumber;
                        }

                        if (!array_key_exists(
                            $nameHash,
                            $crossNameFirstFile
                        )) {
                            $crossNameFirstFile[$nameHash] =
                                $crossFileId;
                        } elseif (
                            $crossNameFirstFile[$nameHash]
                            !== $crossFileId
                        ) {
                            $crossDuplicateName[$nameHash] = [
                                'name' => $nameDisplay,
                                'normalized_name' =>
                                    (string) $nameIdentity['normalized']
                            ];
                        }
                    }

                    if ($payroll === '') {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'missing_payroll',
                            'Missing Payroll number',
                            $recordLabel,
                            'warning'
                        );
                    }

                    if ($lastName === '') {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'missing_last_name',
                            'Missing Last Name',
                            $recordLabel,
                            'warning'
                        );
                    }

                    if ($firstName === '') {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'missing_first_name',
                            'Missing First Name',
                            $recordLabel,
                            'warning'
                        );
                    }

                    if (
                        $verifyStatus !== ''
                        && !in_array(
                            $verifyStatus,
                            [
                                'Verified',
                                'Verified w/ Corrections',
                                'Caution'
                            ],
                            true
                        )
                    ) {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'invalid_verify_status',
                            'Unexpected VerifyStatus',
                            $recordLabel . ': ' . $verifyStatus,
                            'danger'
                        );
                    }

                    if (
                        $verifyTimestamp !== ''
                        && strtotime($verifyTimestamp) === false
                    ) {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'invalid_verify_timestamp',
                            'Malformed VerifyTimeStamp',
                            $recordLabel . ': ' . $verifyTimestamp,
                            'warning'
                        );
                    }

                    if (
                        in_array(
                            $verifyStatus,
                            [
                                'Verified',
                                'Verified w/ Corrections',
                                'Caution'
                            ],
                            true
                        )
                        && $verifyEncoder === ''
                    ) {
                        paffp_quality_add_issue(
                            $fileIssues,
                            'missing_verify_encoder',
                            'Status set without VerifyEncoder',
                            $recordLabel,
                            'warning'
                        );
                    }
                }
            }
        }

        $issueCount = paffp_quality_issue_count($fileIssues);

        $report['summary']['payout_files']++;
        $report['summary']['issues'] += $issueCount;

        if ($issueCount > 0) {
            $report['summary']['files_with_issues']++;
        }

        $report['files'][] = [
            'uid' => $uid,
            'title' => $title,
            'file' => $file,
            'expected_count' => $expectedCount,
            'actual_count' => $actualCount,
            'issue_count' => $issueCount,
            'issues' => array_values($fileIssues)
        ];
    }

    /*
     * Promote only actual cross-file duplicates to the comparison set,
     * then release the large first-pass lookup before loading detail rows.
     */
    $duplicateSysGen = [];

    foreach (
        $crossDuplicateSysGen
        as $sysKey => $displaySysGen
    ) {
        $duplicateSysGen[$sysKey] = [
            'sysgen' => (string) $displaySysGen,
            'occurrences' => []
        ];
    }

    $duplicateName = [];

    foreach (
        $crossDuplicateName
        as $nameHash => $nameInfo
    ) {
        $duplicateName[$nameHash] = [
            'name' => (string) ($nameInfo['name'] ?? ''),
            'normalized_name' =>
                (string) ($nameInfo['normalized_name'] ?? ''),
            'occurrences' => []
        ];
    }

    unset(
        $crossSysGenFirstFile,
        $crossDuplicateSysGen,
        $crossNameFirstFile,
        $crossDuplicateName,
        $crossFileIds
    );

    /*
     * Second pass: load full record details only for actual cross-payout
     * duplicates. In production this is normally a very small subset of all
     * SysGen values, so comparison data remains lightweight.
     */
    if (!empty($duplicateSysGen) || !empty($duplicateName)) {
        foreach ((array) $payouts as $payout) {
            if (!is_array($payout)) {
                continue;
            }

            $uid = trim((string) ($payout['uid'] ?? ''));
            $title = trim((string) ($payout['title'] ?? $uid));
            $file = basename(trim((string) ($payout['file'] ?? '')));

            if (
                $file === ''
                || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json'
            ) {
                continue;
            }

            $path =
                rtrim($dataFolder, '/\\')
                . DIRECTORY_SEPARATOR
                . $file;

            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $raw = @file_get_contents($path);

            if ($raw === false) {
                continue;
            }

            $records = json_decode($raw, true);

            if (
                json_last_error() !== JSON_ERROR_NONE
                || !paffp_quality_is_sequential_array($records)
            ) {
                unset($records, $raw);
                continue;
            }

            foreach ($records as $recordIndex => $record) {
                if (!is_array($record)) {
                    continue;
                }

                $sysgen = trim((string) ($record['SysGen'] ?? ''));
                $sysKey = $sysgen !== ''
                    ? strtolower($sysgen)
                    : '';

                $firstName =
                    trim((string) ($record['FirstName'] ?? ''));
                $middleName =
                    trim((string) ($record['MiddleName'] ?? ''));
                $lastName =
                    trim((string) ($record['LastName'] ?? ''));
                $extName =
                    trim((string) ($record['ExtName'] ?? ''));

                $nameIdentity = paffp_quality_name_identity(
                    $lastName,
                    $firstName,
                    $middleName,
                    $extName
                );

                $nameHash = is_array($nameIdentity)
                    ? (string) $nameIdentity['hash']
                    : '';

                $matchesSysGen =
                    $sysKey !== ''
                    && isset($duplicateSysGen[$sysKey]);

                $matchesName =
                    $nameHash !== ''
                    && isset($duplicateName[$nameHash]);

                if (!$matchesSysGen && !$matchesName) {
                    continue;
                }

                $fullNameParts = [];

                if ($firstName !== '') {
                    $fullNameParts[] = $firstName;
                }

                if ($middleName !== '') {
                    $fullNameParts[] = $middleName;
                }

                if ($lastName !== '') {
                    $fullNameParts[] = $lastName;
                }

                if ($extName !== '') {
                    $fullNameParts[] = $extName;
                }

                $occurrence = [
                    'uid' => $uid,
                    'title' => $title,
                    'file' => $file,
                    'row' => $recordIndex + 1,
                    'sysgen' => $sysgen,
                    'payroll' =>
                        trim((string) ($record['Payroll'] ?? '')),
                    'name' =>
                        trim(implode(' ', $fullNameParts)),
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'ext_name' => $extName,
                    'commodity' =>
                        trim((string) ($record['Commodity'] ?? '')),
                    'verifier_no' =>
                        trim((string) ($record['Verifier'] ?? '')),
                    'teller_no' =>
                        trim((string) ($record['Teller'] ?? '')),
                    'verify_status' =>
                        trim((string) ($record['VerifyStatus'] ?? '')),
                    'verify_encoder' =>
                        trim((string) ($record['VerifyEncoder'] ?? '')),
                    'verify_timestamp' =>
                        trim((string) ($record['VerifyTimeStamp'] ?? ''))
                ];

                if ($matchesSysGen) {
                    $duplicateSysGen[$sysKey]['occurrences'][] =
                        $occurrence;
                }

                if ($matchesName) {
                    $duplicateName[$nameHash]['occurrences'][] =
                        $occurrence;
                }
            }

            unset($records, $raw);
        }
    }

    $report['cross_file_duplicates'] =
        array_values($duplicateSysGen);

    /*
     * Release the large first-pass index before sorting/encoding the report.
     */
    unset($duplicateSysGen);

    usort($report['cross_file_duplicates'], function ($a, $b) {
        $aCount = count((array) ($a['occurrences'] ?? []));
        $bCount = count((array) ($b['occurrences'] ?? []));

        if ($aCount !== $bCount) {
            return $bCount <=> $aCount;
        }

        return strcasecmp(
            (string) ($a['sysgen'] ?? ''),
            (string) ($b['sysgen'] ?? '')
        );
    });

    $report['summary']['cross_file_duplicate_sysgen'] =
        count($report['cross_file_duplicates']);

    $report['cross_file_name_duplicates'] =
        array_values($duplicateName);

    unset($duplicateName);

    usort($report['cross_file_name_duplicates'], function ($a, $b) {
        $aCount = count((array) ($a['occurrences'] ?? []));
        $bCount = count((array) ($b['occurrences'] ?? []));

        if ($aCount !== $bCount) {
            return $bCount <=> $aCount;
        }

        return strcasecmp(
            (string) ($a['name'] ?? ''),
            (string) ($b['name'] ?? '')
        );
    });

    $report['summary']['cross_file_duplicate_name'] =
        count($report['cross_file_name_duplicates']);

    $configurationIssueCount =
        paffp_quality_issue_count($report['configuration_issues']);

    $report['summary']['issues'] += $configurationIssueCount;

    usort($report['files'], function ($a, $b) {
        $aIssues = (int) ($a['issue_count'] ?? 0);
        $bIssues = (int) ($b['issue_count'] ?? 0);

        if ($aIssues !== $bIssues) {
            return $bIssues <=> $aIssues;
        }

        return strcasecmp(
            (string) ($a['title'] ?? ''),
            (string) ($b['title'] ?? '')
        );
    });

    return $report;
}

function paffp_quality_load_cache($cacheFile)
{
    if (!is_file($cacheFile) || !is_readable($cacheFile)) {
        return null;
    }

    /*
     * Reject stale cache versions before decoding the full file.
     *
     * v11 could create a very large schema-v3 Data Quality cache because it
     * retained full comparison details for every SysGen. Reading and decoding
     * that stale cache can itself exceed a modest PHP memory_limit.
     *
     * schema_version is deliberately the first report field, so checking the
     * first 1 KB is sufficient and avoids loading the old cache into memory.
     */
    $handle = @fopen($cacheFile, 'rb');

    if (!$handle) {
        return null;
    }

    $head = @fread($handle, 1024);
    @fclose($handle);

    if (
        !is_string($head)
        || !preg_match(
            '/["\']schema_version["\']\s*:\s*5\b/',
            $head
        )
    ) {
        return null;
    }

    $raw = @file_get_contents($cacheFile);
    $decoded = json_decode((string) $raw, true);

    if (
        $raw === false
        || !is_array($decoded)
        || !isset($decoded['summary'])
        || (int) ($decoded['schema_version'] ?? 0) !== 5
    ) {
        return null;
    }

    return $decoded;
}

function paffp_quality_get_report(
    $cacheFile,
    $dataFolder,
    $payouts,
    $force = false,
    $maxAgeSeconds = 300
) {
    $cached = paffp_quality_load_cache($cacheFile);

    if (
        !$force
        && is_array($cached)
        && (int) ($cached['generated_at'] ?? 0) > 0
        && (
            time() - (int) $cached['generated_at']
        ) <= $maxAgeSeconds
    ) {
        return $cached;
    }

    $report = paffp_quality_scan(
        $dataFolder,
        $payouts
    );

    if (function_exists('paffp_sync_write_json_file')) {
        paffp_sync_write_json_file(
            $cacheFile,
            $report,
            true
        );
    }

    return $report;
}
