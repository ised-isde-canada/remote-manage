<?php

/**
 * Handle all the remote operations for a Moodle site.
 */

namespace RemoteManage\Moodle;

use RemoteManage\Config;
use RemoteManage\Log;

class Site
{
    public $siteType = 'moodle';

    /**
     * Backup a Moodle site, using parameters provided in the POST and local configuration.
     */
    public function backup()
    {
        Log::msg("Moodle backup is running...");
        $this->maintMode(true);
        $this->maintMode(false);
    }

    /**
     * Perform a cleanup on any temporary files that may have been created during backup or restore.
     */
    public function cleanup()
    {
        // Remove any files that were created

        // Take site out of maintenance mode
        $this->maintMode(false);
    }

    /**
     * Static function to detect if this is a Moodle site.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return file_exists(Config::$homeDir . '/config.php') ? true : false;
    }

    /**
     * Get the configuration for a Moodle site.
     */
    public function getConfig()
    {

    }

    /**
     * Take the site in or out of maintenance mode.
     * @param boolean $maint
     */
    public function maintMode($maint=true)
    {
        if ($maint) {
            Log::msg("Enter Moodle maint mode");
        }
        else {
            Log::msg("Exit Moodle maint mode");
        }
    }

    /**
     * Restore a Moodle site, using parameters provided in the POST and local configuration.
     */
    public function restore()
    {

    }
}