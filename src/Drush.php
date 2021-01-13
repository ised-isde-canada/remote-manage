<?php

namespace RemoteManage;

/**
 * This class is a wrapper for the drush CLI.
 */
class Drush
{
    public function pmList()
    {
        $cwd = getcwd();
        chdir('/opt/app-root/src');
        $json = json_decode(`vendor/bin/drush pm:list --format=json`);
        chdir($cwd);
        return $json;
    }

    public function sqlRestore($pathToDump)
    {
        $cwd = getcwd();
        chdir('/opt/app-root/src');
        SysCmd::exec(sprintf('vendor/bin/drush --debug sql:query --file=%s 2>&1',
          $pathToDump . '/database.tar'
        ), '/opt/app-root/src', TRUE, TRUE);
        chdir($cwd);
        return TRUE;
    }
}
