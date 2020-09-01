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
        $this->cfg['moodledata'] = getenv('MOODLE_DATA_DIR'); // Persistent volume.

        // Build list of volumes.
        $this->volumes = [$this->cfg['moodledata'], $this->siteDir];

        // Check for existing Moodle site installation
        $this->siteExists = $this->isInstalled();

        if ($this->siteExists) {
            // Get current maintenance mode status.
            SysCmd::exec('php -f admin/cli/maintenance.php', $this->siteDir, true);
            $this->inMaintMode = (Log::getlast(2, 'msg') == 'Status: enabled');
        }
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
     * Determine if a Moodle site is installed by locating the .htaccess file in moodledata.
     * @return boolean If Moodle installation exists (true), or not (false).
     */
    public function isInstalled()
    {
        return file_exists($this->cfg['moodledata'] . '/.htaccess');
    }

    /**
     * Delete files in moodledata without deleting directory itself.
     * @return boolean If successful (true - i.e. only "." and ".."), or failed (false).
     */
    public function deleteFiles()
    {
        Log::msg("Deleting moodledata files.");
        SysCmd::exec('shopt -s dotglob && rm -rf ' . $this->cfg['moodledata'] . '/*');
        return (count(scandir($this->cfg['moodledata'])) == 2);
    }

    /**
     * Take the site in or out of maintenance mode if not already in that mode.
     * @param boolean $maint Enable (true) or Disable (false) Maintenance Mode.
     */
    public function maintMode($maint = true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Entering maintenance mode");
                // Enable maintenance mode.
                $success = SysCmd::exec('cp ' . dirname(__FILE__) . '/climaintenance.html .', $this->cfg['moodledata']);
                // Purge all cache (no need to backup temp files).
                SysCmd::exec('php -f admin/cli/purge_caches.php', $this->cfg['homedir']);
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exiting maintenance mode");
                // Purge all cache (in case we are doing a restore).
                SysCmd::exec('php -f admin/cli/purge_caches.php', $this->cfg['homedir']);
                // Disable maintenance mode.
                $success = SysCmd::exec('php -f admin/cli/maintenance.php -- --disable', $this->cfg['homedir']);
                $this->inMaintMode = false;
            }
        }
        return ($success == 0);
    }
}
