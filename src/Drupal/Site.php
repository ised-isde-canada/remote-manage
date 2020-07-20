<?php

/**
 * Handle all the remote operations for a Drupal site.
 */

namespace RemoteManage\Drupal;

use RemoteManage\Config;
use RemoteManage\Log;

class Site
{
    public $siteType = 'drupal';

    /**
     * Backup a Drupal site, using parameters provided in the POST and local configuration.
     */
    public function backup()
    {
        Log::msg("Drupal backup is running...");

        // Put site into maintenance mode
        $this->maintMode(true);

        if (0) {
            // Dump database using tar format (-F t).
            try {
                execmd('pg_dump -U ' . $cfg['dbuser'] . ' -h ' . $cfg['dbhost'] . ' -p ' . $cfg['dbport'] . ' -x -F t ' . $cfg['dbname'] . ' > ' . $cfg['tmpdir'] . '/' . $cfg['dbname'] . '.sql 2>&1');
            } catch (\Exception $e) {
                $this->cleanup();
            }

            // Tar the files in the PV directory.
            try {
                execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['databackup'] . '.tar ' . $cfg['datadir'] . ' 2>&1', $pvdir);
            } catch (\Exception $e) {
                $this->cleanup();
            }

            // Tar the files in the application directory.
            try {
                execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['appname'] . '.tar src 2>&1', $homedir . '/..');
            } catch (\Exception $e) {
                $this->cleanup();
            }

            // GZ compress all of the temporary files so far.
            try {
                execmd('gzip -f ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . ' 2>&1', $cfg['tmpdir']);
            } catch (\Exception $e) {
                $this->cleanup();
            }

            // Copy the compressed gz file to S3.
            try {
                execmd('s3cmd -q --mime-type=application/x-gzip put ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . '.gz s3://$bucket/' . $cfg['s3file'] . '.gz 2>&1');
            } catch (\Exception $e) {
                $this->cleanup();
            }
        }
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
     * Static function to detect if this is a Drupal site.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return is_dir(Config::$homeDir . '/drush') ? true : false;
    }

    /**
     * Get the configuration for a Drupal site.
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
            Log::msg("Enter Drupal maint mode");
        }
        else {
            Log::msg("Exit Drupal maint mode");
        }
    }

    /**
     * Restore a Drupal site, using parameters provided in the POST and local configuration.
     */
    public function restore()
    {

    }
}