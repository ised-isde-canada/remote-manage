<?php

namespace RemoteManage;

class Postgres
{
    public function backup($db)
    {
        // Dump database using tar format (-F t).
        $syscmd = new SysCmd();
        $syscmd->exec(sprintf('pg_dump -h %s -p %s -U %s -x -F t %s > %s 2>&1',
            $db['host'],
            $db['port'],
            $db['user'],
            $db['name'],
            $db['file']
        ));
    }

    public function restore($db)
    {
        $syscmd = new SysCmd();
        $syscmd->exec(sprintf('pg_restore --no-privileges --no-owner -h %s -p %s -U %s -d %s -F t -c %s 2>&1',
            $db['host'],
            $db['port'],
            $db['user'],
            $db['name'],
            $db['file']
        ));
    }
}
