<?php

namespace RemoteManage;

class Config
{
    public $homeDir = null;
    public $siteType = null;

    public function __construct()
    {
        $this->homeDir = getenv('HOME');

        if (DrupalSite::detect()) {
            $this->siteType = 'drupal';
        }
        else if (MoodleSite::detect()) {
            $this->siteType = 'moodle';
        }
    }
}