<?php

/**
 * Handle all the remote operations for a Moodle site.
 */

namespace RemoteManage\Moodle;

use RemoteManage\BaseSite;
use RemoteManage\Log;
use RemoteManage\SysCmd;

class Site extends BaseSite
{
    public function __construct()
    {
        parent::__construct();

        $this->siteType = 'moodle';

        // Set the standard configuration parameters
        $this->cfg['dbhost'] = getenv('DB_HOST');     // E.g. 'localhost' or 'db.isp.com' or IP.
        $this->cfg['dbuser'] = getenv('DB_USERNAME'); // Database username.
        $this->cfg['dbpass'] = getenv('DB_PASSWORD'); // Database password.
        $this->cfg['dbname'] = getenv('DB_NAME');     // Database name.
        $this->cfg['volumes'] = [getenv('MODOLE_DATA_DIR'), $this->homedir];
    }

    /**
     * Static function to detect if this is a Moodle site.
     * @return boolean If Moodle (true), otherwise not Moodle (false).
     */
    public static function detect()
    {
        return file_exists(getenv('HOME') . '/lang/en/moodle.php');
    }

    /**
     * Take the site in or out of maintenance mode if not already in that mode.
     * @param boolean $maint Enable (true) or Disable (false) Maintenance Mode.
     */
    public function maintMode($maint=true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Enter maintenance mode");
                // Enable maintenance mode.
                SysCmd::exec('cp ' . dirname(__FILE__) . '/climaintenance.html .', $this->moodledata);
                // Purge cache.
                SysCmd::exec('/usr/local/bin/php -f admin/cli/purge_caches.php', $homedir);
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit maintenance mode");
                // Disable maintenance mode.
                SysCmd::exec('rm ' . $this->moodledata . '/climaintenance.html');
                $this->inMaintMode = false;
            }
        }
    }
}
