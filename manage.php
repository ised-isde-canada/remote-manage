<?php

/**
 * Perform remote management for a website.
 *
 * Date: Auguest 2020
 *
 * @author  Duncan Sutter
 * @author  Samantha Tripp
 * @author  Michael Milette
 * @license MIT https://opensource.org/licenses/MIT
 *
 * This script is the main entry point for remote management. Operations are:
 * - help: Displays help in CLI mode.
 * - backup: Perform backup operation.
 * - restore: Perform restore operation.
 * - space: Display available space for specified volumes.
 * - s3list: Display a list of available archives.
 * - maint: Enable or Disable maintenance mode.
 * - delete: delete all files on persistent volumnes.
 *
 * Coding Guidelines:
 * Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/
 */

// Use the composer PSR-4 autoloader.
$loader = require '/opt/app-root/src/vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');

require_once "helpers.php";

use RemoteManage\Log;
use RemoteManage\S3Cmd;
use RemoteManage\DiskSpace;
use RemoteManage\Drush;

// Set timeout to 3 hours.
set_time_limit(10800);
// Use local timezone
date_default_timezone_set("America/Toronto");
// Keep session alive.
header("Connection: Keep-alive");
// Appropriate HTTP header for json reponse.
header('Content-type: application/json; charset=utf-8');

// If using command line...
Log::$cli_mode = (php_sapi_name() == 'cli') && !isset($_SERVER['REMOTE_ADDR']);

if (Log::$cli_mode) {
    // Note: Delete does not have a short version.
    $options = ['h' => 'help', 'b' => 'backup', 'r:' => 'restore:',
                's' => 'space', 'l' => 's3list', 'm::' => 'maint::',
                '' => 'delete', 'v' => 'verbose', 'f::' => 'format::'
            ];

    // Long options that don't have a corresponding short option
    $longopts = ['background'];

    $params = getopt(join(array_keys($options)), array_merge(array_values($options), $longopts));

    // If invalid or missing parameter, display help.
    if (empty($params)) {
        $params = ['help' => false];
    }

    // Only process the first passed parameter.
    // We don't support multiple parameters at this time.
    reset($params);
    $operation = key($params);

    // Get a filename or other parameter option, if one was passed.
    $filename = !empty($params[$operation]) ? $params[$operation] : '';

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

    // Help establish initial feedback that something is happening.
    echo '.';
}
else { // Web form post mode.
    $operation = $_REQUEST['operation'];

    $filename = isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
    if (!empty($_REQUEST['format'])) {
        $params['format'] = $_REQUEST['format'];
    }

    if (!empty($_REQUEST['verbose'])) {
        $params['verbose'] = true;
    }
}

// Enable verbose mode if requested.
define('DEBUGMODE', isset($params['v']) || isset($params['verbose']));
if (DEBUGMODE) {
    Log::msg('Verbose mode is enabled.');
}

if (!Log::$cli_mode) { // Web form post mode.
    // Help is not supported via POST.
    if ($operation == 'help') {
        Log::error("Invalid operation: $operation");
        Log::endItAll('error');
    }
    else if (empty($operation)) {
        // Someone forgot a little detail!
        Log::error('The operation is missing.');
        Log::endItAll('error');
    }

    // Help establish initial connection.
    echo PHP_EOL;
}

// Ensure filename was specified, if required.
if ($operation == 'restore' && empty('filename')) {
    Log::error('Missing filename.');
    Log::endItAll('error');
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

    // Get credentials and settings if provided via POST .
    // These would override any settings from the .env file above.
    $envVars = ['aws_access_key_id', 'aws_secret_access_key', 'aws_s3_bucket', 'aws_s3_region'];
    foreach ($envVars as $evar) {
        if (isset($_POST[$evar])) {
            putenv(strtoupper($evar) . '=' . $_POST[$evar]);
        } else if (!getenv(strtoupper($evar))) {
            // Report missing credentials.
            Log::error("ERROR: AWS $evar missing.");
            Log::endItAll('error');
        }
    }
}

// Start timer.
Log::stopWatch();

// Get a site object. This will determine the type of site.
$site = getSite();

Log::msg('Site type is: ' . $site->siteType);
Log::msg('Performing ' . trim($operation . ' ' . $filename) . ' operation.');

// Get the application name from the environment
if (empty($site->appEnv = getenv('APP_NAME'))) {
    Log::error('ERROR: APP_NAME is undefined.');
    Log::endItAll('error');
}

// Get the requested operation and dispatch.
switch ($operation) {
    case 'backup':
        $success = $site->backup();
        break;

    case 'restore':
        $success = $site->restore($filename);
        break;

    case 'delete':
        $site->dropTables(); // No status check because it might have already been empty.
        $success = $site->deleteFiles();
        break;

    case 'pmlist':
        $drush = new Drush();
        $success = $drush->pmList();
        break;

    case 's3list':
        $s3 = new S3Cmd();
        $success = $s3->getList();
        break;

    case 'space': // Disk space information.
        $success = true;
        foreach ($site->volumes as $volume) {
            // CLI = human. Web = bytes.
            $format = (Log::$cli_mode ? 'human' : 'bytes');
            // But may be overwridden by format parameter.
            $format = (isset($params['format']) ? $params['format'] : $format);
            // Get disk information.
            $disk = new DiskSpace($volume, $format);
            // If invalid volume specified, disk total space will be FALSE.
            $success = $disk->total !== false;
            if ($success === false) {
                break;
            }
            $diskspace = [
                'volume' => $volume,
                'totalspace' => $disk->total,
                'freespace' => $disk->free,
                'usedspace' => $disk->used,
                'usedpercentage' => round($disk->percentage, 2)
            ];
            Log::data($diskspace);
        }
        break;

    case 'maint': // Set site in production mode.
        switch(strtolower($filename)) { // This is actually mode in this case.
            case 'on':
                $success = $site->maintMode(true);
                break;
            case 'off':
                $success = $site->maintMode(false);
                break;
            default:
                Log::data(($site->inMaintMode ? 'on' : 'off'));
                $success = true;
        }
        break;

    case 'error': // Error, just exit.
        $success = false;
        break;

    default:
        Log::error('The operation "' . $operation . '" invalid.');
        $success = false;

}

// Stop timer and record elapsed time.
Log::stopWatch('stop');

// Complete execution.
Log::endItAll($success ? 'success' : 'error');
