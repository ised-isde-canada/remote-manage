<?php

/**
 * Logger class.
 * This is intended to be used as a static class, to make it easy to add log messages from anywhere,
 * and then retrieve them before the main script terminates.
 */

namespace RemoteManage;

/**
 * The Log class is useful for collecting diagnostic messages.
 */
class Log
{
    private static $messages = [];

    /**
     * Return an array of all messages.
     * @return array
     */
    public static function get()
    {
        return self::$messages;
    }

    /**
     * Set a message. Do not include a newline character at the end of your message!
     * @param string $str
     */
    public static function msg($str)
    {
        self::$messages[] = $str;
    }
}
