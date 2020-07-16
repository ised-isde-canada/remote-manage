<?php

function config()
{
    return "ok\n";
}

/**
 * Execute a shell command - with error handling.
 *
 * @param string $cmd The command to be executed.
 * @param string $dir Optional directory from where to execute the command.
 */
function execmd($cmd, $dir = '')
{
    // Change into specified directory if specified.
    if (!empty($dir)) {
        $cwd = getcwd();
        chdir($dir);
    }

    Log::msg($cmd);
    exec($cmd, $output, $rc);

    // Restore current directory back to its original state, if needed.
    if (!empty($dir)) {
        chdir($cwd);
    }

    // On error, throw an exception
    if ($rc !== 0) {
        Log::msg("ERROR: Command execution failure. Return code=$rc");
        throw new \Exception("Command execution failure. Return code=$rc");
    }
}
