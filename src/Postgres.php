<?php

namespace RemoteManage;

class Postgres
{
    public function backup($db)
    {
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
            return false;
        }

        return true;
    }

    public function restore($db)
    {
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
            return false;
        }

        return true;
    }
}
