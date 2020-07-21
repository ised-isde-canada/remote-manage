<?php

namespace RemoteManage;

/**
 * Provides PostGreSql database functions.
 */
class Postgres
{
    private $pgpassfile = null; // Path to .pgpass file

    public function __construct(){
        $this->pgpassfile = getenv('HOME') . '/.pgpass';
    }

    /**
     * Backup a PostGreSql database.
     * @param array $db
     * @return boolean
     */
    public function backup($db)
    {
        $this->createPassFile($db);

        // Dump database using tar format (-F t).
        try {
            SysCmd::exec(sprintf('pg_dump -h %s -p %s -U %s -x -F t %s > %s 2>&1',
                $db['host'],
                $db['port'],
                $db['user'],
                $db['name'],
                $db['file']
            ));
        }
        catch (\Exception $e) {
            Log::msg("Caught exception from pg_dump");
            $this->removePassFile();
            return false;
        }

        $this->removePassFile();
        return true;
    }

    /**
     * Restore a PostGreSql database.
     * @param array $db
     * @return boolean
     */
    public function restore($db)
    {
        $this->createPassFile($db);

        try {
            SysCmd::exec(sprintf('pg_restore --no-privileges --no-owner -h %s -p %s -U %s -d %s -F t -c %s 2>&1',
                $db['host'],
                $db['port'],
                $db['user'],
                $db['name'],
                $db['file']
            ));
        }
        catch (\Exception $e) {
            $this->removePassFile();
            return false;
        }

        $this->removePassFile();
        return true;
    }

    /**
     * Create the .pgpass file which holds the database credentials.
     * @param array $db
     */
    private function createPassFile($db)
    {
        $pgpass = sprintf("%s:%s:%s:%s:%s",
            $db['host'],
            $db['port'],
            $db['name'],
            $db['user'],
            $db['pass']
        );

        if ($fp = fopen($this->pgpassfile, "w")) {
            fwrite($fp, $pgpass . PHP_EOL);
            fclose($fp);
            chmod($this->pgpassfile, 0600);
        }
    }

    /**
     * Remove the .pgpass file.
     */
    private function removePassFile()
    {
        if (file_exists($this->pgpassfile)) {
            unlink($this->pgpassfile);
        }
    }
}
