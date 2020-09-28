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
}
