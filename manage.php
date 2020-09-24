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
    // Get command line options.
    $params = getopt('r:m::f::hbslv', array('restore:', 'maint::', 'format::', 'help', 'backup', 'space', 's3list', 'verbose'));

    // Help overrides anything else on the command line.
    $operation = (empty($operation) && (isset($params['help'])  || isset($params['h']))) ? 'help' : $operation;

    // Process 'restore' parameters.
    if (empty($operation) && (isset($params['restore']) || isset($params['r']))) {
        $operation = 'restore';
        $option['filename'] = isset($params['restore']) ? $params['restore'] : $params['r'];
    }

    // Process 'maint' parameters.
    if (empty($operation) && (isset($params['maint']) || isset($params['m']))) {
        $operation = 'maint';
        $option['status'] = isset($params['maint']) ? $params['maint'] : $params['m'];
    }

    // The following operations do not have any parameters.
    $operation = (empty($operation) && (isset($params['space'])  || isset($params['s']))) ? 'space'  : $operation;
    $operation = (empty($operation) && (isset($params['s3list']) || isset($params['l']))) ? 's3list' : $operation;
    $operation = (empty($operation) && isset($params['delete'])) ? 'backup' : $operation;
    $operation = (empty($operation) && (isset($params['pmlist']) || isset($params['p']))) ? 'pmlist' : $operation;

    // Process other options.
    $option['background'] = isset($params['background']);
    $option['verbose'] = isset($params['verbose']) || isset($params['v']);
    $option['format'] = isset($params['format']) || isset($params['f']);
    if ($option['format']) {
        $option['format'] = isset($params['format']) ? $params['format'] : $params['f'];
        if (in_array($option['format'], ['bytes', 'off'])) {
            $operation = false;
        }
    }

    if (empty($operation) || $operation == 'help') {
        // Display help and exit.
        fwrite(STDERR, file_get_contents(__DIR__ . '/help.txt'));
        exit(1);
    }

    // Help establish initial feedback that something is happening.
    echo '.';
}
else { // Web form post mode.
    $operation = $_REQUEST['operation'];
    $option['filename'] = isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
    $option['format'] = isset($_REQUEST['format']) ? $_REQUEST['format'] : '';
    $option['verbose'] = isset($_REQUEST['verbose']);
}

// Enable verbose mode if requested.
define('DEBUGMODE', $option['verbose']);
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
if ($operation == 'restore' && empty($option['filename'])) {
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
Log::msg('Performing ' . trim($operation . ' ' . $option['filename']) . ' operation.');

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
        $success = $site->restore($option['filename']);
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
            // Default format: CLI = human. Web = bytes.
            $format = (Log::$cli_mode ? 'human' : 'bytes');
            // But may be overwridden by format parameter.
            $format = (!empty($option['format']) ? $params['format'] : $format);
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
        switch(strtolower($option['status'])) { // This is actually mode in this case.
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
