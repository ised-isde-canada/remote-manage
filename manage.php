<?php

/**
 * Perform remote management for a website.
 *
 * Written by: Duncan Sutter, Samantha Tripp and Michael Milette
 * Date: July 2020
 * License: MIT
 *
 * This script is the main entry point for remote management. Operations are:
 * - backup
 * - restore
 *
 * Coding Guidelines:
 * Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/
 */

// Use the composer PSR-4 autoloader
$loader = require 'vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');

include_once "helpers.php";

use RemoteManage\Log;
use RemoteManage\S3Cmd;

// If using command line...
$cli = (php_sapi_name() == 'cli') && !isset($_SERVER['REMOTE_ADDR']);

if ($cli) {
    Log::$cli_mode = true;

    $options = ['h' => 'help', 'b' => 'backup', 'r:' => 'restore:', 'l' => 's3list', 'd' => 'debug'];
    $params = getopt(join(array_keys($options)), array_values($options));

    // If invalid or missing parameter, display help.
    if (empty($params)) {
        $params = ['help' => false];
    }

    // Only process the first passed parameter.
    reset($params);
    $operation = key($params);

    // Get value. If none, will be false.
    $file = $params[$operation];

    // Convert to long form of option if short form was specified.
    if (isset($options[$operation])) {
        $operation = $options[$operation];
    }

    // Display help.
    if ($operation == 'help') {
        $help = 'Manage v1.0 - July 2020' . PHP_EOL;
        $help .= 'Written by: Duncan Sutter, Samantha Tripp and Michael Milette' . PHP_EOL;
        $help .= 'Purpose: Backup or restore a website.' . PHP_EOL;
        $help .= 'Example: php manage.php --backup' . PHP_EOL;
        $help .= PHP_EOL;
        $help .= 'Must only specify one of the following parameters:' . PHP_EOL;
        $help .= "--help|-h              Display's this information." . PHP_EOL;
        $help .= "--backup|-b            Backup this site" . PHP_EOL;
        $help .= "--restore|-r filename  Restore the specified backup file." . PHP_EOL;
        $help .= "--s3list|-l            List available backups." . PHP_EOL;
        fwrite(STDERR, $help . PHP_EOL);
        $errcode = 1;
        exit($errcode);
    }
}
else {
    $operation = $_REQUEST['operation'];
}

// Load .env file which may accompany this package.
if (($env = @file(__DIR__ . '/.env')) !== false) {
    foreach ($env as $e) {
        if (!empty($e = trim($e))) {
           putenv($e);
        }
    }
}

// Get S3 credentials and settings if provided via POST (only).
// These would override any settings from the .env file above.

if (isset($_POST['aws_access_key'])) {
    putenv('AWS_ACCESS_KEY_ID=' . $_POST['aws_access_key']);
}

if (isset($_POST['aws_secret_access_key'])) {
    putenv('AWS_SECRET_ACCESS_KEY=' . $_POST['aws_secret_access_key']);
}

if (isset($_POST['aws_s3_bucket'])) {
    putenv('AWS_S3_BUCKET=' . $_POST['aws_s3_bucket']);
}

if (isset($_POST['aws_s3_region'])) {
    putenv('AWS_S3_REGION=' . $_POST['aws_s3_region']);
}

// Get a site object. This will determine the type of site.
$site = getSite();

Log::msg('Site type is: ' . $site->siteType);

// Set the site's application name from the request
/*
TODO: Set the default based on environment vars
Here are some OpenShift vars from the Drupal environment. App name is "manage"
OPENSHIFT_BUILD_SOURCE=https://github.com/dsutter-gc/manage-site-wxt.git
OPENSHIFT_BUILD_NAME=manage-30
OPENSHIFT_BUILD_COMMIT=32724cf94432a776c510f53f49240f0edc810de6
OPENSHIFT_BUILD_NAMESPACE=ciodrcoe-dev
OPENSHIFT_BUILD_REFERENCE=ised
 */
if (isset($_REQUEST['app_name'])) {
    $site->appName = $_REQUEST['app_name'];
}

// Get the requested operation and dispatch.
switch ($operation) {
    case 'backup':
        $site->backup();
        break;

    case 'restore':
        if ($site->dropTables()) {
            $site->restore();
        }
        break;

    case 's3list': // temporary, for testing
        $s3 = new S3Cmd();
        $s3->getList();
        break;

    default:
        Log::msg("ERROR: The operation is either missing or invalid.");
}

// If using the CLI, we're done. The message were already printed out as they happened.
if ($cli) {
    exit;
}

// Create the JSON response
$json = [];
$json['messages'] = Log::get();

// Exit with appropriate HTTP header and a valid JSON response.
header('Content-type: application/json; charset=utf-8');
echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
