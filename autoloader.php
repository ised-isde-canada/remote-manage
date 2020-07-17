<?php

// Set up autoloader, which assumes that we are running in a vendor folder.
// If this proves problematic, then see vendor/drush/drush/drush.php for ideas to make this better

spl_autoload_register(function ($class) {
    if (strpos($class, 'RemoteManage\\') !== 0) {
        return;
    }

    $file = __DIR__.'/src'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('RemoteManage'))).'.php';

    if (is_file($file)) {
        require_once $file;
    }
});
