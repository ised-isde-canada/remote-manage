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
class DiskSpace
{
    public $total = 0;
    public $free = 0;
    public $used = 0;
    public $percentage = 0;

    /**
     * Setup properties of the Diskspace class.
     *
     * @param $path string Path of disk for which we want info.
     */
    function __construct($path = null)
    {
        if ($path === null) {
            $path = dirname(__FILE__);
        }
        $this->total = disk_total_space($path);
        $this->free = disk_free_space($path);
        $this->used = $this->total - $this->free;
        $this->percentage = (($this->used / $this->total)  * 100);
    }

    /**
     * Formats number and adds units to provided size.
     *
     * @param $bytes   float Number of bytes.
     * @param $decimal integer Number of decimals in returned value.
     *
     * @return string  Number formatted with appropriate units.
     */
    function formatBytes($bytes, $decimal = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $decimal) . ' ' . $units[$pow];
    }
}
