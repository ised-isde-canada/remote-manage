<?php

// Set up autoloader, which assumes that we are running in a vendor folder.
// If this proves problematic, then see vendor/drush/drush/drush.php for ideas to make this better

if (file_exists($autoloadFile = __DIR__ . '/../../autoload.php')) {
    include_once($autoloadFile);
}
else {
    throw new \Exception("Could not locate autoload.php.");
}