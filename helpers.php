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

