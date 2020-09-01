<?php
/**
 * Reports current disk space information.
 *
 * Date: July 2020
 *
 * @author  Michael Milette <www.tngconsulting.ca>
 * @license MIT https://opensource.org/licenses/MIT
 */

/**
 * Class Retrieve information for current disk or given path.
 *
 * @author  Michael Milette <www.tngconsulting.ca>
 * @license MIT https://opensource.org/licenses/MIT
 */

namespace RemoteManage;

class DiskSpace
{
    public $total = 0;
    public $free = 0;
    public $used = 0;
    public $percentage = 0;

    /**
     * Setup properties of the Diskspace class.
     *
     * @param $path   string Path of disk for which we want info.
     * @param $format string Specify anything other than 'bytes' to get values in human readable format.
     */
    function __construct($path = null, $format = 'bytes')
    {
        if ($path === null) {
            $path = dirname(__FILE__);
        }
        $this->total = disk_total_space($path);
        if ($this->total !== false) {
            if ($format == 'bytes') {
                $this->free = disk_free_space($path);
                $this->used = $this->total - $this->free;
            }
            else { // Human readable format.
                $this->total = formatBytes($this->total);
                $this->free = formatBytes(disk_free_space($path));
                $this->used = formatBytes( $this->total - $this->free);
            }
            $this->percentage = (($this->used / $this->total)  * 100);
        }
    }

}
