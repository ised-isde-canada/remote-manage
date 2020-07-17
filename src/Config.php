<?php

namespace RemoteManage;

class Config
{
    public static $homeDir = null;
    public static $siteType = null;

    public static function initialize()
    {
        self::$homeDir = getenv('HOME');

        if (Drupal\Site::detect()) {
            self::$siteType = 'drupal';
        }
        else if (Moodle\Site::detect()) {
            self::$siteType = 'moodle';
        }
    }
}
