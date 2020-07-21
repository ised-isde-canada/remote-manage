<?php

/**
 * Handle all the remote operations for a Moodle site.
 */

namespace RemoteManage\Moodle;

use RemoteManage\BaseSite;
use RemoteManage\Log;

class Site extends BaseSite
{
    public function __construct()
    {
        $this->siteType = 'moodle';
        $this->filedir = dirname(__FILE__);

        // Set the standard configuration parameters
        $this->cfg['dbhost'] = getenv('DBHOST');     // E.g. 'localhost' or 'db.isp.com' or IP.
        $this->cfg['dbuser'] = getenv('DBUSERNAME'); // Database username.
        $this->cfg['dbpass'] = getenv('DBPASSWORD'); // Database password.
        $this->cfg['dbname'] = getenv('DBNAME');     // Database name.
        $this->cfg['moodledata'] = getenv('MODOLE_DATA_DIR');
        $this->cfg['volumes'] = [$this->moodledata, $this->homedir];

        parent::__construct();
    }

    /**
     * Static function to detect if this is a Moodle site.
     * @return boolean If Moodle (true), otherwise not Moodle (false).
     */
    public static function detect()
    {
        return file_exists(getenv('HOME') . '/config.php');
    }

    /**
     * Take the site in or out of maintenance mode if not already in that mode.
     * @param boolean $maint Enable (true) or Disable (false) Maintenance Mode.
     */
    public function maintMode($maint=true)
    {
        parent::maintMode($maint);
        if ($maint) {
            if (!$this->inMaintMode) {
                // Enable maintenance mode.
                execmd('cp ' . $this->filedir . '/climaintenance.html .', $this->moodledata);
                // Purge cache.
                execmd('/usr/local/bin/php -f admin/cli/purge_caches.php', $homedir);
            }
        }
        else {
            if ($this->inMaintMode) {
                // Disable maintenance mode.
                exec('rm ' . $this->moodledata . '/climaintenance.html');
            }
        }
    }
}
