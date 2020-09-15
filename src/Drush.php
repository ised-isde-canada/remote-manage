<?php

namespace RemoteManage;

/**
 * This class is a wrapper for the drush CLI.
 */
class Drush
{
    public function pmList()
    {
        chdir('/opt/app-root/src');
        $result = explode("\n", `vendor/bin/drush pm:list`);
        Log::data($result);
        return true;
    }
}
