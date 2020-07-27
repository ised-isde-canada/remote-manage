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
        $this->siteDir = $this->cfg['homedir'];

        // Set the standard configuration parameters
        $this->cfg['dbhost'] = getenv('DB_HOST');     // E.g. 'localhost' or 'db.isp.com' or IP.
        $this->cfg['dbuser'] = getenv('DB_USERNAME'); // Database username.
        $this->cfg['dbpass'] = getenv('DB_PASSWORD'); // Database password.
        $this->cfg['dbname'] = getenv('DB_NAME');     // Database name.
        $this->cfg['moodledata'] = getenv('MOODLE_DATA_DIR');
        $this->volumes = [$this->cfg['moodledata'], $this->siteDir];
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
                SysCmd::exec('cp ' . dirname(__FILE__) . '/climaintenance.html .', $this->cfg['moodledata']);
                // Purge all cache (no need to backup temp files).
                SysCmd::exec('php -f admin/cli/purge_caches.php', $this->cfg['homedir']);
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit maintenance mode");
                // Purge all cache (in case we are doing a restore).
                SysCmd::exec('php -f admin/cli/purge_caches.php', $this->cfg['homedir']);
                // Disable maintenance mode.
                SysCmd::exec('rm ' . $this->cfg['moodledata'] . '/climaintenance.html');
                $this->inMaintMode = false;
            }
        }
    }
}
