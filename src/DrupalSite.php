<?php

namespace RemoteManage;

class DrupalSite
{
    public function backup()
    {
        Log::msg("Drupal backup is running...");
    }

    public function maintMode($maint=true)
    {
        if ($maint) {
            Log::msg("Enter Drupal maint mode");
        }
        else {
            Log::msg("Exit Drupal maint mode");
        }
    }
}