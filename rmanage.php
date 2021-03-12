<?php

// Use the composer PSR-4 autoloader.
$loader = require '/opt/app-root/src/vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');

use RemoteManage\RemoteManageServer;

// If a shared secret has been defined, then it is required. Also, using a
// shared secret means that only the POST method is supported.
if ($secret = getenv('RMANAGE_SECRET')) {
    if ($secret != $_POST['secret']) {
        header('Content-type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid credentials.'
        ], JSON_PRETTY_PRINT) . PHP_EOL;
        exit;
    }
}

$rmserver = new RemoteManageServer();

// Get the main operation
$operation = $_REQUEST['operation'] ?? '';

// Get the options and assemble into an array
$options = [];

if (isset($_REQUEST['verbose']) && $_REQUEST['verbose'] == 'true') {
    $options[] = '--verbose --log-stderr';
}

if (isset($_REQUEST['format'])) {
    $options[] = '--format=' . $_REQUEST['format'];
}

// Determine Job ID.
// If a job id is specified, backup and restore will run as a background task.
if (isset($_REQUEST['job'])) {
    if ($_REQUEST['job'] == 'true') {
        // New job ID. System will generate and return job id.
        $job = getmypid();
    } elseif (is_numeric($_REQUEST['job'])) {
        // Specific job id or for job already in progress.
        // For static systems, use job=0.
        $job = $_REQUEST['job'];
    } else {
        // If no job id was specified, task will run in foreground.
        // No log file will be created.
        $job = '';
    }
}

// Determine location for storing rmanage_##.log file.
if (file_exists(getenv('HOME') . '/lang/en/moodle.php')) { // Moodle.
    $rmanageLog = "/data/moodle";
} elseif (is_dir(getenv('HOME') . '/drush')) { // Drupal.
    $rmanageLog = "/opt/app-root/src/data";
} else { // Unknown type.
    $rmanageLog = "/tmp";
}

// Clean-up log files older than 7 days.
if ($files = glob($rmanageLog . '/rmanage_*.log')) {
    $now = time();
    $seconds = 259200; // 60 * 60 * 24 * 3 = 3 days.
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $seconds) { // Too old, delete it.
                unlink($file);
            }
        }
    }
}

// Determine name of log file.
$rmanageLog .= "/rmanage_$job.log";

$json = [];

// Assemble the basic command to run
$cmd = 'php ' . dirname(__FILE__) . '/manage.php';

if ($options) {
    $cmd .= ' ' . join(' ', $options);
}

// If performing a backup or restore, delete the log file if it already exists.
if (in_array($operation, ['backup','restore']) && file_exists($rmanageLog)) {
    unlink($rmanageLog);
}

switch ($operation) {
    case 'backup':
        $rmserver->getS3Credentials();
        if ($job != '') { // Background mode.
            `$cmd backup > $rmanageLog &`;
            $json = ['status' => 'ok', 'job' => $job];
        } else { // Immediate mode.
            $json = $rmserver->getJSONResult(`$cmd backup`);
        }
        break;

    case 'restore':
        if (empty($_REQUEST['s3file'])) {
            $json = ['result' => 'error', 'message' => 'Missing s3file'];
            break;
        }
        $rmserver->getS3Credentials();
        $s3file = $_REQUEST['s3file'];
        if ($job != '') { // Background mode.
            `$cmd restore $s3file --exclude $rmanageLog > $rmanageLog &`;
            $json = ['status' => 'ok', 'job' => $job];
        } else { // Immediate mode.
            $json = $rmserver->getJSONResult(`$cmd restore $s3file`);
        }
        break;

    case 'query':
        $json = @file_get_contents($rmanageLog);
        if (empty($json)) {
            $json = ['result' => 'error', 'message' => 'Log file is missing.'];
        } else {
            $json = $rmserver->getJSONResult($json);
        }
        break;

    case 'maint':
        $mode = $_REQUEST['mode'] ?? '';
        $json = $rmserver->getJSONResult(`$cmd maint $mode`);
        break;

    case 's3list':
        $rmserver->getS3Credentials();
        $json = $rmserver->getJSONResult(`$cmd s3list`);
        break;

    case 'pmlist':
        $json = $rmserver->getJSONResult(`$cmd pmlist`);
        break;

    case 'space':
        $json = $rmserver->getJSONResult(`$cmd space`);
        break;

    default:
        $json = ['status' => 'error', 'message' => "Unknown operation $operation"];
}

// Exit with a JSON result

header('Content-type: application/json');
echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
