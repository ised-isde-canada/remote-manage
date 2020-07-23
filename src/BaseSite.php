<?php

/**
 * Base class to be extended by a site type.
 */

namespace RemoteManage;

abstract class BaseSite
{
    public    $siteType = 'unknown';     // The type of site. Use all lowercase
    public    $appName = 'application';  // The application name for this site
    public    $appEnv = 'dev';           // Environment: dev, test, qa, prod
    public    $cfg = [];                 // Configuration data
    public    $volumes = [];             // List of volumes (directories) to be backed up - use absolute path!
    public    $inMaintMode = false;      // Flag to indicate that the site is in maintenance mode
    private   $backupTarFile = null;     // Filename of the backup tar file (created at backup time)
    private   $backupFiles = [];         // List of individual backup files which will be zipped up at the end

    public function __construct()
    {
        $this->cfg['homedir'] = getenv('HOME');
        $this->cfg['tmpdir'] = sys_get_temp_dir() . '/' . uniqid();
        $this->cfg['dbport'] = '5432';
        mkdir($this->cfg['tmpdir']);
    }

    /**
     * Backup a site.
     * Will abort if any part of the backup fails.
     * @return boolean Success (true), Failure (false).
     */
    public function backup()
    {
        Log::msg("Backup process is running...");

        // Use the current date and time to determine backup type as one of: D (daily), W (weekly), M (monthly).
        $backupType = 'D'; // Default to Daily
        if (date('j') == 1) { // First day of the month
            $backupType = 'M';
        }
        else if (date('w') == 0) { // Sunday
            $backupType = 'W';
        }

        // Set the name of the backup tar file.
        $this->backupTarFile = sprintf('%s-%s-%s-%s.tar',
            $this->appName,
            $this->appEnv,
            date('Y-m-d_H-i'),
            $backupType
        );

        // Put site into maintenance mode.
        $this->maintMode(true);

        // Fails if there is neither a database or files to be backed-up.
        $success = empty($this->cfg['dbname'] && empty($this->volumes));

        // Backup database, if any.
        if (!empty($this->cfg['dbname'])) {
            $success = $this->backupDatabase();
        }

        // Backup files, if any.
        if ($success && !empty($this->volumes)) {
            $success = $this->backupVolumes();
        }

        // Create GZIP file.
        if ($success) {
            $success = $this->createZip();
        }

        // Transfer GZIP file to S3.
        if ($success) {
            $success = $this->copyToArchive();
        }

        $this->cleanup();

        return $success;
    }

    /**
     *
     * @return boolean
     */
    protected function backupDatabase()
    {
        $db = new Postgres();

        $success = $db->backup([
            'host' => $this->cfg['dbhost'],
            'port' => $this->cfg['dbport'],
            'user' => $this->cfg['dbuser'],
            'pass' => $this->cfg['dbpass'],
            'name' => $this->cfg['dbname'],
            'file' => 'database.tar',
        ]);
        if (!$success) {
            Log::msg("Database backup failed!");
            $this->cleanup();
            return false;
        }

        // Add this file to the list
        $this->backupFiles[] = 'database.tar';

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
            Log::msg("Backup volume $volume");
            $parentDir = dirname($volume);
            $backupDir = basename($volume);
            $backupFile = "$backupDir-backup.tar";
            try {
                SysCmd::exec(sprintf('tar cf %s %s 2>&1',
                    $this->cfg['tmpdir'] . '/' . $backupFile,
                    $backupDir,
                ), $parentDir);
            }
            catch (\Exception $e) {
                $this->cleanup();
                return false;
            }
            // Add this file to the list
            $this->backupFiles[] = $backupFile;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function copyToArchive()
    {
        $pwd = getpwd();
        chdir($this->cfg['tmpdir']);
        $s3 = new S3Cmd();
        try {
            $s3->copy($this->backupTarFile);
        }
        catch (\Exception $e) {
            chdir($pwd);
            $this->cleanup();
            return false;
        }
        chdir($pwd);
        return true;
    }

    /**
     *
     * @return boolean
     */
    protected function createZip()
    {
        // Make sure we have some backup files
        if (empty($this->backupFiles)) {
            return false;
        }

        // Create a tar file containing all the backup files
        try {
            SysCmd::exec(sprintf('tar cf %s %s',
                $this->backupTarFile,
                join(' ', $this->backupFiles)
            ), $this->cfg['tmpdir']);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        // Now gzip it up
        try {
            SysCmd::exec(sprintf('gzip -f %s 2>&1',
                $this->backupTarFile
            ), $this->cfg['tmpdir']);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        // Update the name now that it's zipped
        $this->backupTarFile .= '.gz';

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
        SysCmd::exec('rm -rf ' . $this->cfg['tmpdir']);

        // Take site out of maintenance mode
        $this->maintMode(false);

        return true;
    }

    /**
     * Static function to detect what type of site this is.
     * Note: Each site type must override this method.
     * This would be an abstract function if PHP would allow it in combination with static.
     * @return string 'unknown'.
     */
    public static function detect()
    {
        return 'unknown';
    }

    /**
     * Take the site in or out of maintenance mode.
     * NOTE: The class which extends this base class must
     * define how maintMode is implemented. It should also
     * maintain the state of $this->inMaintMode.
     * @param boolean $maint
     */
    abstract public function maintMode($maint=true);

    /**
     * Restore a site, using parameters provided in the POST and local configuration.
     * @return boolean
     */
    public function restore()
    {
        return false;
    }
}