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
        $this->sitesDir = $this->cfg['homedir'] . '/html/sites';

        // Get the database connection parameters from the Drupal settings file if it exists
        if (file_exists("$this->sitesDir/default/settings.php")) {
            require "$this->sitesDir/default/settings.php";
            $this->cfg['dbhost'] = $databases['default']['default']['host'];
            $this->cfg['dbport'] = $databases['default']['default']['port'];
            $this->cfg['dbuser'] = $databases['default']['default']['username'];
            $this->cfg['dbpass'] = $databases['default']['default']['password'];
            $this->cfg['dbname'] = $databases['default']['default']['database'];
        }

        // Define the volumes for backup and restore (must use absolute path)
        $this->volumes = [$this->sitesDir];

        // Set app-specific configuration parameters
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';
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
     * Take the site in or out of maintenance mode if not already in that mode.
     * @param boolean $maint Enable (true) or Disable (false) Maintenance Mode.
     */
    public function maintMode($maint=true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Enter Drupal maintenance mode");
                SysCmd::exec($this->cfg['drush'] . ' state:set system.maintenance_mode 1 --input-format=integer');
                SysCmd::exec($this->cfg['drush'] . ' cr');
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit Drupal maintenance mode");
                SysCmd::exec($this->cfg['drush'] . ' state:set system.maintenance_mode 0 --input-format=integer');
                SysCmd::exec($this->cfg['drush'] . ' cr');
                $this->inMaintMode = false;
            }
        }
    }
}
