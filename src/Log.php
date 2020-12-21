<?php

namespace RemoteManage;

/**
 * Log class.
 * This is intended to be used as a static class, to make it easy to add log messages from anywhere.
 */
class Log
{
    public  static $debugMode = false;
    public  static $errCount = 0;
    public  static $logStderr = false;
    private static $data = null;
    private static $startTime = null;
    private static $endTime = null;

    /**
     * Print an error. Do not include a newline character at the end of your message!
     *
     * @param string $str Error string.
     *
     * @return null
     */
    public static function error($str)
    {
        self::$errCount++;
        self::msg("ERROR: $str");
    }

    /**
     * Print an error and exit. Do not include a newline character at the end of your message!
     *
     * @param string $str Error string.
     */
    public static function exitError($str)
    {
        self::msg("ERROR: $str");
        getSite()->cleanup();
        exit(1);
    }

    /**
     * Set a message. Do not include a newline character at the end of your message!
     *
     * @param string|array $str  Text to be printed. Can be a string or an array of strings.
     *
     * @return null
     */
    public static function msg($str)
    {
        if (self::$debugMode) {
            if (is_array($str)) {
                foreach ($str as $s) {
                    echo $s . PHP_EOL;
                    if (self::$logStderr) {
                        fwrite(STDERR, $s . PHP_EOL);
                    }
                }
            } else {
                echo $str . PHP_EOL;
                if (self::$logStderr) {
                    fwrite(STDERR, $str . PHP_EOL);
                }
            }
        }
    }

    /**
     * Set some data to be output as JSON when the program terminates.
     * To ensure consistency, all data must have a unique key.
     *
     * @param string $key  A unique key
     * @param array $data  The data
     *
     * @return null
     */
    public static function data($key, $value)
    {
        self::$data[$key] = $value;
    }

    /**
     * Print the data array in JSON format.
     *
     * @return null
     */
    public static function printData($status)
    {
        self::msg('DATA:');
        $json = [
            'status' => $status,
            'start_time' => date('Y-m-d H:i:s', self::$startTime),
            'end_time' => date('Y-m-d H:i:s', self::$endTime),
        ];
        if (is_array(self::$data)) {
            foreach (self::$data as $key => $value) {
                $json[$key] = $value;
            }
        }
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    /**
     * Execution time management.
     *
     * @param string $op  Stopwatch operation to execute.
     *
     * @return null
     */
    public static function stopWatch($op = 'start')
    {
        switch ($op) {
            case 'start':
                self::$startTime = microtime(true);
                self::msg('Starting at ' . date('Y-m-d @ H:i:s', self::$startTime) . '...');
                break;
            case 'stop':
                self::$endTime = microtime(true);
                self::msg('Job started at ' . date('Y-m-d @ H:i:s', self::$startTime) . ' and finished at ' . date('H:i:s', self::$endTime) . '.');
                $seconds = self::$endTime - self::$startTime;
                self::msg('Total execution time was ' . floor($seconds / 3600) . gmdate(':i:s', ($seconds % 3600)) . '.');
                break;
            case 'time':
                if (isset(self::$startTime)) {
                    $seconds = microtime(true) - self::$startTime;
                    self::msg('Elapsed execution time is ' . floor($seconds / 3600) . gmdate(':i:s', ($seconds % 3600)) . '.');
                }
                break;
            default:
                self::msg('Invalid stopWatch operation.');
        }
    }
}
