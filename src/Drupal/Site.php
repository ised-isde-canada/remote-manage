<?php

/**
 * Handle all the remote operations for a Drupal site.
 */

namespace RemoteManage\Drupal;

use RemoteManage\Log;
use RemoteManage\SysCmd;

/* NOTE, THIS VERSION IS HALF-BAKED !! */

class Site // extends base class
{
    public $siteType = 'drupal';
    public $cfg;
    public $volumes = [];

    public function __construct()
    {
        // init $this->cfg
        $this->volumes = ['src/html/sites', 'src'];
    }

    /**
     * Backup a Drupal site, using parameters provided in the POST and local configuration.
     */
    public function backup()
    {
        Log::msg("Drupal backup is running...");

        $syscmd = new SysCmd();

        // Put site into maintenance mode
        $this->maintMode(true);

        if (0) {
            // Dump database using tar format (-F t).
            try {
                $syscmd->exec('pg_dump -U ' . $this->cfg['dbuser'] . ' -h ' . $cfg['dbhost'] . ' -p ' . $cfg['dbport'] . ' -x -F t ' . $cfg['dbname'] . ' > ' . $cfg['tmpdir'] . '/' . $cfg['dbname'] . '.sql 2>&1');
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }

            $this->backupVolumes();

            // Tar the files in the PV directory.
            try {
                $syscmd->exec('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['databackup'] . '.tar ' . $cfg['datadir'] . ' 2>&1', $pvdir);
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }

            // Tar the files in the application directory.
            try {
                $syscmd->exec('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['appname'] . '.tar src 2>&1', $homedir . '/..');
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }

            // GZ compress all of the temporary files so far.
            try {
                $syscmd->exec('gzip -f ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . ' 2>&1', $cfg['tmpdir']);
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }

            // Copy the compressed gz file to S3.
            try {
                $syscmd->exec('s3cmd -q --mime-type=application/x-gzip put ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . '.gz s3://$bucket/' . $cfg['s3file'] . '.gz 2>&1');
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }
        }

        $this->cleanup();
        return true;
    }

    protected function backupVolumes()
    {
        $syscmd = new SysCmd();

        foreach ($this->volumes as $volume) {
            // Tar the files in the PV directory.
            try {
                $syscmd->exec('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['databackup'] . '.tar ' . $cfg['datadir'] . ' 2>&1', $pvdir);
            } catch (\Exception $e) {
                $this->cleanup();
                return false;
            }
        }
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
        return is_dir(getenv('HOME') . '/drush') ? true : false;
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