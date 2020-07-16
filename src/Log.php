<?php

namespace RemoteManage;

/**
 * The Log class is useful for collecting diagnostic messages.
 */
class Log
{
    private static $messages = [];

    public static function get()
    {
        return self::$messages;
    }

    public static function log($str)
    {
        self::$messages[] = $str;
    }
}
