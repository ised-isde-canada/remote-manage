<?php

use RemoteManage\BackupNow;

echo "This is the Remote Manager!\n";

$backup = new BackupNow();

$backup->run();
