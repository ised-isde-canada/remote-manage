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
    private static $status = [];
    private static $error = [];
    private static $data = [];
    private static $messages = [];

    /**
     * Return an array of all messages.
     *
     * @return array
     */
    public static function get($type = 'msg')
    {
        switch($type) {
            case 'msg':
                return self::$messages;
                break;
            case 'error':
                return self::$error;
                break;
            case 'status':
                return self::$status;
                break;
            case 'data':
                return self::$data;
                break;
            default:
                return 'Invalid log type.';
        }
    }

    /**
     * Return last message.
     *
     * @param integer $lastindex Offset from last message. Example 2 would be second to last message in queue.
     *
     * @return string
     */
    public static function getlast($lastindex = 1, $type = 'msg')
    {
        switch($type) {
            case 'msg':
                $arr = self::$messages;
                break;
            case 'error':
                $arr = self::$error;
                break;
            case 'status':
                $arr = self::$status;
                break;
            case 'data':
                $arr = self::$data;
                break;
            default:
                $arr = ['Invalid log type.'];
        }
        // Handle case where there are not enough elements in the array).
        $last = count($arr) - 1;
        if ($last < 1 || $lastindex > $last) {
            return null;
        }
        return $arr[count($arr) - $lastindex];
    }

    /**
     * Set an error. Do not include a newline character at the end of your message!
     *
     * @param string $str  Text to be added to messages.
     * @param string $type Adds to json.
     *
     * @return null
     */
    public static function error($str)
    {
        self::$error = $str;

        // Add a copy of everything in self::$messages.
        self::$messages[] = $str;
    }

    /**
     * Set a status. Do not include a newline character at the end of your message!
     *
     * @param string $str  Text to be added to messages.
     * @param string $type Adds to json.
     *
     * @return null
     */
    public static function status($str)
    {
        self::$status = $str;

        // Add a copy of everything in self::$messages.
        self::$messages[] = $str;
    }

    /**
     * Set a message. Do not include a newline character at the end of your message!
     *
     * @param string $str  Text to be added to messages.
     * @param string $type Adds to json.
     *
     * @return null
     */
    public static function msg($str)
    {
        // Add a copy of everything in self::$messages.
        self::$messages[] = $str;
    }

    /**
     * Set some data. Do not include a newline character at the end of your message!
     *
     * @param string $arr  Array to be added to data.
     *
     * @return null
     */
    public static function data($arr)
    {
        self::$data[] = $arr;

        // Add a copy of everything in self::$messages.
        self::$messages[] = $arr;
    }

    /**
     *
     */
    public static function endItAll($status = 'success')
    {
        // If using the CLI, we're done. The message were already printed out as they happened.
        //if (!self::$cli_mode) {
            $exitcode = 0;
            // Create the JSON response
            $json = [];
            $json['status'] = $status;
            if ($status == 'error') {
                $json['error'] = self::get('error');
                $exitcode = 1;
            }
            else {
                $json['data'] = self::get('data');
                if (empty($json['data'])) {
                    unset($json['data']);
                }
            }
            if (DEBUGMODE) {
                $json['messages'] = self::get('msg');
            }

            // Exit with a valid JSON response.
            echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
            exit($exitcode);
        //}
    }
}
