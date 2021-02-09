<?php

/**
 * Handle all the remote operations for a Drupal site.
 */

namespace RemoteManage\Drupal;

use RemoteManage\BaseSite;
use RemoteManage\Log;
use RemoteManage\SysCmd;
use RemoteManage\Drush;

class Site extends BaseSite
{
    public function __construct()
    {
        parent::__construct();

        $this->siteType = 'drupal';
        $this->dataDir = $this->cfg['homedir'] . '/data';
        $this->sitesDir = $this->dataDir . '/sites/default';

        // Set the database configuration parameters.
        $this->cfg['dbhost'] = getenv('DB_HOST');
        $this->cfg['dbport'] = getenv('DB_PORT');
        $this->cfg['dbuser'] = getenv('DB_USERNAME');
        $this->cfg['dbpass'] = getenv('DB_PASSWORD');
        $this->cfg['dbname'] = getenv('DB_NAME');

        // Define the volumes for backup and restore (must use absolute path).
        $this->volumes = [$this->dataDir];

        // Set app-specific configuration parameters.
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';

        // Check for existing Drupal site installation.
        $this->siteExists = $this->isInstalled();
    }

    /**
     * Static function to detect if this is a Drupal site.
     * @return boolean If Drupal (true), otherwise not Drupal (false).
     */
    public static function detect()
    {
        // Just check if we have ~/html/core/modules directory
        return is_dir(getenv('HOME') . '/html/core/modules') ? true : false;
    }

    /**
     * Determine if a Drupal site is installed by determining if a database is connected.
     * @return boolean If Drupal installation exists (true), or not (false).
     */
    public function isInstalled()
    {
        // Check drush status for Database : Connected
        $status = SysCmd::exec($this->cfg['drush'] . ' status', $this->cfg['homedir'], true, true);

        foreach ($status as $str) {
            if (preg_match ('/^\s+Database\s+:\s+Connected/', $str)){
                Log::msg("Database connected.");
                return true;
            }
        }
        Log::msg("Database not connected.");
        return false;
    }


    /**
     * Delete files within persistent volumes, without deleting
     * the directory itself.
     *
     * @return boolean Successful (true), failed (false).
     */
    public function deleteFiles()
    {
        foreach ($this->volumes as $volume) {
            Log::msg("Deleting files in $volume.");
            try {
                SysCmd::exec(sprintf('rm -rf %s',
                    $volume
                ));
            }
            catch (\Exception $e) {
                Log::error($e->getMessage());
                return false;
            }

            try {
                SysCmd::exec(sprintf('mkdir %s',
                    $volume
                ));
            }
            catch (\Exception $e) {
                Log::error($e->getMessage());
                return false;
            }
        }
        return true;
    }


    public function hexToStr($hex) {
      $string='';
      for ($i=0; $i < strlen($hex)-1; $i+=2){
        $string .= chr(hexdec($hex[$i].$hex[$i+1]));
      }
      return $string;
    }


    /**
     * Get current maintenance mode status of site.
     *
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $status Maintenance Mode (true), Not Maintenance Mode (false)
     */
    public function getMaintMode($restoreStatus = false) {
        $output = SysCmd::exec($this->cfg['drush'] . ' sql-query "select value from key_value where name like \'%maintenance_mode%\'"', $this->cfg['homedir'], true, true);
        $string_output = $output[0];
        // value column in key_value table is a serialized variable.
        // unserialize serialize boolean to get the maint mode.
        // Or , do this instead.
        if ($string_output == "b:1;" || unserialize($string_output)) {
          $mode = true;
          Log::msg("Maintenance mode is enabled.");
        }
        else {
          $mode = false;
          Log::msg("Maintenance mode is disabled.");
        }
        if ($restoreStatus) $this->restoreMaintMode = $mode;
        return $mode;
    }

    /**
     * Take the site in or out of maintenance mode.
     * NOTE: The class which extends this base class must
     * define how maintMode is implemented.
     *
     * @param boolean $maint In maintenance mode (true), otherwise false.
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $success Successful (true), failed (false).
     */
    public function maintMode($maint = true, $restoreStatus = false)
    {
        $success = -1;

        // Get current maintenance mode status.
        $inMaintMode = $this->getMaintMode($restoreStatus);

        if ($maint) {
            if (!$inMaintMode) {
                Log::msg("Enter Drupal maintenance mode");
                // Enable maintenance mode.
                $success = SysCmd::exec($this->cfg['drush'] . ' sql-query "UPDATE key_value SET value = \'i:1;\' WHERE name LIKE \'%maintenance_mode%\'"', $this->cfg['homedir']);
                // Rebuild cache (no need to backup temp files).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
            }
        }
        else {
// The flag for $inMaintMode does not seem to be accurate.
//            if ($inMaintMode) {
                Log::msg("Exit Drupal maintenance mode");
                // Rebuild cache (in case we are doing a restore).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
                // Disable maintenance mode.
                $success = SysCmd::exec($this->cfg['drush'] . ' sql-query "UPDATE key_value SET value = \'i:0;\' WHERE name LIKE \'%maintenance_mode%\'"', $this->cfg['homedir']);
//            }
        }
        return ($success == 0);
    }

    /**
     * Project Module Listing.
     * Results will be added to data for output.
     *
     * @return bool success
     */
    public function pmlist()
    {
        $drush = new Drush();
        Log::data('modules', $drush->pmlist());
        return true;
    }
}
