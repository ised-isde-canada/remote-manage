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
        $this->sitesDir = $this->cfg['homedir'] . '/html/sites';

        // Set the database configuration parameters.
        $this->cfg['dbhost'] = getenv('DB_HOST');
        $this->cfg['dbport'] = getenv('DB_PORT');
        $this->cfg['dbuser'] = getenv('DB_USERNAME');
        $this->cfg['dbpass'] = getenv('DB_PASSWORD');
        $this->cfg['dbname'] = getenv('DB_NAME');

        // Define the volumes for backup and restore (must use absolute path).
        $this->volumes = [
            $this->dataDir,
            $this->sitesDir,
        ];

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
            if (preg_match('/^\s+Database\s+:\s+Connected/', $str)) {
                Log::msg("Database connected.");
                return true;
            }
        }
        Log::msg("Database not connected.");
        return false;
    }


    /**
     * Delete files within persistent volumes, without deleting the directory itself.
     *
     * @return boolean Successful (true), failed (false).
     */
    public function deleteFiles()
    {
        foreach ($this->volumes as $volume) {
            Log::msg("Deleting files in $volume.");
            try {
                SysCmd::exec(sprintf(
                    'rm -rf %s',
                    $volume
                ));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return false;
            }

            try {
                SysCmd::exec(sprintf(
                    'mkdir %s',
                    $volume
                ));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * Get current maintenance mode status of site.
     *
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $status Maintenance Mode (true), Not Maintenance Mode (false)
     */
    public function getMaintMode($restoreStatus = false)
    {
        $output = SysCmd::exec($this->cfg['drush'] . ' ev \'echo (integer)\Drupal::state()->get("system.maintenance_mode");\'', $this->cfg['homedir'], true, true);
        if ($output[0]) {
            $mode = true;
            Log::msg("Maintenance mode is enabled.");
        } else {
            $mode = false;
            Log::msg("Maintenance mode is disabled.");
        }
        return $mode;
    }

    /**
     * Take the site in or out of maintenance mode.
     * NOTE: The class which extends this base class must define how maintMode is implemented.
     *
     * @param boolean $maint In maintenance mode (true), otherwise false.
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $success Successful (true), failed (false).
     */
    public function maintMode($maint = true, $restoreStatus = false)
    {
        $success = 0; // Default to success.

        // Get current maintenance mode status.
        $inMaintMode = $this->getMaintMode($restoreStatus);

        if ($maint) {
            if (!$inMaintMode) {
                Log::msg("Enter Drupal maintenance mode");
                // Enable maintenance mode.
                $success = SysCmd::exec($this->cfg['drush'] . ' ev \'\Drupal::state()->set("system.maintenance_mode", true);\'', $this->cfg['homedir'], true, false);
                if (function_exists('opcache_reset')) {
                  // Prevent APC from returning a cached maintenance mode.
                    opcache_reset();
                }
                // Rebuild cache (no need to backup temp files).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
            }
        } else {
            if ($inMaintMode) {
                Log::msg("Exit Drupal maintenance mode");
                // Rebuild cache (in case we are doing a restore).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
                // Disable maintenance mode.
                $success = SysCmd::exec($this->cfg['drush'] . ' ev \'\Drupal::state()->set("system.maintenance_mode", false);\'', $this->cfg['homedir'], true, false);
                if (function_exists('opcache_reset')) {
                  // Prevent APC from returning a cached maintenance mode.
                    opcache_reset();
                }
            }
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
