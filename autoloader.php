<?php

// Finally found this solution here https://stackoverflow.com/questions/12818690/using-composers-autoload

$loader = require 'vendor/autoload.php';
$loader->addPsr4('RemoteManage\\', __DIR__.'/src/');
