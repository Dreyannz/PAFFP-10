<?php

if (!function_exists('paffp_admin_payout_accomplished')) {
    function paffp_admin_payout_accomplished($payout)
    {
        $date = trim((string) ($payout['date'] ?? ''));

        if ($date === '') {
            return false;
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$value || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return false;
        }

        $today = new DateTimeImmutable('today', $timezone);
        return $value < $today;
    }
}

if (!function_exists('paffp_admin_accomplished_access_mode')) {
    function paffp_admin_accomplished_access_mode($payout)
    {
        $mode = strtolower(trim((string) ($payout['accomplished_access_mode'] ?? 'closed')));

        if (!in_array($mode, ['closed', 'all', 'users'], true)) {
            return 'closed';
        }

        return $mode;
    }
}

if (!function_exists('paffp_admin_accomplished_access_all_permission')) {
    function paffp_admin_accomplished_access_all_permission($payout)
    {
        $permission = strtolower(
            trim(
                (string) (
                    $payout['accomplished_access_all_permission']
                    ?? 'edit'
                )
            )
        );

        if (!in_array($permission, ['view', 'edit'], true)) {
            return 'edit';
        }

        return $permission;
    }
}

if (!function_exists('paffp_admin_accomplished_access_users')) {
    function paffp_admin_accomplished_access_users($payout)
    {
        $raw = $payout['accomplished_access_users'] ?? [];

        if (!is_array($raw)) {
            $raw = preg_split('/[\r\n,;]+/', (string) $raw);
        }

        $users = [];
        $seen = [];

        foreach ($raw as $value) {
            $name = trim((string) $value);
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $users[] = $name;
        }

        return $users;
    }
}

if (!function_exists('paffp_admin_accomplished_access_permissions')) {
    function paffp_admin_accomplished_access_permissions($payout)
    {
        $users = paffp_admin_accomplished_access_users($payout);
        $raw = $payout['accomplished_access_permissions'] ?? [];
        $permissions = [];

        if (is_array($raw)) {
            foreach ($raw as $name => $permission) {
                $cleanName = trim((string) $name);
                $cleanPermission = strtolower(trim((string) $permission));

                if ($cleanName === '') {
                    continue;
                }

                if (!in_array($cleanPermission, ['view', 'edit'], true)) {
                    $cleanPermission = 'edit';
                }

                $permissions[strtolower($cleanName)] = $cleanPermission;
            }
        }

        $result = [];

        foreach ($users as $name) {
            $key = strtolower($name);

            /*
             * Backward compatibility:
             * old username-only access entries were editable, so they
             * continue to default to edit until explicitly changed.
             */
            $result[$name] = $permissions[$key] ?? 'edit';
        }

        return $result;
    }
}

if (!function_exists('paffp_admin_payout_open')) {
    function paffp_admin_payout_open($payout)
    {
        if (paffp_admin_payout_accomplished($payout)) {
            $mode = paffp_admin_accomplished_access_mode($payout);

            if ($mode === 'all') {
                return true;
            }

            if ($mode === 'users') {
                return count(paffp_admin_accomplished_access_users($payout)) > 0;
            }

            return false;
        }

        return !array_key_exists('enabled', $payout) || !empty($payout['enabled']);
    }
}

if (!function_exists('paffp_admin_timestamp')) {
    function paffp_admin_timestamp($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $time = strtotime((string) $value);
        return $time === false ? 0 : $time;
    }
}

if (!function_exists('paffp_admin_collect_stats')) {
    function paffp_admin_collect_stats($dataFolder, $payouts)
    {
        $summary = [
            'beneficiaries' => 0,
            'verified' => 0,
            'corrected' => 0,
            'caution' => 0,
            'pending' => 0,
            'completed' => 0,
            'open_payouts' => 0,
            'closed_payouts' => 0,
            'active_verifiers_today' => 0,
            'verifier_count' => 0,
            'files_read' => 0
        ];

        $verifiers = [];
        $recent = [];
        $today = date('Y-m-d');

        foreach ($payouts as $payout) {
            if (paffp_admin_payout_open($payout)) {
                $summary['open_payouts']++;
            } else {
                $summary['closed_payouts']++;
            }

            $file = basename((string) ($payout['file'] ?? ''));
            if ($file === '') {
                continue;
            }

            $path = rtrim($dataFolder, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $raw = @file_get_contents($path);
            $rows = json_decode((string) $raw, true);
            if ($raw === false || !is_array($rows)) {
                continue;
            }

            $summary['files_read']++;
            $summary['beneficiaries'] += count($rows);
            $payoutTitle = (string) ($payout['title'] ?? $file);
            $payoutUid = (string) ($payout['uid'] ?? '');

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $status = (string) ($row['VerifyStatus'] ?? '');
                $encoder = trim((string) ($row['VerifyEncoder'] ?? ''));
                $timestampRaw = (string) ($row['VerifyTimeStamp'] ?? '');
                $timestamp = paffp_admin_timestamp($timestampRaw);

                if ($status === 'Verified') {
                    $summary['verified']++;
                    $summary['completed']++;
                } elseif ($status === 'Verified w/ Corrections') {
                    $summary['corrected']++;
                    $summary['completed']++;
                } elseif ($status === 'Caution') {
                    $summary['caution']++;
                    $summary['pending']++;
                } else {
                    $summary['pending']++;
                }

                if ($encoder === '' || $status === '') {
                    continue;
                }

                if (!isset($verifiers[$encoder])) {
                    $verifiers[$encoder] = [
                        'name' => $encoder,
                        'verified' => 0,
                        'corrected' => 0,
                        'caution' => 0,
                        'total_actions' => 0,
                        'payouts' => [],
                        'last_ts' => 0,
                        'last_status' => '',
                        'last_payout' => '',
                        'active_today' => false
                    ];
                }

                if ($status === 'Verified') {
                    $verifiers[$encoder]['verified']++;
                } elseif ($status === 'Verified w/ Corrections') {
                    $verifiers[$encoder]['corrected']++;
                } elseif ($status === 'Caution') {
                    $verifiers[$encoder]['caution']++;
                }

                $verifiers[$encoder]['total_actions']++;
                $verifiers[$encoder]['payouts'][$payoutUid !== '' ? $payoutUid : $file] = $payoutTitle;

                if ($timestamp > $verifiers[$encoder]['last_ts']) {
                    $verifiers[$encoder]['last_ts'] = $timestamp;
                    $verifiers[$encoder]['last_status'] = $status;
                    $verifiers[$encoder]['last_payout'] = $payoutTitle;
                }

                if ($timestamp > 0 && date('Y-m-d', $timestamp) === $today) {
                    $verifiers[$encoder]['active_today'] = true;
                }

                if ($timestamp > 0) {
                    $recent[] = [
                        'encoder' => $encoder,
                        'status' => $status,
                        'timestamp' => $timestamp,
                        'payout' => $payoutTitle,
                        'payout_uid' => $payoutUid
                    ];
                }
            }
        }

        foreach ($verifiers as &$verifier) {
            $verifier['payout_count'] = count($verifier['payouts']);
            $verifier['payouts'] = array_values($verifier['payouts']);
        }
        unset($verifier);

        uasort($verifiers, function ($a, $b) {
            if ($a['total_actions'] !== $b['total_actions']) {
                return $b['total_actions'] <=> $a['total_actions'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        usort($recent, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $summary['verifier_count'] = count($verifiers);
        $summary['active_verifiers_today'] = count(array_filter($verifiers, function ($item) {
            return !empty($item['active_today']);
        }));

        return [
            'summary' => $summary,
            'verifiers' => array_values($verifiers),
            'recent' => array_slice($recent, 0, 100)
        ];
    }
}

if (!function_exists('paffp_admin_collect_stats_cached')) {
    function paffp_admin_collect_stats_cached(
        $dataFolder,
        $payouts,
        $cacheFile = null
    ) {
        $cacheFile = $cacheFile !== null
            ? (string) $cacheFile
            : rtrim($dataFolder, '/\\')
                . DIRECTORY_SEPARATOR
                . '_adminStatsCache.json';

        $fileSignatures = [];

        foreach ((array) $payouts as $payout) {
            if (!is_array($payout)) {
                continue;
            }

            $file = basename((string) ($payout['file'] ?? ''));
            if ($file === '') {
                continue;
            }

            $path = rtrim($dataFolder, '/\\')
                . DIRECTORY_SEPARATOR
                . $file;

            clearstatcache(true, $path);

            $signature = [
                'file' => $file,
                'mtime' => is_file($path)
                    ? (int) @filemtime($path)
                    : 0,
                'size' => is_file($path)
                    ? (int) @filesize($path)
                    : 0,
                'sync_epoch' => '',
                'sync_version' => 0
            ];

            if (function_exists('paffp_sync_read_meta')) {
                $meta = paffp_sync_read_meta($file);

                if (is_array($meta)) {
                    $signature['sync_epoch'] =
                        (string) ($meta['epoch'] ?? '');
                    $signature['sync_version'] =
                        (int) ($meta['version'] ?? 0);
                }
            }

            $fileSignatures[] = $signature;
        }

        $signaturePayload = [
            'day' => date('Y-m-d'),
            'payouts' => array_values((array) $payouts),
            'files' => $fileSignatures
        ];

        $signatureJson = json_encode(
            $signaturePayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $signature = $signatureJson === false
            ? ''
            : sha1($signatureJson);

        if ($signature !== ''
            && is_file($cacheFile)
            && is_readable($cacheFile)) {

            $raw = @file_get_contents($cacheFile);
            $cached = json_decode((string) $raw, true);

            if ($raw !== false
                && is_array($cached)
                && hash_equals(
                    $signature,
                    (string) ($cached['signature'] ?? '')
                )
                && isset($cached['payload'])
                && is_array($cached['payload'])) {

                return $cached['payload'];
            }
        }

        $payload = paffp_admin_collect_stats(
            $dataFolder,
            $payouts
        );

        if ($signature !== '') {
            $encoded = json_encode(
                [
                    'signature' => $signature,
                    'generated_ts' => time(),
                    'payload' => $payload
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($encoded !== false
                && function_exists('paffp_sync_write_validated_json_text')) {

                paffp_sync_write_validated_json_text(
                    $cacheFile,
                    $encoded
                );
            }
        }

        return $payload;
    }
}

if (!function_exists('paffp_admin_remove_path')) {
    function paffp_admin_remove_path($path)
    {
        if (is_link($path) || is_file($path)) {
            return @unlink($path);
        }

        if (!is_dir($path)) {
            return true;
        }

        $items = @scandir($path);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!paffp_admin_remove_path($path . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return @rmdir($path);
    }
}

if (!function_exists('paffp_admin_copy_tree')) {
    function paffp_admin_copy_tree($source, $destination)
    {
        if (is_file($source)) {
            $parent = dirname($destination);

            if (!is_dir($parent) && !@mkdir($parent, 0777, true)) {
                return false;
            }

            if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'json'
                && function_exists('paffp_sync_write_validated_json_text')) {

                $content = @file_get_contents($source);

                return $content !== false
                    && paffp_sync_write_validated_json_text(
                        $destination,
                        $content
                    );
            }

            if (!@copy($source, $destination)) {
                return false;
            }

            clearstatcache(true, $source);
            clearstatcache(true, $destination);

            return (int) @filesize($source)
                === (int) @filesize($destination);
        }

        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination) && !@mkdir($destination, 0777, true)) {
            return false;
        }

        $items = @scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (!paffp_admin_copy_tree(
                $source . DIRECTORY_SEPARATOR . $item,
                $destination . DIRECTORY_SEPARATOR . $item
            )) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('paffp_admin_full_backup')) {
    function paffp_admin_full_backup($dataFolder, $fullBackupFolder, $reason = 'manual')
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('PHP PharData is not available on this server.');
        }

        if (!is_dir($fullBackupFolder) && !@mkdir($fullBackupFolder, 0777, true)) {
            throw new RuntimeException('Unable to create the full-backup folder.');
        }

        $stamp = date('Ymd_His');
        $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
        $safeReason = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $reason);
        $baseName = 'paffp_data__' . $stamp . '__' . $safeReason . '__' . $suffix;
        $tarPath = $fullBackupFolder . DIRECTORY_SEPARATOR . $baseName . '.tar';
        $gzPath = $tarPath . '.gz';

        @unlink($tarPath);
        @unlink($gzPath);

        $archive = new PharData($tarPath);
        $root = rtrim(realpath($dataFolder) ?: $dataFolder, DIRECTORY_SEPARATOR);
        $backupRoot = realpath(dirname($fullBackupFolder));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dataFolder,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $real = $entry->getRealPath();
            if ($real === false) {
                continue;
            }

            if ($backupRoot !== false && strpos($real, $backupRoot . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }

            $relative = ltrim(substr($real, strlen($root)), DIRECTORY_SEPARATOR);
            if ($relative === '') {
                continue;
            }

            $archive->addFile($real, $relative);
        }

        $archive->compress(Phar::GZ);
        unset($archive);
        @unlink($tarPath);
        @chmod($gzPath, 0777);

        if (!is_file($gzPath)) {
            throw new RuntimeException('The full data backup could not be created.');
        }

        return basename($gzPath);
    }
}

if (!function_exists('paffp_admin_full_backup_list')) {
    function paffp_admin_full_backup_list($fullBackupFolder)
    {
        if (!is_dir($fullBackupFolder)) {
            return [];
        }

        $files = glob($fullBackupFolder . DIRECTORY_SEPARATOR . 'paffp_data__*.tar.gz');
        if ($files === false) {
            return [];
        }

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return array_map(function ($path) {
            return [
                'name' => basename($path),
                'path' => $path,
                'size' => (int) filesize($path),
                'modified' => (int) filemtime($path)
            ];
        }, $files);
    }
}

if (!function_exists('paffp_admin_find_full_backup')) {
    function paffp_admin_find_full_backup($fullBackupFolder, $name)
    {
        $safeName = basename((string) $name);
        foreach (paffp_admin_full_backup_list($fullBackupFolder) as $backup) {
            if ($backup['name'] === $safeName) {
                return $backup;
            }
        }
        return null;
    }
}

if (!function_exists('paffp_admin_restore_full_backup')) {
    function paffp_admin_restore_full_backup($dataFolder, $fullBackupFolder, $backupName)
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('PHP PharData is not available on this server.');
        }

        $backup = paffp_admin_find_full_backup($fullBackupFolder, $backupName);

        if (!$backup) {
            throw new RuntimeException('The selected full backup was not found.');
        }

        paffp_admin_full_backup(
            $dataFolder,
            $fullBackupFolder,
            'before_restore'
        );

        $tempDir = $fullBackupFolder
            . DIRECTORY_SEPARATOR
            . 'restore_'
            . bin2hex(random_bytes(5));

        if (!@mkdir($tempDir, 0777, true)) {
            throw new RuntimeException('Unable to create a temporary restore folder.');
        }

        try {
            $archive = new PharData($backup['path']);
            $archive->extractTo($tempDir, null, true);
            unset($archive);

            $listPath = $tempDir . DIRECTORY_SEPARATOR . '_list.json';

            if (!is_file($listPath)) {
                throw new RuntimeException(
                    'The selected backup does not contain _list.json.'
                );
            }

            /*
             * Validate every JSON file before any live data is replaced.
             */
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $tempDir,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $entry) {
                if (!$entry->isFile()
                    || strtolower($entry->getExtension()) !== 'json') {

                    continue;
                }

                $raw = @file_get_contents($entry->getPathname());

                if ($raw === false
                    || !function_exists('paffp_sync_json_text_is_valid')
                    || !paffp_sync_json_text_is_valid($raw)) {

                    throw new RuntimeException(
                        'The backup contains invalid JSON: '
                        . $entry->getFilename()
                    );
                }
            }

            /*
             * Copy validated backup files over the live set first. JSON files
             * are replaced atomically by paffp_admin_copy_tree().
             */
            if (!paffp_admin_copy_tree($tempDir, $dataFolder)) {
                throw new RuntimeException(
                    'Unable to copy the validated backup into the data folder.'
                );
            }

            /*
             * Remove stale top-level live items only AFTER the backup copy is
             * complete. Never delete the backups directory.
             */
            $liveItems = @scandir($dataFolder);

            if ($liveItems === false) {
                throw new RuntimeException(
                    'Unable to read the data folder after restore.'
                );
            }

            foreach ($liveItems as $item) {
                if ($item === '.'
                    || $item === '..'
                    || $item === 'backups') {

                    continue;
                }

                $sourcePath = $tempDir
                    . DIRECTORY_SEPARATOR
                    . $item;

                if (file_exists($sourcePath)
                    || is_link($sourcePath)) {

                    continue;
                }

                if (!paffp_admin_remove_path(
                    $dataFolder
                    . DIRECTORY_SEPARATOR
                    . $item
                )) {
                    throw new RuntimeException(
                        'The backup was restored, but a stale data item could not be removed: '
                        . $item
                    );
                }
            }
        } finally {
            paffp_admin_remove_path($tempDir);
        }

        return true;
    }
}
