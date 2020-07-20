<?php

/**
 * Handle all the remote operations for a Moodle site.
 */

namespace RemoteManage\Moodle;

use RemoteManage\BaseSite;

class Site extends BaseSite
{
    public function __construct()
    {
        $this->siteType = 'moodle';

        // Set the standard configuration parameters
        $this->cfg['homedir'] = getenv('HOME');
        $this->cfg['dbhost'] = '';
        $this->cfg['dbport'] = '';
        $this->cfg['dbuser'] = '';
        $this->cfg['dbpass'] = '';
        $this->cfg['dbname'] = '';
        $this->cfg['dbbackup'] = 'database.tar';
        $this->cfg['tmpdir'] = '/tmp/' . $this->siteType . '-remote';
        $this->cfg['volumes'] = ['/abs/path/to/dir'];

        // Set app-specific configuration parameters
        $this->cfg['drush'] = $this->cfg['homedir'] . '/vendor/bin/drush';

        parent::__construct();
    }

    /**
     * Static function to detect if this is a Moodle site.
     * Returns true or false.
     * @return boolean
     */
    public static function detect()
    {
        return file_exists(getenv('HOME') . '/config.php') ? true : false;
    }
}
