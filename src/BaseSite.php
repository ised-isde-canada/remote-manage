<?php

/**
 * Base class to be extended by a site type.
 */

namespace RemoteManage;

abstract class BaseSite
{
    public    $siteType = 'unknown';     // The type of site. Use all lowercase
    public    $appEnv = 'dev';           // Environment: dev, test, qa, prod
    public    $cfg = [];                 // Configuration data
    public    $volumes = [];             // List of volumes (directories) to be backed up - use absolute path!
    public    $siteExists = null;         // Flag to indicate if the site already exists or will be newly created
    public    $inMaintMode = false;      // Flag to indicate that the site is in maintenance mode
    private   $backupTarFile = null;     // Filename of the backup tar file (created at backup time)
    private   $backupFiles = [];         // List of individual backup files which will be zipped up at the end
    private   $restoreTarFile = null;    // Filename of the backup tar file received by restore POST request

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
     *
     * @param $startTime integer Time script was started.
     *
     * @return boolean Success (true), Failure (false).
     */
    public function backup($startTime)
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
        $this->backupTarFile = sprintf('%s-%s-%s.tar',
            $this->appEnv,
            date('Y-m-d_H-i'),
            $backupType
        );

        // Put site into maintenance mode.
        if ($this->siteExists) {
            $this->maintMode(true);
        }
        
        // Fails if there is neither a database or directories to be backed-up.
        $success = empty($this->cfg['dbname'] && empty($this->volumes));

        // Backup database, if any.
        if (!empty($this->cfg['dbname'])) {
            $success = $this->backupDatabase();
        }

        // Backup files, if any.
        if ($success && !empty($this->volumes)) {
            $success = $this->backupVolumes();
        }

        // No need to keep the site in maintenance mode from this point on.
        $this->maintMode(false);

        // Display elapsed time..
        $endTime = microtime(true);
        Log::msg('Elapsed execution time is ' . date('H:i:s', $endTime - $startTime) . '.');

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
     * Backup the Database.
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
                    $backupDir
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
     * Copy gzip file to S3.
     * 
     * @return boolean
     */
    protected function copyToArchive()
    {
        $path = $this->cfg['tmpdir'] . '/' . $this->backupTarFile;
        $s3 = new S3Cmd();
        try {
            $s3->copy($this->backupTarFile, $path);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }
       
        return true;
    }

    /**
     * Create a compressed .tar.gz file from all the backup files.
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

        if ($this->inMaintMode) {
            // Take site out of maintenance mode
            $this->maintMode(false);
        }

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
     *
     * @param boolean $maint In maintenance mode (true), otherwise false.
     * 
     * @return boolean $success Successful (true), failed (false).
     */
    abstract public function maintMode($maint=true);

    /**
     * Restore a site, using parameters provided in the POST and local configuration.
     *
     * @return boolean
     */
    public function restore($startTime, $backupFile)
    {
        Log::msg("Restore process is running...");
        $this->restoreTarFile = $backupFile;
        
        // Put site into maintenance mode.
        if ($this->siteExists) {
            $this->maintMode(true);
        }
        

        // Fails if there is neither a database or directories to be restored.
        $success = empty($this->cfg['dbname'] && empty($this->volumes));

        // Drop database tables
        if ($success) {
            $success = $this->dropTables();
        }

        // Get selected backup archive from S3 bucket.
        if ($success) {
            $success = $this->getBackupArchive();
        }

        // Unzip backup archive.
        if ($success) {
            $success = $this->unzipArchive();
        }
        
        // Restore database.
        if ($success && !empty($this->cfg['dbname'])) {
            $success = $this->restoreDatabase();
        }
        
        // Restore files, if any.
        if ($success && !empty($this->volumes)) {
            $success = $this->restoreVolumes();
        }
        
        // Display elapsed time..
        $endTime = microtime(true);
        Log::msg('Elapsed execution time is ' . date('H:i:s', $endTime - $startTime) . '.');

        $this->cleanup();

        return $success;
    }

    /**
     * Drop all of the tables in the database.
     *
     * @return boolean
     */
    public function dropTables()
    {
        $db = new Postgres();

        $success = $db->dropTables([
            'host' => $this->cfg['dbhost'],
            'port' => $this->cfg['dbport'],
            'user' => $this->cfg['dbuser'],
            'pass' => $this->cfg['dbpass'], 
            'name' => $this->cfg['dbname'],  
        ]);
        if (!$success) {
            Log::msg('Failed to drop database tables!');
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     * Retrieve tar.gz backup file.
     *
     * @return boolean
     */
    protected function getBackupArchive()
    {
        $path = $this->cfg['tmpdir'] . '/' . $this->restoreTarFile;
        $s3 = new S3Cmd();
        try {
            $s3->getFile($this->restoreTarFile, $path);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     * Decompress gz archive file.
     *
     * @return boolean
     */
    protected function unzipArchive()
    {
        try {
            SysCmd::exec(sprintf('gunzip -f %s 2>&1',
                $this->restoreTarFile
            ), $this->cfg['tmpdir']);
        }
        catch (\Exception $e) {
            $this->cleanup();
            return false;
        }
        
        $this->restoreTarFile = preg_replace('/\.gz$/', '', $this->restoreTarFile);

        try {
            SysCmd::exec(sprintf('tar xf %s',
                $this->restoreTarFile
            ), $this->cfg['tmpdir']);
        } catch (\Exception $e) {
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     * Restore the database.
     *
     * @return boolean
     */
    protected function restoreDatabase()
    {
        $db = new Postgres();

        $success = $db->restore([
            'host' => $this->cfg['dbhost'],
            'port' => $this->cfg['dbport'],
            'user' => $this->cfg['dbuser'],
            'pass' => $this->cfg['dbpass'],
            'name' => $this->cfg['dbname'],
            'file' => 'database.tar',
        ]);
        if (!$success) {
            Log::msg("Database restore failed!");
            $this->cleanup();
            return false;
        }

        return true;
    }

    /**
     * Restore files.
     *
     * @return boolean
     */
    protected function restoreVolumes()
    {
        // Restore the files in each volume directory
        foreach ($this->volumes as $volume) {
            Log::msg("Restore volume $volume");
            $parentDir = dirname($volume);
            $backupDir = basename($volume);
            $backupFile = "$backupDir-backup.tar";
            try {
                SysCmd::exec(sprintf('tar xf %s',
                    $backupFile
                ), $this->cfg['tmpdir']);
            }
            catch (\Exception $e) {
                $this->cleanup();
                return false;
            }

            try {
                SysCmd::exec(sprintf('cp -Rf %s %s',
                    $backupDir . '/*',
                    $volume . '/'
                ), $this->cfg['tmpdir']);
            }
            catch (\Exception $e) {
                $this->cleanup();
                return false;
            } 
        }
        return true;
    }
}