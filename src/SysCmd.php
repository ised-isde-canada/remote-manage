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
     * @param bool   $forceLog    Optionally capture command output to log.
     * @param bool   $allowErrors Allow errors (true) or don't allow errors (false).
     *
     * @return integer $rc       Return code from executable.
     */
    public static function exec($cmd, $dir = '', $allowErrors = false)
    {
        // Change into specified directory if specified.
        if (!empty($dir)) {
            $cwd = getcwd();
            Log::msg("chdir $dir");
            chdir($dir);
        }

        // Run gzip and tar as background tasks to avoid connection timeout.
        if (strpos($cmd, 'gzip ') === 0
                || strpos($cmd, 'tar ') === 0
                || strpos($cmd, 'rsync ') === 0
                || strpos($cmd, 'gunzip ') === 0) {
            Log::msg('Forking the background process...');
            Log::msg($cmd);
            if (!Log::$cli_mode) {
                flush();
                ob_flush();
            }
            $site = getSite();
            $descriptorspec = [
                0 => ['pipe', 'r'], // Input.
                1 => ['pipe', 'w'], // Output.
                2 => ['pipe', 'w']  // Errors.
            ];

            $process = proc_open($cmd, $descriptorspec, $pipes);
            do { // Keep the party going until we are all partied-out.
                sleep(1);
                $status = @\proc_get_status($process);
                if (empty($status['running']) && !empty($status['stopped'])) {
                    // Kill the zombie king!
                    @\proc_terminate($process);
                    $status['running'] = false;
                }
                if (!empty($status['running'])) {
                    if (Log::$cli_mode) {
                        echo '.';
                    }
                    else {
                        // Waiting for you know who to finish work.
                        echo PHP_EOL; // This line keeps the connection alive.
                        flush();
                        ob_flush();
                    }
                    sleep(3);
                }
            } while (!empty($status['running']));

            // Last words.
            $rc = $status['exitcode'];
            $output = stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $output = explode("\n", $output);
        }
        else {
            Log::msg('Running process...');
            Log::msg($cmd);
            exec($cmd, $output, $rc);
        }

        // Save the last return code in case the caller wants to retrieve it.
        self::$last_rc = $rc;

        // Log output from recent command execution.
        foreach($output as $msg) {
            Log::msg($msg);
        }

        if ($rc !== 0 && !$allowErrors) {
            // We will throw an exception after restoring the current directory.
            Log::error("Command $cmd execution failure. Return code=$rc");
        }

        // Restore current directory back to its original state, if needed.
        if (!empty($dir)) {
            Log::msg("chdir $cwd");
            chdir($cwd);
        }

        // On error, throw an exception.
        if ($rc !== 0 && !$allowErrors) {
            throw new \Exception("Command $cmd execution failure. Return code=$rc");
        }

        return $rc;
    }
}
