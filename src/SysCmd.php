<?php

namespace RemoteManage;

class SysCmd
{
    public static $last_rc = null;

    /**
     * Execute a shell command - with error handling.
     *
     * @param string $cmd         The command to be executed.
     * @param string $dir         Optional directory from where to execute the command.
     * @param bool   $allowErrors Allow errors (true) or don't allow errors (false).
     * @param bool   $retLog      Optionally capture and return command output array instead of exit code.
     *
     * @return integer $rc        Return code from executable.
     */
    public static function exec($cmd, $dir = '', $allowErrors = false, $retLog = false)
    {
        // Change into specified directory if specified.
        if (!empty($dir)) {
            $cwd = getcwd();
            Log::msg("chdir $dir");
            chdir($dir);
        }

        Log::msg('Running process...');
        Log::msg($cmd);
        exec($cmd, $output, $rc);

        // Save the last return code in case the caller wants to retrieve it.
        self::$last_rc = $rc;

        // Log output from recent command execution.
        foreach($output as $msg) {
            Log::msg($msg);
        }

        if (!empty($rc) && !$allowErrors) {
            // We will throw an exception after restoring the current directory.
            Log::error("Command $cmd execution failure. Return code=$rc");
        }

        // Restore current directory back to its original state, if needed.
        if (!empty($dir)) {
            Log::msg("chdir $cwd");
            chdir($cwd);
        }

        // On error, throw an exception.
        if (!empty($rc) && !$allowErrors) {
            throw new \Exception("Command $cmd execution failure. Return code=$rc");
        }

        return ($retLog ? $output : $rc);
    }
}
