<?php

/**
 * Perform remote management for a website.
 *
 * Date: July 2020
 *
 * @author  Duncan Sutter
 * @author  Samantha Tripp
 * @author  Michael Milette
 * @license MIT https://opensource.org/licenses/MIT
 *
 * This script is the main entry point for remote management. Operations are:
 * - backup
 * - restore
 *
 * Coding Guidelines:
 * Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/
 */

// Use the composer PSR-4 autoloader.
$loader = require 'vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');

require_once "helpers.php";

use RemoteManage\Log;
use RemoteManage\S3Cmd;
use RemoteManage\DiskSpace;

// Set timeout to 3 hours.
set_time_limit(10800);

// Keep session alive.
header("Connection: Keep-alive");

// Parameter option or filename.
$filename = '';

// If using command line...
$cli = (php_sapi_name() == 'cli') && !isset($_SERVER['REMOTE_ADDR']);

if ($cli) {
    Log::$cli_mode = true;

    $options = ['h' => 'help', 'b' => 'backup', 'r:' => 'restore:', 's' => 'space', 'l' => 's3list', 'm::' => 'maint::', 'd' => 'delete', 'v' => 'verbose'];
    $params = getopt(join(array_keys($options)), array_values($options));

    // If invalid or missing parameter, display help.
    if (empty($params)) {
        $params = ['help' => false];
    }

    // Only process the first passed parameter.
    // We don't support multiple parameters at this time.
    reset($params);
    $operation = key($params);

    // Get a filename or other parameter option, if one was passed.
    if (!empty($params[$operation])) {
        $filename = $params[$operation];
    }

    // Convert to long form of option if short form was specified.
    for ($cnt = 0; $cnt < 3; $cnt++) {
        // Handle variations if there is one or more colons.
        $op = $operation . str_repeat(':', $cnt);
        if (isset($options[$op])) {
            // Be sure to save long form without colons.
            $operation = rtrim($options[$op], ':');
            break;
        }
    }

    // Display help.
    if ($operation == 'help') {
        fwrite(STDERR, file_get_contents(__DIR__ . '/help.txt'));
        exit(1);
    }
}
else { // Web form post mode.
    $operation = $_REQUEST['operation'];
    $filename = $_POST['filename'];
}

// Enable verbose mode if requested.
define($DEBUGMODE, isset($param['v']) || isset($param['verbose']));
if ($DEBUGMODE) {
    echo 'Verbose mode is enabled.' . ($cli ? PHP_EOL : '<br>');
}

// Ensure filename was specified, if required.
if($operation == 'restore' && empty('filename')) {
    Log::msg("ERROR: Missing filename.");
    $operation = 'error';
}

// Get S3 credentials and settings if required.

$aws_op = in_array($operation, ['backup', 'restore', 's3list']);

if ($aws_op) {
    // Load .env file which may accompany this package.
    if (($env = @file(__DIR__ . '/.env')) !== false) {
        foreach ($env as $e) {
            if (!empty($e = trim($e))) {
            putenv($e);
            }
        }
    }

    if (!$cli) {
        // Get credentials and settings if provided via POST .
        // These would override any settings from the .env file above.

        $envVars = ['aws_access_key_id', 'aws_secret_access_key', 'aws_s3_bucket', 'aws_s3_region'];
        foreach  ($envVars as $evar) {
            if (isset($_POST[$evar])) {
                putenv(strtoupper($evar) . '=' . $_POST[$evar]);
            } else if (!getenv(strtoupper($evar))) {
                Log::msg("ERROR: AWS $evar missing.");
                $operation = 'error';
            }
        }
    }
}

// Display start time.
$startTime = microtime(true);
Log::msg('Starting at ' . date('H:i:s', $startTime) . '...');

// Get a site object. This will determine the type of site.
$site = getSite();

Log::msg('Site type is: ' . $site->siteType);
Log::msg('Performing ' . trim($operation . ' ' . $filename) . ' operation.');

// Get the application name from the environment
if (empty($site->appEnv = getenv('APP_NAME'))) {
    Log::msg("ERROR: APP_NAME is undefined.");
    $operation = 'error';
}

// Get the requested operation and dispatch.
switch ($operation) {
    case 'backup':
        $site->backup($startTime);
        break;

    case 'restore':
        $site->restore($startTime, $filename);
        break;

    case 'delete':
        $site->dropTables();
        $site->deleteFiles();
        break;

    case 's3list':
        $s3 = new S3Cmd();
        $s3->getList();
        break;

    case 'space': // Disk space information.
        foreach ($site->volumes as $volume) {
            $disk = new DiskSpace($volume);
            Log::msg("Disk information for $volume:");
            Log::msg("Total disk space: $disk->total_space");
            Log::msg("Free disk space: $disk->free");
            Log::msg("Used disk space: $disk->used");
            Log::msg('Percentage used: ', round($disk->percentage, 2) . '%');
        }
        break;

    case 'maint': // Set site in production mode.
        switch(strtolower($filename)) { // This is actually mode in this case.
            case 'on':
                $site->maintMode(true);
                break;
            case 'off':
                $site->maintMode(false);
                break;
            default:
                Log::msg('Maintenance mode is ' . ($site->inMaintMode ? 'on' : 'off'));
        }
        break;

    case 'error': // Error, just exit.
        break;

    default:
        Log::msg("ERROR: The operation is either missing or invalid.");
}

// Display end time and duration.
$endTime = microtime(true);
Log::msg('Job started at ' . date('H:i:s', $startTime) . ' and finished at ' . date('H:i:s', $endTime) . '.');
Log::msg('Total execution time was ' . date('H:i:s', $endTime - $startTime) . '.');

// If using the CLI, we're done. The message were already printed out as they happened.
if (!$cli) {
    // Create the JSON response
    $json = [];
    $json['messages'] = Log::get();

    // Exit with appropriate HTTP header and a valid JSON response.
    header('Content-type: application/json; charset=utf-8');
    echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
}
