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
 * This script is the main entry point for remote management.
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
$parameters = ['format::', 'help', 'log-stderr', 'verbose'];

// Get command line options.
$params = getopt('f::hv', $parameters, $opIndex);

// Get the operation and optional args.
$operation = isset($argv[$opIndex]) ? $argv[$opIndex] : '';
$opArgs = isset($argv[$opIndex + 1]) ? array_slice($argv, $opIndex + 1) : [];

// Set boolean options (true or false)
$option['help'] = isset($params['help']) || isset($params['h']);
$option['verbose'] = isset($params['verbose']) || isset($params['v']);
$option['log-stderr'] = isset($params['log-stderr']);

// Set options that may have values. Long options take precedence over short options.
// If this syntax is new to you, search for "php null coalescing operator".
if (!$option['format'] = $params['format'] ?? '') {
    $option['format'] = $params['f'] ?? '';
}

// Handle help.
if (empty($operation) || $operation == 'help' || $option['help']) {
    fwrite(STDERR, file_get_contents(__DIR__ . '/help.md'));
    exit(1);
}

Log::$debugMode = !empty($option['verbose']);
Log::$logStderr = !empty($option['log-stderr']);

// Load .env file which may accompany this package.
if (($env = @file(__DIR__ . '/.env')) !== false) {
    foreach ($env as $e) {
        if (!empty($e = trim($e))) {
            putenv($e);
        }
    }
}

// Start timer.
Log::stopWatch();

// Get a site object. This will determine the type of site.
$site = getSite();

Log::msg('Site type is: ' . $site->siteType);
Log::msg('Performing ' . $operation . ' operation.');

// Get the application name from the environment
if (empty($site->appEnv = getenv('APP_NAME'))) {
    Log::exitError('APP_NAME is undefined.');
}

// Get the requested operation and dispatch.
switch ($operation) {
    case 'backup':
        $success = $site->backup();
        break;

    case 'restore':
        $filename = array_shift($opArgs);
        $exclude = array_shift($opArgs) == '--exclude' ? " --exclude '" . basename(array_shift($opArgs)) . "'" : '';
        $success = $site->restore($filename, $exclude);
        if (function_exists('opcache_reset')) {
            opcache_reset(); // Clear php Opcache in case files changed during restore.
        }
        break;

    case 'download':
        $filename = array_shift($opArgs);
        // Check to make sure we have a filename.
        if (empty($filename)) {
            $success = false;
            Log::error('Missing filename.');
        } else {
            $s3 = new S3Cmd();
            $success = $s3->getFile($filename, '.' . strstr($filename, '/'));
        }
        break;

    case 'delete':
        $appname = array_shift($opArgs);
        if ($appname != $site->appEnv) {
            Log::exitError('Invalid or missing app-name. Are you sure that you are on the right server?');
        }
        $site->dropTables(); // No status check because it might have already been empty.
        $success = $site->deleteFiles();
        break;

    case 'pmlist':
        $success = $site->pmList();
        break;

    case 'app-name':
        Log::data('APP_NAME', $site->appEnv);
        $success = true;
        break;

    case 's3list':
        $filter = array_shift($opArgs);
        $s3 = new S3Cmd();
        $success = $s3->getList($filter);
        break;

    case 'space': // Disk space information.
        $success = true;
        $format = 'human';
        if ($option['format']) {
            if (!in_array($option['format'], ['bytes', 'human'])) {
                Log::exitError('Invalid format: ' . $option['format']);
            }
            $format = $option['format'];
        }
        $volumes = [];
        foreach ($site->volumes as $volume) {
            // Get disk information.
            $disk = new DiskSpace($volume, $format);
            // If invalid volume specified, disk total space will be FALSE.
            $success = $disk->total !== false;
            if ($success === false) {
                break;
            }
            $volumes[] = [
                'volume' => $volume,
                'totalspace' => $disk->total,
                'freespace' => $disk->free,
                'usedspace' => $disk->used,
                'usedpercentage' => $disk->percentage
            ];
        }
        Log::data('volumes', $volumes);
        break;

    case 'maint': // Set site in production mode.
        $mode = strtolower(array_shift($opArgs));
        switch ($mode) {
            case 'on':
                $success = $site->maintMode(true);
                break;
            case 'off':
                $success = $site->maintMode(false);
                break;
            default: // If no parameter, or an invalid parameter was specified, just return status.
                $success = true;
        }
        $inMaintMode = $site->getMaintMode();
        Log::data('maintMode', $inMaintMode? 'on' : 'off');
        $site->cleanup();
        break;

    default:
        Log::exitError('The operation "' . $operation . '" is invalid.');
}

// Stop timer and record elapsed time.
Log::stopWatch('stop');
Log::printData($success ? 'ok' : 'error');

// Complete execution.
exit($success ? 0 : 1);
