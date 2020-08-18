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
        $site = getSite();
        $this->createPassFile($db);

        // Dump database using tar format (-F t).
        try {
            SysCmd::exec(sprintf('pg_dump -h %s -p %s -U %s -x -F t %s > %s 2>&1',
                $db['host'],
                $db['port'],
                $db['user'],
                $db['name'],
                $db['file']
            ), $site->cfg['tmpdir']);
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
        $site = getSite();
        $this->createPassFile($db);

        try {
            SysCmd::exec(sprintf('pg_restore --no-privileges --no-owner -h %s -p %s -U %s -d %s -F t %s 2>&1',
                $db['host'],
                $db['port'],
                $db['user'],
                $db['name'],
                $db['file']
            ), $site->cfg['tmpdir'], FALSE, TRUE);
        }
        catch (\Exception $e) {
        }

        $this->removePassFile();
        return true;
    }

    /**
     * Drop the database tables.
     * @param array $db
     */
    public function dropTables($db)
    {
        // Establish conection to database.
        $conn = @pg_connect(sprintf('host=%s port=%s dbname=%s user=%s password=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['user'],
            $db['pass']
        ));
        if (!$conn) {
            Log::msg("Failed to connect to database $dbname.");
            return false;
        }

        // Functions 'SELECT routines.routine_name FROM information_schema.routines ORDER BY routines.routine_name;'
        // ALL tables 'SELECT table_name FROM information_schema.tables ORDER BY table_name;'
        // User tables 'SELECT relname FROM pg_stat_user_tables ORDER BY relname;'

        $result = pg_query($conn, "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;");
        if (!$result) {
            Log::msg("ERROR: pg_query for dropTables() failed.");
        } else {
            Log::msg("Dropping tables in database $dbname...");
            if (pg_num_rows($result) == 0) {
                Log::msg("No tables found in database $dbname.");
            } else {
                while ($row = pg_fetch_assoc($result)) {
                    $table = $row['tablename'];
                    pg_query($conn, "DROP TABLE IF EXISTS $table CASCADE;");
                }
            } 
        }

        pg_close($conn);

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

        putenv("PGPASSFILE=$this->pgpassfile");
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
