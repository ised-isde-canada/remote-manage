<?php

namespace RemoteManage;

class DrupalSite
{
    /**
     * Backup a Drupal site.
     * Any failures during this process will throw an exception.
     */
    public function backup()
    {
        Log::msg("Drupal backup is running...");

        // Dump database using tar format (-F t).
        execmd('pg_dump -U ' . $cfg['dbuser'] . ' -h ' . $cfg['dbhost'] . ' -p ' . $cfg['dbport'] . ' -x -F t ' . $cfg['dbname'] . ' > ' . $cfg['tmpdir'] . '/' . $cfg['dbname'] . '.sql 2>&1');

        // Tar the files in the PV directory.
        execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['databackup'] . '.tar ' . $cfg['datadir'] . ' 2>&1', $pvdir);

        // Tar the files in the application directory.
        execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['appname'] . '.tar src 2>&1', $homedir . '/..');

        // GZ compress all of the temporary files so far.
        execmd('gzip -f ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . ' 2>&1', $cfg['tmpdir']);

        // Copy the compressed gz file to S3.
        execmd('s3cmd -q --mime-type=application/x-gzip put ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . '.gz s3://$bucket/' . $cfg['s3file'] . '.gz 2>&1');
    }

    public static function detect()
    {
        return is_dir(Config::$homeDir . '/drush') ? true : false;
    }

    public function maintMode($maint=true)
    {
        if ($maint) {
            Log::msg("Enter Drupal maint mode");
        }
        else {
            Log::msg("Exit Drupal maint mode");
        }
    }
}