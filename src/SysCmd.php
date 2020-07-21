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
            chdir($dir);
        }

        Log::msg($cmd);
        exec($cmd, $output, $rc);

        // Save the last return code in case the caller wants to retrieve it.
        self::$last_rc = $rc;

        // Restore current directory back to its original state, if needed.
        if (!empty($dir)) {
            chdir($cwd);
        }

        // On error, throw an exception
        if ($rc !== 0) {
            Log::msg("ERROR: Command execution failure. Return code=$rc");
            foreach($output as $msg)
            {
                Log::msg($msg);
            }
            throw new \Exception("Command execution failure. Return code=$rc");
        }

        return $rc;
    }
}
