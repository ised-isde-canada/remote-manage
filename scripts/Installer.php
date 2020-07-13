<?php

namespace DsutterGc\RemoteManage;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class Installer
{
    public function postPackageInstall(Event $event)
    {
        $composer = $event->getComposer();

        echo "Gonna install\n";
        system("ln -s /opt/app-root/src/vendor/dsutter-gc/remote-manage/vtest.php /opt/app-root/src/html/vtest.php");
    }
}
