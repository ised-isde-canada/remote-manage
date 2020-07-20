<?php

/**
 * Handle all the remote operations for a Moodle site.
 */

namespace RemoteManage\Moodle;

use RemoteManage\BaseSite;

class Site extends BaseSite
{
    public function __construct()
    {
        parent::__construct(); // Call the parent constructor first

        $this->siteType = 'moodle';
        $this->volumes = [];
    }

    /**
     * Static function to detect if this is a Moodle site.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return is_dir(getenv('HOME') . '/drush') ? true : false;
    }
}
