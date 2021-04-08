<?php

/**
 * Helper functions are global in scope.
 */

use RemoteManage\Drupal\Site as DrupalSite;
use RemoteManage\Moodle\Site as MoodleSite;

/**
 * Get a Site instance. This will detect the type of site and return the appropriate class.
 * This function can be called any time to get the current site instance.
 * @return \RemoteManage\Drupal\Site|\RemoteManage\Moodle\Site|NULL
 */
function getSite()
{
    static $site = null;

    if (!$site && DrupalSite::detect()) {
        $site = new DrupalSite();
    }

    if (!$site && MoodleSite::detect()) {
        $site = new MoodleSite();
    }

    return $site;
}

/**
 * Formats number and adds units to provided size.
 *
 * @param $bytes   float Number of bytes.
 * @param $decimal integer Number of decimals in returned value.
 *
 * @return string  Number formatted with appropriate units.
 */
function formatBytes($bytes, $decimal = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $decimal) . ' ' . $units[$pow];
}
