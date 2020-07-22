<?php

namespace RemoteManage;

class SysCmd
{
    public static $last_rc = null;

    /**
     * Execute a shell command - with error handling.
     *
     * @param string $cmd The command to be executed.
     * @param string $dir Optional directory from where to execute the command.
     */
    public static function exec($cmd, $dir = '')
    {
        // Change into specified directory if specified.
        if (!empty($dir)) {
            $cwd = getcwd();
            Log::msg("chdir $dir");
            chdir($dir);
        }

        Log::msg($cmd);
        exec($cmd, $output, $rc);

        // Save the last return code in case the caller wants to retrieve it.
        self::$last_rc = $rc;

        // On error, collect the output in the logs.
        // We will throw an exception after restoring the current directory.
        if ($rc !== 0) {
            Log::msg("ERROR: Command execution failure. Return code=$rc");
            foreach($output as $msg) {
                Log::msg($msg);
            }
        }

        // Restore current directory back to its original state, if needed.
        if (!empty($dir)) {
            Log::msg("chdir $cwd");
            chdir($cwd);
        }

        // On error, throw an exception.
        if ($rc !== 0) {
            throw new \Exception("Command execution failure. Return code=$rc");
        }

        return $rc;
    }
}
