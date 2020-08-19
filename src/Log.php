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
    public  static $cli_mode = false;
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
     * Return last message.
     * @param integer $lastindex Offset from last message. Example 2 would be second to last message in queue.
     * @return string
     */
    public static function getlast($lastindex = 1)
    {
        // Handle case where there are not enough elements in the array).
        $last = count(self::$messages) - 1;
        if ($last < 1 || $lastindex > $last) {
            return null;
        }
        return self::$messages[count(self::$messages) - $lastindex];
    }

    /**
     * Set a message. Do not include a newline character at the end of your message!
     * @param string $status
     * @param bool $log If true, adds to json.
     * @param string $str
     */
    public static function msg($str, $log = false)
    {
        if (self::$cli_mode) {
            echo $str . " (status $status)" . PHP_EOL;
        }
        else {
            echo $str . " (status $status)<br>";
            flush();
            ob_flush();
        }
        if ($log) {
            self::$messages[] = $str;
        }
    }
}
