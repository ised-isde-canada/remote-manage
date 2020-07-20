<?php

/**
 * Handle all the remote operations for a Drupal site.
 */

namespace RemoteManage\Drupal;

use RemoteManage\BaseSite;
use RemoteManage\Log;

class Site extends BaseSite
{
    public function __construct()
    {
        parent::__construct(); // Call the parent constructor first

        $this->siteType = 'drupal';
        $this->volumes = ['/opt/app-root/src/html/sites'];
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';
    }

    /**
     * Static function to detect if this is a Drupal site.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return is_dir($this->cfg['homedir'] . '/drush') ? true : false;
    }

    /**
     * Take the site in or out of maintenance mode.
     * @param boolean $maint
     */
    public function maintMode($maint=true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Enter Drupal maintenance mode");
                $this->syscmd->exec($this->cfg['drush'] . ' state:set system.maintenance_mode 1 --input-format=integer');
                $this->syscmd->exec($this->cfg['drush'] . ' cr');
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit Drupal maintenance mode");
                $this->syscmd->exec($this->cfg['drush'] . ' state:set system.maintenance_mode 0 --input-format=integer');
                $this->syscmd->exec($this->cfg['drush'] . ' cr');
                $this->inMaintMode = false;
            }
        }
    }
}
