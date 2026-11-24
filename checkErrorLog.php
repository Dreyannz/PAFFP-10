<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/sync-lib.php';

header("Content-Type: application/json");


function saveErrorLogData($errorLogFile, $data)
{
    return paffp_sync_write_json_file(
        $errorLogFile,
        array_values((array) $data),
        true
    );
}

function checkErrorLog($filename)
{
    $errorLogFile = __DIR__ . "/data/_errorLog.json";

    /*
     * ---------------------------------------------------------
     * Validate filename
     * ---------------------------------------------------------
     */

    if (empty($filename)) {
        return [
            "trigger" => false,
            "message" => "No file specified."
        ];
    }

    /*
     * Prevent directory traversal
     */
    $filename = basename($filename);

    /*
     * Actual JSON file
     */
    $jsonFile = __DIR__ . "/data/" . $filename;

    /*
     * ---------------------------------------------------------
     * Read _errorLog.json
     * ---------------------------------------------------------
     */

    if (!file_exists($errorLogFile)) {

        $data = [];

        /*
         * Check the actual JSON file first
         */
        $trigger = false;
        $reason = null;

        if (!file_exists($jsonFile)) {

            $trigger = true;
            $reason = "File not found.";

        } else {

            $jsonContent = file_get_contents($jsonFile);

            json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {

                $trigger = true;
                $reason = "Invalid JSON.";
            }
        }

        /*
         * Add new file entry
         */
        $data[] = [
            "file" => $filename,
            "lastTimeStamp" => date("c")
        ];

        saveErrorLogData(
            $errorLogFile,
            $data
        );

        return [
            "trigger" => $trigger,
            "file" => $filename,
            "reason" => $reason
        ];
    }

    /*
     * ---------------------------------------------------------
     * Read existing error log
     * ---------------------------------------------------------
     */

    $json = file_get_contents($errorLogFile);

    $data = json_decode($json, true);

    /*
     * Invalid _errorLog.json
     */
    if (!is_array($data)) {

        return [
            "trigger" => false,
            "message" => "Invalid _errorLog.json"
        ];
    }

    /*
     * ---------------------------------------------------------
     * Find the requested file
     * ---------------------------------------------------------
     */

    $foundIndex = null;

    foreach ($data as $index => $item) {

        if (
            isset($item["file"]) &&
            $item["file"] === $filename
        ) {
            $foundIndex = $index;
            break;
        }
    }

    /*
     * ---------------------------------------------------------
     * File doesn't have an entry yet
     * ---------------------------------------------------------
     */

    if ($foundIndex === null) {

        /*
         * Check actual JSON file
         */
        $trigger = false;
        $reason = null;

        if (!file_exists($jsonFile)) {

            $trigger = true;
            $reason = "File not found.";

        } else {

            $jsonContent = file_get_contents($jsonFile);

            json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {

                $trigger = true;
                $reason = "Invalid JSON.";
            }
        }

        /*
         * Add the new file entry
         */
        $data[] = [
            "file" => $filename,
            "lastTimeStamp" => date("c")
        ];

        saveErrorLogData(
            $errorLogFile,
            $data
        );

        /*
         * IMPORTANT:
         * Trigger immediately if the file is missing
         * or contains invalid JSON.
         */
        return [
            "trigger" => $trigger,
            "file" => $filename,
            "reason" => $reason
        ];
    }

    /*
     * ---------------------------------------------------------
     * Get timestamp
     * ---------------------------------------------------------
     */

    $lastTimeStamp = $data[$foundIndex]["lastTimeStamp"] ?? "";

    if (empty($lastTimeStamp)) {

        $data[$foundIndex]["lastTimeStamp"] = date("c");

        saveErrorLogData(
            $errorLogFile,
            $data
        );

        return [
            "trigger" => false,
            "file" => $filename
        ];
    }

    $lastTime = strtotime($lastTimeStamp);

    if ($lastTime === false) {

        return [
            "trigger" => false,
            "message" => "Invalid timestamp."
        ];
    }

    $currentTime = time();

    $elapsed = $currentTime - $lastTime;

    /*
     * ---------------------------------------------------------
     * Check 5 minutes
     * ---------------------------------------------------------
     */

    if ($elapsed < 300) {

        return [
            "trigger" => false,
            "file" => $filename
        ];
    }

    /*
     * ---------------------------------------------------------
     * Check actual JSON file
     * ---------------------------------------------------------
     */

    /*
     * File doesn't exist
     */
    if (!file_exists($jsonFile)) {

        /*
         * Update timestamp
         */
        $data[$foundIndex]["lastTimeStamp"] = date("c");

        saveErrorLogData(
            $errorLogFile,
            $data
        );

        return [
            "trigger" => true,
            "file" => $filename,
            "reason" => "File not found."
        ];
    }

    /*
     * Read JSON file
     */
    $jsonContent = file_get_contents($jsonFile);

    /*
     * Validate JSON
     */
    json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {

        /*
         * Update timestamp
         */
        $data[$foundIndex]["lastTimeStamp"] = date("c");

        saveErrorLogData(
            $errorLogFile,
            $data
        );

        return [
            "trigger" => true,
            "file" => $filename,
            "reason" => "Invalid JSON."
        ];
    }

    /*
     * ---------------------------------------------------------
     * JSON is valid
     * ---------------------------------------------------------
     */

    return [
        "trigger" => false,
        "file" => $filename
    ];
}


/*
 * ---------------------------------------------------------
 * Get file from URL
 * ---------------------------------------------------------
 */

$filename = $_GET["file"] ?? "";

$result = checkErrorLog($filename);

echo json_encode([
    "success" => true,
    "trigger" => $result["trigger"],
    "file" => $result["file"] ?? $filename,
    "reason" => $result["reason"] ?? null
]);