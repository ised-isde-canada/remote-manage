<?php

namespace RemoteManage;

class Config
{
    public $homeDir = null;
    public $siteType = null;

    public function __construct()
    {
        $this->homeDir = getenv('HOME');
        if (is_dir($this->homeDir . '/drush')) {
            $this->siteType = 'drupal';
        }
        else {
            $this->siteType = 'moodle';
        }
    }
}