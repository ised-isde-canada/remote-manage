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

// Use the comopser PSR-4 autoloader
$loader = require 'vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');

include_once "helpers.php";

use RemoteManage\Config;
use RemoteManage\Drupal\Site as DrupalSite;
use RemoteManage\Moodle\Site as MoodleSite;
use RemoteManage\Log;

// Initialize the configuration. We may move this into the individual sites.
Config::initialize();

// Detect what type of site we're on and instantiate the appropriate class to handle the requested operation.
if (DrupalSite::detect()) {
    $site = new DrupalSite();
}
else if (MoodleSite::detect()) {
    $site = new MoodleSite();
}

Log::msg('Site type is: ' . $site->siteType);

// Get the requested operation and dispatch.
switch ($_POST['operation']) {
    case 'backup':
        $site->backup();
        break;

    case 'restore':
        $site->restore();
        break;
}

// Create the JSON response
$json = [];
$json['messages'] = Log::get();

// Exit with appropriate HTTP header and a valid JSON response.
header('Content-type: application/json; charset=utf-8');
echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
