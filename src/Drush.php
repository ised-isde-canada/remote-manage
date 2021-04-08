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

    public function sqlRestore($file)
    {
        $success = true;
        try {
            SysCmd::exec(sprintf('vendor/bin/drush sql:query --file=%s 2>&1',
                $file
            ), '/opt/app-root/src');
        }
        catch (\Exception $e) {
            $errMsg = "Error restoring Postgres database using Drush: " . $e->getMessage();
            Log::error($errorMsg);
            $success = false;
        }
        return $success;
    }

    public function cacheRebuild()
    {
        $success = true;
        try {
            SysCmd::exec(sprintf('vendor/bin/drush cr 2>&1',
                $file
            ), '/opt/app-root/src');
        }
        catch (\Exception $e) {
            $errMsg = "Error running cache rebuild using Drush: " . $e->getMessage();
            Log::error($errorMsg);
            $success = false;
        }
        return $success;
    }
}
