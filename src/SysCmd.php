<?php

namespace RemoteManage;

class SysCmd
{
    public $output = null;
    public $rc = null;

    /**
     * Execute a shell command - with error handling.
     *
     * @param string $cmd The command to be executed.
     * @param string $dir Optional directory from where to execute the command.
     */
    public function exec($cmd, $dir = '')
    {
        // Change into specified directory if specified.
        if (!empty($dir)) {
            $cwd = getcwd();
            chdir($dir);
        }

        Log::msg($cmd);
        exec($cmd, $this->output, $this->rc);

        // Restore current directory back to its original state, if needed.
        if (!empty($dir)) {
            chdir($cwd);
        }

        // On error, throw an exception
        if ($this->rc !== 0) {
            Log::msg("ERROR: Command execution failure. Return code=$this->rc");
            throw new \Exception("Command execution failure. Return code=$this->rc");
        }
    }
}
