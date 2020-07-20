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
        $this->siteType = 'drupal';

        // Set the standard configuration parameters
        $this->cfg['homedir'] = getenv('HOME');
        $this->cfg['dbhost'] = '';
        $this->cfg['dbport'] = '';
        $this->cfg['dbuser'] = '';
        $this->cfg['dbpass'] = '';
        $this->cfg['dbname'] = '';
        $this->cfg['dbbackup'] = 'database.tar';
        $this->cfg['s3bucket'] = '';
        $this->cfg['tmpdir'] = '/tmp/' . $this->siteType . '-remote';
        $this->cfg['volumes'] = ['/opt/app-root/src/html/sites'];

        // Set app-specific configuration parameters
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';

        parent::__construct();
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
