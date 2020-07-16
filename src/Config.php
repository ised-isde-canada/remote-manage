<?php

namespace RemoteManage;

class Config
{
    public static $homeDir = null;
    public static $siteType = null;

    public static function initialize()
    {
        self::$homeDir = getenv('HOME');

        if (DrupalSite::detect()) {
            self::$siteType = 'drupal';
        }
        else if (MoodleSite::detect()) {
            self::$siteType = 'moodle';
        }
    }
}