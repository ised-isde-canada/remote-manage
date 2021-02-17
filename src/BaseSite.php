<?php
/**
 * Base class to be extended by a site type.
 *
 * @category BaseSite
 * @package  Remote-manage
 * @author   Duncan Sutter <dsutter@qualivera.com>
 * @author   Samantha Tripp <samantha.tripp@canada.ca>
 * @author   Michael Milette <michael.milette@tngconsulting.ca>
 * @license  Copyright 2020-2021. MIT License - <https://opensource.org/licenses/MIT>
 * @link     https://github.com/ised-isde-canada/remote-manage
 * PHP version 7
 */

namespace RemoteManage;

use Exception;

/**
 * BaseSite class.
 *
 * @category BaseSite
 * @package  Remote-manage
 * @author   Duncan Sutter <dsutter@qualivera.com>
 * @author   Samantha Tripp <samantha.tripp@canada.ca>
 * @author   Michael Milette <michael.milette@tngconsulting.ca>
 * @license  Copyright 2020-2021. MIT License - <https://opensource.org/licenses/MIT>
 * @link     https://github.com/ised-isde-canada/remote-manage
 */
abstract class BaseSite
{
    public    $siteType = 'unknown';     // The type of site. Use all lowercase.
    public    $appEnv = 'dev';           // Environment: dev, test, qa, prod.
    public    $cfg = [];                 // Configuration data.
    public    $volumes = [];             // List of volumes (directories) to be backed up - use absolute path!
    public    $siteExists = null;        // Flag to indicate if the site already exists or will be newly created.
    public    $initialMaintMode = null;  // Initial maintenance mode before this script started.
    private   $backupTarFile = null;     // Filename of the backup tar file (created at backup time).
    private   $backupFiles = [];         // List of individual backup files which will be zipped up at the end.
    private   $backupType = 'D';         // Type of backup to perform: D (daily), W (weekly), M (monthly).
    private   $backupDir = ['D' => 'daily', 'W' => 'weekly', 'M' => 'monthly'];
    private   $restoreArchive = null;    // S3 path of archive file (e.g. starts with daily/ ) as requested.
    private   $restoreTarFile = null;    // Actual filename of the archive tar file.

    /**
     * Initializing configuration settings.
     */
    public function __construct()
    {
        $this->cfg['homedir'] = getenv('HOME');
        $this->cfg['tmpdir'] = (empty(getenv('RM_TEMP')) ? sys_get_temp_dir() : $getenv('RM_TEMP')) . '/_rm_' . uniqid();
        $this->cfg['dbport'] = '5432';
        mkdir($this->cfg['tmpdir']);
    }

    /**
     * Backup a site.
     * Will abort if any part of the backup fails.
     *
     * @return boolean Success (true), Failure (false).
     */
    public function backup()
    {
        // Check to make sure we have S3 credentials available
        if (!S3Cmd::checkCredentials()) {
            return false;
        }

        Log::msg("Begin backup process...");

        // Use the current date and time to determine backup type as one of: D (daily), W (weekly), M (monthly).
        if (date('j') == 1) { // First day of the month
            $this->backupType = 'M';
        }
        else if (date('w') == 0) { // Sunday
            $this->backupType = 'W';
        }

        // Set the name of the backup ZIP file.
        $this->backupTarFile = sprintf('%s@%s@%s.zip',
            $this->appEnv,
            date('Y-m-d@H-i'),
            $this->backupType
        );

        // Fails if there is neither a database or directories to be backed-up.
        $success = !(empty($this->cfg['dbname']) && empty($this->volumes));

        // Put site into maintenance mode - preserve original status
        if ($success && $this->siteExists) {
            $this->maintMode(true, true);

            // Allow any executing cronjobs to bleed out before we begin.
            // The following calculates the minimum required delay based on the starting point within the current minute.
            // The delay should match the value of the terminationGracePeriodSeconds of your cronjob's settings.
            $now = time() % 60;
            if ($this->siteType == 'moodle') {
                // Delay is up to 300 seconds (5 minutes).
                $delay = 300;
            // Note: As of Moodle 3.10, you can query the status of Moodle scheduler thereby potentially reducing required delay.
            } else { // Drupal and others.
                // Delay is up to 35 seconds. This may need to be increased if you start having long cronjobs.
                $delay = 35;
            }
            $seconds = ($now > $delay) ? 0 : $delay - $now;
            Log::msg("Waiting for cron to finish. Delaying backup by $seconds seconds ...");
            sleep($seconds);
        }

        // Backup database, if any.
        if ($success && !empty($this->cfg['dbname'])) {
            $success = $this->backupDatabase();
        }

        // Backup files, if any.
        if ($success && !empty($this->volumes)) {
            $success = $this->backupVolumes();
        }

        // No need to keep the site in maintenance mode from this point on. Restore to its initial state.
        $this->maintMode($this->initialMaintMode);

        // Display elapsed time.
        Log::stopWatch('time');

        // Transfer ZIP file to S3.
        if ($success) {
            $success = $this->copyToArchive();
        }

        Log::msg('Cleaning up');
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
        // Plain text for Drupal, tar for everyone else.
        $file = 'database.' . ($this->siteType == 'drupal' ? 'sql' : 'tar');

        $success = $db->backup(
            ['host' => $this->cfg['dbhost'],
            'port' => $this->cfg['dbport'],
            'user' => $this->cfg['dbuser'],
            'pass' => $this->cfg['dbpass'],
            'name' => $this->cfg['dbname'],
            'file' => $file]
        );
        if (!$success) {
            Log::error("Database backup failed!");
            $this->cleanup();
            return false;
        }

        // Add file to ZIP archive and delete the file.

        if ($success) {
            try {
                SysCmd::exec(sprintf('zip -rygq %s %s 2>&1',
                    $this->cfg['tmpdir'] . '/' . $this->backupTarFile,
                    $file
                ), $this->cfg['tmpdir']);
            } catch (\Exception $e) {
                Log::error("Caught exception zipping up the database backup.");
                $success = false;
            }
        }

        // Don't need to clean-up temporary database file. Will be removed in final clean-up.

        return $success;
    }

    /**
     * Backup volumes
     *
     * @return boolean
     */
    protected function backupVolumes()
    {
        // Tar the files in each volume directory
        foreach ($this->volumes as $volume) {
            $success = true;
            $parentDir = dirname($volume);
            $backupDir = basename($volume);
            Log::msg("Backing-up volume $volume");

            try {
                SysCmd::exec(sprintf('zip -rygq %s %s 2>&1',
                    $this->cfg['tmpdir'] . '/' . $this->backupTarFile,
                    $backupDir
                ), $parentDir);
            }
            catch (\Exception $e) {
                Log::error("Caught exception zipping up volume $volume.");
                Log::error($e->getMessage());
                $this->cleanup();
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copy zip file to S3.
     *
     * @return boolean
     */
    protected function copyToArchive()
    {
        $filename = $this->backupDir[$this->backupType] . '/' . $this->backupTarFile;
        $path = $this->cfg['tmpdir'] . '/' . $this->backupTarFile;
        Log::msg("Transferring to S3: $filename");
        $s3 = new S3Cmd();
        try {
            $s3->copy($filename, $path);
        }
        catch (\Exception $e) {
            Log::error('Failed to transfer backup file to S3.');
            Log::error($e->getMessage());
            $this->cleanup();
            return false;
        }

        Log::msg('Transfer complete.');
        return true;
    }

    /**
     * Perform a cleanup of any temp files created.
     * Take the site out of maintenance mode.
     *
     * @return boolean
     */
    public function cleanup()
    {
        // Remove the temporary directory
        if (is_dir($this->cfg['tmpdir'])) {
            SysCmd::exec('chmod -R u+w ' . $this->cfg['tmpdir']);
            SysCmd::exec('rm -rf ' . $this->cfg['tmpdir']);
            // HACK attempts to resolve timing issue... but we will only wait so long.
            clearstatcache();
            $i = 0;
            while (is_dir($this->cfg['tmpdir']) && $i < 60) { // Less than 60 seconds.
                sleep(1);
                $i++;
                clearstatcache();
            }
        }

        // If requested, put the site back in initial maintenance mode state
        if ($this->MaintMode !== null) {
            Log::msg("Restoring original maintenance mode status...");
            $this->maintMode($this->initialMaintMode);
        }

        return true;
    }

    /**
     * Static function to detect what type of site this is.
     * Note: Each site type must override this method.
     * This would be an abstract function if PHP would allow it in combination with static.
     *
     * @return string 'unknown'.
     */
    public static function detect()
    {
        return 'unknown';
    }

    /**
     * Get current maintenance mode status of site.
     *
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $status Maintenance Mode (true), Not Maintenance Mode (false)
     */
    abstract public function getMaintMode($restoreStatus = false);

    /**
     * Take the site in or out of maintenance mode.
     * NOTE: The class which extends this base class must
     * define how maintMode is implemented.
     *
     * @param boolean $maint         In maintenance mode (true), otherwise false.
     * @param boolean $restoreStatus Restore original maintenance mode state (true), otherwise false.
     *
     * @return boolean $success Successful (true), failed (false).
     */
    abstract public function maintMode($maint = true, $restoreStatus = false);

    /**
     * Delete files within persistent volumes, without deleting
     * the directory itself.
     *
     * @return boolean $success Successful (true), failed (false)
     */
    abstract public function deleteFiles();

    /**
     * Restore a site, using parameters provided in the POST and local configuration.
     *
     * @param string $backupFile Name of the backup file we are restoring.
     *
     * @return boolean
     */
    public function restore($backupFile, $exclude)
    {
        // Check to make sure we have S3 credentials available
        if (!S3Cmd::checkCredentials()) {
            return false;
        }

        if (empty($backupFile)) {
            Log::exitError('Missing filename.');
        }

        Log::msg("Begin restore process...");
        $this->restoreArchive = $backupFile;
        $this->restoreTarFile = basename($backupFile);

        // Put site into maintenance mode.
        if ($this->siteExists) {
            $this->maintMode(true);
        }

        // Fails if there is neither a database or directories to be restored.
        $success = !(empty($this->cfg['dbname']) && empty($this->volumes));

        // Get selected backup archive from S3 bucket.
        if ($success) {
            $success = $this->getBackupArchive();
        }

        // Unzip backup archive.
        if ($success) {
            $success = $this->unzipArchive();
        }

        // Restore files, if any.
        if ($success && !empty($this->volumes)) {
            $success = $this->restoreVolumes($exclude);
        }

        // Drop database tables
        if ($success && $this->siteExists) {
            $success = $this->dropTables();
        }

        // Restore database.
        // TODO option to restore database only
        if ($success && !empty($this->cfg['dbname'])) {
            $success = $this->restoreDatabase();
        }

        if ($success) {
            $this->maintMode(false);
        }

        // Display elapsed time.
        Log::stopWatch('time');

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
     * Project Module Listing.
     * Implementation depends on application. Apps should override.
     *
     * @return boolean Always returns false.
     */
    public function pmlist()
    {
        return false;
    }

    /**
     * Retrieve tar.gz backup file.
     *
     * @return boolean
     */
    protected function getBackupArchive()
    {
        $s3 = new S3Cmd();
        try {
            $success = $s3->getFile($this->restoreArchive, $this->cfg['tmpdir'] . '/' . $this->restoreTarFile);
            if (!$success) {
                throw new Exception('Failed to retrieve backup archive.');
            }
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
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
        $success = true;
        $cmd = pathinfo($this->restoreTarFile, PATHINFO_EXTENSION) == 'zip' ? 'unzip -q' : 'gunzip -f';
        try {
            SysCmd::exec(sprintf('%s %s 2>&1',
                $cmd,
                $this->restoreTarFile
            ), $this->cfg['tmpdir']);
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->cleanup();
            $success = false;
        }

        if ($success && $cmd == 'gunzip -f') {
            // Un-tar the files.
            $this->restoreTarFile = preg_replace('/\.gz$/', '', $this->restoreTarFile);

            try {
                SysCmd::exec(sprintf('tar xf %s',
                    $this->restoreTarFile
                ), $this->cfg['tmpdir']);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                $this->cleanup();
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Restore the database.
     *
     * @return boolean
     */
    protected function restoreDatabase()
    {
        $db = new Postgres();
        $file = $this->cfg['tmpdir'] . '/' . 'database.';
        $file .= (file_exists($file . 'sql') ? 'sql' : 'tar');

        $success = $db->restore([
            'host' => $this->cfg['dbhost'],
            'port' => $this->cfg['dbport'],
            'user' => $this->cfg['dbuser'],
            'pass' => $this->cfg['dbpass'],
            'name' => $this->cfg['dbname'],
            'file' => $file,
        ]);
        if (!$success) {
            Log::error("Database restore failed!");
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
    protected function restoreVolumes($exclude = '')
    {

        // Restore the files in each volume directory
        foreach ($this->volumes as $volume) {
            Log::msg("Restore volume $volume");
            $parentDir = dirname($volume);
            $backupDir = basename($volume);
            $backupFile = "$backupDir-backup.tar";
            $success = true;

            // For backwards compatibility with old version of remote-manage.
            // Un-tar files if they exist.
            if (file_exists($backupFile)) {
                try {
                    $cmd = sprintf('tar xf %s', $backupFile);
                    SysCmd::exec($cmd, $this->cfg['tmpdir']);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                    $success = false;
                }
            }

            // RSync to update existing filepaths using extracted files.
            if ($success) {
                try {
                    $cmd = sprintf('rsync -a%s --delete --omit-dir-times --omit-link-times --no-g --no-perms %s %s',
                        $exclude,
                        $backupDir . '/',
                        $volume . '/');
                    SysCmd::exec($cmd, $this->cfg['tmpdir']);
                }
                catch (\Exception $e) {
                    Log::error($e->getMessage());
                    $success = false;
                }
            }

            // Don't restore the rest if we have a failure.
            if (!$success) {
                break;
            }
        }
        return $success;
    }
}
