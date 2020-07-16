<?php

namespace RemoteManage;

class MoodleSite
{
    public function backup()
    {
        Log::msg("Moodle backup is running...");
    }

    public static function detect()
    {
        return file_exists(Config::homeDir . '/config.php') ? true : false;
    }

    public function maintMode($maint=true)
    {
        if ($maint) {
            Log::msg("Enter Moodle maint mode");
        }
        else {
            Log::msg("Exit Moodle maint mode");
        }
    }
}