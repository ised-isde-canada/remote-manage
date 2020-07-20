<?php

/**
 * Base class to be extended by a site type.
 */

namespace RemoteManage;

abstract class BaseSite
{
    public    $siteType = 'unknown'; // The type of site. Use all lowercase
    public    $cfg = [];             // Configuration data
    public    $volumes = [];         // List of volumes (directories) to be backed up - use absolute path!
    public    $inMaintMode = false;  // Flag to indicate that the site is in maintenance mode
    protected $syscmd = null;        // System command object

    public function __construct()
    {
        $this->syscmd = new SysCmd();

        // Each app must do this in their constructor:
        // Set the standard configuration parameters
        // $this->cfg['homedir'] = getenv('HOME');
        // $this->cfg['dbhost'] = '';
        // $this->cfg['dbport'] = '';
        // $this->cfg['dbuser'] = '';
        // $this->cfg['dbpass'] = '';
        // $this->cfg['dbname'] = '';
        // $this->cfg['dbbackup'] = 'database.tar';
        // $this->cfg['tmpdir'] = '/tmp/' . $this->siteType . '-remote';
        // $this->cfg['volumes'] = ['/abs/path/to/dir'];
    }

    /**
     * Backup a site.
     * @return boolean
     */
    public function backup()
    {
        Log::msg("Backup process is running...");

        // Put site into maintenance mode
        $this->maintMode(true);

        $this->backupDatabase();
        $this->backupVolumes();
        $this->createZip();
        $this->copyToArchive();

        $this->cleanup();

        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function backupDatabase()
    {
        $db = new Postgres();

        try {
            $db->backup([
                'host' => $this->cfg['dbhost'],
                'port' => $this->cfg['dbport'],
                'user' => $this->cfg['dbuser'],
                'name' => $this->cfg['dbname'],
                'file' => $this->cfg['tmp'] . '/' . $this->cfg['dbbackup'],
            ]);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function backupVolumes()
    {
        // Tar the files in each volume directory
        foreach ($this->volumes as $volume) {
            $parentDir = dirname($volume);
            $backupDir = basename($volume);
            try {
                $this->syscmd->exec(sprintf('tar rf %s %s 2>&1',
                    $this->cfg['tmpdir'] . '/' . $volume . '-backup.tar',
                    $backupDir,
                ), $parentDir);
            }
            catch (\Exception $e) {
                $this->cleanup();
                return false;
            }
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function copyToArchive()
    {
        $s3 = new S3Cmd();

        try {
            $s3->put();
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function createZip()
    {
        try {
            $this->syscmd->exec(sprintf('gzip -f %s/%s 2>&1',
                $this->cfg['tmpdir'],
                $this->cfg['s3file']
            ), $this->cfg['tmpdir']);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     * Perform a cleanup of any temp files created.
     * Take the site out of maintenance mode.
     * @return boolean
     */
    public function cleanup()
    {
        // Remove the temporary directory
        $this->syscmd->exec('rm -rf ' . $this->cfg['tmpdir']);

        // Take site out of maintenance mode
        $this->maintMode(false);

        return true;
    }

    /**
     * Static function to detect what type of site this is.
     * Note: Each site type must override this method.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return 'unknown';
    }

    /**
     * Take the site in or out of maintenance mode.
     * @param boolean $maint
     */
    public function maintMode($maint=true)
    {
        if ($maint) {
            if (!$this->inMaintMode) {
                Log::msg("Enter site maintenance mode");
                $this->inMaintMode = true;
            }
        }
        else {
            if ($this->inMaintMode) {
                Log::msg("Exit site maintenance mode");
                $this->inMaintMode = false;
            }
        }
    }

    /**
     * Restore a site, using parameters provided in the POST and local configuration.
     * @return boolean
     */
    public function restore()
    {
        return false;
    }
}