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
 * - help: Displays help.
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

// Long form of valid parameters.
$parameters = ['restore:', 'background:', 'maint::', 'format::', 'help', 'backup', 'delete', 'space', 's3list', 'verbose'];

// Get command line options.
$params = getopt('r:m::f::hbslv', $parameters);

// Help overrides anything else on the command line.
$operation = (isset($params['help'])  || isset($params['h']) ? 'help' : '');

// Process 'restore' parameters.
if (empty($operation) && (isset($params['restore']) || isset($params['r']))) {
    $operation = 'restore';
    $option['filename'] = isset($params['restore']) ? $params['restore'] : $params['r'];
}
else {
    $option['filename'] = '';
}

if (empty($operation) && (isset($params['maint']) || isset($params['m']))) {
    // Process 'maint' parameters. Can be blank, on or off.
    $operation = 'maint';
    if (!empty($params['maint'])) {
        $option['maintmode'] = $params['maint'];
    }
    else {
        $option['maintmode'] = $params['m'];
    }
    $option['maintmode'] = strtolower($option['maintmode']);
}

// The following operations do not have any parameters.
$operation = (empty($operation) && isset($params['delete'])) ? 'delete' : $operation;
$operation = (empty($operation) && (isset($params['space'])  || isset($params['s']))) ? 'space'  : $operation;
$operation = (empty($operation) && (isset($params['s3list']) || isset($params['l']))) ? 's3list' : $operation;
$operation = (empty($operation) && (isset($params['backup']) || isset($params['b']))) ? 'backup' : $operation;
$operation = (empty($operation) && (isset($params['pmlist']) || isset($params['p']))) ? 'pmlist' : $operation;

// Process other options.
$option['background'] = !empty($params['background']) ? $params['background'] : ''; // Job pid.
$option['verbose'] = isset($params['verbose']) || isset($params['v']); // True or False.
$option['format'] = !empty($params['format']) || !empty($params['f']);
if ($option['format']) { // Optional parameter.
    $option['format'] = !empty($params['format']) ? $params['format'] : $params['f'];
    // Validate list of possible format options.
    if (!in_array($option['format'], ['bytes', 'human'])) {
        $operation = 'help';
    }
}

// Validate requested operation.

// Trim off all : and :: from each valid parameter in list.
$parameters = array_map('rtrim', $parameters, array_fill(0, count($parameters), ':'));
// Check if operation parameter is in the list.
if (!in_array($operation, $parameters, true)) {
    $noOp = $operation;  // Save invalid parameter.
    $operation = 'help'; // Change operation to help.
}

// Handle help.

if (empty($operation) || $operation == 'help') {
    // Display help and exit.
    fwrite(STDERR, file_get_contents(__DIR__ . '/help.txt'));
    exit(1);
}

Log::$debugMode = isset($option['verbose']) ? true : false;

// Ensure filename was specified, if required.
if ($operation == 'restore' && empty($option['filename'])) {
    Log::error('Missing filename.');
    exit(1);
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

    // Check to ensure that all AWS credentials are set.
    $envVars = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_S3_BUCKET', 'AWS_S3_REGION'];
    foreach ($envVars as $evar) {
        if (!getenv($evar)) {
            Log::error("$evar missing.");
        }
    }
    if (Log::$errCount > 0) {
        exit(1);
    }
}

// Start timer.
Log::stopWatch();

// Get a site object. This will determine the type of site.
$site = getSite();

Log::msg('Site type is: ' . $site->siteType);
Log::msg('Performing ' . trim($operation . ' ' . $option['filename'] . $option['format']) . ' operation.');

// Get the application name from the environment
if (empty($site->appEnv = getenv('APP_NAME'))) {
    Log::error('APP_NAME is undefined.');
    exit(1);
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
        $format = 'human'; // Or could be 'bytes'
        // But may be overwridden by format parameter.
        $format = (!empty($option['format']) ? $option['format'] : $format);
        foreach ($site->volumes as $volume) {
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
                'usedpercentage' => $disk->percentage
            ];
            Log::data($diskspace);
        }
        break;

    case 'maint': // Set site in production mode.
        switch($option['maintmode']) { // This is actually mode in this case.
            case 'on':
                $success = $site->maintMode(true);
                break;
            case 'off':
                $success = $site->maintMode(false);
                break;
            default: // If no parameter was specified, just return status.
                Log::msg('MaintMode: ' . $site->inMaintMode ? 'on' : 'off');
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
exit($success ? 0 : 1);
