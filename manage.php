<?php

/**
 * This is the main entry point for remote management.
 *
 * Coding Guidelines:
 * Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/
 */

include_once "autoloader.php";
include_once "helpers.php";

use RemoteManage\Config;
use RemoteManage\DrupalSite;
use RemoteManage\MoodleSite;
use RemoteManage\Log;

Config::initialize();

Log::msg('Site type is: ' . Config::$siteType);

switch (Config::$siteType) {
    case 'drupal':
        $drupal = new DrupalSite();
        $drupal->maintMode(true);
        try {
            $drupal->backup();
        }
        catch (Exception $e) {

        }
        $drupal->maintMode(false);
        break;

    case 'moodle':
        $moodle = new MoodleSite();
        $moodle->maintMode(true);
        try {
            $moodle->backup();
        }
        catch (Exception $e) {

        }
        $moodle->maintMode(false);
        break;
}

$json = [];
$json['messages'] = Log::get();

header('Content-type: application/json; charset=utf-8');
echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
