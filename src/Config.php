<?php

/**
 * Static configuration. Don't get too comfortable; we may move this into the individual sites.
 */

namespace RemoteManage;

class Config
{
    public static $homeDir = null;

    public static function initialize()
    {
        self::$homeDir = getenv('HOME');
    }
}
