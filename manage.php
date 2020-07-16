<?php

// Set up autoloader, which assumes that we are running in a vendor folder.
// If this proves problematic, then see vendor/drush/drush/drush.php for ideas to make this better

if (file_exists($autoloadFile = __DIR__ . '/../../autoload.php')) {
    include_once($autoloadFile);
}
else {
    throw new \Exception("Could not locate autoload.php.");
}

use RemoteManage\Config;
use RemoteManage\DrupalSite;
use RemoteManage\MoodleSite;
use RemoteManage\Log;

Log::msg("This is the Remote Manager!");

Config::initialize();

Log::msg('Site type is: ' . Config::$siteType);

$drupal = new DrupalSite();
$moodle = new MoodleSite();

$drupal->maintMode(true);
$drupal->backup();
$drupal->maintMode(false);

$moodle->maintMode(true);
$moodle->backup();
$moodle->maintMode(false);

$json = [];
$json['messages'] = Log::get();

header('Content-type: application/json; charset=utf-8');
echo json_encode($json);
