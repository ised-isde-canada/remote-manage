<?php

/**
 * Helper functions are global in scope.
 */

use RemoteManage\Drupal\Site as DrupalSite;
use RemoteManage\Moodle\Site as MoodleSite;

/**
 * Get a Site instance. This will detect the type of site and return the appropriate class.
 * @return \RemoteManage\Drupal\Site|\RemoteManage\Moodle\Site|NULL
 */
function getSite()
{
    if (DrupalSite::detect()) {
        return new DrupalSite();
    }

    if (MoodleSite::detect()) {
        return new MoodleSite();
    }

    return null;
}
