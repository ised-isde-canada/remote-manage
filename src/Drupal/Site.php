<?php

/**
 * Handle all the remote operations for a Drupal site.
 */

namespace RemoteManage\Drupal;

use RemoteManage\BaseSite;
use RemoteManage\Log;
use RemoteManage\SysCmd;

class Site extends BaseSite
{
    public function __construct()
    {
        parent::__construct();

        $this->siteType = 'drupal';
        $this->sitesDir = $this->cfg['homedir'] . '/data/sites';

        // Set the database configuration parameters.
        $this->cfg['dbhost'] = getenv('DB_HOST');
        $this->cfg['dbport'] = getenv('DB_PORT');
        $this->cfg['dbuser'] = getenv('DB_USERNAME');
        $this->cfg['dbpass'] = getenv('DB_PASSWORD');
        $this->cfg['dbname'] = getenv('DB_NAME');

        // Define the volumes for backup and restore (must use absolute path).
        $this->volumes = [$this->sitesDir];

        // Set app-specific configuration parameters.
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';

        // Check for existing Drupal site installation.
        $this->siteExists = $this->isInstalled();

        if ($this->siteExists) {
            // Get current maintenance mode status.
            $this->inMaintMode = SysCmd::exec($this->cfg['drush'] . ' state:get system.maintenance_mode', false, true);
        }
    }

    /**
     * Static function to detect if this is a Drupal site.
     * @return boolean If Drupal (true), otherwise not Drupal (false).
     */
    public static function detect()
    {
        return is_dir(getenv('HOME') . '/drush') ? true : false;
    }

    /**
     * Determine if a Drupal site is installed by locating the settings.php file.
     * @return boolean If Drupal installation exists (true), or not (false).
     */
    public function isInstalled()
    {
        return file_exists($this->sitesDir . '/settings.php');
    }

    /**
     * Take the site in or out of maintenance mode if not already in that mode.
     * @param boolean $maint Enable (true) or Disable (false) Maintenance Mode.
     */
    public function maintMode($maint = true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Enter Drupal maintenance mode");
                // Enable maintenance mode.
                SysCmd::exec($this->cfg['drush'] . ' state:set system.maintenance_mode 1 --input-format=integer', $this->cfg['homedir']);
                // Rebuild cache (no need to backup temp files).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit Drupal maintenance mode");
                // Rebuild cache (in case we are doing a restore).
                SysCmd::exec($this->cfg['drush'] . ' cr', $this->cfg['homedir']);
                // Disable maintenance mode.
                SysCmd::exec($this->cfg['drush'] . ' state:set system.maintenance_mode 0 --input-format=integer', $this->cfg['homedir']);
                $this->inMaintMode = false;
            }
        }
    }
}
