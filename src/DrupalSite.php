<?php

namespace RemoteManage;

class DrupalSite
{
    public function backup()
    {
        Log::msg("Drupal backup is running...");
    }

    public static function detect()
    {
        return is_dir(Config::homeDir . '/drush') ? true : false;
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